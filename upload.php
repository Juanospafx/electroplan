<?php
// upload.php - Backend for Dropzone bulk upload
// PHP 8.1+

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

function jsonResponse(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function sanitizeRelativePath(string $path): string {
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#/+#', '/', $path) ?? '';
    $path = trim($path, '/');

    $parts = array_filter(explode('/', $path), static fn($p) => $p !== '' && $p !== '.');
    $safeParts = [];
    foreach ($parts as $part) {
        if ($part === '..') {
            continue; // block traversal attempts
        }
        $part = preg_replace('/[^A-Za-z0-9._\- ]/u', '_', $part) ?? '_';
        $safeParts[] = $part;
    }

    return implode('/', $safeParts);
}

function ensureDirectory(string $dir): void {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Failed to create directory: ' . $dir);
        }
    }
}

$allowedExt = [
    'pdf','jpg','jpeg','png','gif','webp','bmp','tiff','tif','heic',
    'doc','docx','xls','xlsx','xlsm','csv','ppt','pptx','dwg','dxf','rvt','ifc','zip','rar'
];

if (!isset($_FILES['file'])) {
    jsonResponse(['status' => 'error', 'message' => 'No file received'], 400);
}

$file = $_FILES['file'];
if (!is_array($file) || !isset($file['error'], $file['tmp_name'], $file['name'])) {
    jsonResponse(['status' => 'error', 'message' => 'Invalid upload payload'], 400);
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    $map = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds form limit',
        UPLOAD_ERR_PARTIAL => 'Partial upload',
        UPLOAD_ERR_NO_FILE => 'No file uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temp directory',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
        UPLOAD_ERR_EXTENSION => 'Upload blocked by PHP extension',
    ];
    $msg = $map[$file['error']] ?? 'Unknown upload error';
    jsonResponse(['status' => 'error', 'message' => $msg, 'code' => $file['error']], 400);
}

$relativePathRaw = (string)($_POST['relative_path'] ?? $file['name']);
$relativePath = sanitizeRelativePath($relativePathRaw);
if ($relativePath === '') {
    jsonResponse(['status' => 'error', 'message' => 'Invalid relative path'], 400);
}

$ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
if ($ext === '' || !in_array($ext, $allowedExt, true)) {
    jsonResponse(['status' => 'error', 'message' => 'Extension not allowed: .' . $ext], 400);
}

$baseUploadDir = __DIR__ . '/uploads';
$targetPath = $baseUploadDir . '/' . $relativePath;
$targetDir = dirname($targetPath);

try {
    ensureDirectory($targetDir);
} catch (Throwable $e) {
    jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
}

if (!is_uploaded_file($file['tmp_name'])) {
    jsonResponse(['status' => 'error', 'message' => 'Potential invalid upload source'], 400);
}

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    jsonResponse(['status' => 'error', 'message' => 'Failed moving uploaded file'], 500);
}

jsonResponse([
    'status' => 'success',
    'message' => 'File uploaded',
    'original_name' => $file['name'],
    'relative_path' => $relativePath,
    'saved_as' => 'uploads/' . $relativePath,
]);
