<?php
// index.php - V8.4 (Updated for New Project Structure)
require_once __DIR__ . '/../core/auth/session.php';
require_once __DIR__ . '/../core/db/connection.php';
require_once __DIR__ . '/../core/time.php'; 

// ---------------------------------------------------------
// 1. DEFINICIÓN DE USUARIO Y ROLES
// ---------------------------------------------------------
$userId = $_SESSION['user_id'];
$userName = $_SESSION['username'];
$userRoleRaw = $_SESSION['role'] ?? 'viewer'; 
$userRole = strtolower($userRoleRaw); 

$isAdmin = ($userRole === 'admin');
$canCreate = $isAdmin;
$canDelete = $isAdmin; 
$canUpload = $isAdmin;
$createUsers = [];
if ($canCreate) {
    $stmtUsers = $pdo->query("SELECT id, username, role FROM users ORDER BY username ASC");
    $createUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
}

// ---------------------------------------------------------
// 2. LÓGICA DE ACCIONES (POST y GET)
// ---------------------------------------------------------
// Nota: La creación de proyectos ahora se maneja en 'api/create_project.php'
// Mantenemos la lógica de carpetas por si acaso, aunque el dashboard nuevo la gestionará.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // CREAR CARPETA (Legacy support / Quick create)
    if ($_POST['action'] === 'create_folder' && $isAdmin) {
        $name = trim($_POST['new_folder_name']);
        $pId = $_POST['project_id'];
        if(!empty($name) && !empty($pId)){
            $check = $pdo->prepare("SELECT id FROM folders WHERE name=? AND project_id=? AND deleted_at IS NULL");
            $check->execute([$name, $pId]);
            if(!$check->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO folders (name, project_id) VALUES (?, ?)");
                $stmt->execute([$name, $pId]);
            }
        }
        // Redirigir al nuevo dashboard si se crea desde ahí
        header("Location: project_dashboard.php?id=$pId"); 
        exit;
    }
}

// BORRAR CARPETA
if (isset($_GET['action']) && $_GET['action'] === 'delete_folder' && isset($_GET['id']) && $isAdmin) {
    $folderIdDel = $_GET['id'];
    $stmt = $pdo->prepare("UPDATE folders SET deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$folderIdDel]);
    if(isset($_SERVER['HTTP_REFERER'])) { header("Location: " . $_SERVER['HTTP_REFERER']); } else { header("Location: index.php"); }
    exit;
}

// ---------------------------------------------------------
// 3. INICIALIZAR VARIABLES Y VISTAS
// ---------------------------------------------------------
$projectId = $_GET['project_id'] ?? null;
$folderId = $_GET['folder_id'] ?? null;
$viewTrash = isset($_GET['view']) && $_GET['view'] === 'trash' && $isAdmin;

$viewLevel = 'dashboard';
$pageTitle = "Dashboard"; 
$project = null; $folder = null;
$folders = []; $subFolders = []; $files = []; $recentFiles = []; 
$stats = []; $recentProjects = []; $trashProjects = []; $trashFiles = []; $trashReports = []; 
$assignUsers = []; $assignedUserIds = [];

// ---------------------------------------------------------
// 4. CONSULTAS DE DATOS
// ---------------------------------------------------------

if ($viewTrash) {
    $pageTitle = "Recycle Bin";
    $viewLevel = 'trash';
    $trashProjects = $pdo->query("SELECT * FROM projects WHERE deleted_at IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
    $trashFiles = $pdo->query("SELECT * FROM files WHERE deleted_at IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
    $trashReports = $pdo->query("SELECT r.*, f.filename FROM file_reports r LEFT JOIN files f ON r.file_id = f.id WHERE r.is_deleted = 1 ORDER BY r.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
}
elseif (!$projectId) {
    // VISTA PRINCIPAL (HOME)
    $stats['total_projects'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE deleted_at IS NULL")->fetchColumn();
    $stats['total_files'] = $pdo->query("SELECT COUNT(*) FROM files WHERE deleted_at IS NULL")->fetchColumn();
    $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    
    // Obtener proyectos recientes
    $recentProjects = $pdo->query("SELECT * FROM projects WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener archivos recientes globalmente
    $recentFiles = $pdo->query("SELECT f.*, p.name as project_name, fo.name as folder_name FROM files f LEFT JOIN projects p ON f.project_id = p.id LEFT JOIN folders fo ON f.folder_id = fo.id WHERE f.deleted_at IS NULL ORDER BY f.uploaded_at DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
}
else {
    // VISTA LEGACY (Si alguien entra por un link antiguo) - Redirigir al nuevo dashboard recomendado
    header("Location: project_dashboard.php?id=$projectId"); exit;

    $project = $pdo->query("SELECT * FROM projects WHERE id=$projectId")->fetch(PDO::FETCH_ASSOC);
    if(!$project) die("Project not found");
    $pageTitle = $project['name'];
    
    if (!$folderId) {
        $viewLevel = 'project';
        $folders = $pdo->prepare("SELECT * FROM folders WHERE project_id=? AND deleted_at IS NULL ORDER BY id DESC"); 
        $folders->execute([$projectId]); $folders = $folders->fetchAll(PDO::FETCH_ASSOC);
        $stmtFiles = $pdo->prepare("SELECT f.*, fo.name as folder_name FROM files f LEFT JOIN folders fo ON f.folder_id = fo.id WHERE f.project_id = ? AND f.deleted_at IS NULL ORDER BY f.uploaded_at DESC");
        $stmtFiles->execute([$projectId]); $files = $stmtFiles->fetchAll(PDO::FETCH_ASSOC);

        if ($isAdmin) {
            $assignUsers = $pdo->query("SELECT id, username, role FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
            $stmtAssigned = $pdo->prepare("SELECT user_id FROM directory WHERE project_id = ?");
            $stmtAssigned->execute([$projectId]);
            $assignedUserIds = array_map('intval', $stmtAssigned->fetchAll(PDO::FETCH_COLUMN));
        }
    } else {
        $viewLevel = 'folder';
        $folder = $pdo->query("SELECT * FROM folders WHERE id=$folderId")->fetch(PDO::FETCH_ASSOC);
        if($folder) {
            $pageTitle .= " / " . $folder['name'];
            $subFolders = $pdo->prepare("SELECT * FROM sub_folders WHERE folder_id=? AND deleted_at IS NULL ORDER BY id DESC"); 
            $subFolders->execute([$folderId]); $subFolders = $subFolders->fetchAll(PDO::FETCH_ASSOC);
            $stmtFiles = $pdo->prepare("SELECT f.*, fo.name as folder_name FROM files f LEFT JOIN folders fo ON f.folder_id = fo.id WHERE f.folder_id = ? AND f.sub_folder_id IS NULL AND f.deleted_at IS NULL ORDER BY f.uploaded_at DESC");
            $stmtFiles->execute([$folderId]); $files = $stmtFiles->fetchAll(PDO::FETCH_ASSOC);
        } else { die("Folder not found"); }
    }
}

// INCLUYE HEADER (Trae CSS, Sidebar y abre el d-flex-wrapper)
include __DIR__ . '/../views/header.php'; 
?>

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
/* Override Bootstrap text-muted con la paleta del proyecto */
.text-muted { color: var(--text-gray) !important; }
body.theme-light .text-muted { color: var(--text-gray) !important; }


    body.theme-light .bg-dark { background-color: var(--bg-input) !important; color: var(--text-white) !important; border-color: var(--border-subtle) !important; }
    body.theme-light .text-white { color: var(--text-white) !important; }

    .box-card { background: var(--bg-card); border-radius: var(--radius-box); border: 1px solid var(--border-subtle); transition: 0.3s; }
    .box-card:hover { transform: translateY(-3px); border-color: var(--primary); }
    .box-card-dashed { border: 2px dashed var(--border-subtle); background: transparent; }
    .box-card-dashed:hover { border-color: var(--primary); background: rgba(251, 90, 58, 0.05); }

    .btn-main { background-color: var(--primary) !important; border-color: var(--primary) !important; color: white !important; border-radius: 8px; padding: 8px 16px; border: 1px solid transparent; transition: 0.2s; }
    .btn-main:hover { background-color: var(--primary-hover) !important; border-color: var(--primary-hover) !important; }
    .btn-outline-light { border-color: var(--border-subtle); color: var(--text-gray); }
    .btn-outline-light:hover { background: var(--bg-input); color: var(--primary); border-color: var(--primary); }

    .btn-icon { width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--border-subtle); color: var(--text-gray); transition: 0.2s; background: var(--bg-card); text-decoration: none; }
    .btn-icon:hover { background: var(--primary); color: white; border-color: var(--primary); }
    .btn-icon.border-primary { color: var(--primary); border-color: var(--primary); }
    .btn-icon.border-primary:hover { background: var(--primary); color: white; }
    .btn-icon.border-warning { color: #f59e0b; border-color: #f59e0b; }
    .btn-icon.border-warning:hover { background: #f59e0b; color: white; }
    .btn-icon.border-danger { color: #ef4444; border-color: #ef4444; }
    .btn-icon.border-danger:hover { background: #ef4444; color: white; }

    .bulk-actions-bar { background: var(--bg-card); border: 1px solid var(--border-subtle); }
    
    .modal-content { background-color: var(--bg-card); border: 1px solid var(--border-subtle); color: var(--text-white); border-radius: var(--radius-box); }
    .modal-header { border-bottom: 1px solid var(--border-subtle); }
    .modal-footer { border-top: 1px solid var(--border-subtle); }
    .modal-content .border { border-color: var(--border-subtle) !important; }
    .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }
    body.theme-light .btn-close { filter: none; }

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
        outline: none;
    }
    .file-card-modern:hover .file-overlay,
    .file-card-modern:focus-within .file-overlay { opacity: 1; }

    .overlay-action { display: inline-flex; flex-direction: column; align-items: center; justify-content: center; text-decoration: none; color: white !important; transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), filter 0.2s; }
    .overlay-view { background: rgba(245, 158, 11, 0.95); transform: translateX(-100%); }
    .overlay-edit { background: rgba(59, 130, 246, 0.95); transform: translateX(100%); }
    .overlay-action.w-100 { transform: translateY(100%); }
    .file-card-modern:hover .overlay-action, .file-card-modern:focus-within .overlay-action { transform: translate(0, 0); }
    .overlay-action:hover { filter: brightness(1.1); }
    .overlay-action i { transition: transform 0.2s; }
    .overlay-action:hover i { transform: scale(1.2); }

    @media (max-width: 992px) {
        .proj-actions { flex-wrap: wrap; gap: 8px; }
        .proj-actions .btn,
        .proj-actions .btn-main { width: 100%; }
        .folder-grid .col-md-3,
        .file-grid .col-md-3 { flex: 0 0 100%; max-width: 100%; }
        .box-card { padding: 16px; }
        .file-card-item { padding: 16px; }
    }
    @media (max-width: 768px) {
        .header { flex-direction: column; align-items: flex-start; gap: 12px; }
        .breadcrumbs { margin-top: 4px; }
        .main-content { padding: 20px; }
        .d-flex.justify-content-between.align-items-end { flex-direction: column; align-items: flex-start; gap: 12px; }
    }
</style>

    <main class="main-content">
        
        <header class="header">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>

                <div class="breadcrumbs">
                    <a href="index.php">Home</a>
                    <?php if($viewTrash): ?> <i class="fas fa-chevron-right mx-2" style="font-size:0.7rem"></i><span>Recycle Bin</span> <?php endif; ?>
                    <?php if($projectId && !$viewTrash): ?>
                        <i class="fas fa-chevron-right mx-2" style="font-size:0.7rem"></i><span><?= htmlspecialchars($project['name']) ?></span>
                        <?php if($folderId && $folder): ?><i class="fas fa-chevron-right mx-2" style="font-size:0.7rem"></i><span class="text-primary"><?= htmlspecialchars($folder['name']) ?></span><?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <a href="../admin/settings.php?tab=users" class="user-pill text-decoration-none">
                <div class="avatar"><?= strtoupper(substr($userName,0,1)) ?></div>
                <div class="user-pill-info">
                    <span class="user-pill-name"><?= htmlspecialchars($userName) ?></span>
                    <span class="user-pill-role"><?= ucfirst($userRole) ?></span>
                </div>
            </a>
        </header>

        <?php if(!$projectId && !$viewTrash): ?>
            <div class="d-flex justify-content-between align-items-end mb-5">
                <div><h1 class="fw-bold mb-2">Welcome Back!</h1><p class="text-gray mb-0">Here is the latest activity.</p></div>
                <?php if($canCreate): ?>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-light btn-sm rounded-pill px-3" onclick="document.getElementById('bulk-folder-input').click()" title="Import projects from local folders">
                            <i class="fas fa-folder-tree me-2"></i>Bulk Import
                        </button>
                        <input type="file" id="bulk-folder-input" webkitdirectory multiple style="display:none" onchange="handleBulkFolderImport(this)">
                        <a href="project_create.php" class="btn-main text-decoration-none">
                            <i class="fas fa-plus me-2"></i> New Project
                        </a>
                    </div>
                    <div id="bulk-import-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:9999; align-items:center; justify-content:center;">
                        <div style="background:var(--bg-card); border-radius:16px; padding:32px; width:380px; text-align:center;">
                            <div class="fw-bold text-white mb-2" id="bulk-status-title">Importing...</div>
                            <div class="text-gray small mb-3" id="bulk-status-detail">Starting...</div>
                            <div class="progress mb-3" style="height:8px; border-radius:4px;">
                                <div id="bulk-progress-bar" class="progress-bar bg-primary" style="width:0%; transition:width 0.3s;"></div>
                            </div>
                            <div class="text-gray small" id="bulk-status-count">0 / 0 files</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="row g-4 mb-5">
                <div class="col-md-4"><div class="box-card"><i class="fas fa-folder-open stat-icon-bg" style="color: #3b82f6; opacity: 0.5;"></i><div class="stat-num"><?= $stats['total_projects'] ?></div><div class="stat-label">Active Projects</div></div></div>
                <div class="col-md-4"><div class="box-card"><i class="fas fa-file-contract stat-icon-bg" style="color: #10b981; opacity: 0.5;"></i><div class="stat-num"><?= $stats['total_files'] ?></div><div class="stat-label">Files Uploaded</div></div></div>
                <div class="col-md-4"><div class="box-card"><i class="fas fa-users stat-icon-bg" style="color: #f59e0b; opacity: 0.5;"></i><div class="stat-num"><?= $stats['total_users'] ?></div><div class="stat-label">Active Users</div></div></div>
            </div>
            
            <h5 class="fw-bold mb-4">Recent Projects (Latest 10)</h5>
            <div class="d-flex flex-column gap-2 mb-5">
                <?php foreach($recentProjects as $p): ?>
                <a href="project_dashboard.php?id=<?= $p['id'] ?>" class="box-card d-flex align-items-center justify-content-between text-decoration-none p-3">
                    <div class="d-flex align-items-center gap-3 w-100 overflow-hidden">
                        <div class="bg-primary bg-opacity-10 p-2 rounded text-primary d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; flex-shrink: 0;">
                            <i class="fas fa-folder"></i>
                        </div>
                        <div class="overflow-hidden flex-grow-1">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <div class="fw-bold text-white text-truncate" style="font-size: 1.05rem;"><?= htmlspecialchars($p['name']) ?></div>
                                <span class="badge bg-success bg-opacity-25 text-success px-2 py-0 rounded small d-md-none">Active</span>
                            </div>
                            <div class="small text-gray text-truncate"><?= htmlspecialchars($p['description'] ?: 'No description.') ?></div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-4 flex-shrink-0 ms-3">
                        <div class="d-none d-md-block text-end">
                            <div class="small text-white fw-bold"><?= date('M d, Y', strtotime($p['created_at'])) ?></div>
                        </div>
                        <div class="d-none d-md-block">
                            <span class="badge bg-success bg-opacity-25 text-success px-2 py-1 rounded">Active</span>
                        </div>
                        <i class="fas fa-chevron-right text-gray"></i>
                    </div>
                </a>
                <?php endforeach; ?>
                <?php if(empty($recentProjects)): ?>
                    <div class="text-gray text-center py-4">No projects created yet.</div>
                <?php endif; ?>
            </div>

            <?php if(!empty($recentFiles)): ?>
            <h5 class="fw-bold mb-4">Recently Uploaded Files</h5>
            <div class="row g-3">
                <?php foreach($recentFiles as $f): 
                    $ft = strtolower(pathinfo($f['filename'], PATHINFO_EXTENSION));
                    $isPdf = ($ft === 'pdf'); $isImg = in_array($ft, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
                    $iconClass = 'fa-file-alt'; $colorClass = 'primary';
                    if($isPdf) { $iconClass='fa-file-pdf'; $colorClass='danger'; } elseif($isImg) { $iconClass='fa-image'; $colorClass='info'; }
                ?>
                <div class="col-md-3 col-xl-2">
                    <div class="file-card-modern p-4 text-center h-100">
                        <?php if(isset($f['version_number']) && $f['version_number'] > 1): ?>
                            <div class="position-absolute top-0 start-0 m-2" style="z-index: 5;">
                                <span class="badge bg-primary rounded-pill small">V<?= $f['version_number'] ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="file-icon-large text-<?= $colorClass ?> bg-<?= $colorClass ?> bg-opacity-10">
                            <i class="fas <?= $iconClass ?>"></i>
                        </div>
                        
                        <div class="fw-bold text-truncate mb-1 text-white fs-6" title="<?= htmlspecialchars($f['filename']) ?>"><?= htmlspecialchars($f['filename']) ?></div>
                        <small class="text-accent d-block mb-1 text-truncate" style="font-size:0.75rem"><i class="fas fa-layer-group me-1"></i> <?= htmlspecialchars($f['project_name']) ?></small>
                        <div class="small text-gray fw-medium"><?= date('M d, Y', strtotime($f['uploaded_at'])) ?></div>
                        
                        <!-- OVERLAY INTERACTIVO -->
                        <div class="file-overlay" tabindex="0">
                            <a href="preview.php?id=<?= $f['id'] ?>" class="overlay-action overlay-view <?= ($userRole === 'viewer') ? 'w-100' : 'w-50' ?>"><i class="fas fa-eye fa-lg mb-1"></i><span class="small fw-bold">View</span></a>
                            <?php if($userRole !== 'viewer'): ?><a href="editor.php?id=<?= $f['id'] ?>" class="overlay-action overlay-edit w-50"><i class="fas fa-pen-nib fa-lg mb-1"></i><span class="small fw-bold">Edit</span></a><?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        <?php elseif($viewTrash): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="text-danger mb-0"><i class="fas fa-trash-alt me-2"></i> Recycle Bin</h4>
                <button class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="selectAllTrash()">
                    <i class="fas fa-check-double me-2"></i> Select All
                </button>
            </div>

            <div class="row g-3">
                <?php if(empty($trashProjects) && empty($trashFiles) && empty($trashReports)): ?>
                    <div class="col-12 text-center text-gray py-5">Recycle bin is empty.</div>
                <?php endif; ?>
                <?php foreach($trashProjects as $tp): ?>
                    <div class="col-md-4">
                        <div class="box-card border-danger cursor-pointer" id="t-card-project-<?= $tp['id'] ?>" onclick="toggleTrashSelect('project', <?= $tp['id'] ?>)">
                            <div class="selection-check" id="t-check-project-<?= $tp['id'] ?>"></div>
                            <h5 class="text-white"><?= htmlspecialchars($tp['name']) ?> <small>(Project)</small></h5>
                            <small class="text-gray">Deleted: <?= $tp['deleted_at'] ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php foreach($trashFiles as $tf): ?>
                    <div class="col-md-3">
                        <div class="box-card border-danger text-center cursor-pointer" id="t-card-file-<?= $tf['id'] ?>" onclick="toggleTrashSelect('file', <?= $tf['id'] ?>)">
                            <div class="selection-check" id="t-check-file-<?= $tf['id'] ?>"></div>
                            <i class="fas fa-file fa-2x mb-2 text-danger"></i>
                            <h6 class="text-truncate"><?= htmlspecialchars($tf['filename']) ?></h6>
                            <small class="text-gray">Deleted: <?= $tf['deleted_at'] ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php foreach($trashReports as $tr): ?>
                    <div class="col-md-4">
                        <div class="box-card border-warning cursor-pointer" id="t-card-report-<?= $tr['id'] ?>" onclick="toggleTrashSelect('report', <?= $tr['id'] ?>)">
                            <div class="selection-check" id="t-check-report-<?= $tr['id'] ?>"></div>
                            <div class="d-flex align-items-center gap-2 mb-2"><i class="fas fa-clipboard-list text-warning"></i><span class="fw-bold text-white small">Report</span></div>
                            <div class="small text-gray mb-2">File: <span class="text-white"><?= htmlspecialchars($tr['filename'] ?? 'Unknown File') ?></span></div>
                            <div class="small text-gray mb-2">Tech: <span class="text-white"><?= htmlspecialchars($tr['technician_name']) ?></span></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="bulk-actions-bar" id="trash-bulk-bar">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-danger rounded-circle d-flex align-items-center justify-content-center" style="width:30px;height:30px;font-weight:bold" id="trash-bulk-count">0</div><span class="fw-bold">Selected</span>
                </div>
                <button class="btn btn-outline-success rounded-pill px-4 btn-sm" onclick="restoreSelected()"><i class="fas fa-trash-restore me-2"></i> Restore</button>
                <button class="btn btn-outline-danger rounded-pill px-4 btn-sm" onclick="hardDeleteSelected()"><i class="fas fa-fire me-2"></i> Delete Forever</button>
                <button class="btn btn-sm btn-outline-secondary border-0" onclick="clearTrashSelection()"><i class="fas fa-times"></i></button>
            </div>

        <?php else: ?>
            <?php $backUrl = ($viewLevel === 'folder') ? "index.php?project_id=$projectId" : "index.php"; ?>
            <div class="d-flex justify-content-between align-items-center mb-5">
                <div class="d-flex align-items-center gap-4"><a href="<?= $backUrl ?>" class="btn-back"><i class="fas fa-arrow-left"></i></a><div><h2 class="fw-bold mb-1"><?= htmlspecialchars($viewLevel === 'project' ? $project['name'] : $folder['name']) ?></h2><p class="text-gray mb-0">Project Files</p></div></div>
                <div class="d-flex gap-3 proj-actions">
                    <?php if($viewLevel === 'project' && $canCreate): ?><button class="btn btn-outline-light rounded-pill px-4 fw-bold" data-bs-toggle="modal" data-bs-target="#newFolderModal"><i class="fas fa-folder-plus me-2"></i> Folder</button><?php endif; ?>
                    <?php if($viewLevel === 'project' && $isAdmin): ?><button class="btn btn-outline-info rounded-pill px-4 fw-bold" onclick="openAssignUsersModal()"><i class="fas fa-user-plus me-2"></i> Assign Users</button><?php endif; ?>
                    <?php if($canUpload): ?><button class="btn-main" onclick="openUploadModal()"><i class="fas fa-cloud-upload-alt me-2"></i> Upload</button><?php endif; ?>
                </div>
            </div>

            <?php if(!empty($folders)): ?>
            <div class="row g-3 mb-5 folder-grid">
                <?php foreach($folders as $item): $isRep = ($item['name'] === 'Reports'); ?>
                <div class="col-md-3">
                    <div class="box-card p-3 d-flex align-items-center justify-content-between">
                        <a href="index.php?project_id=<?= $projectId . "&folder_id=" . $item['id'] ?>" class="d-flex align-items-center gap-3 w-100 folder-card">
                            <i class="fas <?= $isRep ? 'fa-clipboard-list text-success' : 'fa-folder folder-icon' ?> fa-2x"></i>
                            <span class="fw-bold fs-5 text-white"><?= htmlspecialchars($item['name']) ?></span>
                        </a>
                        <?php if(!$isRep && $canDelete): ?>
                            <a href="index.php?action=delete_folder&id=<?= $item['id'] ?>" class="text-danger opacity-25 hover-opacity-100 ms-2" onclick="event.preventDefault(); let url=this.href; appConfirm('Delete folder?', 'Confirm Deletion', () => { window.location.href = url; });"><i class="fas fa-trash"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-3"><h6 class="text-gray fw-bold small ls-1 mb-0">Files</h6><?php if(!empty($files) && $canDelete): ?><button class="btn btn-sm btn-outline-light rounded-pill px-3" onclick="selectAll()"><i class="fas fa-check-double me-2"></i> Select All</button><?php endif; ?></div>
            <div class="row g-3 file-grid">
                <?php foreach($files as $f): 
                    $ft = strtolower(pathinfo($f['filename'], PATHINFO_EXTENSION));
                    $isPdf = ($ft === 'pdf'); 
                    $isImg = in_array($ft, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
                    $folderLabel = !empty($f['folder_name']) ? $f['folder_name'] : 'Root';
                    $tileClass = 'file-gen'; 
                    $iconClass = 'fa-file';
                    if($isPdf) { $tileClass='pdf'; $iconClass='fa-file-pdf'; } elseif($isImg) { $tileClass='img'; $iconClass='fa-image'; }
                ?>
                <div class="col-md-3">
                    <div class="box-card text-center p-4 file-card-item" id="card-<?= $f['id'] ?>" <?= $canDelete ? "onclick=\"toggleSelect({$f['id']})\"" : '' ?>>
                        <?php if($canDelete): ?><div class="selection-check" id="check-<?= $f['id'] ?>"></div><?php endif; ?>
                        
                        <div class="file-tile <?= $tileClass ?>">
                            <i class="fas <?= $iconClass ?>"></i>
                            <?php if(isset($f['version_number']) && $f['version_number'] > 1): ?>
                                <span class="version-badge">V<?= $f['version_number'] ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <h6 class="fw-bold text-truncate w-100 mb-1"><?= htmlspecialchars($f['filename']) ?></h6>
                        <small class="text-accent d-block mb-3"><i class="fas fa-folder-open me-1"></i> <?= htmlspecialchars($folderLabel) ?></small>
                        <small class="text-gray d-block mb-3"><?= date('M d, Y', strtotime($f['uploaded_at'])) ?></small>
                        
                        <div class="d-flex justify-content-center gap-2" onclick="event.stopPropagation()">
                            <a href="preview.php?id=<?= $f['id'] ?>" class="btn-icon" title="View"><i class="fas fa-eye"></i></a>
                            
                            <?php if($userRole !== 'viewer'): ?>
                                <a href="editor.php?id=<?= $f['id'] ?>" class="btn-icon text-primary border-primary" title="Edit"><i class="fas fa-pen"></i></a>
                            <?php endif; ?>
                            
                            <?php if($canDelete): ?>
                                <button class="btn-icon text-warning border-warning" onclick="openMoveModal(<?= $f['id'] ?>, 'file')" title="Move"><i class="fas fa-exchange-alt"></i></button>
                                <button class="btn-icon text-danger border-danger" onclick="deleteFile(<?= $f['id'] ?>)" title="Delete"><i class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if(empty($files) && empty($folders)): ?><div class="col-12 py-5 text-center text-gray"><i class="fas fa-box-open fa-3x mb-3 opacity-25"></i><p>Empty Folder</p></div><?php endif; ?>
            </div>
            
            <?php if($canDelete): ?>
            <div class="bulk-actions-bar" id="bulk-bar">
                <div class="d-flex align-items-center gap-3"><div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width:30px;height:30px;font-weight:bold" id="bulk-count">0</div><span class="fw-bold">Selected</span></div>
                <button class="btn btn-outline-danger rounded-pill px-4 btn-sm" onclick="deleteBulk()"><i class="fas fa-trash-alt me-2"></i> Move to Trash</button>
                <button class="btn btn-sm btn-outline-secondary border-0" onclick="clearSelection()"><i class="fas fa-times"></i></button>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

</div> <?php include __DIR__ . '/../views/modals.php'; ?>

<?php if($viewLevel === 'project' && $isAdmin): ?>
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
                    <div class="border rounded p-2" style="max-height:200px; overflow:auto;">
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

<script>
    // Variables Globales PHP -> JS
    const pId = <?= json_encode($projectId) ?>;
    const fId = <?= json_encode($folderId) ?>;
    const allFiles = <?= !empty($files) ? json_encode(array_column($files, 'id')) : '[]' ?>;
    const canUpload = <?= $canUpload ? 'true' : 'false' ?>;
    
    // Variables de Papelera para Select All
    const isTrashView = <?= $viewTrash ? 'true' : 'false' ?>;
    const trashProjects = <?= $viewTrash ? json_encode(array_column($trashProjects, 'id')) : '[]' ?>;
    const trashFiles = <?= $viewTrash ? json_encode(array_column($trashFiles, 'id')) : '[]' ?>;
    const trashReports = <?= $viewTrash ? json_encode(array_column($trashReports, 'id')) : '[]' ?>;


    // ==========================================
    // 1. DRAG & DROP PROFESIONAL
    // ==========================================
    // (Asumiendo que dropOverlay existe en header o footer)
    const dropOverlay = document.getElementById('drop-overlay'); 
    let dragCounter = 0; 

    if (canUpload && pId && dropOverlay) {
        window.addEventListener('dragenter', (e) => {
            e.preventDefault();
            if (e.dataTransfer.types && e.dataTransfer.types.includes('Files')) {
                dragCounter++;
                dropOverlay.style.display = 'flex';
            }
        });
        window.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dragCounter--;
            if (dragCounter <= 0) { dropOverlay.style.display = 'none'; dragCounter = 0; }
        });
        window.addEventListener('dragover', (e) => { e.preventDefault(); });
        window.addEventListener('drop', (e) => {
            e.preventDefault(); dragCounter = 0; dropOverlay.style.display = 'none';
            if (e.dataTransfer.files.length > 0) handleFiles(e.dataTransfer.files);
        });
    }

    // Input Manual
    function openUploadModal() { if(canUpload) document.getElementById('globalFileInput').click(); }
    const gInput = document.getElementById('globalFileInput');
    if(gInput) gInput.addEventListener('change', function() { handleFiles(this.files); });

    // Procesador de Subida
    function uploadFileWithProgress(fd, onProgress) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '../api/api.php', true);
            xhr.onreadystatechange = () => {
                if (xhr.readyState !== 4) return;
                try { resolve(JSON.parse(xhr.responseText || '{}')); }
                catch (e) { reject(e); }
            };
            xhr.onerror = reject;
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable && typeof onProgress === 'function') {
                    onProgress(Math.round((e.loaded / e.total) * 100));
                }
            });
            xhr.send(fd);
        });
    }

    function setBulkOverlayState(title, detail, done, total, pct) {
        const overlay = document.getElementById('bulk-import-overlay');
        const statusTitle = document.getElementById('bulk-status-title');
        const statusDetail = document.getElementById('bulk-status-detail');
        const progressBar = document.getElementById('bulk-progress-bar');
        const statusCount = document.getElementById('bulk-status-count');
        if (overlay) overlay.style.display = 'flex';
        if (statusTitle) statusTitle.textContent = title || 'Uploading...';
        if (statusDetail) statusDetail.textContent = detail || 'Please wait...';
        if (progressBar) progressBar.style.width = Math.max(0, Math.min(100, pct || 0)) + '%';
        if (statusCount) statusCount.textContent = `${done || 0} / ${total || 0} files`;
    }

    async function handleFiles(fileList) {
        if(fileList.length === 0) return;
        if(!pId) { appAlert("Error: No project selected.", "Missing Project", "error"); return; }

        const btnUp = document.querySelector('.btn-main');
        const oldBtnHtml = btnUp ? btnUp.innerHTML : '';
        if(btnUp) { btnUp.disabled = true; btnUp.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...'; }

        const MAX_SIZE = 1073741824; // 1GB
        let done = 0;
        const total = fileList.length;
        const errors = [];

        for (let i = 0; i < fileList.length; i++) {
            const file = fileList[i];

            if (file.size > MAX_SIZE) {
                errors.push(`"${file.name}": exceeds 1GB limit`);
                done++;
                setBulkOverlayState('Uploading files...', `Skipping ${file.name}`, done, total, Math.round((done / total) * 100));
                continue;
            }

            const fd = new FormData();
            fd.append('action', 'upload_file');
            fd.append('file', file);
            fd.append('project_id', pId);
            if(fId) fd.append('folder_id', fId);

            setBulkOverlayState('Uploading files...', `Uploading ${file.name}`, done, total, Math.round((done / total) * 100));

            try {
                const data = await uploadFileWithProgress(fd, (percent) => {
                    const base = done / total;
                    const step = (percent / 100) / total;
                    setBulkOverlayState('Uploading files...', `Uploading ${file.name} (${percent}%)`, done, total, Math.round((base + step) * 100));
                });
                if(data.status !== 'success') {
                    errors.push(`"${file.name}": ${data.msg || 'upload failed'}`);
                }
            } catch (e) {
                console.error(e);
                errors.push(`"${file.name}": connection/upload failed`);
            }

            done++;
            setBulkOverlayState('Uploading files...', `Processed ${file.name}`, done, total, Math.round((done / total) * 100));
        }

        const overlay = document.getElementById('bulk-import-overlay');
        if (overlay) overlay.style.display = 'none';
        if(btnUp) { btnUp.disabled = false; btnUp.innerHTML = oldBtnHtml; }

        if (errors.length) {
            appAlert(`Upload completed with ${errors.length} error(s):<br><small>${errors.slice(0,6).join('<br>')}</small>`, 'Upload Complete (with errors)', 'warning');
        } else {
            appAlert(`${total} file(s) uploaded successfully.`, 'Upload Complete', 'success');
        }
        setTimeout(() => location.reload(), 1200);
    }


    // ==========================================
    // 2. LOGICA MOVER ARCHIVOS
    // ==========================================
    function openMoveModal(id, type) {
        document.getElementById('move_id').value = id;
        document.getElementById('move_type').value = type;
        const projSelect = document.getElementById('move_project_select');
        const folderSelect = document.getElementById('move_folder_select');
        
        projSelect.innerHTML = '<option value="">Loading projects...</option>';
        folderSelect.innerHTML = '<option value="">Root Folder</option>';

        const modalEl = document.getElementById('moveFileModal');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();

        const fd = new FormData(); fd.append('action', 'get_projects_list');
        fetch('../api/api.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if(res.status === 'success') {
                    projSelect.innerHTML = '<option value="">Select Target Project...</option>';
                    res.data.forEach(p => { projSelect.innerHTML += `<option value="${p.id}">${p.name}</option>`; });
                } else { projSelect.innerHTML = '<option value="">Error loading</option>'; }
            })
            .catch(err => { projSelect.innerHTML = '<option value="">Connection Error</option>'; });
    }

    function loadFoldersForMove(projId) {
        const folderSel = document.getElementById('move_folder_select');
        folderSel.innerHTML = '<option value="">Loading...</option>';
        if(!projId) { folderSel.innerHTML = '<option value="">Root Folder</option>'; return; }

        const fd = new FormData(); fd.append('action', 'get_folders_list'); fd.append('project_id', projId);
        fetch('../api/api.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                folderSel.innerHTML = '<option value="">Root Folder (No specific folder)</option>';
                if(res.status === 'success') { res.data.forEach(f => { folderSel.innerHTML += `<option value="${f.id}">${f.name}</option>`; }); }
            });
    }

    const moveForm = document.getElementById('moveFileForm');
    if(moveForm) {
        moveForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Moving...'; btn.disabled = true;

            const fd = new FormData(this);
            fetch('../api/api.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if(d.status === 'success') location.reload();
                    else { appAlert("Error moving item: " + d.msg, "Error", "error"); btn.innerHTML = originalText; btn.disabled = false; }
                })
                .catch(e => { appAlert("Connection error", "Error", "error"); btn.innerHTML = originalText; btn.disabled = false; });
        });
    }

    // ==========================================
    // 3. SELECCION Y BULK ACTIONS
    // ==========================================
    let selectedIds = new Set();
    function toggleSelect(id) { 
        const c = document.getElementById(`card-${id}`); 
        if(selectedIds.has(id)) { selectedIds.delete(id); c.classList.remove('selected'); } 
        else { selectedIds.add(id); c.classList.add('selected'); } 
        updateBulkUI(); 
    }
    
    function selectAll() { 
        const all = allFiles.length > 0 && allFiles.every(id => selectedIds.has(id)); 
        if(all) clearSelection(); 
        else { allFiles.forEach(id => { selectedIds.add(id); document.getElementById(`card-${id}`)?.classList.add('selected'); }); updateBulkUI(); } 
    }
    
    function updateBulkUI() { 
        const b = document.getElementById('bulk-bar'); 
        if(b) { if(selectedIds.size>0) { b.classList.add('visible'); document.getElementById('bulk-count').innerText=selectedIds.size; } else b.classList.remove('visible'); } 
    }
    
    function clearSelection() { 
        selectedIds.forEach(id => document.getElementById(`card-${id}`)?.classList.remove('selected')); 
        selectedIds.clear(); updateBulkUI(); 
    }
    
    function deleteBulk() {
        appConfirm(`Move ${selectedIds.size} files to Recycle Bin?`, "Move to Trash", () => {
            const ids = Array.from(selectedIds); 
            const fd = new FormData(); fd.append('action', 'delete_bulk'); fd.append('ids', JSON.stringify(ids));
            fetch('../api/api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(d => { if(d.status === 'success') location.reload(); });
        });
    }

    function deleteFile(id) {
        appConfirm("Move to Recycle Bin?", "Delete File", () => {
            const fd = new FormData(); fd.append('action', 'delete_entity'); fd.append('type', 'file'); fd.append('id', id);
            fetch('../api/api.php', { method:'POST', body:fd }).then(r=>r.json()).then(d=>{ if(d.status==='success') location.reload(); });
        });
    }

    // --- PAPELERA ---
    let selectedTrash = new Set();
    function toggleTrashSelect(type, id) {
        const compositeId = `${type}_${id}`; const cardId = `t-card-${type}-${id}`; const cardEl = document.getElementById(cardId);
        if (selectedTrash.has(compositeId)) { selectedTrash.delete(compositeId); if(cardEl) cardEl.classList.remove('selected'); } 
        else { selectedTrash.add(compositeId); if(cardEl) cardEl.classList.add('selected'); }
        updateTrashBulkUI();
    }
    
    function updateTrashBulkUI() { const bar = document.getElementById('trash-bulk-bar'); const count = document.getElementById('trash-bulk-count'); if (bar && count) { if (selectedTrash.size > 0) { bar.classList.add('visible'); count.innerText = selectedTrash.size; } else { bar.classList.remove('visible'); } } }
    
    function clearTrashSelection() { selectedTrash.forEach(comp => { const [type, id] = comp.split('_'); const el = document.getElementById(`t-card-${type}-${id}`); if(el) el.classList.remove('selected'); }); selectedTrash.clear(); updateTrashBulkUI(); }
    
    // NUEVA FUNCION: SELECT ALL EN PAPELERA
    function selectAllTrash() {
        const totalItems = trashProjects.length + trashFiles.length + trashReports.length;
        if (totalItems === 0) return;

        // Si ya está todo seleccionado, deseleccionar
        if (selectedTrash.size === totalItems) {
            clearTrashSelection();
            return;
        }

        // Seleccionar todo
        trashProjects.forEach(id => {
            const comp = `project_${id}`;
            selectedTrash.add(comp);
            document.getElementById(`t-card-project-${id}`)?.classList.add('selected');
        });
        trashFiles.forEach(id => {
            const comp = `file_${id}`;
            selectedTrash.add(comp);
            document.getElementById(`t-card-file-${id}`)?.classList.add('selected');
        });
        trashReports.forEach(id => {
            const comp = `report_${id}`;
            selectedTrash.add(comp);
            document.getElementById(`t-card-report-${id}`)?.classList.add('selected');
        });

        updateTrashBulkUI();
    }
    
    async function restoreSelected() { 
        appConfirm(`Restore ${selectedTrash.size} items?`, "Restore Items", async () => {
            const promises = Array.from(selectedTrash).map(comp => { const [type, id] = comp.split('_'); const fd = new FormData(); fd.append('action', 'restore_entity'); fd.append('type', type); fd.append('id', id); return fetch('../api/api.php', { method: 'POST', body: fd }); }); 
            try { await Promise.all(promises); location.reload(); } catch (e) { appAlert("Error processing request", "Error", "error"); } 
        });
    }
    
    async function hardDeleteSelected() { 
        appConfirm(`WARNING: This will permanently delete ${selectedTrash.size} items.`, "Delete Forever", async () => {
            const promises = Array.from(selectedTrash).map(comp => { const [type, id] = comp.split('_'); const fd = new FormData(); fd.append('action', 'hard_delete_entity'); fd.append('type', type); fd.append('id', id); return fetch('../api/api.php', { method: 'POST', body: fd }); }); 
            try { await Promise.all(promises); location.reload(); } catch (e) { appAlert("Error processing request", "Error", "error"); } 
        });
    }

    async function handleBulkFolderImport(input) {
        const files = Array.from(input.files || []);
        input.value = '';
        if (!files.length) return;

        const projectMap = {};
        files.forEach(file => {
            const parts = (file.webkitRelativePath || '').split('/');
            if (parts.length < 2) return;
            const projectName = parts[0];
            if (!projectMap[projectName]) projectMap[projectName] = [];
            projectMap[projectName].push({ file, parts });
        });

        const projectNames = Object.keys(projectMap);
        if (!projectNames.length) {
            appAlert('No valid files found. Make sure your folders have at least one subfolder with files.', 'Empty Selection', 'warning');
            return;
        }

        const totalFiles = files.length;
        appConfirm(`Import ${projectNames.length} project(s) with ${totalFiles} file(s)?<br><small class="text-gray">${projectNames.join(', ')}</small>`, 'Bulk Import', async () => {
            const overlay = document.getElementById('bulk-import-overlay');
            const statusTitle = document.getElementById('bulk-status-title');
            const statusDetail = document.getElementById('bulk-status-detail');
            const progressBar = document.getElementById('bulk-progress-bar');
            const statusCount = document.getElementById('bulk-status-count');
            overlay.style.display = 'flex';

            const errors = [];
            let doneFiles = 0;

            for (const [projectName, projectFiles] of Object.entries(projectMap)) {
                statusTitle.textContent = `Creating project: ${projectName}`;
                statusDetail.textContent = 'Setting up project...';

                const fdProj = new FormData();
                fdProj.append('action', 'create_project_bulk');
                fdProj.append('name', projectName);

                let projectId = null;
                try {
                    const projRes = await fetch('../api/api.php', { method: 'POST', body: fdProj }).then(r => r.json());
                    if (projRes.status !== 'success') {
                        errors.push(`Project "${projectName}": ${projRes.msg || 'create failed'}`);
                        continue;
                    }
                    projectId = projRes.project_id;
                } catch (e) {
                    errors.push(`Project "${projectName}": connection error`);
                    continue;
                }

                const folderCache = {};
                for (const { file, parts } of projectFiles) {
                    const fileName = parts[parts.length - 1];
                    const folderParts = parts.slice(1, -1);

                    doneFiles++;
                    const pct = Math.round((doneFiles / totalFiles) * 100);
                    progressBar.style.width = pct + '%';
                    statusCount.textContent = `${doneFiles} / ${totalFiles} files`;
                    statusDetail.textContent = `${projectName} → ${folderParts.join('/')} / ${fileName}`;

                    let parentId = null;
                    let folderId = null;
                    const pathSoFar = [];

                    for (const folderName of folderParts.slice(0, 4)) {
                        pathSoFar.push(folderName);
                        const cacheKey = pathSoFar.join('/');
                        if (!folderCache[cacheKey]) {
                            const fdF = new FormData();
                            fdF.append('action', 'create_folder');
                            fdF.append('project_id', projectId);
                            fdF.append('name', folderName);
                            if (parentId) fdF.append('parent_id', parentId);
                            try {
                                const fRes = await fetch('../api/api.php', { method: 'POST', body: fdF }).then(r => r.json());
                                folderCache[cacheKey] = fRes.folder_id || null;
                            } catch (e) {
                                folderCache[cacheKey] = null;
                            }
                        }
                        parentId = folderCache[cacheKey];
                        folderId = parentId;
                    }

                    const fdFile = new FormData();
                    fdFile.append('action', 'upload_file');
                    fdFile.append('project_id', projectId);
                    if (folderId) fdFile.append('folder_id', folderId);
                    fdFile.append('file', file, fileName);

                    try {
                        const upRes = await fetch('../api/api.php', { method: 'POST', body: fdFile }).then(r => r.json());
                        if (upRes.status !== 'success') {
                            errors.push(`File "${fileName}": ${upRes.msg || 'upload failed'}`);
                        }
                    } catch (e) {
                        errors.push(`File "${fileName}": upload failed`);
                    }
                }
            }

            overlay.style.display = 'none';
            if (errors.length) {
                appAlert(`Import finished with ${errors.length} error(s):<br><small>${errors.slice(0,5).join('<br>')}</small>`, 'Import Complete (with errors)', 'warning');
            } else {
                appAlert(`${projectNames.length} project(s) imported successfully with ${totalFiles} file(s)!`, 'Import Complete', 'success');
            }
            setTimeout(() => location.reload(), 1500);
        });
    }

    // --- ASIGNAR USUARIOS A PROYECTO ---
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
</script>

<?php include __DIR__ . '/../views/footer.php'; ?>
