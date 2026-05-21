<?php
// pages/project_dashboard.php
// CORRECCIÓN: Agregado "/.." en las rutas para salir de 'pages' y encontrar 'core'
require_once __DIR__ . '/../core/auth/session.php';
require_once __DIR__ . '/../core/db/connection.php';
require_once __DIR__ . '/../api/rbac.php';

$projectId = (int)($_GET['id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($projectId <= 0 || $userId <= 0 || !canAccessProject($pdo, $userId, $projectId)) {
    header("Location: index.php");
    exit;
}

// 1. Obtener Datos del Proyecto
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$projectId]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    header("Location: index.php");
    exit;
}

$projectDesc = $project['description'] ?? '';
if ($projectDesc === '' && !empty($project['notes'])) $projectDesc = $project['notes'];
$projectNotes = $project['notes'] ?? '';
if ($projectNotes === '' && !empty($project['description'])) $projectNotes = $project['description'];
$projectAddress = $project['address'] ?? ($project['job_address'] ?? '');
$projectContactName = $project['contact_name'] ?? ($project['site_contact_name'] ?? '');
$projectContactPhone = $project['contact_phone'] ?? ($project['site_contact_phone'] ?? '');
$projectCompanyName = $project['company_name'] ?? ($project['gc_company'] ?? '');
$projectCompanyPhone = $project['company_phone'] ?? ($project['office_phone'] ?? '');
$projectCompanyAddress = $project['company_address'] ?? ($project['hq_address'] ?? '');

$assignUsers = [];
$assignedUserIds = [];
if (($_SESSION['role'] ?? '') === 'admin') {
    $assignUsers = $pdo->query("SELECT id, username, role FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
    $stmtAssigned = $pdo->prepare("SELECT user_id FROM directory WHERE project_id = ?");
    $stmtAssigned->execute([$projectId]);
    $assignedUserIds = array_map('intval', $stmtAssigned->fetchAll(PDO::FETCH_COLUMN));
}

$pageTitle = $project['name'];
$currentView = $_GET['view'] ?? 'summary'; // summary, desc, files, clockin, etc.
$currentFolderId = $_GET['folder_id'] ?? null;
$toolCatalog = [
    'room_designer' => [
        'name' => 'Room Designer',
        'icon' => 'fa-drafting-compass',
        'desc' => 'Design room layouts and electrical placement.',
        'active' => true,
        'slug' => 'room_designer',
        'url' => '/electroplan/wireway-electrical%20room/electrical-room.html'
    ],
    'wireway_calculator' => [
        'name' => 'Wireway Calculator',
        'icon' => 'fa-ruler-combined',
        'desc' => 'Calculate wireway lengths and load routing.',
        'active' => true,
        'slug' => 'wireway',
        'url' => '/electroplan/wireway-electrical%20room/wireway.html'
    ],
    'panel_schedule' => [
        'name' => 'Panel Schedule',
        'icon' => 'fa-table-columns',
        'desc' => 'Build and manage panel schedules per project.',
        'active' => true,
        'slug' => 'panel_schedule',
        'url' => '/electroplan/panel_schedule/public_html/public/projects'
    ],
    'workflow' => [
        'name' => 'Workflow',
        'icon' => 'fa-diagram-project',
        'desc' => 'Automate task routing and project approvals.',
        'active' => false,
    ],
    'invoice_payapp' => [
        'name' => 'Invoice-Payapp',
        'icon' => 'fa-file-invoice-dollar',
        'desc' => 'Generate and track invoices and pay applications.',
        'active' => false,
    ],
    'afc_calculator' => [
        'name' => 'AFC Calculator',
        'icon' => 'fa-bolt',
        'desc' => 'Estimate AFC loads and electrical constraints.',
        'active' => false,
    ],
];

// 2. Consulta de Carpetas (Para el menú lateral y la vista de archivos)
$userRole = strtolower(trim((string)($_SESSION['role'] ?? 'viewer')));

// Solo carpetas raíz (sin padre) para el grid principal
$foldersStmt = $pdo->prepare("SELECT * FROM folders WHERE project_id = ? AND deleted_at IS NULL AND (parent_id IS NULL OR parent_id = 0) ORDER BY name ASC");
$foldersStmt->execute([$projectId]);
$allFolders = $foldersStmt->fetchAll(PDO::FETCH_ASSOC);

// Subcarpetas agrupadas por parent_id (para mostrar dentro de cada carpeta)
$subStmt = $pdo->prepare("SELECT * FROM folders WHERE project_id = ? AND deleted_at IS NULL AND parent_id IS NOT NULL AND parent_id != 0 ORDER BY depth ASC, name ASC");
$subStmt->execute([$projectId]);
$allSubs = $subStmt->fetchAll(PDO::FETCH_ASSOC);
$subsByParent = [];
foreach($allSubs as $sub) {
    $subsByParent[(int)$sub['parent_id']][] = $sub;
}

// 3. Consulta de Estadísticas Rápidas (Para el Summary)
$fileCount = $pdo->query("SELECT COUNT(*) FROM files WHERE project_id = $projectId AND deleted_at IS NULL")->fetchColumn();
$lastActivity = $pdo->query("SELECT uploaded_at FROM files WHERE project_id = $projectId ORDER BY uploaded_at DESC LIMIT 1")->fetchColumn();
// Recent Files = últimos abiertos por el usuario actual (con fallback a subida reciente)
$recentFiles = $pdo->prepare("
    SELECT f.id, f.filename, f.uploaded_at, COALESCE(fv.viewed_at, f.uploaded_at) AS last_activity
    FROM files f
    LEFT JOIN file_views fv ON fv.file_id = f.id AND fv.user_id = ?
    WHERE f.project_id = ? AND f.deleted_at IS NULL
    ORDER BY last_activity DESC
    LIMIT 6
");
$recentFiles->execute([$userId, $projectId]);
$recentFiles = $recentFiles->fetchAll(PDO::FETCH_ASSOC);

$userName = $_SESSION['username'] ?? 'User';
$isAdmin = ($userRole === 'admin' || $userRole === 'owner');
$canUpload = canUploadToFolder($pdo, $userId, (int)$projectId, $currentFolderId ? (int)$currentFolderId : null);

include __DIR__ . '/../views/header.php'; 
?>

<div class="main-content p-4 pt-5">
    <header class="header mb-4">
        <div class="d-flex align-items-center gap-3">
            <button class="mobile-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div class="breadcrumbs">
                <a href="index.php">Home</a>
                <i class="fas fa-chevron-right mx-2" class="breadcrumb-chevron"></i>
                <a href="projects.php">Projects</a>
                <i class="fas fa-chevron-right mx-2" class="breadcrumb-chevron"></i>
                <?php if($currentView === 'files'): ?>
                    <a href="?id=<?= $projectId ?>&view=summary"><?= htmlspecialchars($project['name']) ?></a>
                <?php else: ?>
                    <span class="text-primary fw-bold"><?= htmlspecialchars($project['name']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <a href="../admin/settings.php?tab=users" class="user-pill text-decoration-none d-none d-md-inline-flex">
            <div class="avatar"><?= strtoupper(substr($userName,0,1)) ?></div>
            <div class="user-pill-info">
                <span class="user-pill-name"><?= htmlspecialchars($userName) ?></span>
                <span class="user-pill-role"><?= ucfirst($userRole) ?></span>
            </div>
        </a>
    </header>

    <!-- PROJECT HERO / WORKSPACE -->
    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-start gap-4 mb-5">
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-3 mb-2">
                <h1 class="project-title-large m-0"><?= htmlspecialchars($project['name']) ?></h1>
                <?php 
                    $statusColors = ['Planning' => 'info', 'Active' => 'success', 'On Hold' => 'warning', 'Completed' => 'secondary'];
                    $projStatus = $project['status'] ?? 'Active';
                    $badgeColor = $statusColors[$projStatus] ?? 'primary';
                ?>
                <?php if($isAdmin): ?>
                <div class="dropdown">
                    <span class="badge bg-<?= $badgeColor ?> bg-opacity-25 text-<?= $badgeColor ?> px-3 py-1 rounded-pill border border-<?= $badgeColor ?> border-opacity-25 dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" class="cursor-pointer">
                        <?= htmlspecialchars($projStatus) ?>
                    </span>
                    <ul class="dropdown-menu dropdown-menu-dark bg-card border-secondary shadow-lg">
                        <li><a class="dropdown-item text-white hover-bg-body" href="#" onclick="updateProjectGeneralStatus('Planning'); return false;">Planning</a></li>
                        <li><a class="dropdown-item text-white hover-bg-body" href="#" onclick="updateProjectGeneralStatus('Active'); return false;">Active</a></li>
                        <li><a class="dropdown-item text-white hover-bg-body" href="#" onclick="updateProjectGeneralStatus('On Hold'); return false;">On Hold</a></li>
                        <li><a class="dropdown-item text-white hover-bg-body" href="#" onclick="updateProjectGeneralStatus('Completed'); return false;">Completed</a></li>
                    </ul>
                </div>
                <?php else: ?>
                    <span class="badge bg-<?= $badgeColor ?> bg-opacity-25 text-<?= $badgeColor ?> px-3 py-1 rounded-pill border border-<?= $badgeColor ?> border-opacity-25"><?= htmlspecialchars($projStatus) ?></span>
                <?php endif; ?>
            </div>
            <div class="d-flex flex-wrap gap-4 text-gray small mb-3">
                <span><i class="fas fa-map-marker-alt me-1 text-accent"></i> <?= htmlspecialchars($projectAddress ?: 'No address specified') ?></span>
                <span><i class="fas fa-building me-1 text-warning"></i> <?= htmlspecialchars($projectCompanyName ?: 'No Company') ?></span>
                <span><i class="fas fa-user-hard-hat me-1 text-primary"></i> <?= htmlspecialchars($projectContactName ?: 'No Contact') ?></span>
                <span><i class="fas fa-calendar-alt me-1 text-success"></i> <?= $project['date_started'] ? date('M d, Y', strtotime($project['date_started'])) : 'TBD' ?></span>
            </div>
            <p class="project-desc-expandable m-0 cursor-pointer" onclick="openProjectDetailsModal()" title="Click for project details">
                <?= htmlspecialchars($projectNotes ?: 'No description provided for this project.') ?>
            </p>
        </div>
        <div class="d-flex gap-2 flex-shrink-0 align-items-start">
            <?php if($canUpload): ?>
            <button class="btn btn-main rounded-pill px-4 shadow-sm" onclick="openUploadModal()"><i class="fas fa-cloud-upload-alt me-2"></i> Upload File</button>
            <?php endif; ?>

            <button class="btn btn-tools rounded-pill px-4 py-2 shadow-sm" onclick="openToolsModal()"><i class="fas fa-toolbox me-2"></i> Tools</button>
            
            <?php if($userRole !== 'viewer'): ?>
            <!-- SMART PM TRIGGER -->
            <button class="btn btn-warning rounded-pill px-4 py-2 shadow-sm fw-bold text-dark" onclick="toggleSmartPM()"><i class="fas fa-project-diagram me-2"></i> Task Manager</button>
            <?php endif; ?>
            
            <?php if($isAdmin): ?>
            <div id="bulk-import-overlay" class="bulk-import-overlay">
                <div class="bulk-import-panel">
                    <i class="fas fa-folder-open text-warning mb-3" class="bulk-import-icon"></i>
                    <div class="fw-bold text-white mb-1 fs-5" id="bulk-status-title">Uploading folder...</div>
                    <div class="text-gray small mb-3" id="bulk-status-detail" class="text-gray small mb-3 bulk-status-detail"></div>
                    <div class="progress mb-3" class="progress mb-3 bulk-progress-wrap">
                        <div id="bulk-progress-bar" class="bulk-progress-bar"></div>
                    </div>
                    <div class="text-gray small" id="bulk-status-count">0 / 0 files</div>
                    <div class="text-gray small mt-2" id="bulk-status-log" class="text-gray small mt-2 bulk-status-log"></div>
                </div>
            </div>

            <!-- Modal Bulk ZIP Import -->
            <div class="modal fade" id="bulkZipModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content p-3">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold"><i class="fas fa-file-archive me-2 text-warning"></i>Bulk Import via ZIP</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="p-3 rounded-3 mb-3" class="panel-soft">
                                <div class="small text-white fw-semibold mb-2"><i class="fas fa-info-circle text-primary me-1"></i> How it works</div>
                                <ol class="small text-gray mb-0 ps-3" class="lh-17">
                                    <li>Compress your folder into a <strong class="text-white">.zip</strong> file on your computer</li>
                                    <li>Select the ZIP file below</li>
                                    <li>The server will recreate the folder structure automatically</li>
                                </ol>
                            </div>
                            <div id="zip-drop-zone" class="drop-zone" onclick="document.getElementById('zip-file-input').click()" ondragover="zipDropOver(event)" ondragleave="zipDropLeave(event)" ondrop="zipDropDrop(event)">
                                <i class="fas fa-file-zipper text-warning mb-2" style="font-size:2.2rem;"></i>
                                <div class="text-white fw-semibold mb-1">Drag & drop your ZIP here</div>
                                <div class="text-gray small">or <span class="text-primary">browse</span> · max 10GB</div>
                            </div>
                            <input type="file" id="zip-file-input" class="d-none" accept=".zip" onchange="zipFileSelected(this.files[0])">
                            <div id="zip-selected-info" class="mt-3 d-none">
                                <div class="d-flex align-items-center gap-3 p-3 rounded-3" style="background:var(--bg-body); border:1px solid var(--primary);">
                                    <i class="fas fa-file-zipper text-warning" style="font-size:1.5rem; flex-shrink:0;"></i>
                                    <div class="flex-grow-1 overflow-hidden">
                                        <div class="text-white small fw-semibold text-truncate" id="zip-selected-name"></div>
                                        <div class="text-gray small" id="zip-selected-size"></div>
                                    </div>
                                    <button type="button" onclick="zipClearSelection()" class="btn btn-sm p-0 text-muted cursor-pointer"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                            <div id="zip-error-msg" class="alert alert-danger small mt-3 d-none py-2"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn-main px-4" id="zip-upload-btn" disabled onclick="startZipUpload()"><i class="fas fa-upload me-2"></i>Upload & Extract</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-light d-flex align-items-center justify-content-center" data-bs-toggle="dropdown" aria-expanded="false" class="round-icon-btn-42"><i class="fas fa-ellipsis-v"></i></button>
                <ul class="dropdown-menu dropdown-menu-end bg-card border-secondary shadow-lg rounded-3 py-2">
                    <li><button class="dropdown-item text-white hover-bg-body py-2" onclick="openNewFolderModal()"><i class="fas fa-folder-plus me-3 text-warning"></i> Add Folder</button></li>
                    <li><button class="dropdown-item text-white hover-bg-body py-2" onclick="openAssignUsersModal()"><i class="fas fa-users me-3 text-info"></i> Manage Team</button></li>
                    <li><hr class="dropdown-divider border-secondary my-1"></li>
                    <li><a class="dropdown-item text-white hover-bg-body py-2" href="project_create.php?id=<?= $projectId ?>"><i class="fas fa-cog me-3 text-gray"></i> Project Settings</a></li>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- GLOBAL SEARCH -->
    <div class="position-relative mb-4" id="global-search-wrap">
        <div class="input-group">
            <span class="input-group-text" class="input-group-text search-input-addon">
                <i class="fas fa-search"></i>
            </span>
            <input type="text" id="globalSearchInput" class="form-control form-input-surface" placeholder="Search files in this project..." autocomplete="off" oninput="globalSearchFiles(this.value)">
            <button class="btn search-clear-btn" onclick="clearGlobalSearch()" id="globalSearchClearBtn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="globalSearchResults" class="global-search-results">
        </div>
    </div>

    <!-- CONTENT AREA -->
    <?php if($currentView === 'summary'): ?>
        
        <h5 class="fw-bold mb-3"><i class="fas fa-history text-accent me-2"></i> Recent Files</h5>
        <div class="row g-3 mb-5">
            <?php foreach(array_slice($recentFiles, 0, 4) as $rf): 
                $ft = strtolower(pathinfo($rf['filename'], PATHINFO_EXTENSION));
                $isPdf = ($ft === 'pdf'); $isImg = in_array($ft, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
                $iconClass = 'fa-file-alt'; $colorClass = 'primary';
                if($isPdf) { $iconClass='fa-file-pdf'; $colorClass='danger'; } elseif($isImg) { $iconClass='fa-image'; $colorClass='info'; }
            ?>
                <div class="col-md-3 col-6">
                    <div class="file-card-modern p-4 text-center h-100" data-file-id="<?= (int)$f['id'] ?>">
                        
                        <div class="file-icon-large text-<?= $colorClass ?> bg-<?= $colorClass ?> bg-opacity-10">
                            <i class="fas <?= $iconClass ?>"></i>
                        </div>
                        
                        <div class="file-title mb-1" title="<?= htmlspecialchars($rf['filename']) ?>"><?= htmlspecialchars($rf['filename']) ?></div>
                        <div class="small text-gray fw-medium"><?= date('M d, Y', strtotime($rf['uploaded_at'])) ?></div>
                        <?php $rfExt = strtolower(pathinfo($rf['filename'], PATHINFO_EXTENSION)); $rfIsExcel = in_array($rfExt, ['xlsx','xls','xlsm','csv']); ?>
                        
                        <!-- INTERACTIVE OVERLAY -->
                        <div class="file-overlay" tabindex="0">
                            <?php if($_SESSION['role'] === 'admin'): ?>
                            <div class="position-absolute top-0 end-0 p-2 d-flex gap-2" class="position-absolute top-0 end-0 p-2 d-flex gap-2 file-overlay-actions-top">
                                <button class="overlay-mini-btn move" onclick="event.stopPropagation(); event.preventDefault(); openMoveModal(<?= $rf['id'] ?>)" title="Move File"><i class="fas fa-exchange-alt"></i></button>
                                <button class="overlay-mini-btn delete" onclick="event.stopPropagation(); event.preventDefault(); deleteFile(<?= $rf['id'] ?>)" title="Delete File"><i class="fas fa-trash"></i></button>
                            </div>
                            <?php endif; ?>
                            
                            <a href="<?= $rfIsExcel ? 'preview.php?id='.$rf['id'].'&mode=spreadsheet' : 'preview.php?id='.$rf['id'] ?>" onclick="trackFileView(<?= (int)$rf['id'] ?>)" class="overlay-action overlay-view <?= ($_SESSION['role'] === 'viewer' || $rfIsExcel) ? 'w-100' : 'w-50' ?>">
                                <i class="fas fa-eye fa-2x mb-2"></i><span class="fw-bold">View</span>
                            </a>
                            <?php if($_SESSION['role'] !== 'viewer' && !$rfIsExcel): ?>
                            <a href="editor.php?id=<?= $rf['id'] ?>" class="overlay-action overlay-edit w-50">
                                <i class="fas fa-pen-nib fa-2x mb-2"></i><span class="fw-bold">Edit</span>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if(empty($recentFiles)): ?>
                <div class="col-12"><div class="text-gray small"><i class="fas fa-info-circle me-2"></i>No files have been uploaded yet.</div></div>
            <?php endif; ?>
        </div>

        <h5 class="fw-bold mb-3"><i class="fas fa-folder-tree text-warning me-2"></i> Project Folders</h5>
        <div class="row g-3 mb-4">
            <?php foreach($allFolders as $folder): 
                $folderNameLower = strtolower($folder['name']);
                $iconColorClass = 'warning';
                if (strpos($folderNameLower, 'bom') !== false) { $iconColorClass = 'success'; } 
                elseif (strpos($folderNameLower, 'drawings') !== false) { $iconColorClass = 'primary'; } 
                elseif (strpos($folderNameLower, 'labor record') !== false) { $iconColorClass = 'purple'; } 
                elseif (strpos($folderNameLower, 'photos') !== false) { $iconColorClass = 'danger'; } 
                elseif (strpos($folderNameLower, 'rfi') !== false) { $iconColorClass = 'success'; }
            ?>
                <div class="col-md-4 col-xl-3">
                    <div class="folder-card-dash" data-folder-id="<?= (int)$folder['id'] ?>">
                        <a href="?id=<?= $projectId ?>&view=files&folder_id=<?= $folder['id'] ?>" class="d-flex align-items-center gap-3 text-decoration-none w-100">
                            <div class="bg-<?= $iconColorClass ?> bg-opacity-10 p-2 rounded text-<?= $iconColorClass ?>">
                                <i class="fas fa-folder fa-lg"></i>
                            </div>
                            <div class="text-white fw-bold text-truncate fs-6 folder-name-label"><?= htmlspecialchars($folder['name']) ?></div>
                        </a>
                        <?php if($_SESSION['role'] === 'admin' && $folder['name'] !== 'Reports'): ?>
                            <div class="dropdown ms-2">
                                <button class="btn btn-sm border-0 btn-folder-menu" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v fa-lg"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end bg-card border-secondary shadow-lg rounded-3 py-1">
                                    <li><button class="dropdown-item text-white hover-bg-body small" onclick="openRenameModal('folder', <?= $folder['id'] ?>, '<?= addslashes(htmlspecialchars($folder['name'])) ?>')"><i class="fas fa-pen me-2 text-primary"></i> Rename</button></li>
                                    <?php if(($folder['depth'] ?? 0) < 3): ?><li><button class="dropdown-item text-white hover-bg-body small" onclick="openAddSubfolderModal(<?= $folder['id'] ?>, '<?= addslashes(htmlspecialchars($folder['name'])) ?>')"><i class="fas fa-folder-plus me-2 text-success"></i> Add Subfolder</button></li><?php endif; ?>
                                    <li><button class="dropdown-item text-white hover-bg-body small" onclick="openMoveFolderModal(<?= $folder['id'] ?>)"><i class="fas fa-exchange-alt me-2 text-warning"></i> Move Folder</button></li>
                                    <li><button class="dropdown-item text-danger hover-bg-body small" onclick="deleteFolder(<?= $folder['id'] ?>)"><i class="fas fa-trash me-2"></i> Delete Folder</button></li>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if(empty($allFolders)): ?>
                <div class="col-12"><div class="text-gray small"><i class="fas fa-info-circle me-2"></i>This project has no folders.</div></div>
            <?php endif; ?>
        </div>

    <?php elseif($currentView === 'files'): 
        $files = [];
        $folderName = "Select a Folder";
        if($currentFolderId) {
            $fStmt = $pdo->prepare("SELECT * FROM files WHERE folder_id = ? AND deleted_at IS NULL ORDER BY uploaded_at DESC");
            $fStmt->execute([$currentFolderId]);
            $files = $fStmt->fetchAll(PDO::FETCH_ASSOC);
            $allFolderLookup = array_merge($allFolders, $allSubs ?? []);
            $currFolder = array_values(array_filter($allFolderLookup, fn($f) => (int)$f['id'] === (int)$currentFolderId));
            $folderName = !empty($currFolder) ? ($currFolder[0]['name'] ?? 'Unknown Folder') : "Unknown Folder";
        }
    ?>
        <?php
            $currentFolderData = array_values(array_filter($allFolders, fn($f) => $f['id'] == $currentFolderId));
            if(empty($currentFolderData) && !empty($allSubs)) {
                $currentFolderData = array_values(array_filter($allSubs, fn($f) => $f['id'] == $currentFolderId));
            }
            $currentFolderDepth = isset($currentFolderData[0]['depth']) ? (int)$currentFolderData[0]['depth'] : 0;
            $canAddSubfolder = $_SESSION['role'] === 'admin' && $currentFolderDepth < 3;
        ?>
        <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom border-secondary">
            <div class="d-flex align-items-center gap-3">
                <a href="?id=<?= $projectId ?>&view=summary" class="btn btn-icon rounded-circle"><i class="fas fa-arrow-left"></i></a>
                <h4 class="fw-bold mb-0 text-white"><i class="fas fa-folder-open text-warning me-2"></i> <?= htmlspecialchars($folderName) ?></h4>
            </div>
            <div class="d-flex align-items-center gap-2">
                <?php if($canAddSubfolder): ?>
                    <button class="btn btn-sm btn-outline-light rounded-pill px-3" onclick="openAddSubfolderModal(<?= $currentFolderId ?>, '<?= addslashes(htmlspecialchars($folderName)) ?>')"><i class="fas fa-folder-plus me-1 text-success"></i> Add Subfolder</button>
                <?php endif; ?>
                <span class="badge bg-secondary rounded-pill px-3"><?= count($files) ?> files</span>
            </div>
        </div>
        <?php if(!empty($subsByParent[$currentFolderId])): ?>
            <div class="mb-4">
                <div class="small text-gray fw-bold mb-2 text-uppercase" class="letter-05"><i class="fas fa-folder-tree me-1"></i> Subfolders</div>
                <div class="row g-2">
                    <?php foreach($subsByParent[$currentFolderId] as $sub): ?>
                        <div class="col-md-4 col-xl-3">
                            <div class="d-flex align-items-center justify-content-between px-3 py-2 rounded-3 h-100" class="panel-soft">
                                <a href="?id=<?= $projectId ?>&view=files&folder_id=<?= $sub['id'] ?>" class="d-flex align-items-center gap-2 text-decoration-none text-white small fw-semibold flex-grow-1"><i class="fas fa-folder text-warning"></i> <?= htmlspecialchars($sub['name']) ?></a>
                                <?php if($_SESSION['role'] === 'admin'): ?>
                                    <div class="dropdown ms-2">
                                        <button class="btn btn-sm border-0 btn-folder-menu py-0 px-1" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                        <ul class="dropdown-menu dropdown-menu-end bg-card border-secondary shadow-lg rounded-3 py-1">
                                            <?php if(($sub['depth'] ?? 1) < 3): ?><li><button class="dropdown-item text-white hover-bg-body small" onclick="openAddSubfolderModal(<?= $sub['id'] ?>, '<?= addslashes(htmlspecialchars($sub['name'])) ?>')"><i class="fas fa-folder-plus me-2 text-success"></i> Add Subfolder</button></li><?php endif; ?>
                                            <li><button class="dropdown-item text-danger hover-bg-body small" onclick="deleteFolder(<?= $sub['id'] ?>)"><i class="fas fa-trash me-2"></i> Delete</button></li>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if(empty($files)): ?>
            <div class="text-center py-5">
                <i class="fas fa-cloud-upload-alt fa-3x text-gray mb-3 opacity-25"></i>
                <p class="text-gray">This folder is empty.</p>
                <?php if($canUpload): ?>
                <button class="btn btn-outline-primary rounded-pill" onclick="openUploadModal()">Upload Here</button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach($files as $f): 
                     $ft = strtolower(pathinfo($f['filename'], PATHINFO_EXTENSION));
                     $iconClass = 'fa-file-alt'; $colorClass = 'primary';
                     if($ft === 'pdf') { $iconClass='fa-file-pdf'; $colorClass='danger'; } elseif(in_array($ft, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) { $iconClass='fa-image'; $colorClass='info'; }
                ?>
                <div class="col-md-3 col-xl-2">
                    <div class="file-card-modern p-4 text-center h-100" data-file-id="<?= (int)$f['id'] ?>">
                        
                        <div class="file-icon-large text-<?= $colorClass ?> bg-<?= $colorClass ?> bg-opacity-10">
                            <i class="fas <?= $iconClass ?>"></i>
                        </div>
                        
                        <div class="file-title mb-1 file-name-label" title="<?= htmlspecialchars($f['filename']) ?>"><?= htmlspecialchars($f['filename']) ?></div>
                        <div class="small text-gray fw-medium"><?= date('M d, Y', strtotime($f['uploaded_at'])) ?></div>
                        
                        <!-- INTERACTIVE OVERLAY -->
                        <div class="file-overlay" tabindex="0">
                            <?php if($_SESSION['role'] === 'admin'): ?>
                            <div class="position-absolute top-0 end-0 p-2 d-flex gap-2" class="position-absolute top-0 end-0 p-2 d-flex gap-2 file-overlay-actions-top">
                                <button class="overlay-mini-btn move" onclick="event.stopPropagation(); event.preventDefault(); openMoveModal(<?= $f['id'] ?>)" title="Move File"><i class="fas fa-exchange-alt"></i></button>
                                <button class="overlay-mini-btn" onclick="event.stopPropagation(); event.preventDefault(); openRenameModal('file', <?= (int)$f['id'] ?>, '<?= addslashes(htmlspecialchars($f['filename'])) ?>')" title="Rename"><i class="fas fa-pen"></i></button>
                                <button class="overlay-mini-btn delete" onclick="event.stopPropagation(); event.preventDefault(); deleteFile(<?= $f['id'] ?>)" title="Delete File"><i class="fas fa-trash"></i></button>
                            </div>
                            <?php endif; ?>
                            
                            <?php $fExt = strtolower(pathinfo($f['filename'], PATHINFO_EXTENSION)); $isExcel = in_array($fExt, ['xlsx','xls','xlsm','csv']); ?>
                            <a href="<?= $isExcel ? 'preview.php?id='.$f['id'].'&mode=spreadsheet' : 'preview.php?id='.$f['id'] ?>" onclick="trackFileView(<?= (int)$f['id'] ?>)" class="overlay-action overlay-view <?= ($_SESSION['role'] === 'viewer') ? 'w-100' : 'w-50' ?>">
                                <i class="fas fa-eye fa-lg mb-1"></i><span class="small fw-bold">View</span>
                            </a>
                            <?php if($_SESSION['role'] !== 'viewer' && !$isExcel): ?>
                            <a href="editor.php?id=<?= $f['id'] ?>" class="overlay-action overlay-edit w-50">
                                <i class="fas fa-pen-nib fa-lg mb-1"></i><span class="small fw-bold">Edit</span>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<style>
    :root {
        /* Paleta Dark Mode (Deep Matte) */
        --bg-body: #1b212d;
        --bg-card: #242a38;
        --bg-input: #151a23;
        --primary: #fb5a3a;
        --primary-hover: #e14e32;
        --text-white: #ffffff;
        --text-gray: #94a3b8;
        --text-muted: #58657a;
        --border-subtle: #2f384a;
        --radius-box: 20px;
        
        /* Custom Folder & Tool Colors */
        --color-blue: #3b82f6;
        --color-emerald: #10b981;
        --color-amber: #f59e0b;
        --color-purple: #8b5cf6;
        --bs-info-rgb: 59, 130, 246; /* Permite que bg-info funcione con bg-opacity */
    }

    body.theme-light {
        --bg-body: #e2e8f0;
        --bg-card: #ffffff;
        --bg-input: #f8fafc;
        --text-white: #0f172a;
        --text-gray: #64748b;
        --text-muted: #94a3b8;
        --border-subtle: #cbd5e1;
    }

    /* --- FASE 51: Rediseño UX - Animación Off-Canvas del Smart PM --- */
    .main-content {
        transition: all 0.3s ease-in-out;
        width: 100%;
    }

    .sidebar {
        transition: all 0.3s ease-in-out;
    }

    @media (min-width: 769px) {
        body.smart-pm-active .sidebar {
            transform: translateX(-100%);
            width: 0;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }
        body.smart-pm-active .main-content {
            margin-left: 0 !important;
            margin-right: 45vw;
            width: 55vw;
        }
    }

    @media (max-width: 768px) {
        body.smart-pm-active {
            overflow: hidden;
        }
    }

/* Override Bootstrap text-muted con la paleta del proyecto */
.text-muted { color: var(--text-gray) !important; }
body.theme-light .text-muted { color: var(--text-gray) !important; }


    body.theme-light .bg-dark { background-color: var(--bg-input) !important; color: var(--text-white) !important; border-color: var(--border-subtle) !important; }
    body.theme-light .text-white { color: var(--text-white) !important; }

    .box-card { background: var(--bg-card); border-radius: var(--radius-box); border: 1px solid var(--border-subtle); transition: 0.3s; }
    .box-card:hover { transform: translateY(-3px); border-color: var(--primary); }

    .btn-main { background-color: var(--primary) !important; border-color: var(--primary) !important; color: var(--text-white) !important; transition: 0.2s; font-weight: 600; }
    .btn-main:hover { background-color: var(--primary-hover) !important; border-color: var(--primary-hover) !important; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(251, 90, 58, 0.3); }

    .btn-tools { background-color: var(--color-blue) !important; border-color: var(--color-blue) !important; color: var(--text-white) !important; transition: 0.2s; font-weight: 600; }
    .btn-tools:hover { filter: brightness(1.1); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); color: var(--text-white) !important; }

    .text-purple { color: var(--color-purple) !important; }
    .bg-purple { background-color: rgba(139, 92, 246, var(--bs-bg-opacity, 1)) !important; }

    .text-info { color: var(--color-blue) !important; }

    .btn-outline-light { border-color: var(--border-subtle); color: var(--text-gray); }
    .btn-outline-light:hover { background: var(--bg-input); color: var(--primary); border-color: var(--primary); }
    
    .btn-outline-secondary, .btn-outline-info, .btn-outline-primary, .btn-outline-warning, .btn-outline-danger { transition: 0.2s; }

    .btn-icon { width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--border-subtle); color: var(--text-gray); transition: 0.2s; background: var(--bg-card); text-decoration: none; }
    .btn-icon:hover { background: var(--primary); color: var(--text-white); border-color: var(--primary); }
    .btn-icon.border-primary { color: var(--primary); border-color: var(--primary); }
    .btn-icon.border-primary:hover { background: var(--primary); color: var(--text-white); }
    .btn-icon.border-danger { color: #ef4444; border-color: #ef4444; }
    .btn-icon.border-danger:hover { background: #ef4444; color: var(--text-white); }

    .form-control, .form-select { background: var(--bg-input) !important; border: 1px solid var(--border-subtle) !important; color: var(--text-white) !important; border-radius: 10px; }
    .form-control::placeholder { color: var(--text-gray) !important; opacity: 1; }
    .form-control:focus, .form-select:focus { border-color: var(--primary) !important; box-shadow: 0 0 0 3px rgba(251, 90, 58, 0.2) !important; }

    .modal-content { background-color: var(--bg-card); border: 1px solid var(--border-subtle); color: var(--text-white); border-radius: var(--radius-box); }
    .modal-header { border-bottom: 1px solid var(--border-subtle); }
    .modal-footer { border-top: 1px solid var(--border-subtle); }
    .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }
    body.theme-light .btn-close { filter: none; }

    .bg-header { border-bottom-color: var(--border-subtle) !important; }
    .border-secondary { border-color: var(--border-subtle) !important; }

    .project-sidebar { background: var(--bg-card) !important; border-right-color: var(--border-subtle) !important; }

    /* --- DROPDOWN MENUS FIX --- */
    .dropdown-menu.bg-card { background-color: var(--bg-card) !important; border-color: var(--border-subtle) !important; }
    .dropdown-menu .dropdown-item:hover { background-color: var(--bg-body) !important; color: var(--text-white) !important; }

    /* --- WORKSPACE LAYOUT STYLES --- */
    .project-title-large {
        font-size: 2.2rem;
        font-weight: 800;
        color: var(--text-white);
        letter-spacing: -0.02em;
    }
    
    .project-desc-expandable {
        font-size: 0.95rem;
        color: var(--text-gray);
        display: -webkit-box;
        -webkit-line-clamp: 2; /* Truncado inicial a 2 líneas */
        -webkit-box-orient: vertical;
        overflow: hidden;
        cursor: pointer;
        transition: color 0.2s ease;
        max-width: 900px;
        line-height: 1.6;
    }
    .project-desc-expandable.expanded {
        -webkit-line-clamp: unset;
        color: var(--text-white);
    }
    .project-desc-expandable:hover { color: var(--text-white); }

    .folder-card-dash {
        background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 16px;
        padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; transition: 0.3s;
    }
    .folder-card-dash:hover { border-color: var(--primary); transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.15); }
    
    .btn-folder-menu { color: var(--text-white); opacity: 0.5; transition: 0.2s; padding: 6px 12px; }
    .btn-folder-menu:hover { opacity: 1; color: var(--primary); }
    
    .hover-bg-body:hover { background-color: var(--bg-body) !important; }

    /* --- MODERN FILE CARDS (OVERLAY UI) --- */
    .file-card-modern {
        background: var(--bg-card);
        border: 1px solid var(--border-subtle);
        border-radius: 16px;
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s;
        position: relative;
        overflow: hidden;
    }
    .file-card-modern:hover {
        transform: translateY(-4px);
        border-color: var(--border-subtle);
        box-shadow: 0 12px 24px rgba(0,0,0,0.2);
    }
    
    .file-card-modern .file-title { font-size: 0.82rem !important; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%; display: block; color: var(--text-white); }

    .file-icon-large {
        font-size: 2.5rem;
        margin: 0.5rem auto 1.2rem auto;
        width: 70px;
        height: 70px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 18px;
    }

    .file-overlay {
        position: absolute;
        inset: 0;
        display: flex;
        opacity: 0;
        z-index: 10;
        transition: opacity 0.3s ease;
        border-radius: 16px;
        overflow: hidden;
        outline: none; /* Quita el borde azul al tocar en móvil */
    }
    /* Soporte para Mobile: focus-within se activa al dar el primer toque */
    .file-card-modern:hover .file-overlay,
    .file-card-modern:focus-within .file-overlay {
        opacity: 1;
    }

    .overlay-action {
        display: inline-flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        color: var(--text-white) !important;
        transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), filter 0.2s;
    }
    
    .overlay-view {
        background: rgba(245, 158, 11, 0.95); /* Warning/Amber */
        transform: translateX(-100%);
    }
    .overlay-edit {
        background: rgba(59, 130, 246, 0.95); /* Info/Blue */
        transform: translateX(100%);
    }
    .overlay-action.w-100 {
        transform: translateY(100%);
    }

    .file-card-modern:hover .overlay-action,
    .file-card-modern:focus-within .overlay-action {
        transform: translate(0, 0);
    }
    
    .overlay-action:hover { filter: brightness(1.1); }
    .overlay-action i { transition: transform 0.2s; }
    .overlay-action:hover i { transform: scale(1.2); }

    .overlay-mini-btn {
        width: 32px; height: 32px; border-radius: 50%;
        background: rgba(0,0,0,0.5); color: var(--text-white);
        border: none; display: inline-flex; align-items: center; justify-content: center;
        transition: 0.2s; backdrop-filter: blur(2px);
    }
    .overlay-mini-btn.move:hover { background: var(--color-amber); transform: scale(1.1); }
    .overlay-mini-btn.delete:hover { background: #ef4444; transform: scale(1.1); }
    
    .search-result-item { border-bottom: 1px solid var(--border-subtle); transition: 0.15s; }
    .search-result-item:hover { background: var(--bg-input); }

    @media (max-width: 992px) {
        .project-title-large { font-size: 1.8rem; }
    }

    @media (max-width: 768px) {
        #global-search-wrap .input-group { flex-wrap: nowrap; }
        #global-search-wrap .input-group-text,
        #global-search-wrap .btn,
        #global-search-wrap .form-control { height: 42px; }
        #globalSearchResults { max-height: 52vh !important; border-radius: 12px !important; }

        .file-card-modern { padding: 14px !important; }
        .file-icon-large { width: 56px; height: 56px; font-size: 1.9rem; margin-bottom: .8rem; }
        .file-card-modern .file-title { font-size: .78rem !important; }

        .overlay-mini-btn { width: 30px; height: 30px; }
        .overlay-action span { font-size: .72rem; }

        #renameModal .modal-dialog,
        #projectDetailsModal .modal-dialog { margin: .75rem; }
        #projectDetailsModal .modal-body { max-height: 70vh; overflow-y: auto; }
    }

    .tools-modal-content {
        background: var(--bg-card) !important;
        border: 1px solid var(--border-subtle) !important;
        border-radius: var(--radius-box) !important;
    }
    .tools-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 12px;
    }
    .tool-card-btn {
        border-radius: 12px;
        border: 1px solid var(--border-subtle);
        padding: 14px;
        text-align: left;
        background: var(--bg-input);
        transition: all .2s ease;
    }
    .tool-card-btn.is-active:hover {
        border-color: var(--primary);
        box-shadow: 0 0 0 1px rgba(251,90,58,.3), 0 8px 24px rgba(0,0,0,.15);
        transform: translateY(-1px);
    }
    .tool-card-btn.is-disabled {
        opacity: .55;
        filter: grayscale(35%);
        cursor: not-allowed;
    }
    .tool-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
        background: rgba(251,90,58,.1);
        border: 1px solid rgba(251,90,58,.2);
        flex-shrink: 0;
    }

    @media (max-width: 768px) {
        .d-flex.flex-wrap.gap-4 { gap: 10px !important; }
    }

    body.theme-light .file-hover:hover { background: rgba(15,23,42,0.06); }
    body.theme-light .project-content .text-white,
    body.theme-light .project-content h1,
    body.theme-light .project-content h2,
    body.theme-light .project-content h3,
    body.theme-light .project-content h4,
    body.theme-light .project-content h5,
    body.theme-light .project-content h6,
    body.theme-light .project-content .fw-bold,
    body.theme-light .project-content .small { color: #0f172a !important; }
    body.theme-light .project-content .text-gray,
    body.theme-light .project-content .text-muted { color: #475569 !important; }
    body.theme-light .project-content .btn.btn-dark { background: #334155 !important; border-color: #334155 !important; color: #fff !important; }
    body.theme-light .project-content .form-select.bg-dark,
    body.theme-light .project-content .form-control.bg-dark { background: #fff !important; color: #0f172a !important; }
</style>

<?php include __DIR__ . '/../views/modals.php'; ?>

<div class="modal fade" id="toolsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content p-3 tools-modal-content">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold" ><i class="fas fa-toolbox me-2 text-accent"></i>Project Tools</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="tools-grid">
                    <?php foreach($toolCatalog as $toolKey => $tool): ?>
                        <?php $isActiveTool = !empty($tool['active']); ?>
                        <button
                            type="button"
                            class="tool-card-btn <?= $isActiveTool ? 'is-active' : 'is-disabled' ?>"
                            <?= $isActiveTool ? 'onclick="openToolView(' . "'" . htmlspecialchars($toolKey, ENT_QUOTES) . "'" . ')"' : 'disabled' ?>
                        >
                            <div class="d-flex align-items-start justify-content-between gap-2 w-100">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="tool-icon"><i class="fas <?= htmlspecialchars($tool['icon']) ?>"></i></div>
                                    <div class="text-start">
                                        <div class="fw-bold" style="color: var(--text-white); font-size: 0.95rem;"><?= htmlspecialchars($tool['name']) ?></div>
                                        <div class="small" class="small text-gray mt-1"><?= htmlspecialchars($tool['desc']) ?></div>
                                    </div>
                                </div>
                                <?php if(!$isActiveTool): ?>
                                    <span class="badge" class="badge border border-secondary text-gray">Coming Soon</span>
                                <?php endif; ?>
                            </div>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="uploadFileModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content p-3">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fas fa-cloud-upload-alt me-2 text-primary"></i>Upload Files</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="uploadFileForm">
                <div class="modal-body">
                    <div id="upload-drop-zone" class="drop-zone drop-zone-lg" onclick="document.getElementById('upload_file_input').click()" ondragover="uploadDropZoneOver(event)" ondragleave="uploadDropZoneLeave(event)" ondrop="uploadDropZoneDrop(event)">
                        <i class="fas fa-file-upload text-primary mb-2" style="font-size:2rem;"></i>
                        <div class="text-white fw-semibold mb-1">Drag & drop files here</div>
                        <div class="text-gray small">or <span class="text-primary" class="cursor-pointer">browse files</span></div>
                        <div class="text-gray small mt-1">PDF, Images, Excel, Word, DWG, ZIP and more · max 1GB each</div>
                    </div>
                    <input type="file" id="upload_file_input" class="d-none" multiple onchange="uploadFilesSelected(this.files)">

                    <div id="upload-file-list" class="d-flex flex-column gap-2 mb-3 file-list-scroll"></div>

                    <?php if(!$currentFolderId): ?>
                    <div id="upload-folder-selector">
                        <label class="text-gray small mb-2 d-block">Destination Folder</label>
                        <select name="folder_id" id="upload_folder_select" class="form-select text-white bg-dark border-secondary" required>
                            <option value="">Select a folder...</option>
                            <?php foreach($allFolders as $folder): ?>
                                <option value="<?= (int)$folder['id'] ?>"><?= htmlspecialchars($folder['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if(empty($allFolders)): ?>
                            <div class="text-gray small mt-2">No folders available. Create a folder first.</div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-gray small">
                        <i class="fas fa-folder-open text-warning me-1"></i> Files will be uploaded to: <strong class="text-white"><?= htmlspecialchars($folderName ?? 'Current folder') ?></strong>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-main px-4" id="upload-submit-btn" disabled>
                        <i class="fas fa-upload me-2"></i>Upload <span id="upload-file-count"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="uploadProgressWrap" class="position-fixed bottom-0 end-0 m-3" style="z-index: 2000; width: 280px; display:none;">
    <div class="box-card p-3">
        <div class="small text-gray mb-2">Uploading file...</div>
        <div class="progress" style="height:8px;">
            <div id="uploadProgressBar" class="progress-bar" role="progressbar" style="width:0%"></div>
        </div>
        <div class="small text-gray mt-2" id="uploadProgressText">0%</div>
    </div>
</div>

<?php if(($_SESSION['role'] ?? '') === 'admin'): ?>
<div class="modal fade" id="moveFolderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Move Folder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="moveFolderForm">
                <input type="hidden" name="action" value="move_folder">
                <input type="hidden" name="folder_id" id="move_folder_id" value="">
                <div class="modal-body">
                    <label class="text-gray small mb-2">Target Project</label>
                    <select name="target_project_id" id="move_folder_project_select" class="form-select text-white bg-dark border-secondary" onchange="loadFoldersForFolderMove(this.value)" required>
                        <option value="">Loading projects...</option>
                    </select>

                    <label class="text-gray small mb-2 mt-3">Move Into Folder (Optional)</label>
                    <select name="target_parent_folder_id" id="move_folder_parent_select" class="form-select text-white bg-dark border-secondary">
                        <option value="">Keep as top-level</option>
                    </select>
                    <div class="text-gray small mt-2">Select a parent folder to create a subfolder with the current name.</div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn-main w-100">Move Folder</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="newFolderModalDash" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Add Folder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="newFolderFormDash">
                <div class="modal-body">
                    <div id="newFolderError" class="alert alert-danger py-2 px-3 mb-3 d-none" role="alert"></div>
                    <label class="text-gray small mb-2">Folder Name</label>
                    <input type="text" name="name" class="form-control" required maxlength="255">
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn-main w-100">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Add Subfolder -->
<div class="modal fade" id="addSubfolderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-folder-plus me-2 text-success"></i> Add Subfolder in: <span id="subfolder-parent-name" class="text-primary"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addSubfolderForm">
                <input type="hidden" id="subfolder-parent-id" name="parent_id" value="">
                <div class="modal-body">
                    <div id="addSubfolderError" class="alert alert-danger py-2 px-3 mb-3 d-none" role="alert"></div>
                    <label class="text-gray small mb-2">Subfolder Name</label>
                    <input type="text" name="name" id="subfolder-name-input" class="form-control" required maxlength="255" placeholder="e.g. Revisions">
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn-main w-100">
                        <i class="fas fa-plus me-2"></i>Create Subfolder
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="assignUsersModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Assign Users to Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="assignUsersForm">
                <input type="hidden" name="action" value="assign_project_users">
                <input type="hidden" name="project_id" value="<?= (int)$projectId ?>">
                <div class="modal-body">
                    <label class="text-gray small mb-2">Assign Users</label>
                    <div class="border rounded p-2 file-list-scroll">
                        <?php foreach($assignUsers as $u): ?>
                            <label class="d-flex align-items-center gap-2 small text-gray mb-2">
                                <input type="checkbox" name="user_ids[]" value="<?= (int)$u['id'] ?>" data-role="<?= htmlspecialchars($u['role']) ?>" <?= in_array((int)$u['id'], $assignedUserIds, true) ? 'checked' : '' ?>>
                                <span><?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['role']) ?>)</span>
                            </label>
                        <?php endforeach; ?>
                        <?php if(empty($assignUsers)): ?>
                            <div class="text-gray small">No users available.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn-main w-100">Assign Selected Users</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>


<!-- Modal Rename -->
<div class="modal fade" id="renameModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content p-3"><div class="modal-header"><h6 class="modal-title fw-bold" id="renameModalTitle"><i class="fas fa-pen me-2 text-primary"></i>Rename</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" id="renameItemId"><input type="hidden" id="renameItemType"><input type="text" id="renameInput" class="form-control" placeholder="New name..." maxlength="255"><div class="mt-3"><label class="small text-gray mb-1">Visible for roles (comma separated)</label><input type="text" id="visibilityRoles" class="form-control" placeholder="project_manager,field_manager"></div><div class="mt-2"><label class="small text-gray mb-1">Visible for user IDs (comma separated)</label><input type="text" id="visibilityUsers" class="form-control" placeholder="12,45"></div><div id="renameError" class="text-danger small mt-2 d-none"></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn-main px-3" onclick="submitRename()"><i class="fas fa-check me-1"></i>Save</button></div></div></div>
</div>
<!-- Modal Project Details -->
<div class="modal fade" id="projectDetailsModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content p-3 modal-surface">
      <div class="modal-header">
        <h5 class="modal-title fw-bold"><i class="fas fa-info-circle me-2 text-primary"></i> <?= htmlspecialchars($project['name']) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body modal-scroll-y">
        <div class="row g-4">
          <div class="col-md-6">
            <div class="p-3 rounded-3 h-100 panel-soft">
              <div class="small text-gray fw-bold text-uppercase mb-3 letter-05"><i class="fas fa-building me-1 text-warning"></i> Company & Contact</div>
              <?php $rows1 = [ ['fas fa-building','Company', $projectCompanyName], ['fas fa-phone','Office Phone', $projectCompanyPhone], ['fas fa-map-marker-alt','HQ Address', $projectCompanyAddress], ['fas fa-user','Site Contact', $projectContactName], ['fas fa-mobile-alt','Contact Phone', $projectContactPhone], ['fas fa-map-pin','Job Address', $projectAddress], ]; foreach($rows1 as [$icon,$label,$val]): if(!$val) continue; ?>
                <div class="d-flex gap-2 mb-2"><i class="fas <?= $icon ?> text-gray mt-1 flex-shrink-0" style="width:16px;"></i><div><div class="text-gray fs-072"><?= $label ?></div><div class="text-white small fw-semibold"><?= htmlspecialchars($val) ?></div></div></div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="col-md-6">
            <div class="p-3 rounded-3 h-100 panel-soft">
              <div class="small text-gray fw-bold text-uppercase mb-3 letter-05"><i class="fas fa-calendar me-1 text-success"></i> Timeline & Status</div>
              <?php $rows2 = [ ['fas fa-paper-plane','Bid Sent', $project['date_bid_sent'] ?? ($project['date_bid_send'] ?? '')], ['fas fa-trophy','Bid Awarded', $project['date_bid_awarded'] ?? ''], ['fas fa-play-circle','Start Date', $project['date_started'] ?? ''], ['fas fa-flag-checkered','Target Finish', $project['date_finished'] ?? ''], ['fas fa-shield-alt','Warranty End', $project['date_warranty_end'] ?? ''], ]; foreach($rows2 as [$icon,$label,$val]): if(!$val) continue; ?>
                <div class="d-flex gap-2 mb-2"><i class="fas <?= $icon ?> text-gray mt-1 flex-shrink-0" style="width:16px;"></i><div><div class="text-gray fs-072"><?= $label ?></div><div class="text-white small fw-semibold"><?= date('M d, Y', strtotime($val)) ?></div></div></div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php if($projectNotes): ?>
          <div class="col-12">
            <div class="p-3 rounded-3 panel-soft">
              <div class="small text-gray fw-bold text-uppercase mb-2 letter-05"><i class="fas fa-align-left me-1"></i> Description </div>
              <p class="text-white small mb-0 lh-17"><?= nl2br(htmlspecialchars($projectNotes)) ?></p>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php if($isAdmin): ?>
      <div class="modal-footer"><a href="project_create.php?edit=<?= $projectId ?>" class="btn btn-outline-light rounded-pill px-4"><i class="fas fa-edit me-1"></i> Edit Project </a></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<input type="file" id="projectUploadInput" class="d-none">

<script>
    const pId = <?= $projectId ?>;
    const currentFolderId = <?= $currentFolderId ? (int)$currentFolderId : 'null' ?>;
    const fId = <?= $currentFolderId ?? 'null' ?>;
    const canUpload = <?= $canUpload ? 'true' : 'false' ?>;

    window.projectToolsMap = <?= json_encode(array_map(function($t){
        return [
            'url' => $t['url'] ?? null,
            'slug' => $t['slug'] ?? null,
            'active' => !empty($t['active'])
        ];
    }, $toolCatalog), JSON_UNESCAPED_SLASHES) ?>;

    function applyProjectSidebarState(collapsed) {
        const layout = document.getElementById('projectLayout');
        const text = document.getElementById('toggleProjectSidebarText');
        if (!layout) return;
        layout.classList.toggle('sidebar-collapsed', collapsed);
        if (text) text.textContent = collapsed ? 'Show Menu' : 'Hide Menu';
    }

    function toggleProjectSidebar() {
        const layout = document.getElementById('projectLayout');
        if (!layout) return;
        const collapsed = !layout.classList.contains('sidebar-collapsed');
        applyProjectSidebarState(collapsed);
        try { localStorage.setItem('projectSidebarCollapsed', collapsed ? '1' : '0'); } catch (e) {}
    }

    document.addEventListener('DOMContentLoaded', function() {
        let collapsed = false;
        try { collapsed = localStorage.getItem('projectSidebarCollapsed') === '1'; } catch (e) {}
        applyProjectSidebarState(collapsed);
    });

    function openProjectDetailsModal() { new bootstrap.Modal(document.getElementById('projectDetailsModal')).show(); }

    function updateProjectGeneralStatus(newStatus) {
        appConfirm(`Are you sure you want to change the project status to <strong>${newStatus}</strong>?`, "Change Project Status", () => {
            const fd = new FormData();
            fd.append('action', 'update_project_status');
            fd.append('project_id', pId);
            fd.append('status', newStatus);
            fetch('../task_manager/api.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.status === 'success') {
                        location.reload();
                    } else {
                        appAlert('Error: ' + d.message, 'Error', 'error');
                    }
                })
                .catch(e => {
                    console.error(e);
                    appAlert('Connection error.', 'Error', 'error');
                });
        });
    }

function openToolsModal() {
        const modalEl = document.getElementById('toolsModal');
        if (!modalEl) return;
        new bootstrap.Modal(modalEl).show();
    }

    function openToolView(toolKey) {
        const modalEl = document.getElementById('toolsModal');
        if (modalEl) {
            const inst = bootstrap.Modal.getInstance(modalEl);
            if (inst) inst.hide();
        }
        const toolMap = window.projectToolsMap || {};
        const tool = toolMap[toolKey];
        if (!tool || !tool.url) return;
        const sep = tool.url.includes('?') ? '&' : '?';
        const fullUrl = `${tool.url}${sep}project_id=${encodeURIComponent(pId)}&tool=${encodeURIComponent(tool.slug || toolKey)}&ep_api=${encodeURIComponent('/electroplan/api/api.php')}&ep_export_action=save_tool_export`;
        window.open(fullUrl, '_blank', 'noopener');
    }

    // ── Upload Drag & Drop ──────────────────────────────────────────
    let uploadSelectedFiles = [];
    function uploadFilesSelected(fileList) {
        const newFiles = Array.from(fileList || []);
        newFiles.forEach(f => {
            if (!uploadSelectedFiles.find(x => x.name === f.name && x.size === f.size)) {
                uploadSelectedFiles.push(f);
            }
        });
        renderUploadFileList();
    }

    function renderUploadFileList() {
        const list = document.getElementById('upload-file-list');
        const btn = document.getElementById('upload-submit-btn');
        const count = document.getElementById('upload-file-count');
        if (!list) return;

        list.innerHTML = '';
        uploadSelectedFiles.forEach((f, i) => {
            const ext = (f.name.split('.').pop() || '').toLowerCase();
            const iconMap = { pdf:'fa-file-pdf text-danger', jpg:'fa-file-image text-info', jpeg:'fa-file-image text-info', png:'fa-file-image text-info', gif:'fa-file-image text-info', xlsx:'fa-file-excel text-success', xls:'fa-file-excel text-success', csv:'fa-file-csv text-success', doc:'fa-file-word text-primary', docx:'fa-file-word text-primary', zip:'fa-file-archive text-warning', rar:'fa-file-archive text-warning', dwg:'fa-drafting-compass text-warning' };
            const icon = iconMap[ext] || 'fa-file text-gray';
            const size = f.size > 1024*1024 ? (f.size/(1024*1024)).toFixed(1)+'MB' : (f.size/1024).toFixed(0)+'KB';
            const row = document.createElement('div');
            row.style.cssText = 'display:flex;align-items:center;gap:10px;padding:8px 12px;background:var(--bg-body);border-radius:10px;border:1px solid var(--border-subtle);';
            row.innerHTML = `<i class="fas ${icon}" style="width:18px;flex-shrink:0;"></i><span class="text-white small flex-grow-1" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${f.name}</span><span class="text-gray small flex-shrink-0">${size}</span><button type="button" onclick="removeUploadFile(${i})" style="background:none;border:none;color:var(--text-muted);cursor:pointer;padding:0 4px;font-size:0.8rem;"><i class="fas fa-times"></i></button>`;
            list.appendChild(row);
        });

        const n = uploadSelectedFiles.length;
        if (btn) btn.disabled = n === 0;
        if (count) count.textContent = n > 0 ? `(${n} file${n > 1 ? 's' : ''})` : '';
        const dz = document.getElementById('upload-drop-zone');
        if (dz) dz.style.borderColor = n > 0 ? 'var(--primary)' : 'var(--border-subtle)';
    }
    function removeUploadFile(index) { uploadSelectedFiles.splice(index, 1); renderUploadFileList(); }
    function uploadDropZoneOver(e) { e.preventDefault(); const dz = document.getElementById('upload-drop-zone'); if (dz) { dz.style.borderColor = 'var(--primary)'; dz.style.background = 'rgba(251,90,58,0.06)'; } }
    function uploadDropZoneLeave(e) { const dz = document.getElementById('upload-drop-zone'); if (dz) { dz.style.borderColor = 'var(--border-subtle)'; dz.style.background = 'var(--bg-input)'; } }
    function uploadDropZoneDrop(e) { e.preventDefault(); uploadDropZoneLeave(e); const files = e.dataTransfer?.files; if (files && files.length) uploadFilesSelected(files); }

    function openUploadModal() {
        if (!canUpload) return;
        uploadSelectedFiles = [];
        renderUploadFileList();
        const modalEl = document.getElementById('uploadFileModal');
        if (modalEl) new bootstrap.Modal(modalEl).show();
    }

    function showUploadProgress() {
        const wrap = document.getElementById('uploadProgressWrap');
        const bar = document.getElementById('uploadProgressBar');
        const txt = document.getElementById('uploadProgressText');
        if (!wrap || !bar || !txt) return;
        wrap.style.display = 'block';
        bar.style.width = '0%';
        txt.textContent = '0%';
    }

    function updateUploadProgress(pct) {
        const bar = document.getElementById('uploadProgressBar');
        const txt = document.getElementById('uploadProgressText');
        if (!bar || !txt) return;
        const clamped = Math.max(0, Math.min(100, Math.round(pct)));
        bar.style.width = clamped + '%';
        txt.textContent = clamped + '%';
    }

    function hideUploadProgress(delay = 1200) {
        const wrap = document.getElementById('uploadProgressWrap');
        if (!wrap) return;
        setTimeout(() => { wrap.style.display = 'none'; }, delay);
    }

    function uploadWithProgress(fd) {
        showUploadProgress();
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '../api/api.php', true);
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    updateUploadProgress((e.loaded / e.total) * 100);
                }
            });
            xhr.addEventListener('load', () => {
                updateUploadProgress(100);
                try {
                    const res = JSON.parse(xhr.responseText);
                    resolve(res);
                } catch (err) {
                    reject(err);
                }
            });
            xhr.addEventListener('error', () => reject(new Error('Upload failed')));
            xhr.send(fd);
        });
    }

    const uploadFileForm = document.getElementById('uploadFileForm');
    if (uploadFileForm) {
        uploadFileForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            if (!canUpload) return;

            if (!uploadSelectedFiles.length) {
                appAlert('Please select at least one file.', 'Missing Files', 'warning');
                return;
            }

            let targetFolderId = fId || null;
            if (!targetFolderId) {
                const folderSelect = document.getElementById('upload_folder_select');
                if (!folderSelect || !folderSelect.value) {
                    appAlert('Please select a destination folder.', 'Missing Folder', 'warning');
                    return;
                }
                targetFolderId = folderSelect.value;
            }

            const modalEl = document.getElementById('uploadFileModal');
            if (modalEl) {
                const inst = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                inst.hide();
            }

            const errors = [];
            const total = uploadSelectedFiles.length;
            for (let i = 0; i < uploadSelectedFiles.length; i++) {
                const file = uploadSelectedFiles[i];
                const fd = new FormData();
                fd.append('action', 'upload_file');
                fd.append('project_id', pId);
                if (targetFolderId) fd.append('folder_id', targetFolderId);
                fd.append('file', file);

                showUploadProgress();
                updateUploadProgress(Math.round((i / total) * 100));

                try {
                    const d = await uploadWithProgress(fd);
                    if (d.status !== 'success') errors.push(`${file.name}: ${d.msg || 'Unknown error'}`);
                } catch(err) {
                    errors.push(`${file.name}: upload failed`);
                }
            }

            hideUploadProgress(errors.length ? 1500 : 600);
            uploadSelectedFiles = [];
            renderUploadFileList();

            if (errors.length) {
                appAlert(`Uploaded with ${errors.length} error(s):<br><small class="text-gray">${errors.slice(0,5).join('<br>')}</small>`, 'Upload Complete', 'warning');
            }
            setTimeout(() => location.reload(), 800);
        });
    }


    function openAssignUsersModal() {
        const modalEl = document.getElementById('assignUsersModal');
        if (!modalEl) return;
        new bootstrap.Modal(modalEl).show();
    }
    const assignUsersForm = document.getElementById('assignUsersForm');
    if (assignUsersForm) {
        assignUsersForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const checked = Array.from(this.querySelectorAll('input[name="user_ids[]"]:checked'));
            const hasAdmin = checked.some(i => i.dataset.role === 'admin');
            if (checked.length === 0 || !hasAdmin) {
                appAlert('At least one admin must be assigned to the project.', "Assignment Error", "warning");
                return;
            }
            const fd = new FormData(this);
            fetch('../api/api.php', { method:'POST', body:fd })
                .then(r => r.json())
                .then(d => {
                    if (d.status === 'success') location.reload();
                    else appAlert('Error assigning users: ' + (d.msg || 'Unknown'), "Error", "error");
                })
                .catch(() => appAlert('Connection error', "Error", "error"));
        });
    }

    function deleteFile(id) {
        appConfirm("Move file to Recycle Bin?", "Delete File", () => {
            const fd = new FormData();
            fd.append('action', 'delete_entity');
            fd.append('type', 'file');
            fd.append('id', id);
            fetch('../api/api.php', { method:'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if(d.status === 'success') location.reload();
                    else appAlert('Error deleting file: ' + (d.msg || 'Unknown'), "Error", "error");
                })
                .catch(() => appAlert('Connection error', "Error", "error"));
        });
    }

    function deleteFolder(id) {
        appConfirm("Move folder to Recycle Bin?", "Delete Folder", () => {
            const fd = new FormData();
            fd.append('action', 'delete_entity');
            fd.append('type', 'folder');
            fd.append('id', id);
            fetch('../api/api.php', { method:'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if(d.status === 'success') location.reload();
                    else appAlert('Error deleting folder: ' + (d.msg || 'Unknown'), "Error", "error");
                })
                .catch(() => appAlert('Connection error', "Error", "error"));
        });
    }

    function openMoveModal(fileId) {
        const moveId = document.getElementById('move_id');
        const moveType = document.getElementById('move_type');
        const projSelect = document.getElementById('move_project_select');
        const folderSelect = document.getElementById('move_folder_select');
        if (!moveId || !moveType || !projSelect || !folderSelect) return;

        moveId.value = fileId;
        moveType.value = 'file';
        projSelect.innerHTML = '<option value="">Loading projects...</option>';
        folderSelect.innerHTML = '<option value="">Root Folder</option>';

        const modalEl = document.getElementById('moveFileModal');
        if (modalEl) new bootstrap.Modal(modalEl).show();

        const fd = new FormData();
        fd.append('action', 'get_projects_list');
        fetch('../api/api.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if(res.status === 'success') {
                    projSelect.innerHTML = '<option value="">Select Target Project...</option>';
                    res.data.forEach(p => { projSelect.innerHTML += `<option value="${p.id}">${p.name}</option>`; });
                } else {
                    projSelect.innerHTML = '<option value="">Error loading</option>';
                }
            })
            .catch(() => { projSelect.innerHTML = '<option value="">Connection Error</option>'; });
    }

    function loadFoldersForMove(projId) {
        const folderSel = document.getElementById('move_folder_select');
        if (!folderSel) return;
        folderSel.innerHTML = '<option value="">Loading...</option>';
        if(!projId) { folderSel.innerHTML = '<option value="">Root Folder</option>'; return; }

        const fd = new FormData();
        fd.append('action', 'get_folders_list');
        fd.append('project_id', projId);
        fetch('../api/api.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                folderSel.innerHTML = '<option value="">Root Folder (No specific folder)</option>';
                if(res.status === 'success') {
                    res.data.forEach(f => { folderSel.innerHTML += `<option value="${f.id}">${f.name}</option>`; });
                }
            })
            .catch(() => { folderSel.innerHTML = '<option value="">Connection Error</option>'; });
    }

    const moveForm = document.getElementById('moveFileForm');
    if (moveForm) {
        moveForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            fetch('../api/api.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if(d.status === 'success') location.reload();
                    else appAlert('Error moving file: ' + (d.msg || 'Unknown'), "Error", "error");
                })
                .catch(() => appAlert('Connection error', "Error", "error"));
        });
    }

    function openMoveFolderModal(folderId) {
        const moveFolderId = document.getElementById('move_folder_id');
        const projSelect = document.getElementById('move_folder_project_select');
        const parentSelect = document.getElementById('move_folder_parent_select');
        if (!moveFolderId || !projSelect || !parentSelect) return;

        moveFolderId.value = folderId;
        projSelect.innerHTML = '<option value="">Loading projects...</option>';
        parentSelect.innerHTML = '<option value="">Keep as top-level</option>';

        const modalEl = document.getElementById('moveFolderModal');
        if (modalEl) new bootstrap.Modal(modalEl).show();

        const fd = new FormData();
        fd.append('action', 'get_projects_list');
        fetch('../api/api.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if(res.status === 'success') {
                    projSelect.innerHTML = '<option value="">Select Target Project...</option>';
                    res.data.forEach(p => { projSelect.innerHTML += `<option value="${p.id}">${p.name}</option>`; });
                } else {
                    projSelect.innerHTML = '<option value="">Error loading</option>';
                }
            })
            .catch(() => { projSelect.innerHTML = '<option value="">Connection Error</option>'; });
    }

    function loadFoldersForFolderMove(projId) {
        const parentSel = document.getElementById('move_folder_parent_select');
        if (!parentSel) return;
        parentSel.innerHTML = '<option value="">Loading...</option>';
        if(!projId) { parentSel.innerHTML = '<option value="">Keep as top-level</option>'; return; }

        const currentFolderId = parseInt(document.getElementById('move_folder_id').value || '0', 10);
        const fd = new FormData();
        fd.append('action', 'get_folders_list');
        fd.append('project_id', projId);
        fetch('../api/api.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                parentSel.innerHTML = '<option value="">Keep as top-level</option>';
                if(res.status === 'success') {
                    res.data.forEach(f => {
                        if (parseInt(f.id, 10) === currentFolderId) return;
                        parentSel.innerHTML += `<option value="${f.id}">${f.name}</option>`;
                    });
                }
            })
            .catch(() => { parentSel.innerHTML = '<option value="">Connection Error</option>'; });
    }

    const moveFolderForm = document.getElementById('moveFolderForm');
    if (moveFolderForm) {
        moveFolderForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            fetch('../api/api.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if(d.status === 'success') location.reload();
                    else appAlert('Error moving folder: ' + (d.msg || 'Unknown'), "Error", "error");
                })
                .catch(() => appAlert('Connection error', "Error", "error"));
        });
    }

    function openNewFolderModal() {
        const modalEl = document.getElementById('newFolderModalDash');
        if (!modalEl) return;
        if (typeof clearNewFolderError === 'function') clearNewFolderError();
        new bootstrap.Modal(modalEl).show();
    }
    const newFolderFormDash = document.getElementById('newFolderFormDash');
    const newFolderError = document.getElementById('newFolderError');
    const showNewFolderError = (msg) => {
        if (!newFolderError) return;
        newFolderError.textContent = msg;
        newFolderError.classList.remove('d-none');
    };
    const clearNewFolderError = () => {
        if (!newFolderError) return;
        newFolderError.textContent = '';
        newFolderError.classList.add('d-none');
    };

    // --- Subfolder Modal ---
    function openAddSubfolderModal(parentId, parentName) {
        document.getElementById('subfolder-parent-id').value = parentId;
        document.getElementById('subfolder-parent-name').textContent = parentName;
        document.getElementById('subfolder-name-input').value = '';
        const errEl = document.getElementById('addSubfolderError');
        if (errEl) {
            errEl.textContent = '';
            errEl.classList.add('d-none');
        }
        new bootstrap.Modal(document.getElementById('addSubfolderModal')).show();
    }

    const addSubfolderForm = document.getElementById('addSubfolderForm');
    if (addSubfolderForm) {
        addSubfolderForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const errEl = document.getElementById('addSubfolderError');
            const fd = new FormData(this);
            fd.append('action', 'create_folder');
            fd.append('project_id', pId);
            fetch('../api/api.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.status === 'success') {
                        location.reload();
                    } else {
                        errEl.textContent = 'Error: ' + (d.msg || 'Unknown error');
                        errEl.classList.remove('d-none');
                    }
                })
                .catch(() => {
                    errEl.textContent = 'Connection error. Please try again.';
                    errEl.classList.remove('d-none');
                });
        });
    }

    if (newFolderFormDash) {
        newFolderFormDash.addEventListener('submit', function(e) {
            e.preventDefault();
            clearNewFolderError();
            const fd = new FormData(this);
            fd.append('action', 'create_folder');
            fd.append('project_id', pId);
            fetch('../api/api.php', { method:'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.status === 'success') {
                        location.reload();
                    } else {
                        showNewFolderError('Error creating folder: ' + (d.msg || 'Unknown'));
                    }
                })
                .catch(() => showNewFolderError('Connection error while creating folder.'));
        });
    }

    // ── Bulk ZIP Import ─────────────────────────────────────────────
    let zipSelectedFile = null;
    function openBulkZipModal() {
        zipClearSelection();
        const errEl = document.getElementById('zip-error-msg');
        if (errEl) { errEl.textContent = ''; errEl.classList.add('d-none'); }
        new bootstrap.Modal(document.getElementById('bulkZipModal')).show();
    }
    function zipDropOver(e) { e.preventDefault(); const dz = document.getElementById('zip-drop-zone'); if (dz) { dz.style.borderColor = 'var(--primary)'; dz.style.background = 'rgba(251,90,58,0.06)'; } }
    function zipDropLeave(e) { const dz = document.getElementById('zip-drop-zone'); if (dz) { dz.style.borderColor = 'var(--border-subtle)'; dz.style.background = 'var(--bg-input)'; } }
    function zipDropDrop(e) { e.preventDefault(); zipDropLeave(e); const file = e.dataTransfer?.files?.[0]; if (file) zipFileSelected(file); }

    function zipFileSelected(file) {
        const errEl = document.getElementById('zip-error-msg');
        if (!file) return;
        if (!file.name.toLowerCase().endsWith('.zip')) { if (errEl) { errEl.textContent = 'Only .zip files are supported.'; errEl.classList.remove('d-none'); } return; }
        const maxBytes = 10 * 1024 * 1024 * 1024;
        if (file.size > maxBytes) { if (errEl) { errEl.textContent = 'ZIP file exceeds 10GB limit.'; errEl.classList.remove('d-none'); } return; }
        if (errEl) errEl.classList.add('d-none');
        zipSelectedFile = file;
        const info = document.getElementById('zip-selected-info');
        const name = document.getElementById('zip-selected-name');
        const size = document.getElementById('zip-selected-size');
        const btn = document.getElementById('zip-upload-btn');
        const dz = document.getElementById('zip-drop-zone');
        if (name) name.textContent = file.name;
        if (size) size.textContent = `${(file.size / (1024 * 1024)).toFixed(2)} MB`;
        if (info) info.classList.remove('d-none');
        if (btn) btn.disabled = false;
        if (dz) dz.style.borderColor = 'var(--primary)';
    }

    function zipClearSelection() {
        zipSelectedFile = null;
        const info = document.getElementById('zip-selected-info');
        const btn = document.getElementById('zip-upload-btn');
        const dz = document.getElementById('zip-drop-zone');
        const input = document.getElementById('zip-file-input');
        if (info) info.classList.add('d-none');
        if (btn) btn.disabled = true;
        if (dz) dz.style.borderColor = 'var(--border-subtle)';
        if (input) input.value = '';
    }

    async function startZipUpload() {
        if (!zipSelectedFile) return;
        const modalEl = document.getElementById('bulkZipModal');
        if (modalEl) { const inst = bootstrap.Modal.getInstance(modalEl); if (inst) inst.hide(); }

        const overlay = document.getElementById('bulk-import-overlay');
        const statusTitle = document.getElementById('bulk-status-title');
        const statusDetail = document.getElementById('bulk-status-detail');
        const progressBar = document.getElementById('bulk-progress-bar');
        const statusCount = document.getElementById('bulk-status-count');
        const statusLog = document.getElementById('bulk-status-log');
        if (overlay) overlay.style.display = 'flex';
        if (statusTitle) statusTitle.textContent = 'Uploading ZIP...';
        if (statusDetail) statusDetail.textContent = zipSelectedFile.name;
        if (statusCount) statusCount.textContent = 'Extracting...';
        if (progressBar) progressBar.style.width = '30%';

        const fd = new FormData();
        fd.append('action', 'upload_zip_bulk');
        fd.append('project_id', pId);
        if (currentFolderId) fd.append('parent_folder_id', currentFolderId);
        fd.append('zip_file', zipSelectedFile, zipSelectedFile.name);

        try {
            const result = await new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', '../api/api.php', true);
                xhr.timeout = 3600000;
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        const pct = Math.round((e.loaded / e.total) * 80);
                        if (progressBar) progressBar.style.width = pct + '%';
                        if (statusDetail) statusDetail.textContent = `Uploading: ${pct}%`;
                    }
                });
                xhr.addEventListener('load', () => {
                    if (progressBar) progressBar.style.width = '85%';
                    if (statusDetail) statusDetail.textContent = 'Extracting ZIP...';
                    try { resolve(JSON.parse(xhr.responseText)); } catch(e) { reject(new Error('Invalid server response')); }
                });
                xhr.addEventListener('error', () => reject(new Error('Upload failed')));
                xhr.send(fd);
            });

            if (progressBar) progressBar.style.width = '100%';
            if (result.status === 'success') {
                if (statusTitle) statusTitle.textContent = 'Done!';
                if (statusCount) statusCount.textContent = `${result.files_created || 0} files · ${result.folders_created || 0} folders created`;
                if (statusLog && result.log && result.log.length) {
                    statusLog.innerHTML = result.log.slice(0, 20).map(l => `<div class="text-gray" style="font-size:0.72rem;">${l}</div>`).join('');
                }
                setTimeout(() => { if (overlay) overlay.style.display = 'none'; location.reload(); }, 2000);
            } else {
                if (overlay) overlay.style.display = 'none';
                appAlert('Error: ' + (result.msg || 'Unknown error'), 'Import Failed', 'error');
            }
        } catch(e) {
            if (overlay) overlay.style.display = 'none';
            appAlert('Connection error: ' + e.message, 'Error', 'error');
        }
    }


// ── Global Search ────────────────────────────────────────────────
let globalSearchTimer = null;
function globalSearchFiles(q) { const resultsBox = document.getElementById('globalSearchResults'); const clearBtn = document.getElementById('globalSearchClearBtn'); if (clearBtn) clearBtn.style.display = q ? 'inline-block' : 'none'; clearTimeout(globalSearchTimer); if (!q || q.trim().length < 2) { if (resultsBox) resultsBox.style.display = 'none'; return; } globalSearchTimer = setTimeout(async () => { const fd = new FormData(); fd.append('action', 'search_project_files'); fd.append('project_id', pId); fd.append('query', q.trim()); try { const d = await fetch('../api/api.php', { method:'POST', body:fd }).then(r => r.json()); if (!resultsBox) return; if (!d.results || !d.results.length) { resultsBox.innerHTML = '<div class="p-3 text-gray small text-center">No files found for "' + q + '"</div>'; resultsBox.style.display = 'block'; return; } const iconMap = { pdf:'fa-file-pdf text-danger', jpg:'fa-file-image text-info', jpeg:'fa-file-image text-info', png:'fa-file-image text-info', xlsx:'fa-file-excel text-success', xls:'fa-file-excel text-success', doc:'fa-file-word text-primary', docx:'fa-file-word text-primary', dwg:'fa-drafting-compass text-warning' }; resultsBox.innerHTML = d.results.map(r => { const ext = (r.file_type || r.filename.split('.').pop()).toLowerCase(); const icon = iconMap[ext] || 'fa-file text-gray'; const bc = r.breadcrumb.slice(0,-1).map(p => `<span class="text-gray">${p}</span>`).join(' <span class="text-muted mx-1">›</span> '); const fname = r.breadcrumb[r.breadcrumb.length - 1]; const url = `?id=${pId}&view=files&folder_id=${r.folder_id || ''}`; return `<a href="${url}" class="d-flex align-items-center gap-3 px-4 py-3 text-decoration-none search-result-item"> <i class="fas ${icon} flex-shrink-0" style="font-size:1.2rem; width:22px;"></i> <div class="overflow-hidden"> <div class="text-white small fw-semibold text-truncate">${fname}</div> <div class="small" style="font-size:0.72rem;">${bc}</div> </div> </a>`; }).join(''); resultsBox.style.display = 'block'; } catch(e) { console.error('Search error:', e); } }, 320); }
function clearGlobalSearch() { const input = document.getElementById('globalSearchInput'); const box = document.getElementById('globalSearchResults'); const btn = document.getElementById('globalSearchClearBtn'); if (input) input.value = ''; if (box) box.style.display = 'none'; if (btn) btn.style.display = 'none'; }
document.addEventListener('click', (e) => { const wrap = document.getElementById('global-search-wrap'); if (wrap && !wrap.contains(e.target)) { const box = document.getElementById('globalSearchResults'); if (box) box.style.display = 'none'; } });
// ── Rename ───────────────────────────────────────────────────────
function openRenameModal(type, id, currentName) { document.getElementById('renameItemId').value = id; document.getElementById('renameItemType').value = type; document.getElementById('renameInput').value = currentName; document.getElementById('renameModalTitle').innerHTML = `<i class="fas fa-pen me-2 text-primary"></i>Rename ${type === 'file' ? 'File' : 'Folder'}`; const errEl = document.getElementById('renameError'); if (errEl) { errEl.textContent = ''; errEl.classList.add('d-none'); } new bootstrap.Modal(document.getElementById('renameModal')).show(); }
async function submitRename() { const id = document.getElementById('renameItemId').value; const type = document.getElementById('renameItemType').value; const newName = document.getElementById('renameInput').value.trim(); const roles = (document.getElementById('visibilityRoles')?.value || '').trim(); const users = (document.getElementById('visibilityUsers')?.value || '').trim(); const errEl = document.getElementById('renameError'); if (!newName) { errEl.textContent = 'Name cannot be empty.'; errEl.classList.remove('d-none'); return; } const fd = new FormData(); fd.append('action', type === 'file' ? 'rename_file' : 'rename_folder'); fd.append('id', id); fd.append('name', newName); try { const d = await fetch('../api/api.php', { method:'POST', body:fd }).then(r => r.json()); if (d.status === 'success') { const vr = new FormData(); vr.append('action','set_visibility_rules'); vr.append('entity_type', type); vr.append('entity_id', id); vr.append('roles', roles); vr.append('users', users); await fetch('../api/api.php', { method:'POST', body:vr }).then(r => r.json()).catch(()=>({})); bootstrap.Modal.getInstance(document.getElementById('renameModal')).hide(); location.reload(); } else { errEl.textContent = d.msg || 'Error renaming.'; errEl.classList.remove('d-none'); } } catch(e) { errEl.textContent = 'Connection error.'; errEl.classList.remove('d-none'); } }

</script>

<?php if($userRole !== 'viewer'): ?>
    <?php include __DIR__ . '/../views/smart_pm_sidebar.php'; ?>
<?php endif; ?>
<?php include __DIR__ . '/../views/footer.php'; ?>
