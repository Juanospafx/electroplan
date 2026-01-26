<?php
// api.php - Backend Enterprise V6.3 (Strict File Validation)
require_once __DIR__ . '/../core/auth/session.php';
require_once __DIR__ . '/../core/db/connection.php';
require_once __DIR__ . '/../funciones/file_names.php';
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

switch($action) {
    
    // --- 1. CREAR PROYECTO (ADMIN ONLY) ---
    case 'create_project':
        if($userRole !== 'admin') { echo json_encode(['status'=>'error', 'msg'=>'Access Denied']); exit; }
        
        $name = $_POST['name'];
        $desc = $_POST['description'] ?? '';
        $status = $_POST['status'] ?? 'Active';

        try {
            $stmt = $pdo->prepare("INSERT INTO projects (name, description, status, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $desc, $status, $userId]);
            echo json_encode(['status'=>'success']);
        } catch(Exception $e) { echo json_encode(['status'=>'error', 'msg'=>$e->getMessage()]); }
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
            echo json_encode(['status'=>'success']);
        } catch(Exception $e) { echo json_encode(['status'=>'error', 'msg'=>$e->getMessage()]); }
        break;

    // --- 2. CREAR CARPETA (ADMIN ONLY) ---
    case 'create_folder':
        if($userRole !== 'admin') { echo json_encode(['status'=>'error', 'msg'=>'Access Denied']); exit; }

        $projectId = $_POST['project_id'];
        $name = $_POST['name'];
        
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM folders WHERE project_id = ? AND deleted_at IS NULL");
        $stmtCount->execute([$projectId]);
        if($stmtCount->fetchColumn() >= 10) {
            echo json_encode(['status'=>'error', 'msg'=>'Limit reached: Max 10 folders per project.']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO folders (project_id, name) VALUES (?, ?)");
            $stmt->execute([$projectId, $name]);
            echo json_encode(['status'=>'success']);
        } catch(Exception $e) { echo json_encode(['status'=>'error', 'msg'=>$e->getMessage()]); }
        break;

    // --- 3. SUBIR ARCHIVO CON VERSIONADO (ADMIN ONLY + VALIDACIONES) ---
    case 'upload_file':
        if($userRole !== 'admin') { echo json_encode(['status'=>'error', 'msg'=>'Access Denied']); exit; }

        if (!isset($_FILES['file'])) { echo json_encode(['status'=>'error', 'msg'=>'No file']); exit; }
        
        // 3.1 Validar Tamaño (1GB = 1,073,741,824 bytes)
        $maxSize = 1073741824;
        if ($_FILES['file']['size'] > $maxSize) {
            echo json_encode(['status'=>'error', 'msg'=>'File exceeds 1GB limit']); 
            exit;
        }

        // 3.2 Validar Extensiones (PDF e Imágenes)
        $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff'];
        $origName = $_FILES["file"]["name"];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExts)) {
            echo json_encode(['status'=>'error', 'msg'=>'Invalid file type. Only PDF and Images allowed.']);
            exit;
        }

        $projectId = $_POST['project_id'];
        $folderId = !empty($_POST['folder_id']) ? $_POST['folder_id'] : NULL;
        $subFolderId = !empty($_POST['sub_folder_id']) ? $_POST['sub_folder_id'] : NULL;
        
        $sqlCheck = "SELECT id, version_group_id, version_number FROM files 
                     WHERE project_id = ? AND filename = ? AND deleted_at IS NULL ";
        $params = [$projectId, $origName];
        
        if($folderId) { $sqlCheck .= " AND folder_id = ?"; $params[] = $folderId; } 
        else { $sqlCheck .= " AND folder_id IS NULL"; }

        if($subFolderId) { $sqlCheck .= " AND sub_folder_id = ?"; $params[] = $subFolderId; }
        else { $sqlCheck .= " AND sub_folder_id IS NULL"; }
        
        $sqlCheck .= " ORDER BY version_number DESC LIMIT 1";

        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->execute($params);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        $versionGroup = uniqid('vgroup_'); 
        $versionNum = 1;

        if ($existing) {
            $versionGroup = $existing['version_group_id'] ?: uniqid('vgroup_');
            $versionNum = $existing['version_number'] + 1;
            if(!$existing['version_group_id']) {
                $pdo->prepare("UPDATE files SET version_group_id = ? WHERE id = ?")->execute([$versionGroup, $existing['id']]);
            }
        }

        $fileName = time() . '_' . cleanName($origName);
        $targetDir = __DIR__ . "/../uploads/";
        if (!file_exists($targetDir)) mkdir($targetDir, 0755, true);
        $targetPath = $targetDir . $fileName;
        $type = $ext; // Usamos la extensión validada
        
        if(move_uploaded_file($_FILES["file"]["tmp_name"], $targetPath)){
            $publicPath = "uploads/" . $fileName;
            $stmt = $pdo->prepare("INSERT INTO files (project_id, folder_id, sub_folder_id, filename, filepath, file_type, uploaded_by, version_group_id, version_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$projectId, $folderId, $subFolderId, $origName, $publicPath, $type, $userId, $versionGroup, $versionNum]);
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
            // Opcional: Borrar el PDF del disco
            $stmt = $pdo->prepare("SELECT report_pdf_path FROM file_reports WHERE id = ?");
            $stmt->execute([$id]);
            $rep = $stmt->fetch();
            if ($rep && !empty($rep['report_pdf_path'])) {
                $repPath = $rep['report_pdf_path'];
                $repDiskPath = $repPath;
                if (strpos($repPath, 'uploads/') === 0) {
                    $repDiskPath = __DIR__ . '/../' . $repPath;
                }
                if (file_exists($repDiskPath)) {
                    unlink($repDiskPath);
                }
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

            $fileId = $_POST['file_id'];
            $json = $_POST['annotations_json'];
            $techName = $_POST['tech_name'];
            $techRole = $_POST['tech_role'];
            $desc = $_POST['description'];
            
            $reportDir = __DIR__ . '/../uploads/reports/';
            if (!is_dir($reportDir)) { mkdir($reportDir, 0777, true); }

            $fileName = 'Report_F' . $fileId . '_' . time() . '.pdf';
            $destPath = $reportDir . $fileName;
            
            if (!move_uploaded_file($_FILES['pdf_file']['tmp_name'], $destPath)) {
                throw new Exception("Failed to save PDF file to server");
            }

            $publicReportPath = 'uploads/reports/' . $fileName;
            $stmt = $pdo->prepare("INSERT INTO file_reports (file_id, technician_name, technician_role, description, report_pdf_path, annotations_json) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$fileId, $techName, $techRole, $desc, $publicReportPath, $json]);

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
        $pid = $_POST['project_id'] ?? 0;
        try {
            $stmt = $pdo->prepare("SELECT id, name FROM folders WHERE project_id = ? AND deleted_at IS NULL ORDER BY name ASC");
            $stmt->execute([$pid]);
            $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'data' => $folders]);
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

    default: echo json_encode(['status'=>'error', 'msg'=>'Invalid action']);
}
?>
