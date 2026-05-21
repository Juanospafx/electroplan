<?php
require_once __DIR__ . '/../core/auth/session.php';
require_once __DIR__ . '/../core/db/connection.php';
require_once __DIR__ . '/../core/file_paths.php';
require_once __DIR__ . '/../api/rbac.php';

function normalize_proxy_path(?string $filepath): string {
    $p = normalize_file_path($filepath);
    if ($p === '') return '';

    // forzar sin prefijos absolutos ni /electroplan/
    $p = preg_replace('~^https?://[^/]+/electroplan/~i', '', $p);
    $p = preg_replace('~^/?electroplan/~i', '', $p);
    $p = preg_replace('~^/+~', '', $p);

    // si contiene uploads en cualquier posición, recortar desde ahí
    if (preg_match('~(?:^|/)(uploads/.+)$~i', $p, $m)) {
        $p = $m[1];
    }
    return $p;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Invalid id\n");
}

$stmt = $pdo->prepare('SELECT id, project_id, filename, filepath, file_type FROM files WHERE id = ? AND deleted_at IS NULL LIMIT 1');
$stmt->execute([$id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$file) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit("File not found for id={$id}\n");
}

$uid = (int)($_SESSION['user_id'] ?? 0);
$pid = (int)($file['project_id'] ?? 0);
if ($uid <= 0 || $pid <= 0 || !canDownloadFile($pdo, $uid, $pid, (int)$file['id'])) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Access denied\n");
}

$dbFilepath = (string)($file['filepath'] ?? '');
$relativePath = normalize_proxy_path($dbFilepath);
$filename = (string)($file['filename'] ?? '');

$docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$baseDir = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');

$candidates = [];
if ($relativePath !== '') {
    $candidates[] = $baseDir . '/' . $relativePath;
    $candidates[] = $baseDir . '/uploads/' . basename($relativePath);
}
if ($filename !== '') {
    $candidates[] = $baseDir . '/uploads/' . $filename;
}
if ($docRoot !== '' && $relativePath !== '') {
    $candidates[] = $docRoot . '/electroplan/' . $relativePath;
    $candidates[] = $docRoot . '/electroplan/uploads/' . basename($relativePath);
}
if ($docRoot !== '' && $filename !== '') {
    $candidates[] = $docRoot . '/electroplan/uploads/' . $filename;
}

$found = null;
foreach ($candidates as $cand) {
    if (is_file($cand)) { $found = $cand; break; }
}

if (!$found) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "file_proxy 404 debug\n";
    echo "id: {$id}\n";
    echo "filename: {$filename}\n";
    echo "filepath(db): {$dbFilepath}\n";
    echo "relativePath: {$relativePath}\n";
    echo "candidates:\n";
    foreach ($candidates as $c) echo " - {$c}\n";
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo ? finfo_file($finfo, $found) : '';
if ($finfo) finfo_close($finfo);
if (!$mime) {
    $mime = (string)($file['file_type'] ?? 'application/octet-stream');
}

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . str_replace('"', '', ($filename ?: basename($found))) . '"');
header('Content-Length: ' . filesize($found));
header('X-Content-Type-Options: nosniff');
readfile($found);
exit;
