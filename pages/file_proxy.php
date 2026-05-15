<?php
require_once __DIR__ . '/../core/auth/session.php';
require_once __DIR__ . '/../core/db/connection.php';
require_once __DIR__ . '/../core/file_paths.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit('Invalid id'); }

$stmt = $pdo->prepare('SELECT id, filename, filepath FROM files WHERE id = ? AND deleted_at IS NULL LIMIT 1');
$stmt->execute([$id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$file) { http_response_code(404); exit('File not found'); }

$rel = normalize_file_path($file['filepath'] ?? '');
if ($rel === '') { http_response_code(404); exit('Invalid filepath'); }

$root = realpath(__DIR__ . '/..');
$candidates = [
    $root . '/' . $rel,
    $root . '/api/' . $rel,
];

$found = null;
foreach ($candidates as $c) {
    $real = realpath($c);
    if ($real && str_starts_with($real, $root) && is_file($real)) {
        $found = $real;
        break;
    }
}
if (!$found) { http_response_code(404); exit('File missing on server'); }

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo ? finfo_file($finfo, $found) : 'application/octet-stream';
if ($finfo) finfo_close($finfo);

$filename = $file['filename'] ?: basename($found);
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . str_replace('"', '', $filename) . '"');
header('Content-Length: ' . filesize($found));
header('X-Content-Type-Options: nosniff');
readfile($found);
exit;
