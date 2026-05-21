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

// Permiso Admin/Owner (RBAC defensivo para raíz de Projects)
$isAdmin = ($userRole === 'admin' || $userRole === 'owner');
$projectIdFromGet = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$folderIdFromGet = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : 0;
$isRootProjectsView = ($projectIdFromGet <= 0 && $folderIdFromGet <= 0);
$canSeeBulkImport = ($isAdmin && $isRootProjectsView);

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

$users = [];
$assignedUsersByProject = [];
if ($isAdmin) {
    $stmtUsers = $pdo->query("SELECT id, username, role FROM users ORDER BY username ASC");
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    $stmtDir = $pdo->query("SELECT project_id, user_id FROM directory ORDER BY project_id, user_id");
    foreach ($stmtDir->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $pid = (int)$row['project_id'];
        $uid = (int)$row['user_id'];
        if (!isset($assignedUsersByProject[$pid])) $assignedUsersByProject[$pid] = [];
        $assignedUsersByProject[$pid][] = $uid;
    }
}

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

        .table-responsive { border-radius: var(--radius-box); overflow: hidden; border: 1px solid var(--border-subtle); }
        .table-rounded { width: 100%; border-collapse: separate; border-spacing: 0; background: var(--bg-card); }
        .table-rounded th { background: var(--bg-input); color: var(--text-gray); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; padding: 18px 25px; border-bottom: 1px solid var(--border-subtle); white-space: nowrap; }
.table-rounded th:last-child, .table-rounded td:last-child { min-width: 170px; }
        .table-rounded td { padding: 20px 25px; color: var(--text-white); vertical-align: middle; border-bottom: 1px solid var(--border-subtle); }
        .table-rounded tr:last-child td { border-bottom: none; }
        .table-rounded tr:hover td { background: var(--bg-body); }

        .btn-action { border-radius: 8px; display: inline-flex; align-items: center; justify-content: flex-start; border: 1px solid var(--border-subtle); color: var(--text-gray); transition: 0.2s; background: var(--bg-card); gap: 8px; width: auto; min-height: 34px; padding: 6px 10px; white-space: nowrap; }
        .btn-action:hover { background: var(--primary); color: white; border-color: var(--primary); }
        .btn-action.delete:hover { background: #ef4444; color: white; border-color: #ef4444; }
        .action-label { font-size: 0.78rem; font-weight: 600; }

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
                <a href="project_create.php" class="btn-main text-decoration-none">
                    <i class="fas fa-plus me-2"></i> New Project
                </a>
            </div>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table class="table-rounded">
                <thead>
                    <tr>
                        <th width="35%">Project Details</th>
                        <th width="20%">Company / Client</th>
                        <th width="15%">Timeline</th>
                        <th width="12%">Assigned</th>
                        <th width="10%">Status</th>
                        <th width="10%">Files</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($projects as $p): 
                        $stColor = getStatusColor($p['status'] ?? 'Active');
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-start gap-3">
                                <div class="bg-primary bg-opacity-10 p-2 rounded text-primary mt-1">
                                    <i class="fas fa-folder"></i>
                                </div>
                                <div>
                                    <a class="fw-bold mb-1 d-inline-block text-decoration-none" href="project_dashboard.php?id=<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></a>
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
                            <?php if($isAdmin): ?>
                            <select class="form-select form-select-sm" onchange="updateProjectStatusInline(<?= (int)$p['id'] ?>, this.value)">
                                <?php foreach(['Planning','Active','On Hold','Completed'] as $st): ?>
                                  <option value="<?= $st ?>" <?= (($p['status'] ?? 'Active') === $st) ? 'selected' : '' ?>><?= $st ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php else: ?>
                            <span class="status-badge bg-<?= $stColor ?> bg-opacity-25 text-<?= $stColor ?>"><?= $p['status'] ?? 'Active' ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-dark border border-secondary fw-normal">
                                <?= $p['file_count'] ?> Files
                            </span>
                        </td>
                        <td>
                            <div class="d-flex flex-column gap-2 align-items-start" style="min-width:150px;">
                                <a href="project_dashboard.php?id=<?= $p['id'] ?>" class="btn-action" title="Open project"><i class="fas fa-external-link-alt"></i><span class="action-label">Open</span></a>
                                <?php if($isAdmin): ?>
                                <button class="btn-action delete" onclick="deleteProject(<?= $p['id'] ?>)" title="Delete project">
                                    <i class="fas fa-trash"></i><span class="action-label">Delete</span>
                                </button>
                                <?php endif; ?>
                            </div>
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
                <div class="proj-card">
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
                            <?= $p['status'] ?? 'Active' ?>
                        </span>
                    </div>
                    <div class="proj-meta mb-2"><?= htmlspecialchars($p['description'] ?: 'No description') ?></div>
                    <div class="proj-meta mb-2">Assigned: <?= htmlspecialchars($p['assigned_name'] ?: 'Unassigned') ?></div>
                    <div class="proj-meta mb-3">Created: <?= date('M d, Y', strtotime($p['created_at'])) ?> ?? <?= $p['file_count'] ?> Files</div>
                    <div class="d-flex gap-2">
                        <a href="project_dashboard.php?id=<?= $p['id'] ?>" class="btn-action" title="Open"><i class="fas fa-external-link-alt"></i></a>
                        <?php if($isAdmin): ?>
                            <button class="btn-action delete" onclick="deleteProject(<?= $p['id'] ?>)" title="Move to Trash"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if(empty($projects)): ?>
                <div class="proj-card text-center text-gray">No projects found.</div>
            <?php endif; ?>
        </div>
    </main>

<?php if($isAdmin): ?>
<div class="modal fade" id="editProjectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Project Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editForm">
                <input type="hidden" name="action" value="update_project">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <label class="text-gray small mb-2">Project Name</label>
                    <input type="text" name="name" id="edit_name" class="form-control mb-3" required>
                    
                    <label class="text-gray small mb-2">Status</label>
                    <select name="status" id="edit_status" class="form-control mb-3">
                        <option value="Planning">Planning</option>
                        <option value="Active">Active</option>
                        <option value="On Hold">On Hold</option>
                        <option value="Completed">Completed</option>
                    </select>

                    <label class="text-gray small mb-2">Description</label>
                    <textarea name="description" id="edit_desc" class="form-control" rows="3"></textarea>
                    
                    <div class="alert alert-info mt-3 small">
                        <i class="fas fa-info-circle me-1"></i> To edit dates and contacts, please use the "Edit Info" button inside the Project Dashboard.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn-main w-100">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if($isAdmin): ?>
<div class="modal fade" id="assignUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Assign User to Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="assignForm">
                <input type="hidden" name="action" value="assign_project_users">
                <input type="hidden" name="project_id" id="assign_project_id">
                <div class="modal-body">
                    <label class="text-gray small mb-2">Project</label>
                    <input type="text" id="assign_project_name" class="form-control mb-3" disabled>

                    <label class="text-gray small mb-2">Users</label>
                    <div class="border rounded p-2" style="max-height:240px; overflow:auto;">
                        <?php foreach($users as $u): ?>
                            <label class="d-flex align-items-center gap-2 small text-gray mb-2">
                                <input type="checkbox" name="user_ids[]" value="<?= (int)$u['id'] ?>" data-role="<?= htmlspecialchars($u['role']) ?>">
                                <span><?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['role']) ?>)</span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <small class="text-gray d-block mt-2" style="font-size:0.75rem;">At least one admin must be assigned.</small>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn-main w-100">Save assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // 1. Editar
    function editProject(id, name, desc, status) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_desc').value = desc;
        const stSelect = document.getElementById('edit_status');
        if(stSelect) stSelect.value = status;
        new bootstrap.Modal(document.getElementById('editProjectModal')).show();
    }
    document.getElementById('editForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        fetch('../api/api.php', { method:'POST', body:fd })
        .then(r => r.json()).then(d => {
            if(d.status === 'success') location.reload();
            else appAlert('Error updating project: ' + (d.msg || 'Unknown'), "Update Error", "error");
        })
        .catch(() => appAlert('Connection error', "Error", "error"));
    });

    
    async function updateProjectStatusInline(projectId, status) {
        const fd = new FormData();
        fd.append('action','update_project_status');
        fd.append('project_id', String(projectId));
        fd.append('status', status);
        try {
            const d = await fetch('../api/api.php', { method:'POST', body:fd }).then(r=>r.json());
            if (d.status !== 'success') appAlert('Status update failed: ' + (d.msg || 'Unknown'), 'Error', 'error');
            else location.reload();
        } catch(e) { appAlert('Connection error', 'Error', 'error'); }
    }

    // 2. Eliminar (SOFT DELETE)
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

    const assignedUsersByProject = <?= json_encode($assignedUsersByProject, JSON_UNESCAPED_UNICODE) ?>;

    // 3. Asignar Usuario(s)
    function openAssignModal(projectId, projectName, assignedUserId = null) {
        document.getElementById('assign_project_id').value = projectId;
        document.getElementById('assign_project_name').value = projectName;

        const selected = (assignedUsersByProject[String(projectId)] || assignedUsersByProject[projectId] || []).map(String);
        const fallback = (assignedUserId !== null && assignedUserId !== undefined) ? [String(assignedUserId)] : [];
        const finalSelected = selected.length ? selected : fallback;

        document.querySelectorAll('#assignForm input[name="user_ids[]"]').forEach(ch => {
            ch.checked = finalSelected.includes(String(ch.value));
        });

        new bootstrap.Modal(document.getElementById('assignUserModal')).show();
    }
    document.getElementById('assignForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const checked = Array.from(this.querySelectorAll('input[name="user_ids[]"]:checked'));
        const hasAdmin = checked.some(ch => (ch.dataset.role || '').toLowerCase() === 'admin');
        if (!hasAdmin) {
            appAlert('At least one admin must be assigned to the project.', "Assignment Error", "warning");
            return;
        }

        const fd = new FormData(this);
        fetch('../api/api.php', { method:'POST', body:fd })
        .then(r => r.json()).then(d => {
            if(d.status === 'success') location.reload();
            else appAlert('Error assigning user: ' + (d.msg || 'Unknown'), "Assignment Error", "error");
        })
        .catch(() => appAlert('Connection error', "Error", "error"));
    });
</script>
<?php endif; ?>

<?php include __DIR__ . '/../views/footer.php'; ?>
