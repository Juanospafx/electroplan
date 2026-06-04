<?php

function normalize_file_path(?string $filepath): string {
    $p = str_replace('\\', '/', trim((string)$filepath));
    if ($p === '') return '';

    $prefixes = [
        'https://androidelectro.brightronix.net/electroplan/',
        'http://androidelectro.brightronix.net/electroplan/',
        'https://electroplan.brightronix.net/electroplan/',
        'http://electroplan.brightronix.net/electroplan/',
        '/electroplan/',
    ];
    foreach ($prefixes as $pre) {
        if (stripos($p, $pre) === 0) {
            $p = substr($p, strlen($pre));
            break;
        }
    }

    if (preg_match('~(?:^|/)(api/)?uploads/.+$~i', $p, $m)) {
        $p = ltrim($m[0], '/');
    }

    $p = preg_replace('~^(\./|\.\./)+~', '', $p);
    return ltrim($p, '/');
}

function get_file_url(int $fileId): string {
    return 'file_proxy.php?id=' . $fileId;
}

function resolve_file_disk_path(?string $filepath, ?string $filename = null): ?string {
    $relativePath = normalize_file_path($filepath);
    $filename = trim((string)$filename);
    $baseDir = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
    $docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');

    $candidates = [];
    if ($relativePath !== '') {
        $candidates[] = $baseDir . '/' . $relativePath;
        $candidates[] = $baseDir . '/uploads/' . basename($relativePath);
    }
    if ($filename !== '') {
        $candidates[] = $baseDir . '/uploads/' . basename($filename);
    }
    if ($docRoot !== '' && $relativePath !== '') {
        $candidates[] = $docRoot . '/electroplan/' . $relativePath;
        $candidates[] = $docRoot . '/electroplan/uploads/' . basename($relativePath);
    }
    if ($docRoot !== '' && $filename !== '') {
        $candidates[] = $docRoot . '/electroplan/uploads/' . basename($filename);
    }

    foreach (array_unique($candidates) as $candidate) {
        if (is_file($candidate)) return $candidate;
    }
    return null;
}

function detect_file_mime(string $path, ?string $fallback = null): string {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $path) : false;
    if ($finfo) finfo_close($finfo);
    if (is_string($mime) && strpos($mime, '/') !== false) return $mime;
    return is_string($fallback) && strpos($fallback, '/') !== false ? $fallback : 'application/octet-stream';
}
