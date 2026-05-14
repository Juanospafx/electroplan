<?php
// pages/projects.php - Gesti??n de Proyectos V2.5 (Enlazado con Nueva Creaci??n y Layout Mejorado)
require_once __DIR__ . '/../core/auth/session.php';
require_once __DIR__ . '/../core/db/connection.php';
require_once __DIR__ . '/../core/time.php';
require_once __DIR__ . '/../funciones/projects.php'; 

$userId = $_SESSION['user_id'];
$userName = $_SESSION['username'];
$userRoleRaw = $_SESSION['role'] ?? 'viewer';
$userRole = strtolower($userRoleRaw);

// Permiso Admin
$isAdmin = ($userRole === 'admin');

// Obtener todos los proyectos (FILTRADO POR NO BORRADOS)
$stmt = $pdo->query("
    SELECT p.*, u.username as creator_name, au.username as assigned_name,
    (SELECT COUNT(*) FROM files f WHERE f.project_id = p.id AND f.deleted_at IS NULL) as file_count
    FROM projects p 
    LEFT JOIN users u ON p.created_by = u.id 
    LEFT JOIN users au ON p.assigned_user_id = au.id
    WHERE p.deleted_at IS NULL
    ORDER BY p.created_at DESC
");
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Projects | Brightronix";
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

        .table-responsive { border-radius: var(--radius-box); overflow-x: auto; overflow-y: visible; border: 1px solid var(--border-subtle); padding-right: 12px; }
        .table-rounded { width: 100%; border-collapse: separate; border-spacing: 0; background: var(--bg-card); }
        .table-rounded th { background: var(--bg-input); color: var(--text-gray); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; padding: 18px 25px; border-bottom: 1px solid var(--border-subtle); white-space: nowrap; }
        .table-rounded td { padding: 20px 25px; color: var(--text-white); vertical-align: middle; border-bottom: 1px solid var(--border-subtle); }
        .table-rounded tr:last-child td { border-bottom: none; }
        .table-rounded tr:hover td { background: var(--bg-body); }

        .btn-action { border-radius: 8px; display: inline-flex; align-items: center; justify-content: flex-start; border: 1px solid var(--border-subtle); color: var(--text-gray); transition: 0.2s; background: var(--bg-card); gap: 8px; width: 100%; min-height: 34px; padding: 6px 10px; white-space: nowrap; }
        .btn-action:hover { background: var(--primary); color: white; border-color: var(--primary); }
        .btn-action.delete:hover { background: #ef4444; color: white; border-color: #ef4444; }
        .action-label { font-size: 0.78rem; font-weight: 600; }
        .action-buttons { display:flex; flex-direction:column; align-items:flex-start; gap:8px; min-width:140px; }
        .table-rounded th.actions-col,
        .table-rounded td.actions-cell { min-width: 60px; }
        .table-rounded td.actions-cell { overflow: visible; white-space: nowrap; }

        .status-badge { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; padding: 5px 10px; border-radius: 8px; letter-spacing: 0.5px; }
        .info-pill { background: var(--bg-input); border: 1px solid var(--border-subtle); padding: 4px 10px; border-radius: 5px; font-size: 0.75rem; color: var(--text-gray); display: inline-flex; align-items: center; gap: 6px; }

        /* Responsive cards */
        .proj-cards { display: none; }
        .proj-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 16px; padding: 16px; transition: 0.3s; }
        .proj-card:hover { transform: translateY(-3px); border-color: var(--primary); }
        .proj-card + .proj-card { margin-top: 12px; }
        .proj-meta { font-size: 0.8rem; color: var(--text-gray); }

        /* Form Controls & Modals Integration */
        .form-control { background: var(--bg-input) !important; border: 1px solid var(--border-subtle) !important; color: var(--text-white) !important; border-radius: 10px; }
        .form-control::placeholder { color: var(--text-gray) !important; opacity: 1; }
        .form-control:focus { border-color: var(--primary) !important; box-shadow: 0 0 0 3px rgba(251, 90, 58, 0.2) !important; }

        .btn-main { background-color: var(--primary) !important; border-color: var(--primary) !important; color: white !important; }
        .btn-main:hover { background-color: var(--primary-hover) !important; border-color: var(--primary-hover) !important; }

        .modal-content { background-color: var(--bg-card); border: 1px solid var(--border-subtle); color: var(--text-white); border-radius: var(--radius-box); }
        .modal-header { border-bottom: 1px solid var(--border-subtle); }
        .modal-footer { border-top: 1px solid var(--border-subtle); }
        .modal-content .border { border-color: var(--border-subtle) !important; }
        
        .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }
        body.theme-light .btn-close { filter: none; }

    @media (max-width: 992px) {
        .table-responsive { display: none; }
        .proj-cards { display: block; }
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
                    <i class="fas fa-chevron-right mx-2" style="font-size:0.7rem"></i>
                    <span>Projects</span>
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

        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <h2 class="fw-bold mb-1">Project Management</h2>
                <p class="text-gray mb-0">Manage, edit or archive your ongoing projects.</p>
            </div>
            
            <?php if($isAdmin): ?>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-light btn-sm rounded-pill px-3" onclick="document.getElementById('bulk-folder-input').click()" title="Import projects from local folders" style="display: flex; align-items: center;">
                    <i class="fas fa-folder-tree me-2"></i>Bulk Import
                </button>
                <input type="file" id="bulk-folder-input" webkitdirectory multiple style="display:none" onchange="handleBulkFolderImport(this)">
                <a href="project_create.php" class="btn-main text-decoration-none" style="display: flex; align-items: center;">
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

        <div class="table-responsive">
            <table class="table-rounded">
                <thead>
                    <tr>
                        <th width="30%">Project Details</th>
                        <th width="20%">Company / Client</th>
                        <th width="15%">Timeline</th>
                        <th width="12%">Assigned</th>
                        <th width="10%">Status</th>
                        <th width="8%">Files</th>
                        <th class="actions-col text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($projects as $p): 
                        $stColor = getStatusColor($p['status'] ?? 'Active');
                    ?>
                    <tr style="cursor: pointer;" onclick="window.location.href='project_dashboard.php?id=<?= $p['id'] ?>'">
                        <td>
                            <div class="d-flex align-items-start gap-3">
                                <div class="bg-primary bg-opacity-10 p-2 rounded text-primary mt-1">
                                    <i class="fas fa-folder"></i>
                                </div>
                                <div>
                                    <div class="fw-bold mb-1"><?= htmlspecialchars($p['name']) ?></div>
                                    <?php if(!empty($p['address'])): ?>
                                        <div class="small text-gray mb-1"><i class="fas fa-map-marker-alt me-1 text-accent"></i> <?= htmlspecialchars($p['address']) ?></div>
                                    <?php endif; ?>
                                    <div class="small text-gray text-truncate" style="max-width: 250px; opacity:0.7"><?= htmlspecialchars($p['description'] ?: '') ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if(!empty($p['company_name'])): ?>
                                <div class="fw-bold small"><?= htmlspecialchars($p['company_name']) ?></div>
                                <div class="small text-gray"><?= htmlspecialchars($p['contact_name'] ?: '') ?></div>
                            <?php else: ?>
                                <span class="text-gray small">Not specified</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if(!empty($p['date_started'])): ?>
                                <div class="info-pill mb-1"><i class="fas fa-play text-success" style="font-size:0.6rem"></i> <?= date('M d, Y', strtotime($p['date_started'])) ?></div>
                            <?php endif; ?>
                            <?php if(!empty($p['date_finished'])): ?>
                                <div class="info-pill"><i class="fas fa-flag-checkered text-danger" style="font-size:0.6rem"></i> <?= date('M d, Y', strtotime($p['date_finished'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="small text-gray">
                            <?= htmlspecialchars($p['assigned_name'] ?: 'Unassigned') ?>
                        </td>
                        <td>
                            <span class="status-badge bg-<?= $stColor ?> bg-opacity-25 text-<?= $stColor ?>">
                                <?= htmlspecialchars($p['status'] ?? 'Active') ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-dark border border-secondary fw-normal">
                                <?= $p['file_count'] ?> Files
                            </span>
                        </td>
                        <td class="actions-cell text-end" onclick="event.stopPropagation();">
                            <?php if($isAdmin): ?>
                                <button class="btn-action delete d-inline-flex justify-content-center" style="width: 36px; min-width: 36px; padding: 0;" onclick="deleteProject(<?= $p['id'] ?>)" title="Delete project">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if(empty($projects)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-gray">
                            No projects found.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="proj-cards">
            <?php foreach($projects as $p): 
                $stColor = getStatusColor($p['status'] ?? 'Active');
            ?>
                <div class="proj-card" style="cursor: pointer;" onclick="window.location.href='project_dashboard.php?id=<?= $p['id'] ?>'">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div class="bg-primary bg-opacity-10 p-2 rounded text-primary">
                            <i class="fas fa-folder"></i>
                        </div>
                        <div>
                            <div class="fw-bold"><?= htmlspecialchars($p['name']) ?></div>
                            <div class="proj-meta">ID: #<?= $p['id'] ?></div>
                        </div>
                    </div>
                    <div class="proj-meta mb-2">
                        <span class="status-badge bg-<?= $stColor ?> bg-opacity-25 text-<?= $stColor ?>">
                            <?= htmlspecialchars($p['status'] ?? 'Active') ?>
                        </span>
                    </div>
                    <div class="proj-meta mb-2"><?= htmlspecialchars($p['description'] ?: 'No description') ?></div>
                    <div class="proj-meta mb-2">Assigned: <?= htmlspecialchars($p['assigned_name'] ?: 'Unassigned') ?></div>
                    <div class="proj-meta mb-3">Created: <?= date('M d, Y', strtotime($p['created_at'])) ?> ?? <?= $p['file_count'] ?> Files</div>
                    <div class="d-flex justify-content-end mt-2" onclick="event.stopPropagation();">
                        <?php if($isAdmin): ?>
                            <button class="btn-icon text-danger border-danger" onclick="deleteProject(<?= $p['id'] ?>)" title="Move to Trash"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if(empty($projects)): ?>
                <div class="proj-card text-center text-gray">No projects found.</div>
            <?php endif; ?>
        </div>
    </main>

<script>
    function deleteProject(id) {
        appConfirm("Move project to Recycle Bin?", "Delete Project", () => {
            const fd = new FormData();
            fd.append('action', 'delete_entity'); fd.append('type', 'project'); fd.append('id', id);
            fetch('../api/api.php', { method:'POST', body:fd })
            .then(r => r.json()).then(d => {
                if(d.status === 'success') location.reload();
                else appAlert('Error deleting project', "Delete Error", "error");
            })
            .catch(() => appAlert('Connection error', "Error", "error"));
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
</script>

<?php include __DIR__ . '/../views/footer.php'; ?>
