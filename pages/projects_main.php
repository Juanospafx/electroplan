<?php
// projects.php - Gestión de Proyectos V2.4 (Con Breadcrumbs y Toggle)
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

$users = [];
if ($isAdmin) {
    $stmtUsers = $pdo->query("SELECT id, username, role FROM users ORDER BY username ASC");
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
}

// Configuración del Header
$pageTitle = "Projects | Brightronix";
include __DIR__ . '/../views/header.php';
?>

    <style>
        .table-responsive { border-radius: var(--radius-box); overflow: hidden; border: 1px solid rgba(255,255,255,0.05); }
        .table-rounded { width: 100%; border-collapse: separate; border-spacing: 0; background: var(--bg-card); }
        .table-rounded th { background: rgba(0,0,0,0.2); color: var(--text-gray); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; padding: 18px 25px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .table-rounded td { padding: 20px 25px; color: white; vertical-align: middle; border-bottom: 1px solid rgba(255,255,255,0.02); }
        .table-rounded tr:last-child td { border-bottom: none; }
        .table-rounded tr:hover td { background: rgba(255,255,255,0.02); }

        .btn-action { width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.1); color: var(--text-gray); transition: 0.2s; background: transparent; }
        .btn-action:hover { background: white; color: var(--bg-body); }
        .btn-action.delete:hover { background: #ef4444; color: white; border-color: #ef4444; }

        .status-badge { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; padding: 5px 10px; border-radius: 8px; letter-spacing: 0.5px; }

        /* Responsive cards */
        .proj-cards { display: none; }
        .proj-card { background: var(--bg-card); border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; padding: 16px; }
        .proj-card + .proj-card { margin-top: 12px; }
        .proj-meta { font-size: 0.8rem; color: var(--text-gray); }

        @media (max-width: 992px) {
            .table-responsive { display: none; }
            .proj-cards { display: block; }
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

            <div class="user-pill">
                <div class="avatar"><?= strtoupper(substr($userName,0,1)) ?></div>
                <span class="small fw-bold"><?= htmlspecialchars($userName) ?></span>
            </div>
        </header>

        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <h2 class="fw-bold mb-1">Project Management</h2>
                <p class="text-gray mb-0">Manage, edit or archive your ongoing projects.</p>
            </div>
            
            <?php if($isAdmin): ?>
            <button class="btn-main" onclick="openCreateModal()">
                <i class="fas fa-plus me-2"></i> New Project
            </button>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table class="table-rounded">
                <thead>
                    <tr>
                        <th width="30%">Project Name</th>
                        <th width="15%">Status</th>
                        <th width="25%">Description</th>
                        <th>Assigned</th>
                        <th>Created</th>
                        <th>Files</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($projects as $p): 
                        $stColor = getStatusColor($p['status'] ?? 'Active');
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-primary bg-opacity-10 p-2 rounded text-primary">
                                    <i class="fas fa-folder"></i>
                                </div>
                                <div>
                                    <div class="fw-bold"><?= htmlspecialchars($p['name']) ?></div>
                                    <div class="small text-gray" style="font-size:0.75rem">ID: #<?= $p['id'] ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="status-badge bg-<?= $stColor ?> bg-opacity-25 text-<?= $stColor ?>">
                                <?= $p['status'] ?? 'Active' ?>
                            </span>
                        </td>
                        <td>
                            <span class="text-gray"><?= htmlspecialchars($p['description'] ?: 'No description') ?></span>
                        </td>
                        <td class="small text-gray">
                            <?= htmlspecialchars($p['assigned_name'] ?: 'Unassigned') ?>
                        </td>
                        <td class="small text-gray">
                            <?= date('M d, Y', strtotime($p['created_at'])) ?>
                        </td>
                        <td>
                            <span class="badge bg-dark border border-secondary fw-normal">
                                <?= $p['file_count'] ?> Files
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="index.php?project_id=<?= $p['id'] ?>" class="btn-action me-1" title="Open"><i class="fas fa-external-link-alt"></i></a>
                            
                            <?php if($isAdmin): ?>
                                <button class="btn-action me-1" onclick="editProject(<?= $p['id'] ?>, '<?= addslashes($p['name']) ?>', '<?= addslashes($p['description']) ?>', '<?= $p['status'] ?? 'Active' ?>')" title="Edit"><i class="fas fa-pen"></i></button>
                                <button class="btn-action me-1" onclick="openAssignModal(<?= $p['id'] ?>, '<?= addslashes($p['name']) ?>')" title="Assign User"><i class="fas fa-user-plus"></i></button>
                                <button class="btn-action delete" onclick="deleteProject(<?= $p['id'] ?>)" title="Move to Trash"><i class="fas fa-trash"></i></button>
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
                    <div class="proj-meta mb-3">Created: <?= date('M d, Y', strtotime($p['created_at'])) ?> · <?= $p['file_count'] ?> Files</div>
                    <div class="d-flex gap-2">
                        <a href="index.php?project_id=<?= $p['id'] ?>" class="btn-action" title="Open"><i class="fas fa-external-link-alt"></i></a>
                        <?php if($isAdmin): ?>
                            <button class="btn-action" onclick="editProject(<?= $p['id'] ?>, '<?= addslashes($p['name']) ?>', '<?= addslashes($p['description']) ?>', '<?= $p['status'] ?? 'Active' ?>')" title="Edit"><i class="fas fa-pen"></i></button>
                            <button class="btn-action" onclick="openAssignModal(<?= $p['id'] ?>, '<?= addslashes($p['name']) ?>')" title="Assign User"><i class="fas fa-user-plus"></i></button>
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
                <h5 class="modal-title fw-bold">Edit Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editForm">
                <input type="hidden" name="action" value="update_project">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <label class="text-gray small mb-2">Project Name</label>
                    <input type="text" name="name" id="edit_name" class="form-control mb-3" required>
                    
                    <label class="big-body text-gray small mb-2">Status</label>
                    <select name="status" id="edit_status" class="form-control mb-3">
                        <option value="Planning">Planning</option>
                        <option value="Active">Active</option>
                        <option value="On Hold">On Hold</option>
                        <option value="Completed">Completed</option>
                    </select>

                    <label class="text-gray small mb-2">Description</label>
                    <textarea name="description" id="edit_desc" class="form-control" rows="3"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn-main w-100">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="createProjectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Create New Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createForm">
                <input type="hidden" name="action" value="create_project">
                <div class="modal-body">
                    <label class="text-gray small mb-2">Project Name</label>
                    <input type="text" name="name" class="form-control mb-3" required>
                    
                    <label class="text-gray small mb-2">Status</label>
                    <select name="status" class="form-control mb-3">
                        <option value="Planning">Planning</option>
                        <option value="Active" selected>Active</option>
                        <option value="On Hold">On Hold</option>
                    </select>

                    <label class="text-gray small mb-2">Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>

                    <label class="text-gray small mb-2 mt-3">Assign Users</label>
                    <div class="border rounded p-2" style="max-height:180px; overflow:auto;">
                        <?php foreach($users as $u): ?>
                            <label class="d-flex align-items-center gap-2 small text-gray mb-2">
                                <input type="checkbox" name="user_ids[]" value="<?= (int)$u['id'] ?>" data-role="<?= htmlspecialchars($u['role']) ?>">
                                <span><?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['role']) ?>)</span>
                            </label>
                        <?php endforeach; ?>
                        <?php if(empty($users)): ?>
                            <div class="text-gray small">No users available.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn-main w-100">Create Project</button>
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
                <input type="hidden" name="action" value="assign_project_user">
                <input type="hidden" name="project_id" id="assign_project_id">
                <div class="modal-body">
                    <label class="text-gray small mb-2">Project</label>
                    <input type="text" id="assign_project_name" class="form-control mb-3" disabled>

                    <label class="text-gray small mb-2">User</label>
                    <select name="user_id" id="assign_user_id" class="form-control" required>
                        <option value="">Select a user...</option>
                        <?php foreach($users as $u): ?>
                            <option value="<?= (int)$u['id'] ?>">
                                <?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['role']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn-main w-100">Assign</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // 1. Crear
    function openCreateModal() {
        new bootstrap.Modal(document.getElementById('createProjectModal')).show();
    }
    document.getElementById('createForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const checked = Array.from(this.querySelectorAll('input[name="user_ids[]"]:checked'));
        const hasAdmin = checked.some(i => i.dataset.role === 'admin');
        if (checked.length > 0 && !hasAdmin) {
            alert('At least one admin must be assigned to the project.');
            return;
        }
        const fd = new FormData(this);
        fetch('../api/api.php', { method:'POST', body:fd })
        .then(r => r.json()).then(d => { location.reload(); });
    });

    // 2. Editar
    function editProject(id, name, desc, status) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_desc').value = desc;
        // Set Status
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
            else alert('Error updating project: ' + (d.msg || 'Unknown'));
        });
    });

    // 3. Eliminar (SOFT DELETE)
    function deleteProject(id) {
        if(confirm("Move project to Recycle Bin?")) {
            const fd = new FormData();
            fd.append('action', 'delete_entity'); fd.append('type', 'project'); fd.append('id', id);
            fetch('../api/api.php', { method:'POST', body:fd })
            .then(r => r.json()).then(d => {
                if(d.status === 'success') location.reload();
                else alert('Error deleting project');
            });
        }
    }

    // 4. Asignar Usuario
    function openAssignModal(projectId, projectName) {
        document.getElementById('assign_project_id').value = projectId;
        document.getElementById('assign_project_name').value = projectName;
        document.getElementById('assign_user_id').value = '';
        new bootstrap.Modal(document.getElementById('assignUserModal')).show();
    }
    document.getElementById('assignForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        fetch('../api/api.php', { method:'POST', body:fd })
        .then(r => r.json()).then(d => {
            if(d.status === 'success') location.reload();
            else alert('Error assigning user: ' + (d.msg || 'Unknown'));
        });
    });
</script>
<?php endif; ?>

<?php include __DIR__ . '/../views/footer.php'; ?>