<?php
declare(strict_types=1);

// API v1 configuration loader
// Fail-closed HMAC credential loader:
// 1. Environment variables take precedence.
// 2. Private PHP configuration file outside repo/document root.
// 3. Fail-closed defaults.

$getEnv = static function (string $key): ?string {
    $val = getenv($key);
    if ($val !== false) {
        $val = trim((string)$val);
        if ($val !== '') {
            return $val;
        }
    }
    if (isset($_ENV[$key])) {
        $val = trim((string)$_ENV[$key]);
        if ($val !== '') {
            return $val;
        }
    }
    if (isset($_SERVER[$key])) {
        $val = trim((string)$_SERVER[$key]);
        if ($val !== '') {
            return $val;
        }
    }
    return null;
};

$getEnvVar = static function (string $key) use ($getEnv): ?string {
    return $getEnv($key) ?? $getEnv('ELECTROPLAN_' . $key);
};

$privateConfigFile = $getEnvVar('PRIVATE_CONFIG');

$repoRoot = dirname(__DIR__, 2);
$parentDir = dirname($repoRoot);

if ($privateConfigFile === null || $privateConfigFile === '') {
    $defaultCandidates = [
        $parentDir . '/electroplan_hmac.php',
        $parentDir . '/electroplan_private.php',
        $parentDir . '/electroplan_config.php',
        $parentDir . '/private_config.php',
        $parentDir . '/config/electroplan_hmac.php',
        $parentDir . '/config/private.php',
        '/etc/electroplan/api_config.php',
        '/etc/electroplan/hmac.php',
    ];
    foreach ($defaultCandidates as $candidate) {
        if (is_file($candidate)) {
            $privateConfigFile = $candidate;
            break;
        }
    }
}

$privateConfig = [];
if ($privateConfigFile !== null && is_file($privateConfigFile)) {
    $realPrivate = realpath($privateConfigFile);
    $isSafe = ($realPrivate !== false);

    if ($isSafe && !empty($_SERVER['DOCUMENT_ROOT'])) {
        $realDoc = realpath($_SERVER['DOCUMENT_ROOT']);
        if ($realDoc !== false) {
            $normDoc = str_replace('\\', '/', strtolower($realDoc));
            $normDocPrefix = rtrim($normDoc, '/') . '/';
            $normPrivate = str_replace('\\', '/', strtolower($realPrivate));
            if ($normPrivate === $normDoc || strncmp($normPrivate, $normDocPrefix, strlen($normDocPrefix)) === 0) {
                $isSafe = false;
            }
        }
    }

    if ($isSafe) {
        try {
            $loaded = require $privateConfigFile;
            if (is_array($loaded)) {
                $privateConfig = $loaded;
            }
        } catch (\Throwable $e) {
            $privateConfig = [];
        }
    }
}

$activeKeyId = $getEnvVar('ACTIVE_KEY_ID')
    ?? ($privateConfig['ACTIVE_KEY_ID'] ?? ($privateConfig['active_key_id'] ?? null));

if ($activeKeyId === null && isset($privateConfig['CLIENTS']) && is_array($privateConfig['CLIENTS']) && !empty($privateConfig['CLIENTS'])) {
    reset($privateConfig['CLIENTS']);
    $firstKey = key($privateConfig['CLIENTS']);
    if (is_string($firstKey) && $firstKey !== '') {
        $activeKeyId = $firstKey;
    }
}
$activeKeyId = is_string($activeKeyId) ? trim($activeKeyId) : null;
if ($activeKeyId === '') {
    $activeKeyId = null;
}

$activeKeySecret = $getEnvVar('ACTIVE_KEY_SECRET')
    ?? ($privateConfig['ACTIVE_KEY_SECRET'] ?? ($privateConfig['active_key_secret'] ?? null));

if ($activeKeySecret === null && $activeKeyId !== null && isset($privateConfig['CLIENTS'][$activeKeyId])) {
    $activeKeySecret = (string)$privateConfig['CLIENTS'][$activeKeyId];
}
$activeKeySecret = is_string($activeKeySecret) && $activeKeySecret !== '' ? $activeKeySecret : null;

$prevKeyId = $getEnvVar('PREVIOUS_KEY_ID')
    ?? ($privateConfig['PREVIOUS_KEY_ID'] ?? ($privateConfig['previous_key_id'] ?? null));
$prevKeyId = is_string($prevKeyId) ? trim($prevKeyId) : null;
if ($prevKeyId === '') {
    $prevKeyId = null;
}

$prevKeySecret = $getEnvVar('PREVIOUS_KEY_SECRET')
    ?? ($privateConfig['PREVIOUS_KEY_SECRET'] ?? ($privateConfig['previous_key_secret'] ?? null));
$prevKeySecret = is_string($prevKeySecret) && $prevKeySecret !== '' ? $prevKeySecret : null;

$rawPrevAcceptUntil = $getEnvVar('PREVIOUS_ACCEPT_UNTIL')
    ?? ($privateConfig['PREVIOUS_ACCEPT_UNTIL'] ?? ($privateConfig['previous_accept_until'] ?? null));

$prevAcceptUntil = null;
if ($rawPrevAcceptUntil !== null && $rawPrevAcceptUntil !== '') {
    if (is_int($rawPrevAcceptUntil)) {
        $prevAcceptUntil = $rawPrevAcceptUntil > 0 ? $rawPrevAcceptUntil : null;
    } elseif (is_numeric($rawPrevAcceptUntil)) {
        $intVal = (int)$rawPrevAcceptUntil;
        $prevAcceptUntil = $intVal > 0 ? $intVal : null;
    } elseif (is_string($rawPrevAcceptUntil)) {
        $parsed = strtotime($rawPrevAcceptUntil);
        $prevAcceptUntil = ($parsed !== false && $parsed > 0) ? $parsed : null;
    }
}

$rawSkew = $getEnvVar('MAX_TIMESTAMP_SKEW_SECONDS')
    ?? $getEnv('MAX_TIMESTAMP_SKEW')
    ?? ($privateConfig['MAX_TIMESTAMP_SKEW_SECONDS'] ?? ($privateConfig['max_timestamp_skew_seconds'] ?? ($privateConfig['MAX_TIMESTAMP_SKEW'] ?? 300)));

$maxTimestampSkew = 300;
if ($rawSkew !== null && is_numeric($rawSkew)) {
    $intSkew = (int)$rawSkew;
    if ($intSkew > 0) {
        $maxTimestampSkew = $intSkew;
    }
}

$clientRoles = [];
if (isset($privateConfig['CLIENT_ROLES']) && is_array($privateConfig['CLIENT_ROLES'])) {
    $clientRoles = $privateConfig['CLIENT_ROLES'];
} elseif (isset($privateConfig['client_roles']) && is_array($privateConfig['client_roles'])) {
    $clientRoles = $privateConfig['client_roles'];
}

$clients = [];
if ($activeKeyId !== null && $activeKeySecret !== null) {
    $clients[$activeKeyId] = $activeKeySecret;
}

return [
    'ACTIVE_KEY_ID' => $activeKeyId,
    'ACTIVE_KEY_SECRET' => $activeKeySecret,
    'PREVIOUS_KEY_ID' => $prevKeyId,
    'PREVIOUS_KEY_SECRET' => $prevKeySecret,
    'PREVIOUS_ACCEPT_UNTIL' => $prevAcceptUntil,
    'MAX_TIMESTAMP_SKEW_SECONDS' => $maxTimestampSkew,
    'MAX_TIMESTAMP_SKEW' => $maxTimestampSkew,
    'PRIVATE_CONFIG' => $privateConfigFile,
    'CLIENTS' => $clients,
    'CLIENT_ROLES' => $clientRoles,
];
