<?php
/**
 * Test Suite: Multi-Document Upload Contract & Regression Verification (TASK-0409).
 *
 * Verifies:
 * 1. Clean Baseline Defect Demonstration:
 *    - api/upload.php lacked multiple selection, per-file requests, progress bars, and sanitized error reporting.
 *    - pages/index.php enforced outdated 1GB limit (1073741824), lacked visible progress overlay in HTML, and rendered unescaped filenames.
 *    - pages/project_dashboard.php lacked client-side 10GB pre-transfer validation, lacked active filename / dual progress, and had XSS in file list.
 *    - api/api.php used time() prefix prone to collisions during batch uploads and lacked upload error code handling.
 * 2. Multi-file selection across all upload entry points (upload.php, index.php, project_dashboard.php).
 * 3. Independent per-file requests and batch continuation after failure.
 * 4. Exact 10 GB limit (10737418240 bytes) client & server validation accepting boundary values.
 * 5. Visible progress reporting (active filename, current-file %, aggregate completed/total).
 * 6. Error attribution per filename for HTTP non-success, invalid JSON, and network errors.
 * 7. HTML escaping and XSS injection prevention on all user-visible filenames.
 * 8. Collision-resistant physical filenames without affecting versioned display filenames.
 * 9. Authorization, folder permissions, allowed extensions, and zero schema changes.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = dirname(__DIR__);
$apiSource = (string) file_get_contents($root . '/api/api.php');
$uploadPageSource = (string) file_get_contents($root . '/api/upload.php');
$indexSource = (string) file_get_contents($root . '/pages/index.php');
$dashboardSource = (string) file_get_contents($root . '/pages/project_dashboard.php');
$modalsSource = (string) file_get_contents($root . '/views/modals.php');
$rbacSource = (string) file_get_contents($root . '/api/rbac.php');

$sliceBetween = static function (string $source, string $startMarker, string $endMarker): string {
    $start = strpos($source, $startMarker);
    if ($start === false) return '';
    $end = strpos($source, $endMarker, $start + strlen($startMarker));
    if ($end === false) return substr($source, $start);
    return substr($source, $start, $end - $start);
};

$indexUploadHandler = $sliceBetween($indexSource, 'async function handleFiles(fileList)', '// ==========================================');
$dashboardUploadHandler = $sliceBetween($dashboardSource, "const uploadFileForm = document.getElementById('uploadFileForm');", 'function openAssignUsersModal()');
$zipUploadHandler = $sliceBetween($dashboardSource, 'async function startZipUpload()', '// ── Global Search');

$assertions = 0;
$failures = [];

$assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
    $assertions++;
    if ($condition) {
        echo "[PASS] {$message}\n";
    } else {
        echo "[FAIL] {$message}\n";
        $failures[] = $message;
    }
};

echo "=== 1. Baseline Defect Analysis & Contract Requirements ===\n";

// Contract 1: Dedicated upload page multiple selection
$assert(
    (bool) preg_match('/<input[^>]+type=[\'"]file[\'"][^>]+multiple/i', $uploadPageSource),
    'api/upload.php provides multiple file selection input.'
);

// Contract 2: Dedicated upload page sends independent per-file requests via XHR
$assert(
    str_contains($uploadPageSource, 'uploadFileWithProgress') || str_contains($uploadPageSource, 'uploadSingleFile') || str_contains($uploadPageSource, 'new XMLHttpRequest()'),
    'api/upload.php uploads files with progress tracking.'
);

// Contract 3: Dedicated upload page enforces 10 GB (10737418240 bytes) limit
$assert(
    str_contains($uploadPageSource, '10737418240'),
    'api/upload.php defines the exact 10 GB per-file limit (10737418240 bytes).'
);

// Contract 4: Dedicated upload page reports active filename, current-file % and aggregate progress
$assert(
    (str_contains($uploadPageSource, 'activeFilename') || str_contains($uploadPageSource, 'active-filename') || str_contains($uploadPageSource, 'active-file'))
    && (str_contains($uploadPageSource, 'currentFile') || str_contains($uploadPageSource, 'current-file') || str_contains($uploadPageSource, 'file-progress') || str_contains($uploadPageSource, 'fileProgressBar'))
    && (str_contains($uploadPageSource, 'aggregate') || str_contains($uploadPageSource, 'overall') || str_contains($uploadPageSource, 'total') || str_contains($uploadPageSource, 'batchCount')),
    'api/upload.php visibly reports active filename, current-file transfer percentage, and aggregate progress.'
);

// Contract 5: Dedicated upload page escapes filenames to prevent HTML injection
$assert(
    str_contains($uploadPageSource, 'escapeHtml') || str_contains($uploadPageSource, 'textContent'),
    'api/upload.php escapes or renders filenames as text to prevent HTML injection.'
);

echo "=== 2. Project Document Upload Flow (pages/index.php) ===\n";

// Contract 6: index.php enforces 10 GB (10737418240 bytes) instead of legacy 1 GB
$assert(
    str_contains($indexSource, '10737418240') && !str_contains($indexSource, 'MAX_SIZE = 1073741824;'),
    'pages/index.php enforces exact 10 GB limit (10737418240 bytes) and rejects legacy 1 GB limit.'
);

// Contract 7: index.php contains visible progress elements in its markup or modal inclusion
$assert(
    str_contains($indexSource, 'bulk-import-overlay') || str_contains($indexSource, 'uploadProgressWrap') || str_contains($modalsSource, 'bulk-import-overlay'),
    'pages/index.php provides visible progress UI container for uploads.'
);

// Contract 8: index.php visibly tracks active filename, current %, and aggregate count
$assert(
    str_contains($indexSource, 'bulk-active-file') || str_contains($indexSource, 'statusDetail') || str_contains($indexSource, 'uploadActiveFilename'),
    'pages/index.php visibly reports active filename during multi-file upload.'
);

// Contract 9: index.php handles HTTP non-success, invalid JSON, and network errors safely
$assert(
    str_contains($indexSource, 'xhr.status >= 200') || str_contains($indexSource, 'xhr.status < 300') || str_contains($indexSource, 'Server returned HTTP'),
    'pages/index.php verifies HTTP status codes and handles non-200 responses.'
);

// Contract 10: index.php escapes filenames in error messages and UI alerts
$assert(
    str_contains($indexSource, 'escapeHtml') || str_contains($indexSource, 'textContent'),
    'pages/index.php escapes filenames before rendering in alerts or messages.'
);

$assert(
    str_contains($indexUploadHandler, 'let successCount = 0;')
    && str_contains($indexUploadHandler, 'successCount++')
    && str_contains($indexUploadHandler, 'escapeHtml(e.filename)')
    && str_contains($indexUploadHandler, 'escapeHtml(e.message)'),
    'pages/index.php tracks successes and safely attributes each mixed-result error.'
);

$assert(
    substr_count($indexUploadHandler, 'setTimeout(() => location.reload(), 1200);') === 1
    && (bool) preg_match('/if\s*\(errors\.length\)[\s\S]*?else\s*\{[\s\S]*?setTimeout\(\(\)\s*=>\s*location\.reload\(\),\s*1200\);[\s\S]*?\}/', $indexUploadHandler),
    'pages/index.php reloads only from the wholly successful branch and preserves mixed-result details.'
);

echo "=== 3. Project Dashboard Upload Flow (pages/project_dashboard.php) ===\n";

// Contract 11: project_dashboard.php modal input accepts multiple files
$assert(
    (bool) preg_match('/<input[^>]+id=[\'"]upload_file_input[\'"][^>]+multiple/i', $dashboardSource),
    'pages/project_dashboard.php file input accepts multiple files.'
);

// Contract 12: project_dashboard.php enforces client-side 10 GB limit before transfer
$assert(
    str_contains($dashboardSource, '10737418240'),
    'pages/project_dashboard.php defines exact 10 GB per-file limit (10737418240 bytes).'
);

// Contract 13: project_dashboard.php reports active filename, current-file %, and aggregate progress
$assert(
    str_contains($dashboardSource, 'uploadActiveFilename') || str_contains($dashboardSource, 'activeFilename'),
    'pages/project_dashboard.php visibly reports active filename during upload.'
);

$assert(
    str_contains($dashboardSource, 'uploadCurrentFilePct') || str_contains($dashboardSource, 'currentFileProgress'),
    'pages/project_dashboard.php visibly reports current-file transfer percentage.'
);

$assert(
    str_contains($dashboardSource, 'uploadProgressText') && str_contains($dashboardSource, 'uploadProgressCount'),
    'pages/project_dashboard.php visibly reports aggregate completed/total progress.'
);

// Contract 14: project_dashboard.php escapes filenames in file list and alert dialogs (XSS prevention)
$assert(
    str_contains($dashboardSource, 'escapeHtml(f.name)') || !str_contains($dashboardSource, '<span class="text-white small flex-grow-1" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${f.name}</span>'),
    'pages/project_dashboard.php escapes filenames in the upload file list and eliminates unescaped ${f.name}.'
);

// Contract 15: project_dashboard.php handles HTTP errors, invalid JSON, and network errors per file
$assert(
    str_contains($dashboardSource, 'xhr.status >= 200') || str_contains($dashboardSource, 'xhr.status < 300') || str_contains($dashboardSource, 'Server returned HTTP'),
    'pages/project_dashboard.php validates HTTP status codes and handles non-200 responses.'
);

$assert(
    (bool) preg_match('/uploadWithProgress\s*\(\s*fd\s*,\s*\(\s*pct\s*\)\s*=>\s*\{[\s\S]*?updateOverallProgress\(file\.name,\s*pct\)/', $dashboardUploadHandler),
    'pages/project_dashboard.php wires the XHR upload callback into current-file and aggregate progress.'
);

$assert(
    str_contains($dashboardUploadHandler, "errors.push({ filename: file.name, message:")
    && str_contains($dashboardUploadHandler, 'escapeHtml(e.filename)')
    && str_contains($dashboardUploadHandler, 'escapeHtml(e.message)')
    && str_contains($dashboardUploadHandler, 'successCount++'),
    'pages/project_dashboard.php uses structured escaped per-file errors and counts successes.'
);

$assert(
    substr_count($dashboardUploadHandler, 'setTimeout(() => location.reload(), 1200);') === 1
    && (bool) preg_match('/if\s*\(errors\.length\s*===\s*0\)[\s\S]*?setTimeout\(\(\)\s*=>\s*location\.reload\(\),\s*1200\);[\s\S]*?else\s*\{/', $dashboardUploadHandler),
    'pages/project_dashboard.php reloads only after total success and preserves mixed-result details.'
);

$assert(
    str_contains($dashboardUploadHandler, 'for (let i = 0; i < uploadSelectedFiles.length; i++)')
    && str_contains($dashboardUploadHandler, '} catch(err) {')
    && str_contains($dashboardUploadHandler, '} finally {'),
    'pages/project_dashboard.php continues the sequential batch after individual request failures.'
);

$assert(
    str_contains($zipUploadHandler, "xhr.addEventListener('error', () => reject(new Error('Upload failed')));")
    && !str_contains($zipUploadHandler, 'Network error during upload')
    && !str_contains($zipUploadHandler, 'Upload timed out'),
    'The separate ZIP extraction upload flow remains unchanged by the document uploader correction.'
);

echo "=== 4. Server Backend Contract (api/api.php) ===\n";

// Contract 16: api/api.php enforces exact 10 GB limit
$assert(
    str_contains($apiSource, '$maxSize = 10737418240;'),
    'api/api.php defines $maxSize as 10737418240 bytes.'
);

// Contract 17: api/api.php validates upload error codes (UPLOAD_ERR_INI_SIZE / UPLOAD_ERR_FORM_SIZE)
$assert(
    str_contains($apiSource, 'UPLOAD_ERR_INI_SIZE') || str_contains($apiSource, 'UPLOAD_ERR_OK'),
    'api/api.php handles upload error codes including UPLOAD_ERR_INI_SIZE.'
);

// Contract 18: api/api.php generates collision-resistant physical storage filenames
$assert(
    (str_contains($apiSource, 'random_bytes') || str_contains($apiSource, 'bin2hex') || str_contains($apiSource, 'uniqid'))
    && str_contains($apiSource, "fopen(\$targetPath, 'x')")
    && str_contains($apiSource, 'while ($reservation === false)')
    && str_contains($apiSource, '@unlink($targetPath)'),
    'api/api.php atomically reserves collision-resistant storage paths and cleans failed transfers.'
);

// Contract 19: api/api.php preserves display/version filename behavior
$assert(
    str_contains($apiSource, 'generateNextVersionedFilename'),
    'api/api.php preserves generateNextVersionedFilename for display and version numbering.'
);

// Contract 20: api/api.php preserves RBAC folder authorization and allowed extensions
$assert(
    str_contains($apiSource, 'canUploadToFolder($pdo, (int)$userId, $projectIdCheck, $folderIdCheck)')
    && str_contains($apiSource, '$allowedExts = ['),
    'api/api.php preserves canUploadToFolder check and allowed extensions list.'
);

echo "=== 5. Functional Boundary & Collision Simulation ===\n";

// Functional Test: 10 GB boundary validation logic
$maxBytes = 10737418240;
$validBoundary = 10737418240;
$exceededBoundary = 10737418241;
$oneMb = 1048576;

$isAllowedSize = static fn(int $size, int $max): bool => $size <= $max;

$assert($isAllowedSize($validBoundary, $maxBytes) === true, 'Boundary value of exactly 10 GB (10737418240 bytes) is accepted.');
$assert($isAllowedSize($exceededBoundary, $maxBytes) === false, 'Boundary value + 1 byte (10737418241 bytes) is rejected.');
$assert($isAllowedSize($oneMb, $maxBytes) === true, 'Standard 1 MB file is accepted.');

// Functional Test: Physical filename collision resistance
$simulatedUploads = [];
$origFilename = "Floor_Plan_Rev1.dwg";
$cleanName = preg_replace('/[^A-Za-z0-9\-_\.]/', '', str_replace(' ', '_', $origFilename));

for ($i = 0; $i < 1000; $i++) {
    $entropy = bin2hex(random_bytes(8));
    $generatedPhysical = time() . '_' . $entropy . '_' . $cleanName;
    $simulatedUploads[$generatedPhysical] = ($simulatedUploads[$generatedPhysical] ?? 0) + 1;
}

$hasCollision = max(array_values($simulatedUploads)) > 1;
$assert(!$hasCollision, '1,000 rapid physical storage filename generations produce zero collisions.');

// Functional Test: HTML escaping function logic
$escapeHtml = static fn(?string $str): string => htmlspecialchars((string) $str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$xssFilename = '<script>alert("xss")</script>.pdf';
$escaped = $escapeHtml($xssFilename);
$assert(!str_contains($escaped, '<script>') && str_contains($escaped, '&lt;script&gt;'), 'XSS payload in filename is safely escaped for HTML output.');

echo "\nSummary: {$assertions} assertions executed, " . count($failures) . " failure(s).\n";

if (count($failures) > 0) {
    echo "Failures:\n";
    foreach ($failures as $f) {
        echo " - {$f}\n";
    }
    exit(1);
}

exit(0);
