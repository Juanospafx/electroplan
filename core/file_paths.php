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
