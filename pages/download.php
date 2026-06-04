<?php
require_once __DIR__ . '/../core/auth/session.php';
require_once __DIR__ . '/../core/db/connection.php';
require_once __DIR__ . '/../core/file_paths.php';
require_once __DIR__ . '/../api/rbac.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid file');
}

$stmt = $pdo->prepare('SELECT id, project_id, filename, filepath, file_type FROM files WHERE id = ? AND deleted_at IS NULL LIMIT 1');
$stmt->execute([$id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$file) {
    http_response_code(404);
    exit('File not found');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$projectId = (int)($file['project_id'] ?? 0);
if ($userId <= 0 || $projectId <= 0 || !canDownloadFile($pdo, $userId, $projectId, $id)) {
    http_response_code(403);
    exit('Access denied');
}

$filename = basename((string)($file['filename'] ?? 'download'));
$path = resolve_file_disk_path((string)($file['filepath'] ?? ''), $filename);
if (!$path) {
    http_response_code(404);
    exit('File not found');
}

$downloadName = $filename !== '' ? $filename : basename($path);
$asciiName = preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName) ?: 'download';

header('Content-Type: ' . detect_file_mime($path, (string)($file['file_type'] ?? 'application/octet-stream')));
header('Content-Disposition: attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
