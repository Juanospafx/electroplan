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

// 2. Consulta de Carpetas (Para el menú lateral y la vista de archivos)
$foldersStmt = $pdo->prepare("SELECT * FROM folders WHERE project_id = ? AND deleted_at IS NULL ORDER BY name ASC");
$foldersStmt->execute([$projectId]);
$allFolders = $foldersStmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Consulta de Estadísticas Rápidas (Para el Summary)
$fileCount = $pdo->query("SELECT COUNT(*) FROM files WHERE project_id = $projectId AND deleted_at IS NULL")->fetchColumn();
$lastActivity = $pdo->query("SELECT uploaded_at FROM files WHERE project_id = $projectId ORDER BY uploaded_at DESC LIMIT 1")->fetchColumn();

// CORRECCIÓN: Agregado "/.." para encontrar las vistas
include __DIR__ . '/../views/header.php'; 
?>

<div class="main-content d-flex flex-column h-100">
    
    <div class="bg-header border-bottom border-secondary p-4 d-flex justify-content-between align-items-center">
        <div>
            <div class="d-flex align-items-center gap-3 mb-1">
                <a href="index.php" class="text-muted"><i class="fas fa-arrow-left"></i></a>
                <h2 class="fw-bold mb-0 text-white"><?= htmlspecialchars($project['name']) ?></h2>
                <span class="badge bg-success rounded-pill px-3"><?= $project['status'] ?></span>
            </div>
            <div class="d-flex gap-4 text-gray small mt-2">
                <span><i class="fas fa-map-marker-alt me-1 text-accent"></i> <?= htmlspecialchars($project['address'] ?: 'No address') ?></span>
                <span><i class="fas fa-building me-1 text-warning"></i> <?= htmlspecialchars($project['company_name'] ?: 'No Company') ?></span>
                <span><i class="fas fa-calendar me-1"></i> Start: <?= $project['date_started'] ? date('M d, Y', strtotime($project['date_started'])) : 'TBD' ?></span>
            </div>
        </div>
        
        <div class="d-flex gap-2">
            <?php if($_SESSION['role'] === 'admin'): ?>
                <a href="project_create.php?id=<?= (int)$projectId ?>" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="fas fa-edit me-2"></i>Edit Info</a>
                <button class="btn btn-outline-info btn-sm rounded-pill" onclick="openAssignUsersModal()"><i class="fas fa-user-plus me-2"></i>Assign Users</button>
            <?php endif; ?>
            <button class="btn btn-primary rounded-pill btn-sm px-4" onclick="openUploadModal()"><i class="fas fa-cloud-upload-alt me-2"></i> Upload File</button>
        </div>
    </div>

    <div class="flex-grow-1 d-flex overflow-hidden">
        
        <aside class="project-sidebar bg-panel border-end border-secondary" style="width: 260px; overflow-y: auto;">
            <div class="p-3">
                <p class="text-muted small fw-bold text-uppercase ls-1 mb-3 ps-2">Project Menu</p>
                
                <nav class="nav flex-column gap-1 nav-pills custom-pills">
                    <a href="?id=<?= $projectId ?>&view=summary" class="nav-link <?= $currentView=='summary'?'active':'' ?>"><i class="fas fa-chart-pie me-2"></i> Summary</a>
                    <a href="?id=<?= $projectId ?>&view=desc" class="nav-link <?= $currentView=='desc'?'active':'' ?>"><i class="fas fa-align-left me-2"></i> Description of Work</a>
                    
                    <div class="mt-3 mb-2 ps-2 text-muted small fw-bold text-uppercase ls-1">Files & Folders</div>
                    
                    <?php foreach($allFolders as $folder): ?>
                        <a href="?id=<?= $projectId ?>&view=files&folder_id=<?= $folder['id'] ?>" class="nav-link <?= ($currentView=='files' && $currentFolderId==$folder['id'])?'active':'' ?>">
                            <i class="fas fa-folder me-2 text-warning opacity-75"></i> <?= htmlspecialchars($folder['name']) ?>
                        </a>
                    <?php endforeach; ?>

                    <div class="mt-3 mb-2 ps-2 text-muted small fw-bold text-uppercase ls-1">Management</div>
                    <a href="#" class="nav-link disabled"><i class="fas fa-clock me-2"></i> Clock in Asistencia</a>
                    <a href="#" class="nav-link disabled"><i class="fas fa-hard-hat me-2"></i> Labor Record</a>
                    <a href="#" class="nav-link disabled"><i class="fas fa-file-invoice-dollar me-2"></i> Expenses</a>
                    <a href="#" class="nav-link disabled"><i class="fas fa-shield-alt me-2"></i> Warranty Supplier</a>
                </nav>
            </div>
        </aside>

        <main class="project-content flex-grow-1 p-4 overflow-auto">
            
            <?php if($currentView === 'summary'): ?>
                <h4 class="fw-bold mb-4">Project Summary</h4>
                <div class="row g-4">
                    <div class="col-md-3">
                        <div class="box-card p-4 text-center">
                            <h1 class="fw-bold text-primary mb-0"><?= $fileCount ?></h1>
                            <p class="text-gray">Total Files</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="box-card p-4 text-center">
                            <h1 class="fw-bold text-success mb-0"><?= count($allFolders) ?></h1>
                            <p class="text-gray">Active Folders</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="box-card p-4">
                            <h6 class="text-white mb-2">Last Activity</h6>
                            <p class="text-gray mb-0"><?= $lastActivity ? date('F d, Y h:i A', strtotime($lastActivity)) : 'No activity yet' ?></p>
                        </div>
                    </div>
                </div>

            <?php elseif($currentView === 'desc'): ?>
                <h4 class="fw-bold mb-4">Description of Work</h4>
                <div class="box-card p-4">
                    <p class="text-white mb-4"><?= nl2br(htmlspecialchars($project['notes'] ?: 'No detailed description available.')) ?></p>
                    
                    <hr class="border-secondary opacity-25 my-4">
                    
                    <h6 class="fw-bold text-accent mb-3">Contact Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <p class="small text-gray mb-1">Site Contact</p>
                            <p class="text-white"><?= htmlspecialchars($project['contact_name']) ?> <br> <?= htmlspecialchars($project['contact_phone']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="small text-gray mb-1">Company Contact</p>
                            <p class="text-white"><?= htmlspecialchars($project['company_name']) ?> <br> <?= htmlspecialchars($project['company_phone']) ?></p>
                        </div>
                    </div>
                </div>

            <?php elseif($currentView === 'files'): 
                // Lógica para obtener archivos de la carpeta seleccionada
                $files = [];
                $folderName = "Select a Folder";
                if($currentFolderId) {
                    $fStmt = $pdo->prepare("SELECT * FROM files WHERE folder_id = ? AND deleted_at IS NULL ORDER BY uploaded_at DESC");
                    $fStmt->execute([$currentFolderId]);
                    $files = $fStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Buscar nombre de la carpeta actual
                    $currFolder = array_filter($allFolders, fn($f) => $f['id'] == $currentFolderId);
                    $folderName = !empty($currFolder) ? reset($currFolder)['name'] : "Unknown Folder";
                }
            ?>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="fw-bold mb-0"><i class="fas fa-folder-open text-warning me-2"></i> <?= htmlspecialchars($folderName) ?></h4>
                    <span class="badge bg-secondary"><?= count($files) ?> files</span>
                </div>

                <?php if(empty($files)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-cloud-upload-alt fa-3x text-gray mb-3 opacity-25"></i>
                        <p class="text-gray">This folder is empty.</p>
                        <button class="btn btn-outline-primary btn-sm rounded-pill" onclick="openUploadModal()">Upload Here</button>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach($files as $f): 
                             $ft = strtolower(pathinfo($f['filename'], PATHINFO_EXTENSION));
                             $icon = ($ft === 'pdf') ? 'fa-file-pdf text-danger' : 'fa-file-image text-primary';
                        ?>
                        <div class="col-md-3 col-xl-2">
                            <div class="box-card p-3 text-center h-100 file-hover">
                                <i class="fas <?= $icon ?> fa-3x mb-3"></i>
                                <h6 class="text-truncate small mb-1"><?= htmlspecialchars($f['filename']) ?></h6>
                                <small class="text-gray d-block mb-2"><?= date('M d', strtotime($f['uploaded_at'])) ?></small>
                                <div class="d-flex justify-content-center gap-2">
                                    <a href="preview.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-dark rounded-circle"><i class="fas fa-eye"></i></a>
                                    <a href="editor.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-dark rounded-circle text-primary"><i class="fas fa-pen"></i></a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="text-center py-5">
                    <p class="text-gray">Module under development.</p>
                </div>
            <?php endif; ?>

        </main>
    </div>
</div>

<style>
    .project-sidebar .nav-link {
        color: var(--text-muted);
        border-radius: 8px;
        padding: 10px 15px;
        transition: 0.2s;
        font-size: 0.95rem;
    }
    .project-sidebar .nav-link:hover {
        background: rgba(255,255,255,0.05);
        color: white;
    }
    .project-sidebar .nav-link.active {
        background: var(--primary);
        color: white;
        font-weight: 500;
        box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
    }
    .file-hover:hover {
        background: rgba(255,255,255,0.05);
        transform: translateY(-2px);
    }

    @media (max-width: 992px) {
        .bg-header { flex-direction: column; align-items: flex-start; gap: 12px; }
        .bg-header .d-flex.gap-2 { width: 100%; flex-wrap: wrap; }
        .project-sidebar { width: 100% !important; border-right: 0; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .project-content { padding: 20px !important; }
        .flex-grow-1.d-flex.overflow-hidden { flex-direction: column; }
    }
</style>

<?php include __DIR__ . '/../views/modals.php'; ?>

<?php if(($_SESSION['role'] ?? '') === 'admin'): ?>
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
    const pId = <?= $projectId ?>;
    const fId = <?= $currentFolderId ?? 'null' ?>;

    function openUploadModal() {
        const input = document.getElementById('projectUploadInput');
        if (input) input.click();
    }

    const projectUploadInput = document.getElementById('projectUploadInput');
    if (projectUploadInput) {
        projectUploadInput.addEventListener('change', function() {
            if (!this.files || this.files.length === 0) return;
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
</script>

<input type="file" id="projectUploadInput" class="d-none">

<?php include __DIR__ . '/../views/footer.php'; ?>
