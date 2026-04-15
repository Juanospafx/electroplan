<?php
// pages/project_dashboard.php
// CORRECCIÓN: Agregado "/.." en las rutas para salir de 'pages' y encontrar 'core'
require_once __DIR__ . '/../core/auth/session.php';
require_once __DIR__ . '/../core/db/connection.php';

$projectId = $_GET['id'] ?? 0;

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
$foldersStmt = $pdo->prepare("SELECT * FROM folders WHERE project_id = ? AND deleted_at IS NULL ORDER BY name ASC");
$foldersStmt->execute([$projectId]);
$allFolders = $foldersStmt->fetchAll(PDO::FETCH_ASSOC);

// Priorizar carpetas principales (con colores) al principio de la lista
$specialFolders = ['bom', 'drawings', 'labor record', 'photos', 'rfi'];
usort($allFolders, function($a, $b) use ($specialFolders) {
    $aName = strtolower($a['name']);
    $bName = strtolower($b['name']);
    $aIsSpecial = 0; $bIsSpecial = 0;
    foreach($specialFolders as $sf) { if(strpos($aName, $sf) !== false) { $aIsSpecial = 1; break; } }
    foreach($specialFolders as $sf) { if(strpos($bName, $sf) !== false) { $bIsSpecial = 1; break; } }
    if ($aIsSpecial !== $bIsSpecial) return $bIsSpecial - $aIsSpecial;
    return strcmp($aName, $bName);
});

// 3. Consulta de Estadísticas Rápidas (Para el Summary)
$fileCount = $pdo->query("SELECT COUNT(*) FROM files WHERE project_id = $projectId AND deleted_at IS NULL")->fetchColumn();
$lastActivity = $pdo->query("SELECT uploaded_at FROM files WHERE project_id = $projectId ORDER BY uploaded_at DESC LIMIT 1")->fetchColumn();
$recentFiles = $pdo->prepare("SELECT id, filename, uploaded_at FROM files WHERE project_id = ? AND deleted_at IS NULL ORDER BY uploaded_at DESC LIMIT 6");
$recentFiles->execute([$projectId]);
$recentFiles = $recentFiles->fetchAll(PDO::FETCH_ASSOC);

$userName = $_SESSION['username'] ?? 'User';
$userRole = $_SESSION['role'] ?? 'viewer';

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
                <i class="fas fa-chevron-right mx-2" style="font-size:0.7rem"></i>
                <a href="projects.php">Projects</a>
                <i class="fas fa-chevron-right mx-2" style="font-size:0.7rem"></i>
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
                <span class="badge bg-success bg-opacity-25 text-success px-3 py-1 rounded-pill border border-success border-opacity-25"><?= $project['status'] ?></span>
            </div>
            <div class="d-flex flex-wrap gap-4 text-gray small mb-3">
                <span><i class="fas fa-map-marker-alt me-1 text-accent"></i> <?= htmlspecialchars($projectAddress ?: 'No address specified') ?></span>
                <span><i class="fas fa-building me-1 text-warning"></i> <?= htmlspecialchars($projectCompanyName ?: 'No Company') ?></span>
                <span><i class="fas fa-user-hard-hat me-1 text-primary"></i> <?= htmlspecialchars($projectContactName ?: 'No Contact') ?></span>
                <span><i class="fas fa-calendar-alt me-1 text-success"></i> <?= $project['date_started'] ? date('M d, Y', strtotime($project['date_started'])) : 'TBD' ?></span>
            </div>
            <p class="project-desc-expandable m-0" onclick="this.classList.toggle('expanded')" title="Click to read more">
                <?= htmlspecialchars($projectNotes ?: 'No description provided for this project.') ?>
            </p>
        </div>
        <div class="d-flex gap-2 flex-shrink-0 align-items-start">
            <button class="btn btn-main rounded-pill px-4 shadow-sm" onclick="openUploadModal()"><i class="fas fa-cloud-upload-alt me-2"></i> Upload File</button>
            
            <button class="btn btn-tools rounded-pill px-4 py-2 shadow-sm" onclick="openToolsModal()"><i class="fas fa-toolbox me-2"></i> Tools</button>
            <?php if($_SESSION['role'] === 'admin'): ?>
            <div class="dropdown">
                <button class="btn btn-outline-light d-flex align-items-center justify-content-center" data-bs-toggle="dropdown" aria-expanded="false" style="width: 42px; height: 42px; border-radius: 50%; padding: 0;"><i class="fas fa-ellipsis-v"></i></button>
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
                    <div class="file-card-modern p-4 text-center h-100">
                        
                        <div class="file-icon-large text-<?= $colorClass ?> bg-<?= $colorClass ?> bg-opacity-10">
                            <i class="fas <?= $iconClass ?>"></i>
                        </div>
                        
                        <div class="fw-bold text-truncate mb-1 text-white fs-5" title="<?= htmlspecialchars($rf['filename']) ?>"><?= htmlspecialchars($rf['filename']) ?></div>
                        <div class="small text-gray fw-medium"><?= date('M d, Y', strtotime($rf['uploaded_at'])) ?></div>
                        
                        <!-- OVERLAY INTERACTIVO -->
                        <div class="file-overlay" tabindex="0">
                            <?php if($_SESSION['role'] === 'admin'): ?>
                            <div class="position-absolute top-0 end-0 p-2 d-flex gap-2" style="z-index: 20;">
                                <button class="overlay-mini-btn move" onclick="event.stopPropagation(); event.preventDefault(); openMoveModal(<?= $rf['id'] ?>)" title="Move File"><i class="fas fa-exchange-alt"></i></button>
                                <button class="overlay-mini-btn delete" onclick="event.stopPropagation(); event.preventDefault(); deleteFile(<?= $rf['id'] ?>)" title="Delete File"><i class="fas fa-trash"></i></button>
                            </div>
                            <?php endif; ?>
                            
                            <a href="preview.php?id=<?= $rf['id'] ?>" class="overlay-action overlay-view <?= ($_SESSION['role'] === 'viewer') ? 'w-100' : 'w-50' ?>">
                                <i class="fas fa-eye fa-2x mb-2"></i><span class="fw-bold">View</span>
                            </a>
                            <?php if($_SESSION['role'] !== 'viewer'): ?>
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
                $iconColorClass = 'warning'; // Por defecto amarillo
                if (strpos($folderNameLower, 'bom') !== false) { $iconColorClass = 'success'; } 
                elseif (strpos($folderNameLower, 'drawings') !== false) { $iconColorClass = 'primary'; } 
                elseif (strpos($folderNameLower, 'labor record') !== false) { $iconColorClass = 'purple'; } 
                elseif (strpos($folderNameLower, 'photos') !== false) { $iconColorClass = 'danger'; } 
                elseif (strpos($folderNameLower, 'rfi') !== false) { $iconColorClass = 'success'; }
            ?>
                <div class="col-md-4 col-xl-3">
                    <div class="folder-card-dash">
                        <a href="?id=<?= $projectId ?>&view=files&folder_id=<?= $folder['id'] ?>" class="d-flex align-items-center gap-3 text-decoration-none w-100">
                            <div class="bg-<?= $iconColorClass ?> bg-opacity-10 p-2 rounded text-<?= $iconColorClass ?>">
                                <i class="fas fa-folder fa-lg"></i>
                            </div>
                            <div class="text-white fw-bold text-truncate fs-6"><?= htmlspecialchars($folder['name']) ?></div>
                        </a>
                        <?php if($_SESSION['role'] === 'admin' && $folder['name'] !== 'Reports'): ?>
                            <div class="dropdown ms-2">
                                <button class="btn btn-sm border-0 btn-folder-menu" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v fa-lg"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end bg-card border-secondary shadow-lg rounded-3 py-1">
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
            $currFolder = array_filter($allFolders, fn($f) => $f['id'] == $currentFolderId);
            $folderName = !empty($currFolder) ? reset($currFolder)['name'] : "Unknown Folder";
        }
    ?>
        <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom border-secondary">
            <div class="d-flex align-items-center gap-3">
                <a href="?id=<?= $projectId ?>&view=summary" class="btn btn-icon rounded-circle"><i class="fas fa-arrow-left"></i></a>
                <h4 class="fw-bold mb-0 text-white"><i class="fas fa-folder-open text-warning me-2"></i> <?= htmlspecialchars($folderName) ?></h4>
            </div>
            <span class="badge bg-secondary rounded-pill px-3"><?= count($files) ?> files</span>
        </div>

        <?php if(empty($files)): ?>
            <div class="text-center py-5">
                <i class="fas fa-cloud-upload-alt fa-3x text-gray mb-3 opacity-25"></i>
                <p class="text-gray">This folder is empty.</p>
                <button class="btn btn-outline-primary rounded-pill" onclick="openUploadModal()">Upload Here</button>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach($files as $f): 
                     $ft = strtolower(pathinfo($f['filename'], PATHINFO_EXTENSION));
                     $iconClass = 'fa-file-alt'; $colorClass = 'primary';
                     if($ft === 'pdf') { $iconClass='fa-file-pdf'; $colorClass='danger'; } elseif(in_array($ft, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) { $iconClass='fa-image'; $colorClass='info'; }
                ?>
                <div class="col-md-3 col-xl-2">
                    <div class="file-card-modern p-4 text-center h-100">
                        
                        <div class="file-icon-large text-<?= $colorClass ?> bg-<?= $colorClass ?> bg-opacity-10">
                            <i class="fas <?= $iconClass ?>"></i>
                        </div>
                        
                        <div class="fw-bold text-truncate mb-1 text-white fs-6" title="<?= htmlspecialchars($f['filename']) ?>"><?= htmlspecialchars($f['filename']) ?></div>
                        <div class="small text-gray fw-medium"><?= date('M d, Y', strtotime($f['uploaded_at'])) ?></div>
                        
                        <!-- OVERLAY INTERACTIVO -->
                        <div class="file-overlay" tabindex="0">
                            <?php if($_SESSION['role'] === 'admin'): ?>
                            <div class="position-absolute top-0 end-0 p-2 d-flex gap-2" style="z-index: 20;">
                                <button class="overlay-mini-btn move" onclick="event.stopPropagation(); event.preventDefault(); openMoveModal(<?= $f['id'] ?>)" title="Move File"><i class="fas fa-exchange-alt"></i></button>
                                <button class="overlay-mini-btn delete" onclick="event.stopPropagation(); event.preventDefault(); deleteFile(<?= $f['id'] ?>)" title="Delete File"><i class="fas fa-trash"></i></button>
                            </div>
                            <?php endif; ?>
                            
                            <a href="preview.php?id=<?= $f['id'] ?>" class="overlay-action overlay-view <?= ($_SESSION['role'] === 'viewer') ? 'w-100' : 'w-50' ?>">
                                <i class="fas fa-eye fa-lg mb-1"></i><span class="small fw-bold">View</span>
                            </a>
                            <?php if($_SESSION['role'] !== 'viewer'): ?>
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

    body.theme-light .bg-dark { background-color: var(--bg-input) !important; color: var(--text-white) !important; border-color: var(--border-subtle) !important; }
    body.theme-light .text-white { color: var(--text-white) !important; }

    .box-card { background: var(--bg-card); border-radius: var(--radius-box); border: 1px solid var(--border-subtle); transition: 0.3s; }
    .box-card:hover { transform: translateY(-3px); border-color: var(--primary); }

    .btn-main { background-color: var(--primary) !important; border-color: var(--primary) !important; color: white !important; transition: 0.2s; font-weight: 600; }
    .btn-main:hover { background-color: var(--primary-hover) !important; border-color: var(--primary-hover) !important; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(251, 90, 58, 0.3); }

    .btn-tools { background-color: var(--color-blue) !important; border-color: var(--color-blue) !important; color: white !important; transition: 0.2s; font-weight: 600; }
    .btn-tools:hover { filter: brightness(1.1); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); color: white !important; }

    .text-purple { color: var(--color-purple) !important; }
    .bg-purple { background-color: rgba(139, 92, 246, var(--bs-bg-opacity, 1)) !important; }

    .text-info { color: var(--color-blue) !important; }

    .btn-outline-light { border-color: var(--border-subtle); color: var(--text-gray); }
    .btn-outline-light:hover { background: var(--bg-input); color: var(--primary); border-color: var(--primary); }
    
    .btn-outline-secondary, .btn-outline-info, .btn-outline-primary, .btn-outline-warning, .btn-outline-danger { transition: 0.2s; }

    .btn-icon { width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--border-subtle); color: var(--text-gray); transition: 0.2s; background: var(--bg-card); text-decoration: none; }
    .btn-icon:hover { background: var(--primary); color: white; border-color: var(--primary); }
    .btn-icon.border-primary { color: var(--primary); border-color: var(--primary); }
    .btn-icon.border-primary:hover { background: var(--primary); color: white; }
    .btn-icon.border-danger { color: #ef4444; border-color: #ef4444; }
    .btn-icon.border-danger:hover { background: #ef4444; color: white; }

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
        color: white !important;
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
        background: rgba(0,0,0,0.5); color: white;
        border: none; display: inline-flex; align-items: center; justify-content: center;
        transition: 0.2s; backdrop-filter: blur(2px);
    }
    .overlay-mini-btn.move:hover { background: var(--color-amber); transform: scale(1.1); }
    .overlay-mini-btn.delete:hover { background: #ef4444; transform: scale(1.1); }

    @media (max-width: 992px) {
        .project-title-large { font-size: 1.8rem; }
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
                <h5 class="modal-title fw-bold"><i class="fas fa-toolbox me-2 text-accent"></i>Project Tools</h5>
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
                                        <div class="fw-bold text-white"><?= htmlspecialchars($tool['name']) ?></div>
                                        <div class="small text-gray"><?= htmlspecialchars($tool['desc']) ?></div>
                                    </div>
                                </div>
                                <?php if(!$isActiveTool): ?>
                                    <span class="badge bg-secondary">Coming Soon</span>
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
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Upload File</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="uploadFileForm">
                <div class="modal-body">
                    <label class="text-gray small mb-2">Select File</label>
                    <input type="file" name="file" id="upload_file_input" class="form-control mb-3" required>

                    <label class="text-gray small mb-2">Select Folder</label>
                    <select name="folder_id" id="upload_folder_select" class="form-select text-white bg-dark border-secondary" required>
                        <option value="">Select a folder...</option>
                        <?php foreach($allFolders as $folder): ?>
                            <option value="<?= (int)$folder['id'] ?>"><?= htmlspecialchars($folder['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if(empty($allFolders)): ?>
                        <div class="text-muted small mt-2">No folders available. Create a folder first.</div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn-main w-100">Upload</button>
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
                    <div class="text-muted small mt-2">Select a parent folder to create a subfolder with the current name.</div>
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

<input type="file" id="projectUploadInput" class="d-none">

<script>
    const pId = <?= $projectId ?>;
    const fId = <?= $currentFolderId ?? 'null' ?>;

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

    function openUploadModal() {
        if (fId) {
            const input = document.getElementById('projectUploadInput');
            if (input) input.click();
            return;
        }
        const modalEl = document.getElementById('uploadFileModal');
        if (modalEl) new bootstrap.Modal(modalEl).show();
    }

    const projectUploadInput = document.getElementById('projectUploadInput');
    if (projectUploadInput) {
        projectUploadInput.addEventListener('change', function() {
            if (!this.files || this.files.length === 0) return;
            if (!fId) return;
            const fd = new FormData();
            fd.append('action', 'upload_file');
            fd.append('project_id', pId);
            if (fId) fd.append('folder_id', fId);
            fd.append('file', this.files[0]);
            fetch('../api/api.php', { method:'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.status === 'success') location.reload();
                    else alert('Error uploading file: ' + (d.msg || 'Unknown'));
                })
                .catch(() => alert('Connection error'));
        });
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
        uploadFileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const fileInput = document.getElementById('upload_file_input');
            const folderSelect = document.getElementById('upload_folder_select');
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                alert('Please select a file.');
                return;
            }
            if (!folderSelect || !folderSelect.value) {
                alert('Please select a folder.');
                return;
            }
            const fd = new FormData();
            fd.append('action', 'upload_file');
            fd.append('project_id', pId);
            fd.append('folder_id', folderSelect.value);
            fd.append('file', fileInput.files[0]);
            const modalEl = document.getElementById('uploadFileModal');
            if (modalEl) {
                const inst = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                inst.hide();
            }
            uploadWithProgress(fd)
                .then(d => {
                    if (d.status === 'success') {
                        hideUploadProgress(800);
                        location.reload();
                    } else {
                        hideUploadProgress(1500);
                        alert('Error uploading file: ' + (d.msg || 'Unknown'));
                    }
                })
                .catch(() => {
                    hideUploadProgress(1500);
                    alert('Upload failed. The file may still finish uploading in the background.');
                });
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
                alert('At least one admin must be assigned to the project.');
                return;
            }
            const fd = new FormData(this);
            fetch('../api/api.php', { method:'POST', body:fd })
                .then(r => r.json())
                .then(d => {
                    if (d.status === 'success') location.reload();
                    else alert('Error assigning users: ' + (d.msg || 'Unknown'));
                })
                .catch(() => alert('Connection error'));
        });
    }

    function deleteFile(id) {
        if(!confirm("Move file to Recycle Bin?")) return;
        const fd = new FormData();
        fd.append('action', 'delete_entity');
        fd.append('type', 'file');
        fd.append('id', id);
        fetch('../api/api.php', { method:'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if(d.status === 'success') location.reload();
                else alert('Error deleting file: ' + (d.msg || 'Unknown'));
            })
            .catch(() => alert('Connection error'));
    }

    function deleteFolder(id) {
        if(!confirm("Move folder to Recycle Bin?")) return;
        const fd = new FormData();
        fd.append('action', 'delete_entity');
        fd.append('type', 'folder');
        fd.append('id', id);
        fetch('../api/api.php', { method:'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if(d.status === 'success') location.reload();
                else alert('Error deleting folder: ' + (d.msg || 'Unknown'));
            })
            .catch(() => alert('Connection error'));
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
                    else alert('Error moving file: ' + (d.msg || 'Unknown'));
                })
                .catch(() => alert('Connection error'));
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
                    else alert('Error moving folder: ' + (d.msg || 'Unknown'));
                })
                .catch(() => alert('Connection error'));
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
</script>

<?php include __DIR__ . '/../views/footer.php'; ?>
