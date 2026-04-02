<?php
declare(strict_types=1);

use App\Lib\Csrf;

function base_url(string $path = ''): string
{
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    if ($base === '/') {
        $base = '';
    }
    return $base . $path;
}

function csrf_field(): string
{
    $token = Csrf::token();
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token) . '">';
}
