<?php
// directorio.php - Directorio de Proyectos y Usuarios
require_once __DIR__ . '/../core/auth/session.php';
require_once __DIR__ . '/../core/db/connection.php';
require_once __DIR__ . '/../core/time.php';

$userName = $_SESSION['username'];
$userRole = strtolower((string)($_SESSION['role'] ?? 'viewer'));
$isAdmin = in_array($userRole, ['admin','owner'], true);

$stmt = $pdo->query("
    SELECT 
        p.id AS project_id,
        p.name AS project_name,
        p.description AS project_description,
        p.status AS project_status,
        p.assigned_user_id AS primary_user_id,
        u.id AS user_id,
        u.username AS username,
        u.role AS user_role
    FROM projects p
    LEFT JOIN directory d ON d.project_id = p.id
    LEFT JOIN users u ON u.id = d.user_id
    WHERE p.deleted_at IS NULL
    ORDER BY
        CASE WHEN LOWER(COALESCE(p.status,'')) IN ('completed','closed','finished') THEN 1 ELSE 0 END ASC,
        p.created_at DESC,
        u.username ASC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$directory = [];
$allUsers = [];
if ($isAdmin) {
    $allUsers = $pdo->query("SELECT id, username, role FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
}
foreach ($rows as $row) {
    $pid = (int)$row['project_id'];
    if (!isset($directory[$pid])) {
        $directory[$pid] = [
            'project_id' => $pid,
            'project_name' => $row['project_name'],
            'project_description' => $row['project_description'],
            'project_status' => $row['project_status'],
            'primary_user_id' => $row['primary_user_id'],
            'users' => [],
        ];
    }
    if (!empty($row['user_id'])) {
        $directory[$pid]['users'][] = [
            'id' => (int)$row['user_id'],
            'username' => $row['username'],
            'role' => $row['user_role'],
        ];
    }
}

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $directory = array_filter($directory, function($p) use ($q) {
        return stripos($p['project_name'], $q) !== false;
    });
}

$completedStatuses = ['completed', 'closed', 'finished'];
$activeProjects = array_filter($directory, function($p) use ($completedStatuses) {
    return !in_array(strtolower(trim((string)($p['project_status'] ?? ''))), $completedStatuses, true);
});
$completedProjects = array_filter($directory, function($p) use ($completedStatuses) {
    return in_array(strtolower(trim((string)($p['project_status'] ?? ''))), $completedStatuses, true);
});
$activeCount = count($activeProjects);
$completedCount = count($completedProjects);

$pageTitle = "Directory | Brightronix";
include __DIR__ . '/../views/header.php';
?>

<style>
    :root {
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


    .table-responsive { border-radius: var(--radius-box); overflow: auto; border: 1px solid var(--border-subtle); }
    .table-rounded { width: 100%; border-collapse: separate; border-spacing: 0; background: var(--bg-card); }
    .table-rounded th { background: var(--bg-input); color: var(--text-gray); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; padding: 18px 25px; border-bottom: 1px solid var(--border-subtle); }
    .table-rounded td { padding: 20px 25px; color: var(--text-white); vertical-align: middle; border-bottom: 1px solid var(--border-subtle); }
    .table-rounded tr:last-child td { border-bottom: none; }
    .table-rounded tr:hover td { background: rgba(255,255,255,0.02); }
    .group-row td { background: var(--bg-input); color: var(--text-gray); font-weight: 700; text-transform: uppercase; letter-spacing: .6px; font-size: .72rem; padding: 14px 25px; border-bottom: 2px solid var(--border-subtle); }
    .group-count { margin-left: 8px; font-size: .68rem; opacity: .85; }
    .user-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 8px; border-radius: 8px; background: var(--bg-input); border: 1px solid var(--border-subtle); margin-right: 6px; margin-bottom: 6px; font-size: 0.75rem; }
    .user-role { color: var(--text-gray); font-size: 0.7rem; }

    .form-control {
        background: var(--bg-input) !important;
        border: 1px solid var(--border-subtle) !important;
        color: var(--text-white) !important;
    }
    .form-control:focus {
        border-color: var(--primary) !important;
        box-shadow: 0 0 0 .2rem rgba(251, 90, 58, .18) !important;
    }

    body.theme-light .btn-outline-light {
        color: #334155;
        border-color: #cbd5e1;
    }

    @media (max-width: 768px) {
        .header { flex-direction: column; align-items: flex-start; gap: 12px; }
        .breadcrumbs { margin-top: 4px; }
        .main-content { padding: 20px; }
        .d-flex.justify-content-between.align-items-end { flex-direction: column; align-items: flex-start; gap: 12px; }
        form.d-flex { width: 100%; }
        form.d-flex .form-control { flex: 1; }
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
                <span>Directory</span>
            </div>
        </div>

        <a href="../admin/settings.php?tab=users" class="user-pill text-decoration-none">
            <div class="avatar"><?= strtoupper(substr($userName,0,1)) ?></div>
            <div class="user-pill-info">
                <span class="user-pill-name"><?= htmlspecialchars($userName) ?></span>
                <span class="user-pill-role"><?= ucfirst($_SESSION['role'] ?? 'Viewer') ?></span>
            </div>
        </a>
    </header>

    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <h2 class="fw-bold mb-1">Directory</h2>
            <p class="text-gray mb-0">Projects and their assigned users.</p>
        </div>
        <form class="d-flex gap-2" method="get" action="directorio.php">
            <input type="text" name="q" class="form-control form-control-sm" style="max-width:240px" placeholder="Search project..." value="<?= htmlspecialchars($q) ?>">
            <button class="btn btn-outline-light btn-sm rounded-pill px-3" type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table-rounded">
            <thead>
                <tr>
                    <th width="30%">Project</th>
                    <th width="20%">Status</th>
                    <th width="30%">Description</th>
                    <th>Users</th>
                </tr>
            </thead>
            <tbody>
                <?php if($activeCount > 0): ?>
                <tr class="group-row"><td colspan="4"><i class="fas fa-check-circle me-2 text-success"></i> Active Projects <span class="group-count">(<?= $activeCount ?>)</span></td></tr>
                <?php endif; ?>
                <?php foreach($activeProjects as $p): ?>
                <tr>
                    <td>
                        <div class="fw-bold"><?= htmlspecialchars($p['project_name']) ?></div>
                        <div class="small text-gray" style="font-size:0.75rem">ID: #<?= $p['project_id'] ?></div>
                    </td>
                    <td class="small text-gray"><?= htmlspecialchars($p['project_status'] ?? 'Active') ?></td>
                    <td class="small text-gray"><?= htmlspecialchars($p['project_description'] ?: 'No description') ?></td>
                    <td>
                        <?php if (!empty($p['users'])): ?>
                            <?php foreach($p['users'] as $u): ?>
                                <span class="user-chip">
                                    <?= htmlspecialchars($u['username']) ?>
                                    <span class="user-role">(<?= htmlspecialchars($u['role']) ?>)</span>
                                    <?php if ((int)$p['primary_user_id'] === (int)$u['id']): ?>
                                        <span class="user-role">• primary</span>
                                    <?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-gray">Unassigned</span>
                        <?php endif; ?>
                        <?php if($isAdmin): ?>
                            <div class="mt-2"><button class="btn btn-sm btn-outline-light rounded-pill" onclick="openDirAssignModal(<?= (int)$p['project_id'] ?>, '<?= addslashes($p['project_name']) ?>')"><i class="fas fa-user-plus me-1"></i> Manage</button></div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if($completedCount > 0): ?>
                <tr class="group-row"><td colspan="4"><i class="fas fa-flag-checkered me-2 text-muted"></i> Completed Projects <span class="group-count">(<?= $completedCount ?>)</span></td></tr>
                <?php endif; ?>
                <?php foreach($completedProjects as $p): ?>
                <tr>
                    <td>
                        <div class="fw-bold"><?= htmlspecialchars($p['project_name']) ?></div>
                        <div class="small text-gray" style="font-size:0.75rem">ID: #<?= $p['project_id'] ?></div>
                    </td>
                    <td class="small text-gray"><?= htmlspecialchars($p['project_status'] ?? 'Completed') ?></td>
                    <td class="small text-gray"><?= htmlspecialchars($p['project_description'] ?: 'No description') ?></td>
                    <td>
                        <?php if (!empty($p['users'])): ?>
                            <?php foreach($p['users'] as $u): ?>
                                <span class="user-chip">
                                    <?= htmlspecialchars($u['username']) ?>
                                    <span class="user-role">(<?= htmlspecialchars($u['role']) ?>)</span>
                                    <?php if ((int)$p['primary_user_id'] === (int)$u['id']): ?><span class="user-role">primary</span><?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                        <?php else: ?><span class="text-gray">Unassigned</span><?php endif; ?>
                        <?php if($isAdmin): ?>
                            <div class="mt-2"><button class="btn btn-sm btn-outline-light rounded-pill" onclick="openDirAssignModal(<?= (int)$p['project_id'] ?>, '<?= addslashes($p['project_name']) ?>')"><i class="fas fa-user-plus me-1"></i> Manage</button></div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if(empty($directory)): ?>
                <tr>
                    <td colspan="4" class="text-center py-5 text-gray">
                        No projects found.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php include __DIR__ . '/../views/footer.php'; ?>
<?php if($isAdmin): ?>
<div class="modal fade" id="dirAssignModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-3">
      <div class="modal-header"><h5 class="modal-title fw-bold">Assign users</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="dirAssignForm">
        <input type="hidden" name="action" value="assign_project_users">
        <input type="hidden" name="project_id" id="dir_project_id">
        <div class="modal-body">
          <div class="small text-gray mb-2" id="dir_project_name"></div>
          <div class="border rounded p-2" style="max-height:220px;overflow:auto;">
            <?php foreach($allUsers as $u): ?>
              <label class="d-flex align-items-center gap-2 small text-gray mb-2"><input type="checkbox" name="user_ids[]" value="<?= (int)$u['id'] ?>" data-role="<?= htmlspecialchars($u['role']) ?>"> <span><?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['role']) ?>)</span></label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="modal-footer"><button type="submit" class="btn-main w-100">Save</button></div>
      </form>
    </div>
  </div>
</div>
<script>
const directoryData = <?= json_encode(array_values($directory), JSON_UNESCAPED_UNICODE) ?>;
function openDirAssignModal(projectId, projectName){
  document.getElementById('dir_project_id').value = String(projectId);
  document.getElementById('dir_project_name').textContent = projectName;
  const rec = directoryData.find(p => Number(p.project_id)===Number(projectId));
  const assigned = new Set((rec?.users||[]).map(u => String(u.id)));
  document.querySelectorAll('#dirAssignForm input[name="user_ids[]"]').forEach(ch => ch.checked = assigned.has(String(ch.value)));
  new bootstrap.Modal(document.getElementById('dirAssignModal')).show();
}
document.getElementById('dirAssignForm').addEventListener('submit', async function(e){
 e.preventDefault();
 const checked = Array.from(this.querySelectorAll('input[name="user_ids[]"]:checked'));
 const hasAdmin = checked.some(ch => (ch.dataset.role||'').toLowerCase()==='admin' || (ch.dataset.role||'').toLowerCase()==='owner');
 if(!hasAdmin){ appAlert('At least one admin/owner must be assigned.', 'Validation', 'warning'); return; }
 const fd = new FormData(this);
 const d = await fetch('../api/api.php', { method:'POST', body:fd }).then(r=>r.json());
 if(d.status==='success') location.reload(); else appAlert(d.msg||'Error saving assignment','Error','error');
});
</script>
<?php endif; ?>
