<?php
declare(strict_types=1);

use App\Lib\Csrf;

function base_url(string $path = ''): string
{
    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $requestPath = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

    $base = '';

    // Preferred: running through /public/index.php
    if (strpos($scriptName, '/public/index.php') !== false) {
        $base = rtrim(dirname($scriptName), '/');
    }

    // Fallback: infer /public from pretty URL (e.g. /.../public/projects)
    if ($base === '' && preg_match('#^(.*?/public)(?:/.*)?$#', $requestPath, $m)) {
        $base = rtrim($m[1], '/');
    }

    // Last fallback: dirname(script)
    if ($base === '') {
        $base = rtrim(dirname($scriptName), '/');
        if ($base === '/') $base = '';
    }

    return $base . $path;
}

function csrf_field(): string
{
    $token = Csrf::token();
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token) . '">';
}
