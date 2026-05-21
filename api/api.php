<?php
// api.php - Backend Enterprise V6.3 (Strict File Validation)
require_once __DIR__ . '/../core/auth/session.php';
require_once __DIR__ . '/../core/db/connection.php';
require_once __DIR__ . '/../funciones/file_names.php';
require_once __DIR__ . '/rbac.php';
header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? 1;
$userRoleRaw = $_SESSION['role'] ?? 'viewer';
$userRole = strtolower($userRoleRaw); 

$action = $_POST['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);
if(isset($input['action'])) $action = $input['action'];

function cleanName($name) {
    return clean_filename($name);
}

function splitVersionedName(string $filename): array {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $base = pathinfo($filename, PATHINFO_FILENAME);
    if (preg_match('/^(.*)_v(\d+)$/i', $base, $m)) {
        return ['root' => $m[1], 'version' => (int)$m[2], 'ext' => $ext];
    }
    return ['root' => $base, 'version' => 1, 'ext' => $ext];
}

function generateNextVersionedFilename(PDO $pdo, int $projectId, ?int $folderId, ?int $subFolderId, string $originalName): array {
    $parts = splitVersionedName($originalName);
    $root = $parts['root'];
    $ext = $parts['ext'];

    $sql = "SELECT filename, version_number FROM files WHERE project_id=? AND deleted_at IS NULL";
    $params = [$projectId];
    if ($folderId !== null) { $sql .= " AND folder_id=?"; $params[] = $folderId; } else { $sql .= " AND folder_id IS NULL"; }
    if ($subFolderId !== null) { $sql .= " AND sub_folder_id=?"; $params[] = $subFolderId; } else { $sql .= " AND sub_folder_id IS NULL"; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $max = 0;
    foreach ($rows as $r) {
        $p = splitVersionedName((string)$r['filename']);
        if (strtolower($p['root']) === strtolower($root) && strtolower($p['ext']) === strtolower($ext)) {
            $max = max($max, (int)($r['version_number'] ?: $p['version']));
        }
    }
    $next = max(1, $max + 1);
    $final = $next <= 1 ? ($root . '.' . $ext) : ($root . '_v' . $next . '.' . $ext);
    return ['filename' => $final, 'version' => $next];
}

$defaultViewerFolders = ['drawings', 'photos', 'rfi'];
function getVisibleFolders(PDO $pdo, int $projectId, string $role, int $userId): array {
    global $defaultViewerFolders;

    if($role !== 'viewer') {
        $stmt = $pdo->prepare("SELECT id, name, parent_id, depth FROM folders WHERE project_id = ? AND deleted_at IS NULL ORDER BY depth ASC, name ASC");
        $stmt->execute([$projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $placeholders = implode(',', array_fill(0, count($defaultViewerFolders), '?'));
    $sql = "
        SELECT f.id, f.name, f.parent_id, f.depth
        FROM folders f
        WHERE f.project_id = ?
          AND f.deleted_at IS NULL
          AND (
            LOWER(f.name) IN ($placeholders)
            OR f.id IN (SELECT folder_id FROM folder_permissions WHERE user_id = ?)
            OR f.parent_id IN (
              SELECT id FROM folders
              WHERE project_id = ?
                AND deleted_at IS NULL
                AND (
                  LOWER(name) IN ($placeholders)
                  OR id IN (SELECT folder_id FROM folder_permissions WHERE user_id = ?)
                )
            )
          )
        ORDER BY f.depth ASC, f.name ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$projectId], $defaultViewerFolders, [$userId, $projectId], $defaultViewerFolders, [$userId]));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function db_has_column(PDO $pdo, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        $cache[$key] = ($stmt->rowCount() > 0);
    } catch (Throwable $e) {
        $cache[$key] = false;
    }
    return $cache[$key];
}

function ensure_image_annotations_table(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $sql = "CREATE TABLE IF NOT EXISTS image_annotations (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        file_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        annotations_json LONGTEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_file (file_id),
        KEY idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    try { $pdo->exec($sql); } catch (Throwable $e) {}
}

function ensure_report_attachments_table(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $sql = "CREATE TABLE IF NOT EXISTS field_report_attachments (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        field_report_id BIGINT UNSIGNED NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        mime_type VARCHAR(191) NOT NULL,
        file_size BIGINT UNSIGNED NOT NULL,
        storage_path VARCHAR(1024) NOT NULL,
        public_url VARCHAR(1024) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_fra_report (field_report_id),
        CONSTRAINT fk_fra_report FOREIGN KEY (field_report_id) REFERENCES file_reports(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    try { $pdo->exec($sql); } catch (Throwable $e) { /* migration not guaranteed at runtime */ }
}

function inventory_sync_log_line(string $line): void {
    $dir = __DIR__ . '/../integrations/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $file = $dir . '/inventory_sync.log';
    $entry = '[' . gmdate('c') . '] ' . $line . PHP_EOL;
    @file_put_contents($file, $entry, FILE_APPEND);
}

function sync_project_to_inventory_from_api(PDO $pdo, int $projectId): void {
    $inventoryUpsertUrl = trim((string)getenv('INVENTORY_UPSERT_URL'));
    if ($inventoryUpsertUrl === '') {
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, name, status, created_at, updated_at FROM projects WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$projectId]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$project) {
            return;
        }

        $payload = [
            'project_id' => (string)$projectId,
            'name' => (string)($project['name'] ?? ''),
            'status' => isset($project['status']) ? (string)$project['status'] : null,
            'updated_at' => $project['updated_at'] ?? $project['created_at'] ?? null,
            'metadata' => [
                'source' => 'electroplan',
                'electroplan_project_id' => $projectId,
            ],
        ];

        $json = json_encode($payload);
        if ($json === false) {
            inventory_sync_log_line("project_id={$projectId} exception=json_encode_failed");
            return;
        }

        $headers = ['Content-Type: application/json'];
        $sharedKey = trim((string)getenv('INVENTORY_SHARED_KEY'));
        if ($sharedKey !== '') {
            $headers[] = 'X-Integration-Key: ' . $sharedKey;
        }

        $ch = curl_init($inventoryUpsertUrl);
        if ($ch === false) {
            inventory_sync_log_line("project_id={$projectId} curl_error=curl_init_failed");
            return;
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            inventory_sync_log_line("project_id={$projectId} curl_error code={$errno} msg=" . $error);
            return;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $body = is_string($response) ? substr(preg_replace('/\\s+/', ' ', $response), 0, 500) : '';
            inventory_sync_log_line("project_id={$projectId} http_error code={$httpCode} body=" . $body);
            return;
        }

        inventory_sync_log_line("project_id={$projectId} sync_ok code={$httpCode}");
    } catch (Throwable $e) {
        inventory_sync_log_line("project_id={$projectId} exception=" . $e->getMessage());
    }
}

switch($action) {
    
    // --- 1. CREAR PROYECTO (ADMIN ONLY) ---
    case 'create_project':
        if($userRole !== 'admin') { echo json_encode(['status'=>'error', 'msg'=>'Access Denied']); exit; }
        
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'Active';
        $userIds = $_POST['user_ids'] ?? [];

        try {
            if ($name === '') {
                echo json_encode(['status'=>'error', 'msg'=>'Project name required']); exit;
            }

            $selectedIds = [];
            $adminSelectedIds = [];
            if (is_array($userIds)) {
                foreach ($userIds as $uid) {
                    $uid = (int)$uid;
                    if ($uid > 0) $selectedIds[] = $uid;
                }
                $selectedIds = array_values(array_unique($selectedIds));
            }
            $hasUserSelection = !empty($selectedIds);
            if (!empty($selectedIds)) {
                $in = implode(',', array_fill(0, count($selectedIds), '?'));
                $stmtUsers = $pdo->prepare("SELECT id, role FROM users WHERE id IN ($in)");
                $stmtUsers->execute($selectedIds);
                $rows = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
                if (count($rows) !== count($selectedIds)) {
                    echo json_encode(['status'=>'error', 'msg'=>'Invalid users']); exit;
                }
                foreach ($rows as $row) {
                    if ($row['role'] === 'admin') $adminSelectedIds[] = (int)$row['id'];
                }
                if ($hasUserSelection && empty($adminSelectedIds)) {
                    echo json_encode(['status'=>'error', 'msg'=>'At least one admin must be assigned']); exit;
                }
            }

            $creatorId = (int)$userId;
            $selectedIds[] = $creatorId;
            $selectedIds = array_values(array_unique($selectedIds));
            $assignedUserId = !empty($adminSelectedIds) ? (int)$adminSelectedIds[0] : $creatorId;

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO projects (name, description, status, created_by, assigned_user_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $desc, $status, $creatorId, $assignedUserId]);
            $projectId = (int)$pdo->lastInsertId();

            if (!empty($selectedIds)) {
                $stmtDir = $pdo->prepare("INSERT IGNORE INTO directory (project_id, user_id) VALUES (?, ?)");
                foreach ($selectedIds as $uid) {
                    $stmtDir->execute([$projectId, $uid]);
                }
            }
            $pdo->commit();
            sync_project_to_inventory_from_api($pdo, $projectId);
            echo json_encode(['status'=>'success', 'id'=>$projectId]);
        } catch(Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['status'=>'error', 'msg'=>$e->getMessage()]);
        }
        break;

    // --- 1.1 ACTUALIZAR PROYECTO (ADMIN ONLY) ---
    case 'update_project':
        if($userRole !== 'admin') { echo json_encode(['status'=>'error', 'msg'=>'Access Denied']); exit; }
        
        $id = $_POST['id'];
        $name = $_POST['name'];
        $desc = $_POST['description'];
        $status = $_POST['status']; 

        try {
            $stmt = $pdo->prepare("UPDATE projects SET name=?, description=?, status=? WHERE id=?");
            $stmt->execute([$name, $desc, $status, $id]);
            sync_project_to_inventory_from_api($pdo, (int)$id);
            echo json_encode(['status'=>'success']);
        } catch(Exception $e) { echo json_encode(['status'=>'error', 'msg'=>$e->getMessage()]); }
        break;

    // --- 1.2 ASIGNAR USUARIO A PROYECTO (ADMIN ONLY) ---
    case 'assign_project_user':
        if($userRole !== 'admin') { echo json_encode(['status'=>'error', 'msg'=>'Access Denied']); exit; }

        $projectId = (int)($_POST['project_id'] ?? 0);
        $targetUserId = (int)($_POST['user_id'] ?? 0);

        if($projectId <= 0 || $targetUserId <= 0) { echo json_encode(['status'=>'error', 'msg'=>'Invalid data']); exit; }

        try {
            $stmt = $pdo->prepare("UPDATE projects SET assigned_user_id = ? WHERE id = ?");
            $stmt->execute([$targetUserId, $projectId]);

            $stmtDir = $pdo->prepare("INSERT IGNORE INTO directory (project_id, user_id) VALUES (?, ?)");
            $stmtDir->execute([$projectId, $targetUserId]);

            echo json_encode(['status'=>'success']);
        } catch(Exception $e) { echo json_encode(['status'=>'error', 'msg'=>$e->getMessage()]); }
        break;

    // --- 1.1.1 ACTUALIZAR INFO DE PROYECTO (ADMIN ONLY) ---
    case 'update_project_info':
        if($userRole !== 'admin') { echo json_encode(['status'=>'error', 'msg'=>'Access Denied']); exit; }

        $id = (int)($_POST['id'] ?? ($_POST['project_id'] ?? 0));
        if ($id <= 0) { echo json_encode(['status'=>'error', 'msg'=>'Invalid project']); exit; }

        try {
            $cols = $pdo->query("DESCRIBE projects")->fetchAll(PDO::FETCH_COLUMN);
            $allowed = [
                'name','status','description','notes','address',
                'contact_name','contact_phone',
                'company_name','company_phone','company_address',
                'date_bid_sent','date_bid_awarded','date_started','date_finished','date_warranty_end'
            ];

            $payload = is_array($input ?? null) ? $input : [];
            $aliases = [
                'name' => ['name', 'project_name'],
                'date_bid_sent' => ['date_bid_sent', 'date_bid_send']
            ];
            $set = [];
            $params = [];
            foreach ($allowed as $col) {
                $keys = $aliases[$col] ?? [$col];
                $found = false;
                $val = null;
                foreach ($keys as $k) {
                    if (array_key_exists($k, $_POST)) { $val = $_POST[$k]; $found = true; break; }
                    if (array_key_exists($k, $payload)) { $val = $payload[$k]; $found = true; break; }
                }
                if (in_array($col, $cols, true) && $found) {
                    $set[] = "$col = ?";
                    if ($val === '') $val = null;
                    $params[] = $val;
                }
            }
            if (empty($set)) { echo json_encode(['status'=>'error', 'msg'=>'No valid fields','debug'=>['post_keys'=>array_keys($_POST),'json_keys'=>array_keys($payload)]]); exit; }

            $params[] = $id;
            $sql = "UPDATE projects SET " . implode(', ', $set) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            sync_project_to_inventory_from_api($pdo, $id);
            echo json_encode(['status'=>'success']);
        } catch(Exception $e) { echo json_encode(['status'=>'error', 'msg'=>$e->getMessage()]); }
        break;

    // --- 1.1.2 AGREGAR CARPETAS A PROYECTO (ADMIN ONLY) ---
    case 'add_project_folders':
        if($userRole !== 'admin') { echo json_encode(['status'=>'error', 'msg'=>'Access Denied']); exit; }
        $projectId = (int)($_POST['project_id'] ?? 0);
        $folders = $_POST['folders'] ?? [];
        if($projectId <= 0 || !is_array($folders)) { echo json_encode(['status'=>'error', 'msg'=>'Invalid data']); exit; }

        $folderNames = [
            'bom' => 'BoM',
            'schedule_values' => 'Schedule of Values',
            'rfi' => 'RFI',
            'drawings' => 'Drawings',
            'photos' => 'Photos',
            'panel_schedule' => 'Panel Schedule',
            'panel_tags' => 'Panel Tags',
            'noc' => 'NOC',
            'submittal' => 'Submittal',
            'permit' => 'Permit',
            'acknowledgement' => 'Acknowledgement',
            'payapp' => 'Payapp',
            'insurance' => 'Certificate of Insurance',
            'fault_calc' => 'Fault Current Calc',
            'labor_record' => 'Labor Record',
            'expenses' => 'Expenses',
            'warranty_sup' => 'Warranty Supplier',
            'clock_in' => 'Clock In'
        ];

        try {
            $stmtExisting = $pdo->prepare("SELECT name FROM folders WHERE project_id = ? AND deleted_at IS NULL");
            $stmtExisting->execute([$projectId]);
            $existing = array_map('strtolower', $stmtExisting->fetchAll(PDO::FETCH_COLUMN));

            $stmtIns = $pdo->prepare("INSERT INTO folders (project_id, name) VALUES (?, ?)");
            foreach ($folders as $key) {
                if (!isset($folderNames[$key])) continue;
                $name = $folderNames[$key];
                if (in_array(strtolower($name), $existing, true)) continue;
                $stmtIns->execute([$projectId, $name]);
                $existing[] = strtolower($name);
            }
            echo json_encode(['status'=>'success']);
        } catch(Exception $e) { echo json_encode(['status'=>'error', 'msg'=>$e->getMessage()]); }
        break;

    // --- 1.1.3 SET/SYNC CARPETAS DE PROYECTO (ADMIN ONLY) ---
    case 'set_project_folders':
        if($userRole !== 'admin') { echo json_encode(['status'=>'error', 'msg'=>'Access Denied']); exit; }
        $projectId = (int)($_POST['project_id'] ?? 0);
        $folders = $_POST['folders'] ?? [];
        if($projectId <= 0 || !is_array($folders)) { echo json_encode(['status'=>'error', 'msg'=>'Invalid data']); exit; }

        $folderNames = [
            'bom' => 'BoM','schedule_values' => 'Schedule of Values','rfi' => 'RFI','drawings' => 'Drawings','photos' => 'Photos',
            'panel_schedule' => 'Panel Schedule','panel_tags' => 'Panel Tags','noc' => 'NOC','submittal' => 'Submittal','permit' => 'Permit',
            'acknowledgement' => 'Acknowledgement','payapp' => 'Payapp','insurance' => 'Certificate of Insurance','fault_calc' => 'Fault Current Calc',
            'labor_record' => 'Labor Record','expenses' => 'Expenses','warranty_sup' => 'Warranty Supplier','clock_in' => 'Clock In'
        ];

        $selectedNames = [];
        foreach ($folders as $key) {
            if (isset($folderNames[$key])) $selectedNames[] = $folderNames[$key];
        }

        try {
            error_log('[set_project_folders] project_id=' . $projectId . ' selected=' . json_encode($selectedNames));

            // 1) Crear faltantes
            $stmtExisting = $pdo->prepare("SELECT id, name FROM folders WHERE project_id = ? AND parent_id IS NULL AND deleted_at IS NULL");
            $stmtExisting->execute([$projectId]);
            $existingRows = $stmtExisting->fetchAll(PDO::FETCH_ASSOC);
            $existingByName = [];
            foreach($existingRows as $r) $existingByName[strtolower($r['name'])] = (int)$r['id'];

            $stmtIns = $pdo->prepare("INSERT INTO folders (project_id, name) VALUES (?, ?)");
            foreach ($selectedNames as $name) {
                if (!isset($existingByName[strtolower($name)])) {
                    $stmtIns->execute([$projectId, $name]);
                }
            }

            // 2) Remover no seleccionadas (seguro):
            // - si carpeta vacía (sin archivos ni subcarpetas): soft delete
            // - si tiene contenido: conservar (no borrar) y reportar
            $stmtTop = $pdo->prepare("SELECT id, name FROM folders WHERE project_id = ? AND parent_id IS NULL AND deleted_at IS NULL");
            $stmtTop->execute([$projectId]);
            $topFolders = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

            $preserved = [];
            $deleted = 0;
            foreach ($topFolders as $f) {
                $name = (string)$f['name'];
                $fid = (int)$f['id'];
                if (in_array($name, $selectedNames, true)) continue;

                $cntFiles = $pdo->prepare("SELECT COUNT(*) FROM files WHERE folder_id = ? AND deleted_at IS NULL");
                $cntFiles->execute([$fid]);
                $hasFiles = (int)$cntFiles->fetchColumn() > 0;

                $cntSubs = $pdo->prepare("SELECT COUNT(*) FROM folders WHERE parent_id = ? AND deleted_at IS NULL");
                $cntSubs->execute([$fid]);
                $hasSubs = (int)$cntSubs->fetchColumn() > 0;

                if ($hasFiles || $hasSubs) {
                    $preserved[] = $name;
                    continue;
                }

                $pdo->prepare("UPDATE folders SET deleted_at = NOW() WHERE id = ?")->execute([$fid]);
                $deleted++;
            }

            echo json_encode(['status'=>'success','deleted_folders'=>$deleted,'preserved_folders'=>$preserved]);
        } catch(Exception $e) {
            error_log('[set_project_folders] ERROR: ' . $e->getMessage());
            echo json_encode(['status'=>'error', 'msg'=>$e->getMessage()]);
        }
        break;

    // --- 12.1 MOVER CARPETA (ADMIN ONLY) ---
    case 'move_folder':
        if($userRole !== 'admin') { echo json_encode(['status'=>'error', 'msg'=>'Access Denied']); exit; }
        $folderId = (int)($_POST['folder_id'] ?? 0);
        $targetProjectId = (int)($_POST['target_project_id'] ?? 0);
        $targetParentId = (int)($_POST['target_parent_folder_id'] ?? 0);

        if($folderId <= 0 || $targetProjectId <= 0) { echo json_encode(['status'=>'error', 'msg'=>'Invalid data']); exit; }

        try {
            $stmtFolder = $pdo->prepare("SELECT id, name, project_id FROM folders WHERE id = ? AND deleted_at IS NULL");
            $stmtFolder->execute([$folderId]);
            $folder = $stmtFolder->fetch(PDO::FETCH_ASSOC);
            if(!$folder) { echo json_encode(['status'=>'error', 'msg'=>'Folder not found']); exit; }

            if ($targetParentId > 0) {
                $stmtParent = $pdo->prepare("SELECT id FROM folders WHERE id = ? AND project_id = ? AND deleted_at IS NULL");
                $stmtParent->execute([$targetParentId, $targetProjectId]);
                if(!$stmtParent->fetch()) { echo json_encode(['status'=>'error', 'msg'=>'Target parent not found']); exit; }
            }

            $pdo->beginTransaction();

            if ($targetParentId > 0) {
                // Create/locate subfolder in target parent
                $stmtSub = $pdo->prepare("SELECT id FROM sub_folders WHERE folder_id = ? AND name = ? AND deleted_at IS NULL");
                $stmtSub->execute([$targetParentId, $folder['name']]);
                $subId = (int)$stmtSub->fetchColumn();
                if ($subId <= 0) {
                    $stmtInsSub = $pdo->prepare("INSERT INTO sub_folders (folder_id, name) VALUES (?, ?)");
                    $stmtInsSub->execute([$targetParentId, $folder['name']]);
                    $subId = (int)$pdo->lastInsertId();
                }

                // Move files into new subfolder
                $stmtFiles = $pdo->prepare("UPDATE files SET project_id = ?, folder_id = ?, sub_folder_id = ? WHERE folder_id = ?");
                $stmtFiles->execute([$targetProjectId, $targetParentId, $subId, $folderId]);

                // Move any subfolders to target parent
                $stmtMoveSub = $pdo->prepare("UPDATE sub_folders SET folder_id = ? WHERE folder_id = ?");
                $stmtMoveSub->execute([$targetParentId, $folderId]);

                // Soft delete original folder
                $pdo->prepare("UPDATE folders SET deleted_at = NOW() WHERE id = ?")->execute([$folderId]);
            } else {
                // Move folder to another project (top-level)
                $pdo->prepare("UPDATE folders SET project_id = ? WHERE id = ?")->execute([$targetProjectId, $folderId]);
                $pdo->prepare("UPDATE files SET project_id = ? WHERE folder_id = ?")->execute([$targetProjectId, $folderId]);
            }

            $pdo->commit();
            echo json_encode(['status'=>'success']);
        } catch(Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['status'=>'error', 'msg'=>$e->getMessage()]);
        }
        break;

    // --- 1.3 ASIGNAR MULTIPLES USUARIOS A PROYECTO (ADMIN ONLY) ---
    case 'assign_project_users':
        if($userRole !== 'admin') { echo json_encode(['status'=>'error', 'msg'=>'Access Denied']); exit; }

        $projectId = (int)($_POST['project_id'] ?? 0);
        $userIds = $_POST['user_ids'] ?? [];

        if($projectId <= 0 || !is_array($userIds) || empty($userIds)) {
            echo json_encode(['status'=>'error', 'msg'=>'Invalid data']); exit;
        }

        try {
            $cleanIds = [];
            foreach($userIds as $uid) {
                $uid = (int)$uid;
                if($uid > 0) $cleanIds[] = $uid;
            }
            $cleanIds = array_values(array_unique($cleanIds));
            if(empty($cleanIds)) { echo json_encode(['status'=>'error', 'msg'=>'Invalid users']); exit; }

            $in = implode(',', array_fill(0, count($cleanIds), '?'));
            $stmtUsers = $pdo->prepare("SELECT id, role FROM users WHERE id IN ($in)");
            $stmtUsers->execute($cleanIds);
            $rows = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) !== count($cleanIds)) {
                echo json_encode(['status'=>'error', 'msg'=>'Invalid users']); exit;
            }
            $adminIds = [];
            foreach ($rows as $r) {
                if ($r['role'] === 'admin') $adminIds[] = (int)$r['id'];
            }
            if (empty($adminIds)) {
                echo json_encode(['status'=>'error', 'msg'=>'At least one admin must be assigned']); exit;
            }

            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM directory WHERE project_id = ?")->execute([$projectId]);
            $stmtDir = $pdo->prepare("INSERT IGNORE INTO directory (project_id, user_id) VALUES (?, ?)");
            foreach($cleanIds as $uid) {
                $stmtDir->execute([$projectId, $uid]);
            }
            $primaryId = $adminIds[0];
            $pdo->prepare("UPDATE projects SET assigned_user_id = ? WHERE id = ?")->execute([$primaryId, $projectId]);
            $pdo->commit();

            echo json_encode(['status'=>'success']);
        } catch(Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['status'=>'error', 'msg'=>$e->getMessage()]);
        }
        break;

    // --- 2. CREAR CARPETA (ADMIN ONLY) ---
    case 'create_folder':
        $projectId = (int)($_POST['project_id'] ?? 0);
        $folderName = trim((string)($_POST['folder_name'] ?? $_POST['name'] ?? ''));
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

        if($userRole === 'viewer') { echo json_encode(['status'=>'error', 'msg'=>'Access Denied']); exit; }
        if(!$projectId) { echo json_encode(['status'=>'error', 'msg'=>'Invalid project.']); exit; }
        if($folderName === '') { echo json_encode(['status'=>'error', 'msg'=>'Folder name is required.']); exit; }
        if(mb_strlen($folderName) > 255) { echo json_encode(['status'=>'error', 'msg'=>'Folder name is too long (max 255 chars).']); exit; }

        try {
            $depth = 0;
            if($parentId) {
                $stmtP = $pdo->prepare("SELECT depth FROM folders WHERE id = ? AND project_id = ? AND deleted_at IS NULL");
                $stmtP->execute([$parentId, $projectId]);
                $parentRow = $stmtP->fetch(PDO::FETCH_ASSOC);
                if(!$parentRow) { echo json_encode(['status'=>'error', 'msg'=>'Parent folder not found.']); exit; }
                $depth = ((int)$parentRow['depth']) + 1;
                if($depth > 3) { echo json_encode(['status'=>'error', 'msg'=>'Maximum folder depth is 4 levels.']); exit; }
            }

            $stmtChk = $pdo->prepare("SELECT id FROM folders WHERE project_id = ? AND parent_id " . ($parentId ? "= ?" : "IS NULL") . " AND name = ? AND deleted_at IS NULL LIMIT 1");
            $params = $parentId ? [$projectId, $parentId, $folderName] : [$projectId, $folderName];
            $stmtChk->execute($params);
            if($stmtChk->fetch()) { echo json_encode(['status'=>'error', 'msg'=>'A folder with that name already exists here.']); exit; }

            $stmtIns = $pdo->prepare("INSERT INTO folders (project_id, name, parent_id, depth) VALUES (?, ?, ?, ?)");
            $stmtIns->execute([$projectId, $folderName, $parentId, $depth]);
            $newId = (int)$pdo->lastInsertId();
            echo json_encode(['status'=>'success','folder_id'=>$newId,'depth'=>$depth]);
        } catch(Exception $e) { echo json_encode(['status'=>'error', 'msg'=>$e->getMessage()]); }
        break;

    // --- 3. SUBIR ARCHIVO CON VERSIONADO (ADMIN ONLY + VALIDACIONES) ---
    case 'upload_file':
        $projectIdCheck = (int)($_POST['project_id'] ?? 0);
        $folderIdCheck = !empty($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;
        if (!canUploadToFolder($pdo, (int)$userId, $projectIdCheck, $folderIdCheck)) { echo json_encode(['status'=>'error', 'msg'=>'Access Denied']); exit; }

        if (!isset($_FILES['file'])) { echo json_encode(['status'=>'error', 'msg'=>'No file']); exit; }
        
        // 3.1 Validar Tamaño (1GB = 1,073,741,824 bytes)
        $maxSize = 10737418240; // 10GB
        if ($_FILES['file']['size'] > $maxSize) {
            echo json_encode(['status'=>'error', 'msg'=>'File exceeds 10GB limit']); 
            exit;
        }

        // 3.2 Validar Extensiones permitidas para importación masiva
        $allowedExts = [
            // Imágenes
            'jpg','jpeg','png','gif','webp','bmp','tiff','tif','heic',
            // Documentos
            'pdf','doc','docx','xls','xlsx','xlsm','csv','ppt','pptx',
            // Otros comunes en construcción
            'dwg','dxf','rvt','ifc','zip','rar'
        ];
        $origName = $_FILES["file"]["name"];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExts)) {
            echo json_encode(['status'=>'error', 'msg'=>'File type .' . $ext . ' not allowed.']);
            exit;
        }

        $projectId = $_POST['project_id'];
        $folderId = !empty($_POST['folder_id']) ? $_POST['folder_id'] : NULL;
        $subFolderId = !empty($_POST['sub_folder_id']) ? $_POST['sub_folder_id'] : NULL;

        if ($subFolderId && !$folderId) {
            echo json_encode(['status'=>'error', 'msg'=>'Select a folder before choosing a subfolder.']);
            exit;
        }

        // Default folder: Drawings (create if missing)
        if (!$folderId) {
            $defaultFolderName = 'Drawings';
            $stmtDef = $pdo->prepare("SELECT id FROM folders WHERE project_id = ? AND name = ? AND deleted_at IS NULL LIMIT 1");
            $stmtDef->execute([$projectId, $defaultFolderName]);
            $folderId = $stmtDef->fetchColumn();
            if (!$folderId) {
                $stmtCreate = $pdo->prepare("INSERT INTO folders (project_id, name) VALUES (?, ?)");
                $stmtCreate->execute([$projectId, $defaultFolderName]);
                $folderId = (int)$pdo->lastInsertId();
            }
        }
        
        $next = generateNextVersionedFilename($pdo, (int)$projectId, $folderId ? (int)$folderId : null, $subFolderId ? (int)$subFolderId : null, (string)$origName);
        $finalName = $next['filename'];
        $versionNum = (int)$next['version'];
        $versionGroup = uniqid('vgroup_');

        $fileName = time() . '_' . cleanName($origName);
        $targetDir = __DIR__ . "/../uploads/";
        if (!file_exists($targetDir)) mkdir($targetDir, 0755, true);
        $targetPath = $targetDir . $fileName;
        $type = $ext; // Usamos la extensión validada
        
        if(move_uploaded_file($_FILES["file"]["tmp_name"], $targetPath)){
            $publicPath = "uploads/" . $fileName;
            $stmt = $pdo->prepare("INSERT INTO files (project_id, folder_id, sub_folder_id, filename, filepath, file_type, uploaded_by, version_group_id, version_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$projectId, $folderId, $subFolderId, $finalName, $publicPath, $type, $userId, $versionGroup, $versionNum]);
            echo json_encode(['status'=>'success']);
        } else echo json_encode(['status'=>'error', 'msg'=>'Upload failed']);
        break;

    // --- 4. SOFT DELETE (PAPELERA) ---
    case 'delete_entity':
        if($userRole !== 'admin') { echo json_encode(['status'=>'error', 'msg'=>'Access Denied']); exit; }

        $type = $_POST['type']; 
        $id = $_POST['id'];
        $tableMap = ['project' => 'projects', 'folder' => 'folders', 'subfolder' => 'sub_folders', 'file' => 'files'];
        
        if(!isset($tableMap[$type])) { echo json_encode(['status'=>'error']); exit; }
        
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("UPDATE {$tableMap[$type]} SET deleted_at = ? WHERE id = ?");
        
        if($stmt->execute([$now, $id])) echo json_encode(['status'=>'success']);
        else echo json_encode(['status'=>'error']);
        break;

    // --- 4.1 ELIMINAR ARCHIVO (SOFT DELETE) ---
    case 'delete_file':
        if($userRole !== 'admin') { echo json_encode(['status'=>'error', 'msg'=>'Access Denied']); exit; }
        $id = $_POST['id'] ?? 0;
        if(!$id) { echo json_encode(['status'=>'error', 'msg'=>'Invalid data']); exit; }
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("UPDATE files SET deleted_at = ? WHERE id = ?");
        if($stmt->execute([$now, $id])) echo json_encode(['status'=>'success']);
        else echo json_encode(['status'=>'error']);
        break;

    // --- 5. RESTAURAR (DE PAPELERA - UPDATED FOR REPORTS) ---
    case 'restore_entity':
        if($userRole !== 'admin') { echo json_encode(['status'=>'error', 'msg'=>'Access Denied']); exit; }

        $type = $_POST['type']; 
        $id = $_POST['id'];
        
        // Manejo especial para Reportes (usan is_deleted)
        if ($type === 'report') {
            $stmt = $pdo->prepare("UPDATE file_reports SET is_deleted = 0 WHERE id = ?");
            if($stmt->execute([$id])) echo json_encode(['status'=>'success']);
            else echo json_encode(['status'=>'error']);
            exit;
        }

        // Manejo normal (deleted_at)
        $tableMap = ['project' => 'projects', 'folder' => 'folders', 'subfolder' => 'sub_folders', 'file' => 'files'];

        if(!isset($tableMap[$type])) { echo json_encode(['status'=>'error']); exit; }

        $stmt = $pdo->prepare("UPDATE {$tableMap[$type]} SET deleted_at = NULL WHERE id = ?");
        if($stmt->execute([$id])) echo json_encode(['status'=>'success']);
        else echo json_encode(['status'=>'error']);
        break;

    // --- 6. BORRADO MASIVO (SOFT DELETE) ---
    case 'delete_bulk':
        if($userRole !== 'admin') { echo json_encode(['status'=>'error', 'msg'=>'Access Denied']); exit; }

        $ids = json_decode($_POST['ids'], true);
        if (is_array($ids)) {
            $now = date('Y-m-d H:i:s');
            $inQuery = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("UPDATE files SET deleted_at = ? WHERE id IN ($inQuery)");
            $params = array_merge([$now], $ids);
            $stmt->execute($params);
            
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Invalid data']);
        }
        break;
        
    // --- 7. HARD DELETE (PERMANENTE - UPDATED FOR REPORTS) ---
    case 'hard_delete_entity':
        if($userRole !== 'admin') { echo json_encode(['status'=>'error', 'msg'=>'Access Denied']); exit; }

        $type = $_POST['type'];
        $id = (int)$_POST['id'];

        if ($type === 'file') {
            $stmt = $pdo->prepare("SELECT filepath FROM files WHERE id = ?");
            $stmt->execute([$id]);
            $file = $stmt->fetch();

            if ($file) {
                $path = $file['filepath'];
                $diskPath = $path;
                if (!empty($path) && strpos($path, 'uploads/') === 0) {
                    $diskPath = __DIR__ . '/../' . $path;
                }
                if (!empty($diskPath) && file_exists($diskPath)) {
                    unlink($diskPath);
                }
                $pdo->prepare("DELETE FROM files WHERE id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM file_reports WHERE file_id = ?")->execute([$id]);
            }
        } 
        elseif ($type === 'project') {
            $pdo->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);
        }
        // NUEVO: Borrar reporte individualmente
        elseif ($type === 'report') {
            // Borrar PDF y adjuntos físicos si existen
            $stmt = $pdo->prepare("SELECT report_pdf_path, attachments_json FROM file_reports WHERE id = ?");
            $stmt->execute([$id]);
            $rep = $stmt->fetch();
            if ($rep) {
                if (!empty($rep['report_pdf_path'])) {
                    $repPath = $rep['report_pdf_path'];
                    $repDiskPath = $repPath;
                    if (strpos($repPath, 'uploads/') === 0) {
                        $repDiskPath = __DIR__ . '/../' . $repPath;
                    }
                    if (file_exists($repDiskPath)) {
                        unlink($repDiskPath);
                    }
                }
                if (!empty($rep['attachments_json'])) {
                    $arr = json_decode($rep['attachments_json'], true);
                    if (is_array($arr)) {
                        foreach ($arr as $a) {
                            $p = $a['path'] ?? '';
                            if ($p && strpos($p, 'uploads/') === 0) {
                                $disk = __DIR__ . '/../' . $p;
                                if (file_exists($disk)) @unlink($disk);
                            }
                        }
                    }
                }
            }
            try {
                $pdo->prepare("DELETE FROM field_report_attachments WHERE field_report_id = ?")->execute([$id]);
            } catch (Throwable $e) {
                // optional table
            }
            $pdo->prepare("DELETE FROM file_reports WHERE id = ?")->execute([$id]);
        }

        echo json_encode(['status' => 'success']);
        break;

    // --- 8. GUARDAR REPORTE ---
    case 'save_report_flow':
        if($userRole === 'viewer') { echo json_encode(['status'=>'error', 'msg'=>'Access Denied']); exit; }

        try {
            if (!isset($_POST['file_id']) || !isset($_FILES['pdf_file'])) {
                throw new Exception("Missing data (ID or PDF)");
            }

            $fileId = (int)$_POST['file_id'];
            $json = $_POST['annotations_json'] ?? '{}';
            $techName = trim((string)($_POST['tech_name'] ?? ''));
            $techRole = trim((string)($_POST['tech_role'] ?? ''));
            $desc = trim((string)($_POST['description'] ?? ''));

            $reportDir = __DIR__ . '/../uploads/reports/';
            if (!is_dir($reportDir)) { mkdir($reportDir, 0777, true); }

            $fileName = 'Report_F' . $fileId . '_' . time() . '.pdf';
            $destPath = $reportDir . $fileName;

            if (!move_uploaded_file($_FILES['pdf_file']['tmp_name'], $destPath)) {
                throw new Exception("Failed to save PDF file to server");
            }

            $allowedMimes = [
                'application/pdf',
                'application/msword',
                'application/vnd.ms-excel'
            ];
            $allowedPrefix = [
                'image/',
                'application/vnd.openxmlformats-officedocument.'
            ];
            $maxFiles = 5;
            $maxBytes = 10 * 1024 * 1024;
            $attachments = [];
            $attachmentsInput = $_FILES['attachments'] ?? null;

            if ($attachmentsInput && is_array($attachmentsInput['name'] ?? null)) {
                $count = count($attachmentsInput['name']);
                if ($count > $maxFiles) {
                    throw new Exception('Maximum 5 attachments per report');
                }

                $attachDir = __DIR__ . '/../uploads/report_attachments/';
                if (!is_dir($attachDir)) { mkdir($attachDir, 0777, true); }

                for ($i = 0; $i < $count; $i++) {
                    $err = (int)($attachmentsInput['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                    if ($err === UPLOAD_ERR_NO_FILE) continue;
                    if ($err !== UPLOAD_ERR_OK) throw new Exception('Attachment upload error');

                    $tmp = $attachmentsInput['tmp_name'][$i] ?? '';
                    $orig = (string)($attachmentsInput['name'][$i] ?? 'attachment');
                    $size = (int)($attachmentsInput['size'][$i] ?? 0);
                    $mime = (string)($attachmentsInput['type'][$i] ?? 'application/octet-stream');

                    if ($size <= 0 || $size > $maxBytes) {
                        throw new Exception('Attachment exceeds 10MB: ' . $orig);
                    }

                    $mimeAllowed = in_array($mime, $allowedMimes, true);
                    if (!$mimeAllowed) {
                        foreach ($allowedPrefix as $prefix) {
                            if (strpos($mime, $prefix) === 0) { $mimeAllowed = true; break; }
                        }
                    }
                    if (!$mimeAllowed) {
                        throw new Exception('Unsupported attachment type: ' . $orig);
                    }

                    $safeBase = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($orig));
                    if ($safeBase === '' || $safeBase === '.' || $safeBase === '..') $safeBase = 'attachment';
                    $stored = 'R' . $fileId . '_' . time() . '_' . $i . '_' . $safeBase;
                    $target = $attachDir . $stored;

                    if (!move_uploaded_file($tmp, $target)) {
                        throw new Exception('Failed to save attachment: ' . $orig);
                    }

                    $attachments[] = [
                        'name' => $orig,
                        'mime' => $mime,
                        'size' => $size,
                        'path' => 'uploads/report_attachments/' . $stored,
                        'url' => '../uploads/report_attachments/' . $stored
                    ];
                }
            }

            $publicReportPath = 'uploads/reports/' . $fileName;
            $hasAttachmentsJson = db_has_column($pdo, 'file_reports', 'attachments_json');

            if ($hasAttachmentsJson) {
                $stmt = $pdo->prepare("INSERT INTO file_reports (file_id, technician_name, technician_role, description, report_pdf_path, annotations_json, attachments_json) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$fileId, $techName, $techRole, $desc, $publicReportPath, $json, json_encode($attachments)]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO file_reports (file_id, technician_name, technician_role, description, report_pdf_path, annotations_json) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$fileId, $techName, $techRole, $desc, $publicReportPath, $json]);
            }

            $reportId = (int)$pdo->lastInsertId();

            if (!empty($attachments)) {
                ensure_report_attachments_table($pdo);
                try {
                    $ins = $pdo->prepare("INSERT INTO field_report_attachments (field_report_id, original_name, mime_type, file_size, storage_path, public_url) VALUES (?, ?, ?, ?, ?, ?)");
                    foreach ($attachments as $a) {
                        $ins->execute([$reportId, $a['name'], $a['mime'], $a['size'], $a['path'], $a['url']]);
                    }
                } catch (Throwable $e) {
                    // optional relational table may not exist yet; keep attachments_json fallback
                }
            }

            echo json_encode(['status' => 'success', 'msg' => 'Report saved']);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
        }
        break;    

    // --- 9. ELIMINAR REPORTE (SOFT DELETE) ---
    case 'soft_delete_report':
        if($userRole === 'viewer') { echo json_encode(['status'=>'error', 'msg'=>'Access Denied']); exit; }

        $reportId = $_POST['report_id'] ?? 0;

        try {
            $stmt = $pdo->prepare("UPDATE file_reports SET is_deleted = 1 WHERE id = ?");
            $stmt->execute([$reportId]);
            echo json_encode(['status'=>'success']);
        } catch(Exception $e) {
            echo json_encode(['status'=>'error', 'msg'=>$e->getMessage()]);
        }
        break;

    // --- 10. OBTENER LISTA DE PROYECTOS (Reforzado) ---
    case 'get_projects_list':
        // Quitamos la restricción estricta de 'viewer' para permitir cargar la lista, 
        // pero la acción de mover seguirá bloqueada para viewers en el frontend y backend.
        try {
            $stmt = $pdo->query("SELECT id, name FROM projects WHERE deleted_at IS NULL ORDER BY created_at DESC");
            $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $projects]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
        }
        break;

    // --- 11. OBTENER CARPETAS (Reforzado) ---
    case 'get_folders_list':
        $projectId = (int)($_POST['project_id'] ?? $_GET['project_id'] ?? 0);
        if(!$projectId) { echo json_encode(['status'=>'error','msg'=>'Invalid project.']); exit; }
        try {
            $all = getVisibleFolders($pdo, $projectId, $userRole, (int)$userId);
            $byId = [];
            foreach($all as $f) { $byId[$f['id']] = $f + ['children'=>[]]; }
            $tree = [];
            foreach($byId as $id => &$f) {
                if(!empty($f['parent_id']) && isset($byId[$f['parent_id']])) $byId[$f['parent_id']]['children'][] = &$f;
                else $tree[] = &$f;
            }
            echo json_encode(['status' => 'success', 'folders' => $tree, 'data' => $all]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
        }
        break;

    // --- 12. MOVER ENTIDAD (Reforzado) ---
    case 'move_entity':
        if($userRole === 'viewer') { echo json_encode(['status'=>'error', 'msg'=>'Access Denied']); exit; }
        
        $id = $_POST['id'];
        $type = $_POST['type']; 
        $targetProj = $_POST['target_project_id'];
        $targetFolder = !empty($_POST['target_folder_id']) ? $_POST['target_folder_id'] : NULL;

        if ($type === 'file') {
            try {
                $stmt = $pdo->prepare("UPDATE files SET project_id = ?, folder_id = ? WHERE id = ?");
                $stmt->execute([$targetProj, $targetFolder, $id]);
                echo json_encode(['status' => 'success']);
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Invalid type']);
        }
        break;    


    // --- 12.2 GUARDAR EXPORT DE TOOL COMO ARCHIVO DE PROYECTO ---
    case 'save_tool_export':
        $projectId = (int)($_POST['project_id'] ?? 0);
        if (!canUploadToFolder($pdo, (int)$userId, $projectId, null)) { echo json_encode(['status'=>'error', 'msg'=>'Access Denied']); exit; }
        $toolNameRaw = trim((string)($_POST['tool_name'] ?? ''));
        $filenameRaw = trim((string)($_POST['filename'] ?? ''));

        $toolMap = [
            'panel_schedule' => ['slug' => 'panel_schedule', 'folder' => 'Panel Schedule'],
            'wireway' => ['slug' => 'wireway', 'folder' => 'Wireway'],
            'wireway_calculator' => ['slug' => 'wireway', 'folder' => 'Wireway'],
            'room_designer' => ['slug' => 'room_designer', 'folder' => 'Room Designer']
        ];

        if ($projectId <= 0) { echo json_encode(['status'=>'error', 'msg'=>'Invalid project_id']); exit; }
        if (!isset($_FILES['pdf_file'])) { echo json_encode(['status'=>'error', 'msg'=>'Missing pdf_file']); exit; }

        $toolKey = strtolower(preg_replace('/[^a-z0-9_\-]/i', '', $toolNameRaw));
        $toolCfg = $toolMap[$toolKey] ?? null;
        if (!$toolCfg) { echo json_encode(['status'=>'error', 'msg'=>'Invalid tool_name']); exit; }
        $toolSlug = $toolCfg['slug'];
        $toolFolderName = $toolCfg['folder'];

        $origName = $_FILES['pdf_file']['name'] ?? '';
        $ext = strtolower(pathinfo($origName ?: $filenameRaw, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') { echo json_encode(['status'=>'error', 'msg'=>'Only PDF allowed']); exit; }

        if ($_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['status'=>'error', 'msg'=>'Upload error']); exit;
        }

        try {
            $stmtFolder = $pdo->prepare("SELECT id FROM folders WHERE project_id = ? AND name = 'Exports' AND deleted_at IS NULL LIMIT 1");
            $stmtFolder->execute([$projectId]);
            $toolsFolderId = (int)$stmtFolder->fetchColumn();

            if ($toolsFolderId <= 0) {
                $pdo->prepare("INSERT INTO folders (project_id, name) VALUES (?, 'Exports')")->execute([$projectId]);
                $toolsFolderId = (int)$pdo->lastInsertId();
            }

            $stmtSub = $pdo->prepare("SELECT id FROM sub_folders WHERE folder_id = ? AND name = ? AND deleted_at IS NULL LIMIT 1");
            $stmtSub->execute([$toolsFolderId, $toolFolderName]);
            $toolSubFolderId = (int)$stmtSub->fetchColumn();
            if ($toolSubFolderId <= 0) {
                $pdo->prepare("INSERT INTO sub_folders (folder_id, name) VALUES (?, ?)")->execute([$toolsFolderId, $toolFolderName]);
                $toolSubFolderId = (int)$pdo->lastInsertId();
            }

            $datePart = gmdate('Y-m-d');
            $baseFile = $filenameRaw !== '' ? cleanName($filenameRaw) : ('export_' . $datePart . '.pdf');
            if (strtolower(pathinfo($baseFile, PATHINFO_EXTENSION)) !== 'pdf') {
                $baseFile .= '.pdf';
            }

            $targetDir = __DIR__ . '/../uploads/tool/' . $toolSlug . '/' . $projectId . '/';
            if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                throw new Exception('Failed creating target directory');
            }

            $diskName = time() . '_' . cleanName($baseFile);
            $targetPath = $targetDir . $diskName;

            if (!move_uploaded_file($_FILES['pdf_file']['tmp_name'], $targetPath)) {
                throw new Exception('Failed moving uploaded file');
            }

            $publicPath = 'uploads/tool/' . $toolSlug . '/' . $projectId . '/' . $diskName;

            $next = generateNextVersionedFilename($pdo, (int)$projectId, (int)$toolsFolderId, (int)$toolSubFolderId, (string)$baseFile);
            $baseFile = $next['filename'];
            $versionNum = (int)$next['version'];
            $versionGroup = uniqid('vgroup_');

            $stmt = $pdo->prepare("INSERT INTO files (project_id, folder_id, sub_folder_id, filename, filepath, file_type, uploaded_by, version_group_id, version_number)
                                   VALUES (?, ?, ?, ?, ?, 'pdf', ?, ?, ?)");
            $stmt->execute([$projectId, $toolsFolderId, $toolSubFolderId, $baseFile, $publicPath, $userId, $versionGroup, $versionNum]);

            echo json_encode([
                'status' => 'success',
                'path' => $publicPath,
                'folder_id' => $toolsFolderId,
                'sub_folder_id' => $toolSubFolderId,
                'sub_folder_name' => $toolFolderName,
                'filename' => $baseFile,
                'version' => $versionNum
            ]);
        } catch(Exception $e) {
            echo json_encode(['status'=>'error', 'msg'=>$e->getMessage()]);
        }
        break;


    case 'grant_folder_permission':
        if($userRole !== 'admin') { echo json_encode(['status'=>'error','msg'=>'Access Denied']); exit; }
        $folderId = (int)($_POST['folder_id'] ?? 0);
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        if(!$folderId || !$targetUserId) { echo json_encode(['status'=>'error','msg'=>'Invalid data']); exit; }
        $stmt = $pdo->prepare("INSERT IGNORE INTO folder_permissions (folder_id, user_id, granted_by) VALUES (?, ?, ?)");
        $stmt->execute([$folderId, $targetUserId, $userId]);
        echo json_encode(['status'=>'success']);
        break;

    case 'revoke_folder_permission':
        if($userRole !== 'admin') { echo json_encode(['status'=>'error','msg'=>'Access Denied']); exit; }
        $folderId = (int)($_POST['folder_id'] ?? 0);
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM folder_permissions WHERE folder_id = ? AND user_id = ?");
        $stmt->execute([$folderId, $targetUserId]);
        echo json_encode(['status'=>'success']);
        break;

    case 'create_project_bulk':
        if($userRole !== 'admin') { echo json_encode(['status'=>'error','msg'=>'Access Denied']); exit; }
        $name = trim($_POST['name'] ?? '');
        if($name === '') { echo json_encode(['status'=>'error','msg'=>'Project name required']); exit; }
        try {
            $creatorId = (int)$userId;
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO projects (name, description, status, created_by, assigned_user_id) VALUES (?, '', 'Active', ?, ?)");
            $stmt->execute([$name, $creatorId, $creatorId]);
            $newProjectId = (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT IGNORE INTO directory (project_id, user_id) VALUES (?, ?)")->execute([$newProjectId, $creatorId]);
            $pdo->commit();
            echo json_encode(['status'=>'success','project_id'=>$newProjectId]);
        } catch(Exception $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['status'=>'error','msg'=>$e->getMessage()]);
        }
        break;

    case 'upload_zip_bulk':
        if($userRole !== 'admin') { echo json_encode(['status'=>'error','msg'=>'Access Denied']); exit; }
        if(!isset($_FILES['zip_file']) || $_FILES['zip_file']['error'] !== UPLOAD_ERR_OK) { echo json_encode(['status'=>'error','msg'=>'ZIP upload failed. Check file size limits.']); exit; }

        $zipOrigName = $_FILES['zip_file']['name'];
        if(strtolower(pathinfo($zipOrigName, PATHINFO_EXTENSION)) !== 'zip') { echo json_encode(['status'=>'error','msg'=>'Only .zip files are allowed.']); exit; }
        if(!class_exists('ZipArchive')) { echo json_encode(['status'=>'error','msg'=>'ZIP extraction not available on this server. Please contact support.']); exit; }

        $projectId = (int)($_POST['project_id'] ?? 0);
        $parentFolderId = !empty($_POST['parent_folder_id']) ? (int)$_POST['parent_folder_id'] : null;
        if(!$projectId) { echo json_encode(['status'=>'error','msg'=>'Invalid project.']); exit; }

        $zip = new ZipArchive();
        $tmpPath = $_FILES['zip_file']['tmp_name'];
        if($zip->open($tmpPath) !== true) { echo json_encode(['status'=>'error','msg'=>'Could not open ZIP file.']); exit; }

        $foldersCreated = 0; $filesCreated = 0; $log = []; $folderCache = [];
        // Block only server-side executables — everything else is allowed
        $blockedExts = ['php','php3','php4','php5','php7','php8','phtml','phar','cgi','pl','py','rb','sh','bash','zsh','exe','bat','cmd','com','msi','dll','so','htaccess','htpasswd','env'];

        $rootFolderName = pathinfo($zipOrigName, PATHINFO_FILENAME);
        $rootFolderName = preg_replace('/[^a-zA-Z0-9\s\-_\(\)\.]/u', '', $rootFolderName);
        if(empty(trim($rootFolderName))) $rootFolderName = 'Imported';

        // Detect if ZIP has a single internal root folder (Mac/Windows compress behavior)
        // e.g. Drawings.zip contains Drawings/file.pdf → use "Drawings" as root, strip prefix
        $zipInternalRoot = null;
        $firstEntry = $zip->numFiles > 0 ? $zip->getNameIndex(0) : null;
        if ($firstEntry && substr($firstEntry, -1) === '/' && strpos(rtrim($firstEntry, '/'), '/') === false) {
            $detectedRoot = rtrim($firstEntry, '/');
            $allSameRoot = true;
            for ($j = 0; $j < $zip->numFiles; $j++) {
                $n = $zip->getNameIndex($j);
                if ($n && $n !== $firstEntry && strpos($n, $detectedRoot . '/') !== 0) { $allSameRoot = false; break; }
            }
            if ($allSameRoot) {
                $zipInternalRoot = $detectedRoot;
                $rootFolderName = $detectedRoot;
            }
        }

        $getOrCreateFolder = function(string $relPath, ?int $parentId) use ($pdo, $projectId, &$folderCache, &$foldersCreated, &$log) {
            if(isset($folderCache[$relPath])) return $folderCache[$relPath];
            $name = basename($relPath);
            if(empty(trim($name))) return $parentId;
            $depth = min(substr_count($relPath, '/'), 3);

            $stmtChk = $pdo->prepare("SELECT id FROM folders WHERE project_id = ? AND parent_id " . ($parentId ? "= ?" : "IS NULL") . " AND name = ? AND deleted_at IS NULL LIMIT 1");
            $params = $parentId ? [$projectId, $parentId, $name] : [$projectId, $name];
            $stmtChk->execute($params);
            $existing = $stmtChk->fetchColumn();
            if($existing) { $folderCache[$relPath] = (int)$existing; return (int)$existing; }

            $stmtIns = $pdo->prepare("INSERT INTO folders (project_id, parent_id, name, depth) VALUES (?, ?, ?, ?)");
            $stmtIns->execute([$projectId, $parentId, $name, $depth]);
            $newId = (int)$pdo->lastInsertId();
            $folderCache[$relPath] = $newId; $foldersCreated++; $log[] = "📁 Created folder: {$relPath}";
            return $newId;
        };

        // ALWAYS create the root folder — never skip it
        $rootFolderId = $getOrCreateFolder($rootFolderName, $parentFolderId);

        $targetDir = __DIR__ . '/../uploads/';
        if(!file_exists($targetDir)) mkdir($targetDir, 0755, true);

        for($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            if(!$entryName) continue;
            $baseName = basename($entryName);
            if($baseName === '' || $baseName[0] === '.' || str_contains($entryName, '__MACOSX') || str_contains($entryName, 'Thumbs.db')) continue;

            // Strip the ZIP's internal root prefix so paths are relative inside rootFolder
            if ($zipInternalRoot !== null) {
                if ($entryName === $zipInternalRoot . '/') continue;
                $relPath = substr($entryName, strlen($zipInternalRoot) + 1);
                if ($relPath === false || $relPath === '') continue;
            } else {
                $relPath = $entryName;
            }

            if(str_ends_with($entryName, '/')) {
                $dirRelPath = rtrim($relPath, '/');
                if(empty($dirRelPath)) continue;
                $pathParts = explode('/', $dirRelPath);
                $currentPath = ''; $currentParent = $rootFolderId;
                foreach($pathParts as $part) {
                    if(empty($part)) continue;
                    $currentPath = $currentPath ? $currentPath . '/' . $part : $part;
                    $currentParent = $getOrCreateFolder($currentPath, $currentParent);
                }
                continue;
            }

            $ext = strtolower(pathinfo($baseName, PATHINFO_EXTENSION));
            if(in_array($ext, $blockedExts)) { $log[] = "⚠️ Skipped (type not allowed): {$baseName}"; continue; }

            $fileDirRel = ltrim(dirname($relPath), '/');
            $fileFolderId = $rootFolderId;
            if(!empty($fileDirRel) && $fileDirRel !== '.') {
                $pathParts = explode('/', $fileDirRel);
                $currentPath = ''; $currentParent = $rootFolderId;
                foreach($pathParts as $part) {
                    if(empty($part)) continue;
                    $currentPath = $currentPath ? $currentPath . '/' . $part : $part;
                    $currentParent = $getOrCreateFolder($currentPath, $currentParent);
                }
                $fileFolderId = $currentParent;
            }

            $content = $zip->getFromIndex($i);
            if($content === false) { $log[] = "❌ Failed to read: {$baseName}"; continue; }
            $storedName = time() . '_' . mt_rand(100,999) . '_' . preg_replace('/[^a-zA-Z0-9\.\-_]/', '_', $baseName);
            $destPath = $targetDir . $storedName;
            if(file_put_contents($destPath, $content) === false) { $log[] = "❌ Failed to save: {$baseName}"; continue; }

            $vGroup = uniqid('vgroup_');
            $stmt = $pdo->prepare("INSERT INTO files (project_id, folder_id, sub_folder_id, filename, filepath, file_type, uploaded_by, version_group_id, version_number) VALUES (?, ?, NULL, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$projectId, $fileFolderId, $baseName, 'uploads/' . $storedName, $ext, $userId, $vGroup]);
            $filesCreated++; $log[] = "✅ {$baseName}";
        }
        $zip->close();
        echo json_encode(['status' => 'success','files_created' => $filesCreated,'folders_created' => $foldersCreated,'log' => $log]);
        break;


    case 'search_project_files':
        $projectId = (int)($_POST['project_id'] ?? 0);
        if (!canAccessProject($pdo, (int)$userId, $projectId)) { echo json_encode(['status'=>'error','msg'=>'Access Denied']); exit; }
        $query = trim($_POST['query'] ?? '');
        if (!$projectId || strlen($query) < 2) { echo json_encode(['status'=>'success','results'=>[]]); exit; }
        $stmtAccess = $pdo->prepare("SELECT id FROM projects WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $stmtAccess->execute([$projectId]);
        if (!$stmtAccess->fetch()) { echo json_encode(['status'=>'error','msg'=>'Access denied']); exit; }
        $stmtFiles = $pdo->prepare("SELECT f.id, f.filename, f.filepath, f.file_type, f.folder_id, fo.name AS folder_name, fo.parent_id, p.name AS parent_folder_name FROM files f LEFT JOIN folders fo ON fo.id = f.folder_id AND fo.deleted_at IS NULL LEFT JOIN folders p ON p.id = fo.parent_id AND p.deleted_at IS NULL WHERE f.project_id = ? AND f.deleted_at IS NULL AND f.filename LIKE ? ORDER BY f.filename ASC LIMIT 30");
        $stmtFiles->execute([$projectId, '%' . $query . '%']);
        $files = $stmtFiles->fetchAll(PDO::FETCH_ASSOC); $results = [];
        foreach($files as $file){ $breadcrumb=[]; if($file['parent_folder_name']) $breadcrumb[]=$file['parent_folder_name']; if($file['folder_name']) $breadcrumb[]=$file['folder_name']; $breadcrumb[]=$file['filename']; $results[]=['id'=>$file['id'],'filename'=>$file['filename'],'file_type'=>$file['file_type'],'folder_id'=>$file['folder_id'],'breadcrumb'=>$breadcrumb]; }
        echo json_encode(['status'=>'success','results'=>$results]);
        break;

    case 'rename_file':
        $id = (int)($_POST['id'] ?? 0); $newName = trim($_POST['name'] ?? '');
        if(!$id || $newName === '') { echo json_encode(['status'=>'error','msg'=>'Invalid data']); exit; }
        $q = $pdo->prepare("SELECT project_id FROM files WHERE id=? AND deleted_at IS NULL LIMIT 1"); $q->execute([$id]); $pid=(int)$q->fetchColumn();
        if(!$pid || !canEditFile($pdo,(int)$userId,$pid,$id)) { echo json_encode(['status'=>'error','msg'=>'Access Denied']); exit; }
        if(mb_strlen($newName) > 255) { echo json_encode(['status'=>'error','msg'=>'Name too long']); exit; }
        $stmt = $pdo->prepare("UPDATE files SET filename = ? WHERE id = ? AND deleted_at IS NULL LIMIT 1"); $stmt->execute([$newName, $id]);
        echo json_encode(['status'=> $stmt->rowCount() ? 'success' : 'error', 'msg'=> $stmt->rowCount() ? '' : 'File not found']);
        break;

    case 'rename_folder':
        $id = (int)($_POST['id'] ?? 0); $newName = trim($_POST['name'] ?? '');
        if(!$id || $newName === '') { echo json_encode(['status'=>'error','msg'=>'Invalid data']); exit; }
        $q = $pdo->prepare("SELECT project_id FROM folders WHERE id=? AND deleted_at IS NULL LIMIT 1"); $q->execute([$id]); $pid=(int)$q->fetchColumn();
        if(!$pid || !canAccessProject($pdo,(int)$userId,$pid)) { echo json_encode(['status'=>'error','msg'=>'Access Denied']); exit; }
        if(mb_strlen($newName) > 255) { echo json_encode(['status'=>'error','msg'=>'Name too long']); exit; }
        $stmt = $pdo->prepare("UPDATE folders SET name = ? WHERE id = ? AND deleted_at IS NULL LIMIT 1"); $stmt->execute([$newName, $id]);
        echo json_encode(['status'=> $stmt->rowCount() ? 'success' : 'error', 'msg'=> $stmt->rowCount() ? '' : 'Folder not found']);
        break;

    case 'set_visibility_rules':
        if($userRole === 'viewer') { echo json_encode(['status'=>'error','msg'=>'Access Denied']); exit; }
        $entityType = trim((string)($_POST['entity_type'] ?? ''));
        $entityId = (int)($_POST['entity_id'] ?? 0);
        $rolesCsv = trim((string)($_POST['roles'] ?? ''));
        $usersCsv = trim((string)($_POST['users'] ?? ''));
        if(!$entityId || !in_array($entityType, ['file','folder'], true)) { echo json_encode(['status'=>'error','msg'=>'Invalid data']); exit; }

        $projectId = 0;
        if($entityType === 'file') { $s=$pdo->prepare("SELECT project_id FROM files WHERE id=? LIMIT 1"); $s->execute([$entityId]); $projectId=(int)$s->fetchColumn(); }
        else { $s=$pdo->prepare("SELECT project_id FROM folders WHERE id=? LIMIT 1"); $s->execute([$entityId]); $projectId=(int)$s->fetchColumn(); }
        if(!$projectId || !canAccessProject($pdo,(int)$userId,$projectId)) { echo json_encode(['status'=>'error','msg'=>'Access Denied']); exit; }

        $roles = array_values(array_filter(array_map('trim', explode(',', $rolesCsv))));
        $users = array_values(array_filter(array_map('intval', explode(',', $usersCsv))));

        $table = $entityType === 'file' ? 'file_visibility_rules' : 'folder_visibility_rules';
        $fk = $entityType === 'file' ? 'file_id' : 'folder_id';
        $pdo->prepare("DELETE FROM {$table} WHERE {$fk}=?")->execute([$entityId]);

        $ins = $pdo->prepare("INSERT INTO {$table} ({$fk}, subject_type, subject_value, subject_id, allow_view, deny_view, created_by) VALUES (?, 'role', ?, NULL, 1, 0, ?)");
        foreach($roles as $r){ $ins->execute([$entityId, strtolower($r), (int)$userId]); }
        $insU = $pdo->prepare("INSERT INTO {$table} ({$fk}, subject_type, subject_value, subject_id, allow_view, deny_view, created_by) VALUES (?, 'user', NULL, ?, 1, 0, ?)");
        foreach($users as $u){ if($u>0) $insU->execute([$entityId, $u, (int)$userId]); }

        echo json_encode(['status'=>'success']);
        break;

    case 'load_annotations':
        $fileId = (int)($_POST['file_id'] ?? 0);
        if(!$fileId){ echo json_encode(['status'=>'error','msg'=>'Invalid file_id']); exit; }
        $pdo->exec("CREATE TABLE IF NOT EXISTS image_annotations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, file_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, annotations_json LONGTEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_file_user(file_id,user_id), KEY idx_file(file_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $s = $pdo->prepare("SELECT annotations_json FROM image_annotations WHERE file_id=? AND user_id=? LIMIT 1");
        $s->execute([$fileId, (int)$userId]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['status'=>'success','annotations_json'=>$row['annotations_json'] ?? null]);
        break;

    case 'save_annotations':
        if($userRole === 'viewer'){ echo json_encode(['status'=>'error','msg'=>'Access Denied']); exit; }
        $fileId = (int)($_POST['file_id'] ?? 0);
        $json = $_POST['annotations_json'] ?? '{}';
        if(!$fileId){ echo json_encode(['status'=>'error','msg'=>'Invalid file_id']); exit; }
        $pdo->exec("CREATE TABLE IF NOT EXISTS image_annotations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, file_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, annotations_json LONGTEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_file_user(file_id,user_id), KEY idx_file(file_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $s = $pdo->prepare("INSERT INTO image_annotations (file_id,user_id,annotations_json) VALUES (?,?,?) ON DUPLICATE KEY UPDATE annotations_json=VALUES(annotations_json), updated_at=CURRENT_TIMESTAMP");
        $s->execute([$fileId,(int)$userId,$json]);
        echo json_encode(['status'=>'success']);
        break;

    case 'export_edited_image':
        if($userRole === 'viewer'){ echo json_encode(['status'=>'error','msg'=>'Access Denied']); exit; }
        $fileId = (int)($_POST['file_id'] ?? 0);
        $imageData = (string)($_POST['image_data'] ?? '');
        if(!$fileId || strpos($imageData,'data:image/png;base64,') !== 0){ echo json_encode(['status'=>'error','msg'=>'Invalid payload']); exit; }
        $raw = base64_decode(substr($imageData, strlen('data:image/png;base64,')));
        if($raw === false){ echo json_encode(['status'=>'error','msg'=>'Invalid image']); exit; }
        $dir = __DIR__ . '/uploads/edited'; if(!is_dir($dir)) @mkdir($dir,0775,true);
        $name = 'edited_' . $fileId . '_' . time() . '.png';
        $path = $dir . '/' . $name;
        file_put_contents($path, $raw);
        echo json_encode(['status'=>'success','path'=>'uploads/edited/'.$name]);
        break;

    case 'load_annotations':
        $fileId = (int)($_POST['file_id'] ?? 0);
        if(!$fileId){ echo json_encode(['status'=>'error','msg'=>'Invalid file']); exit; }
        $f = $pdo->prepare("SELECT id FROM files WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $f->execute([$fileId]);
        if(!$f->fetch()){ echo json_encode(['status'=>'error','msg'=>'File not found']); exit; }
        ensure_image_annotations_table($pdo);
        $st = $pdo->prepare("SELECT annotations_json FROM image_annotations WHERE file_id = ? LIMIT 1");
        $st->execute([$fileId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['status'=>'success','annotations_json'=>$row['annotations_json'] ?? null]);
        break;

    case 'save_annotations':
        if($userRole === 'viewer'){ echo json_encode(['status'=>'error','msg'=>'Access Denied']); exit; }
        $fileId = (int)($_POST['file_id'] ?? 0);
        $json = (string)($_POST['annotations_json'] ?? '{}');
        if(!$fileId){ echo json_encode(['status'=>'error','msg'=>'Invalid file']); exit; }
        json_decode($json);
        if(json_last_error() !== JSON_ERROR_NONE){ echo json_encode(['status'=>'error','msg'=>'Invalid JSON']); exit; }
        ensure_image_annotations_table($pdo);
        $sql = "INSERT INTO image_annotations (file_id, user_id, annotations_json) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE annotations_json = VALUES(annotations_json), user_id = VALUES(user_id), updated_at = CURRENT_TIMESTAMP";
        $st = $pdo->prepare($sql);
        $st->execute([$fileId, (int)$userId, $json]);
        echo json_encode(['status'=>'success']);
        break;

    case 'export_edited_image':
        if($userRole === 'viewer'){ echo json_encode(['status'=>'error','msg'=>'Access Denied']); exit; }
        $fileId = (int)($_POST['file_id'] ?? 0);
        $imageData = (string)($_POST['image_data'] ?? '');
        if(!$fileId || strpos($imageData, 'data:image/png;base64,') !== 0){ echo json_encode(['status'=>'error','msg'=>'Invalid export payload']); exit; }
        $bin = base64_decode(substr($imageData, strlen('data:image/png;base64,')), true);
        if($bin === false){ echo json_encode(['status'=>'error','msg'=>'Invalid image data']); exit; }
        $dir = __DIR__ . '/../uploads/edited_snapshots';
        if(!is_dir($dir)) @mkdir($dir, 0775, true);
        $name = 'edited_' . $fileId . '_' . time() . '.png';
        $full = $dir . '/' . $name;
        if(file_put_contents($full, $bin) === false){ echo json_encode(['status'=>'error','msg'=>'Unable to write snapshot']); exit; }
        echo json_encode(['status'=>'success','url'=>'../uploads/edited_snapshots/' . $name]);
        break;

    case 'track_file_view':
        $fileId = (int)($_POST['file_id'] ?? 0);
        if(!$fileId) { echo json_encode(['status'=>'error']); exit; }
        $stmtDel = $pdo->prepare("DELETE FROM file_views WHERE file_id=? AND user_id=?");
        $stmtDel->execute([$fileId, $userId]);
        $stmtIns = $pdo->prepare("INSERT INTO file_views (file_id, user_id, viewed_at) VALUES (?,?,NOW())");
        $stmtIns->execute([$fileId, $userId]);
        echo json_encode(['status'=>'success']);
        break;

    default: echo json_encode(['status'=>'error', 'msg'=>'Invalid action']);
}
?>
