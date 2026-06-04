<?php
// archivos.php - Archivos Subidos al Sistema
require_once __DIR__ . '/../core/auth/session.php';
require_once __DIR__ . '/../core/db/connection.php';
require_once __DIR__ . '/../core/time.php';

$userName = $_SESSION['username'];

$q = trim($_GET['q'] ?? '');
$filterProject = trim((string)($_GET['project'] ?? 'all'));
$where = "f.deleted_at IS NULL";
$params = [];

if ($filterProject !== 'all') {
    $where .= " AND f.project_id = ?";
    $params[] = (int)$filterProject;
}

if ($q !== '') {
    $where .= " AND (f.filename LIKE ? OR p.name LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}

// Get available projects for filter
$projectOptions = $pdo->query("SELECT DISTINCT id, name FROM projects WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT f.*, p.name AS project_name,
    (SELECT COUNT(*) FROM files f2 WHERE f2.project_id = f.project_id AND f2.filename = f.filename AND f2.deleted_at IS NULL AND f2.id != f.id) as version_count,
    (SELECT MAX(id) FROM files f3 WHERE f3.project_id = f.project_id AND f3.filename = f.filename AND f3.deleted_at IS NULL) as is_latest_id
    FROM files f
    LEFT JOIN projects p ON f.project_id = p.id
    WHERE $where
    ORDER BY f.uploaded_at DESC
");
$stmt->execute($params);
$filesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Add is_latest_version flag to each file
$files = array_map(function($f) {
    $f['is_latest_version'] = ((int)$f['id'] === (int)$f['is_latest_id']) && ((int)$f['version_count'] > 0);
    return $f;
}, $filesRaw);

$pageTitle = "Files | Brightronix";
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


    .table-responsive { border-radius: var(--radius-box); overflow: visible; border: 1px solid var(--border-subtle); }
    .table-rounded { width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 0; background: var(--bg-card); }
    .table-rounded th { background: var(--bg-input); color: var(--text-gray); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; padding: 18px 25px; border-bottom: 1px solid var(--border-subtle); white-space: nowrap; }
    .table-rounded td { padding: 20px 25px; color: var(--text-white); vertical-align: middle; border-bottom: 1px solid var(--border-subtle); word-wrap: break-word; overflow-wrap: break-word; }
    .table-rounded tr:last-child td { border-bottom: none; }
    .table-rounded tr:hover td { background: rgba(255,255,255,0.02); }
    
    .table-rounded th:first-child { border-top-left-radius: calc(var(--radius-box) - 1px); }
    .table-rounded th:last-child { border-top-right-radius: calc(var(--radius-box) - 1px); }
    .table-rounded tr:last-child td:first-child { border-bottom-left-radius: calc(var(--radius-box) - 1px); }
    .table-rounded tr:last-child td:last-child { border-bottom-right-radius: calc(var(--radius-box) - 1px); }

    .btn-action { width: 32px; height: 32px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--border-subtle); color: var(--text-gray); transition: 0.2s; background: transparent; }
    .btn-action:hover { background: var(--primary); color: white; border-color: var(--primary); }
    .btn-action.text-danger:hover { background: #ef4444; color: white; border-color: #ef4444; }

    /* --- Dropdown Actions Menu Styles --- */
    .btn-action-menu { color: var(--text-gray); opacity: 0.7; transition: 0.2s; padding: 4px 10px; background: transparent; border: none; font-size: 1.1rem; }
    .btn-action-menu:hover { opacity: 1; color: var(--primary); }
    .dropdown-menu.bg-card { background-color: var(--bg-card) !important; border-color: var(--border-subtle) !important; }
    .dropdown-menu .dropdown-item:hover { background-color: var(--bg-body) !important; color: var(--text-white) !important; }
    .hover-bg-body:hover { background-color: var(--bg-body) !important; }
    body.theme-light .dropdown-menu.bg-card { background-color: #ffffff !important; }
    body.theme-light .dropdown-item { color: #0f172a !important; }
    body.theme-light .dropdown-item:hover { background-color: #f8fafc !important; }
    body.theme-light .border-secondary { border-color: rgba(15,23,42,0.18) !important; }

    .form-control {
        background: var(--bg-input) !important;
        border: 1px solid var(--border-subtle) !important;
        color: var(--text-white) !important;
    }
    .form-control:focus {
        border-color: var(--primary) !important;
        box-shadow: 0 0 0 .2rem rgba(251, 90, 58, .18) !important;
    }

    /* Responsive cards */
    .file-cards { display: none; }
    .file-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 16px; padding: 16px; transition: .2s ease; }
    .file-card:hover { transform: translateY(-3px); border-color: var(--primary); }
    .file-card + .file-card { margin-top: 12px; }
    .file-meta { font-size: 0.8rem; color: var(--text-gray); }

    @media (max-width: 992px) {
        .table-responsive { display: none; }
        .file-cards { display: block; }
    }

    @media (max-width: 768px) {
        .header { flex-direction: column; align-items: flex-start; gap: 12px; }
        .breadcrumbs { margin-top: 4px; }
        .main-content { padding: 20px; }
        .d-flex.justify-content-between.align-items-end { flex-direction: column; align-items: flex-start; gap: 12px; }
        form.d-flex { width: 100%; gap: 8px; }
        form.d-flex .form-control { flex: 1; min-width: 100px; }
        form.d-flex select { width: 100%; }
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
                <span>Files</span>
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
            <h2 class="fw-bold mb-1">Uploaded Files</h2>
            <p class="text-gray mb-0">All files currently stored in the system.</p>
        </div>
        <form class="d-flex gap-2" method="get" action="archivos.php" style="flex-wrap: wrap;">
            <select name="project" class="form-control form-control-sm" style="max-width: 200px;">
                <option value="all">All Projects</option>
                <?php foreach($projectOptions as $proj): ?>
                    <option value="<?= (int)$proj['id'] ?>" <?= $filterProject === (string)$proj['id'] ? 'selected' : '' ?>><?= htmlspecialchars($proj['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="q" class="form-control form-control-sm" style="max-width:240px" placeholder="Search file or project..." value="<?= htmlspecialchars($q) ?>">
            <button class="btn btn-outline-light btn-sm rounded-pill px-3" type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table-rounded">
            <thead>
                <tr>
                    <th width="40%">File</th>
                    <th width="30%">Assigned Project</th>
                    <th width="15%">Uploaded</th>
                    <th class="text-end" width="15%">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($files as $f): 
                    $projectLabel = !empty($f['project_name']) ? $f['project_name'] : 'No assigned project';
                    $filePath = $f['filepath'] ?? '';
                    if (strpos($filePath, 'uploads/') === 0) {
                        $expected = __DIR__ . '/../' . $filePath;
                        $legacy = __DIR__ . '/../api/' . $filePath;
                        if (!file_exists($expected) && file_exists($legacy)) {
                            $filePath = 'api/' . $filePath;
                        }
                    }
                    if (strpos($filePath, 'uploads/') === 0 || strpos($filePath, 'api/uploads/') === 0) {
                        $filePath = '../' . $filePath;
                    }
                ?>
                <tr>
                    <td>
                        <div class="fw-bold">
                            <?= htmlspecialchars($f['filename']) ?>
                            <?php if($f['is_latest_version']): ?>
                                <span class="badge bg-success bg-opacity-75 ms-2 small">Latest Version</span>
                            <?php endif; ?>
                        </div>
                        <div class="small text-gray">ID: #<?= (int)$f['id'] ?></div>
                    </td>
                    <td class="small text-gray"><?= htmlspecialchars($projectLabel) ?></td>
                    <td class="small text-gray"><?= !empty($f['uploaded_at']) ? date('M d, Y', strtotime($f['uploaded_at'])) : '-' ?></td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn-action-menu" data-bs-toggle="dropdown" data-bs-boundary="window" title="Actions"><i class="fas fa-ellipsis-v"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end bg-card border-secondary shadow-lg rounded-3 py-1">
                                <?php $ext = strtolower(pathinfo($f['filename'], PATHINFO_EXTENSION)); $isExcel = in_array($ext, ['xlsx','xls','xlsm','csv']); ?>
                                <?php if($isExcel): ?>
                                    <li><a class="dropdown-item text-white hover-bg-body small" href="preview.php?id=<?= (int)$f['id'] ?>&mode=spreadsheet" target="_blank"><i class="fas fa-table me-2 text-success"></i> Preview</a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item text-white hover-bg-body small" href="preview.php?id=<?= (int)$f['id'] ?>"><i class="fas fa-eye me-2 text-info"></i> Preview</a></li>
                                <?php endif; ?>
                                <?php if(!empty($filePath)): ?>
                                    <li><a class="dropdown-item text-white hover-bg-body small" href="<?= htmlspecialchars($filePath) ?>" target="_blank" rel="noopener"><i class="fas fa-download me-2 text-primary"></i> Download</a></li>
                                <?php endif; ?>
                                <?php if(($_SESSION['role'] ?? '') === 'admin'): ?>
                                    <li><hr class="dropdown-divider border-secondary my-1"></li>
                                    <li><button class="dropdown-item text-danger hover-bg-body small" onclick="deleteFile(<?= (int)$f['id'] ?>)"><i class="fas fa-trash me-2"></i> Delete</button></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if(empty($files)): ?>
                <tr>
                    <td colspan="4" class="text-center py-5 text-gray">
                        No files found.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="file-cards">
        <?php foreach($files as $f): 
            $projectLabel = !empty($f['project_name']) ? $f['project_name'] : 'No assigned project';
            $filePath = $f['filepath'] ?? '';
            if (strpos($filePath, 'uploads/') === 0) {
                $expected = __DIR__ . '/../' . $filePath;
                $legacy = __DIR__ . '/../api/' . $filePath;
                if (!file_exists($expected) && file_exists($legacy)) {
                    $filePath = 'api/' . $filePath;
                }
            }
            if (strpos($filePath, 'uploads/') === 0 || strpos($filePath, 'api/uploads/') === 0) {
                $filePath = '../' . $filePath;
            }
        ?>
            <div class="file-card">
                <div class="fw-bold">
                    <?= htmlspecialchars($f['filename']) ?>
                    <?php if($f['is_latest_version']): ?>
                        <span class="badge bg-success bg-opacity-75 ms-2 small">Latest Version</span>
                    <?php endif; ?>
                </div>
                <div class="file-meta">Project: <?= htmlspecialchars($projectLabel) ?></div>
                <div class="file-meta">Uploaded: <?= !empty($f['uploaded_at']) ? date('M d, Y', strtotime($f['uploaded_at'])) : '-' ?></div>
                
                <div class="d-flex justify-content-end gap-2 mt-3 pt-3" style="border-top: 1px solid var(--border-subtle);">
                    <?php $ext = strtolower(pathinfo($f['filename'], PATHINFO_EXTENSION)); $isExcel = in_array($ext, ['xlsx','xls','xlsm','csv']); ?>
                    <?php if($isExcel): ?>
                        <a href="preview.php?id=<?= (int)$f['id'] ?>&mode=spreadsheet" class="btn-action" title="Preview Spreadsheet" target="_blank"><i class="fas fa-table"></i></a>
                    <?php else: ?>
                        <a href="preview.php?id=<?= (int)$f['id'] ?>" class="btn-action" title="Preview"><i class="fas fa-eye"></i></a>
                    <?php endif; ?>
                    <?php if(!empty($filePath)): ?>
                        <a href="<?= htmlspecialchars($filePath) ?>" class="btn-action" title="Download" target="_blank" rel="noopener"><i class="fas fa-download"></i></a>
                    <?php endif; ?>
                    <?php if(($_SESSION['role'] ?? '') === 'admin'): ?>
                        <button class="btn-action text-danger" title="Delete" onclick="deleteFile(<?= (int)$f['id'] ?>)" style="border-color: #ef4444;"><i class="fas fa-trash"></i></button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if(empty($files)): ?>
            <div class="file-card text-center text-gray">No files found.</div>
        <?php endif; ?>
    </div>
</main>

<script>
    function deleteFile(id) {
        appConfirm("Move file to Recycle Bin?", "Delete File", () => {
            const fd = new FormData();
            fd.append('action', 'delete_file');
            fd.append('id', id);
            fetch('../api/api.php', { method:'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if(d.status === 'success') location.reload();
                    else appAlert('Error deleting file: ' + (d.msg || 'Unknown'), 'Error', 'error');
                })
                .catch(() => appAlert('Connection error', 'Error', 'error'));
        });
    }
</script>

<?php include_once __DIR__ . '/../funciones/alerts.php'; ?>
<?php include __DIR__ . '/../views/footer.php'; ?>
