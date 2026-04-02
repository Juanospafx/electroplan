<?php
declare(strict_types=1);

namespace App\Lib;

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $relative = str_replace('App\\', '', $class);
    $relativePath = str_replace('\\', '/', $relative);
    $path = BASE_PATH . '/app/' . $relativePath . '.php';
    if (!file_exists($path)) {
        $parts = explode('/', $relativePath);
        $file = array_pop($parts);
        $parts = array_map('strtolower', $parts);
        $altPath = BASE_PATH . '/app/' . implode('/', $parts) . '/' . $file . '.php';
        if (file_exists($altPath)) {
            require_once $altPath;
        }
        return;
    }
    require_once $path;
});
