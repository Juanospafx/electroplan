<?php
// directorio.php - Directorio de Proyectos y Usuarios
require_once __DIR__ . '/../core/auth/session.php';
require_once __DIR__ . '/../core/db/connection.php';
require_once __DIR__ . '/../core/time.php';

$userName = $_SESSION['username'];

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
    ORDER BY p.created_at DESC, u.username ASC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$directory = [];
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

$pageTitle = "Directory | Brightronix";
include __DIR__ . '/../views/header.php';
?>

<style>
    .table-responsive { border-radius: var(--radius-box); overflow: hidden; border: 1px solid rgba(255,255,255,0.05); }
    .table-rounded { width: 100%; border-collapse: separate; border-spacing: 0; background: var(--bg-card); }
    .table-rounded th { background: rgba(0,0,0,0.2); color: var(--text-gray); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; padding: 18px 25px; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .table-rounded td { padding: 20px 25px; color: white; vertical-align: middle; border-bottom: 1px solid rgba(255,255,255,0.02); }
    .table-rounded tr:last-child td { border-bottom: none; }
    .table-rounded tr:hover td { background: rgba(255,255,255,0.02); }
    .user-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 8px; border-radius: 8px; background: rgba(255,255,255,0.08); margin-right: 6px; margin-bottom: 6px; font-size: 0.75rem; }
    .user-role { opacity: 0.7; font-size: 0.7rem; }

    /* Responsive cards */
    .dir-cards { display: none; }
    .dir-card { background: var(--bg-card); border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; padding: 16px; }
    .dir-card + .dir-card { margin-top: 12px; }
    .dir-meta { font-size: 0.8rem; color: var(--text-gray); }

    @media (max-width: 992px) {
        .table-responsive { display: none; }
        .dir-cards { display: block; }
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

        <div class="user-pill">
            <div class="avatar"><?= strtoupper(substr($userName,0,1)) ?></div>
            <span class="small fw-bold"><?= htmlspecialchars($userName) ?></span>
        </div>
    </header>

    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <h2 class="fw-bold mb-1">Directory</h2>
            <p class="text-gray mb-0">Projects and their assigned users.</p>
        </div>
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
                <?php foreach($directory as $p): ?>
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

    <div class="dir-cards">
        <?php foreach($directory as $p): ?>
            <div class="dir-card">
                <div class="fw-bold"><?= htmlspecialchars($p['project_name']) ?></div>
                <div class="dir-meta">ID: #<?= $p['project_id'] ?> · <?= htmlspecialchars($p['project_status'] ?? 'Active') ?></div>
                <div class="dir-meta mt-2"><?= htmlspecialchars($p['project_description'] ?: 'No description') ?></div>
                <div class="mt-3">
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
                </div>
            </div>
        <?php endforeach; ?>

        <?php if(empty($directory)): ?>
            <div class="dir-card text-center text-gray">No projects found.</div>
        <?php endif; ?>
    </div>
</main>

<<<<<<< HEAD
<?php include __DIR__ . '/../views/footer.php'; ?>
=======
<?php include __DIR__ . '/../views/footer.php'; ?>
>>>>>>> Isaac_probe
