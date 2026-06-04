<?php
// archivos.php - Archivos Subidos al Sistema
require_once __DIR__ . '/../core/auth/session.php';
require_once __DIR__ . '/../core/db/connection.php';
require_once __DIR__ . '/../core/time.php';
require_once __DIR__ . '/../funciones/file_search.php';

$userName = $_SESSION['username'];

$q = trim($_GET['q'] ?? '');
$filterProject = trim((string)($_GET['project'] ?? 'all'));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$where = "f.deleted_at IS NULL";
$params = [];

if ($filterProject !== 'all') {
    $where .= " AND f.project_id = ?";
    $params[] = (int)$filterProject;
}

// Get available projects for filter
$projectOptions = $pdo->query("SELECT DISTINCT id, name FROM projects WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$selectSql = "
    SELECT f.*, p.name AS project_name,
    p.description AS project_description,
    fo.name AS folder_name,
    sf.name AS sub_folder_name,
    (SELECT COUNT(*) FROM files f2 WHERE f2.project_id = f.project_id AND f2.filename = f.filename AND f2.deleted_at IS NULL AND f2.id != f.id) as version_count,
    (SELECT MAX(id) FROM files f3 WHERE f3.project_id = f.project_id AND f3.filename = f.filename AND f3.deleted_at IS NULL) as is_latest_id
    FROM files f
    LEFT JOIN projects p ON f.project_id = p.id
    LEFT JOIN folders fo ON f.folder_id = fo.id
    LEFT JOIN sub_folders sf ON f.sub_folder_id = sf.id
    WHERE $where
";

if ($q !== '') {
    $normalizedQuery = file_search_normalize($q);
    $candidateSeed = substr(str_replace(' ', '', $normalizedQuery), 0, 3);
    $escapeLike = fn(string $value): string => str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    $candidateLike = '%' . $escapeLike($candidateSeed !== '' ? $candidateSeed : $q) . '%';
    $queryStarts = $escapeLike($q) . '%';
    $queryContains = '%' . $escapeLike($q) . '%';
    $candidateSql = $selectSql . "
        AND (
            f.filename LIKE ? OR p.name LIKE ? OR fo.name LIKE ? OR sf.name LIKE ? OR f.file_type LIKE ? OR p.description LIKE ?
        )
        ORDER BY
            CASE
                WHEN LOWER(f.filename) = LOWER(?) THEN 0
                WHEN LOWER(f.filename) LIKE LOWER(?) THEN 1
                WHEN LOWER(f.filename) LIKE LOWER(?) THEN 2
                ELSE 3
            END ASC,
            f.uploaded_at DESC
        LIMIT 2500
    ";
    $stmt = $pdo->prepare($candidateSql);
    $stmt->execute(array_merge($params, array_fill(0, 6, $candidateLike), [$q, $queryStarts, $queryContains]));
    $scoredFiles = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $file) {
        $file['_search_score'] = file_search_score($file, $q);
        if ($file['_search_score'] > 0) $scoredFiles[] = $file;
    }
    usort($scoredFiles, function($a, $b) {
        $scoreCompare = ((int)$b['_search_score']) <=> ((int)$a['_search_score']);
        return $scoreCompare !== 0 ? $scoreCompare : strcmp((string)$b['uploaded_at'], (string)$a['uploaded_at']);
    });
    $totalFiles = count($scoredFiles);
    $totalPages = max(1, (int)ceil($totalFiles / $perPage));
    $page = min($page, $totalPages);
    $filesRaw = array_slice($scoredFiles, ($page - 1) * $perPage, $perPage);
} else {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM files f LEFT JOIN projects p ON f.project_id = p.id WHERE $where");
    $countStmt->execute($params);
    $totalFiles = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalFiles / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;
    $stmt = $pdo->prepare($selectSql . " ORDER BY f.uploaded_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $filesRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

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
    .search-status { color: var(--text-gray); font-size: .82rem; min-height: 20px; }
    .search-loading { display: none; align-items: center; gap: 8px; }
    .search-loading.show { display: inline-flex; }
    .download-action[disabled] { opacity: .65; cursor: wait; }
    .pagination .page-link { background: var(--bg-card); border-color: var(--border-subtle); color: var(--text-gray); }
    .pagination .page-item.active .page-link { background: var(--primary); border-color: var(--primary); color: #fff; }
    .pagination .page-item.disabled .page-link { background: var(--bg-input); color: var(--text-muted); }

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
        <form id="fileSearchForm" class="d-flex gap-2" method="get" action="archivos.php" style="flex-wrap: wrap;">
            <select id="fileProjectFilter" name="project" class="form-control form-control-sm" style="max-width: 200px;">
                <option value="all">All Projects</option>
                <?php foreach($projectOptions as $proj): ?>
                    <option value="<?= (int)$proj['id'] ?>" <?= $filterProject === (string)$proj['id'] ? 'selected' : '' ?>><?= htmlspecialchars($proj['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input id="fileSearchInput" type="text" name="q" class="form-control form-control-sm" style="max-width:240px" placeholder="Search file name, project, or folder..." value="<?= htmlspecialchars($q) ?>" autocomplete="off">
            <button class="btn btn-outline-light btn-sm rounded-pill px-3" type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-3 search-status">
        <span><?= number_format($totalFiles) ?> file<?= $totalFiles === 1 ? '' : 's' ?> found<?= $q !== '' ? ' for "' . htmlspecialchars($q) . '"' : '' ?></span>
        <span id="fileSearchLoading" class="search-loading"><i class="fas fa-spinner fa-spin"></i> Searching...</span>
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
                                    <li><a class="dropdown-item text-white hover-bg-body small" href="preview.php?id=<?= (int)$f['id'] ?>&mode=spreadsheet"><i class="fas fa-table me-2 text-success"></i> Preview</a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item text-white hover-bg-body small" href="preview.php?id=<?= (int)$f['id'] ?>"><i class="fas fa-eye me-2 text-info"></i> Preview</a></li>
                                <?php endif; ?>
                                <li><button type="button" class="dropdown-item text-white hover-bg-body small download-action" onclick="downloadFile(<?= (int)$f['id'] ?>, <?= htmlspecialchars(json_encode((string)$f['filename']), ENT_QUOTES) ?>, this)"><i class="fas fa-download me-2 text-primary"></i> Download</button></li>
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
                        <a href="preview.php?id=<?= (int)$f['id'] ?>&mode=spreadsheet" class="btn-action" title="Preview Spreadsheet"><i class="fas fa-table"></i></a>
                    <?php else: ?>
                        <a href="preview.php?id=<?= (int)$f['id'] ?>" class="btn-action" title="Preview"><i class="fas fa-eye"></i></a>
                    <?php endif; ?>
                    <button type="button" class="btn-action download-action" title="Download" onclick="downloadFile(<?= (int)$f['id'] ?>, <?= htmlspecialchars(json_encode((string)$f['filename']), ENT_QUOTES) ?>, this)"><i class="fas fa-download"></i></button>
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

    <?php if($totalPages > 1): ?>
    <nav class="mt-4" aria-label="Files pagination">
        <ul class="pagination pagination-sm justify-content-center">
            <?php
            $pageUrl = function(int $targetPage) use ($q, $filterProject): string {
                return 'archivos.php?' . http_build_query(['q' => $q, 'project' => $filterProject, 'page' => $targetPage]);
            };
            ?>
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= htmlspecialchars($pageUrl(max(1, $page - 1))) ?>">Previous</a></li>
            <?php for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= htmlspecialchars($pageUrl($i)) ?>"><?= $i ?></a></li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="<?= htmlspecialchars($pageUrl(min($totalPages, $page + 1))) ?>">Next</a></li>
        </ul>
    </nav>
    <?php endif; ?>
</main>

<script>
    const fileSearchForm = document.getElementById('fileSearchForm');
    const fileSearchInput = document.getElementById('fileSearchInput');
    const fileProjectFilter = document.getElementById('fileProjectFilter');
    const fileSearchLoading = document.getElementById('fileSearchLoading');
    let fileSearchTimer = null;

    function submitFileSearch() {
        fileSearchLoading?.classList.add('show');
        fileSearchForm?.submit();
    }

    fileSearchInput?.addEventListener('input', () => {
        clearTimeout(fileSearchTimer);
        fileSearchLoading?.classList.add('show');
        fileSearchTimer = setTimeout(submitFileSearch, 500);
    });
    fileProjectFilter?.addEventListener('change', submitFileSearch);
    fileSearchForm?.addEventListener('submit', () => fileSearchLoading?.classList.add('show'));

    async function downloadFile(id, fallbackName, button) {
        const originalHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Downloading...';
        try {
            const response = await fetch(`download.php?id=${encodeURIComponent(id)}`, { credentials: 'same-origin' });
            if (!response.ok) throw new Error('Download failed');

            const disposition = response.headers.get('Content-Disposition') || '';
            const utf8Match = disposition.match(/filename\*=UTF-8''([^;]+)/i);
            const plainMatch = disposition.match(/filename="?([^";]+)"?/i);
            const filename = utf8Match ? decodeURIComponent(utf8Match[1]) : (plainMatch ? plainMatch[1] : fallbackName);
            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            anchor.href = url;
            anchor.download = filename || fallbackName || 'download';
            document.body.appendChild(anchor);
            anchor.click();
            anchor.remove();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
            appAlert('File downloaded successfully.', 'Download', 'success');
        } catch (error) {
            console.error(error);
            appAlert('Unable to download file.', 'Download Error', 'error');
        } finally {
            button.disabled = false;
            button.innerHTML = originalHtml;
        }
    }

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
