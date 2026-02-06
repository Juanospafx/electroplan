<?php
// pages/projects.php - Gestión de Proyectos V2.5 (Enlazado con Nueva Creación y Layout Mejorado)
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
    SELECT p.*, u.username as creator_name, 
    (SELECT COUNT(*) FROM files f WHERE f.project_id = p.id AND f.deleted_at IS NULL) as file_count
    FROM projects p 
    LEFT JOIN users u ON p.created_by = u.id 
    WHERE p.deleted_at IS NULL
    ORDER BY p.created_at DESC
");
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Projects | Brightronix";
include __DIR__ . '/../views/header.php';
?>

    <style>
        .table-responsive { border-radius: var(--radius-box); overflow: hidden; border: 1px solid rgba(255,255,255,0.05); }
        .table-rounded { width: 100%; border-collapse: separate; border-spacing: 0; background: var(--bg-card); }
        .table-rounded th { background: rgba(0,0,0,0.2); color: var(--text-gray); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; padding: 18px 25px; border-bottom: 1px solid rgba(255,255,255,0.05); white-space: nowrap; }
        .table-rounded td { padding: 20px 25px; color: white; vertical-align: middle; border-bottom: 1px solid rgba(255,255,255,0.02); }
        .table-rounded tr:last-child td { border-bottom: none; }
        .table-rounded tr:hover td { background: rgba(255,255,255,0.02); }

        .btn-action { width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.1); color: var(--text-gray); transition: 0.2s; background: transparent; }
        .btn-action:hover { background: white; color: var(--bg-body); }
        .btn-action.delete:hover { background: #ef4444; color: white; border-color: #ef4444; }

        .status-badge { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; padding: 5px 10px; border-radius: 8px; letter-spacing: 0.5px; }
        .info-pill { background: rgba(255,255,255,0.05); padding: 4px 10px; border-radius: 5px; font-size: 0.75rem; color: var(--text-gray); display: inline-flex; align-items: center; gap: 6px; }
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
            <a href="project_create.php" class="btn-main text-decoration-none">
                <i class="fas fa-plus me-2"></i> New Project
            </a>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table class="table-rounded">
                <thead>
                    <tr>
                        <th width="35%">Project Details</th>
                        <th width="20%">Company / Client</th>
                        <th width="15%">Timeline</th>
                        <th width="10%">Status</th>
                        <th width="10%">Files</th>
                        <th class="text-end">Actions</th>
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
                                <span class="text-muted small">Not specified</span>
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
                        <td>
                            <span class="status-badge bg-<?= $stColor ?> bg-opacity-25 text-<?= $stColor ?>">
                                <?= $p['status'] ?? 'Active' ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-dark border border-secondary fw-normal">
                                <?= $p['file_count'] ?> Files
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="project_dashboard.php?id=<?= $p['id'] ?>" class="btn-action me-1" title="Open Dashboard"><i class="fas fa-columns"></i></a>
                            
                            <?php if($isAdmin): ?>
                                <button class="btn-action me-1" onclick="editProject(<?= $p['id'] ?>, '<?= addslashes($p['name']) ?>', '<?= addslashes($p['description']) ?>', '<?= $p['status'] ?? 'Active' ?>')" title="Quick Edit"><i class="fas fa-pen"></i></button>
                                <button class="btn-action delete" onclick="deleteProject(<?= $p['id'] ?>)" title="Move to Trash"><i class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if(empty($projects)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-gray">
                            No projects found.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
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
            else alert('Error updating project: ' + (d.msg || 'Unknown'));
        });
    });

    // 2. Eliminar (SOFT DELETE)
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
</script>
<?php endif; ?>

<?php include __DIR__ . '/../views/footer.php'; ?>