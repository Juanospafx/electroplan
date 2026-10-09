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

$repoRoot = dirname(__DIR__, 2);

$isInsideOrEqual = static function (string $targetPath, string $basePath): bool {
    $realTarget = realpath($targetPath);
    $realBase = realpath($basePath);
    if ($realTarget === false || $realBase === false) {
        return false;
    }
    $normTarget = str_replace('\\', '/', $realTarget);
    $normBase = str_replace('\\', '/', $realBase);
    if (DIRECTORY_SEPARATOR === '\\') {
        $normTarget = strtolower($normTarget);
        $normBase = strtolower($normBase);
    }
    $normBase = rtrim($normBase, '/');
    if ($normTarget === $normBase) {
        return true;
    }
    $prefix = $normBase . '/';
    return (strncmp($normTarget, $prefix, strlen($prefix)) === 0);
};

$isPrivateConfigDefined = (getenv('WON_PROJECT_PRIVATE_CONFIG') !== false)
    || isset($_ENV['WON_PROJECT_PRIVATE_CONFIG'])
    || isset($_SERVER['WON_PROJECT_PRIVATE_CONFIG']);

$privateConfigFile = null;
$privateConfig = [];

if ($isPrivateConfigDefined) {
    $rawVal = getenv('WON_PROJECT_PRIVATE_CONFIG');
    if ($rawVal === false && isset($_ENV['WON_PROJECT_PRIVATE_CONFIG'])) {
        $rawVal = (string)$_ENV['WON_PROJECT_PRIVATE_CONFIG'];
    }
    if (($rawVal === false || $rawVal === null) && isset($_SERVER['WON_PROJECT_PRIVATE_CONFIG'])) {
        $rawVal = (string)$_SERVER['WON_PROJECT_PRIVATE_CONFIG'];
    }

    $candidatePath = is_string($rawVal) ? trim($rawVal) : '';
    if ($candidatePath === '' || !file_exists($candidatePath) || !is_file($candidatePath) || !is_readable($candidatePath)) {
        throw new \RuntimeException('Configuration error');
    }

    $realPrivate = realpath($candidatePath);
    if ($realPrivate === false) {
        throw new \RuntimeException('Configuration error');
    }

    if ($isInsideOrEqual($realPrivate, $repoRoot)) {
        throw new \RuntimeException('Configuration error');
    }

    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        if ($isInsideOrEqual($realPrivate, (string)$_SERVER['DOCUMENT_ROOT'])) {
            throw new \RuntimeException('Configuration error');
        }
    }

    try {
        $loaded = require $realPrivate;
    } catch (\Throwable $e) {
        throw new \RuntimeException('Configuration error');
    }

    if (!is_array($loaded)) {
        throw new \RuntimeException('Configuration error');
    }

    $privateConfigFile = $realPrivate;
    $privateConfig = $loaded;
}

$activeKeyId = $getEnv('WON_PROJECT_HMAC_ACTIVE_KEY_ID')
    ?? (isset($privateConfig['WON_PROJECT_HMAC_ACTIVE_KEY_ID']) && is_string($privateConfig['WON_PROJECT_HMAC_ACTIVE_KEY_ID'])
        ? $privateConfig['WON_PROJECT_HMAC_ACTIVE_KEY_ID']
        : null);
$activeKeyId = is_string($activeKeyId) ? trim($activeKeyId) : null;
if ($activeKeyId === '') {
    $activeKeyId = null;
}

$activeKeySecret = $getEnv('WON_PROJECT_HMAC_ACTIVE_SECRET')
    ?? (isset($privateConfig['WON_PROJECT_HMAC_ACTIVE_SECRET']) && is_string($privateConfig['WON_PROJECT_HMAC_ACTIVE_SECRET'])
        ? $privateConfig['WON_PROJECT_HMAC_ACTIVE_SECRET']
        : null);
$activeKeySecret = is_string($activeKeySecret) && trim($activeKeySecret) !== '' ? trim($activeKeySecret) : null;

// Fail-closed: partial active configuration is invalidated
if ($activeKeyId === null || $activeKeySecret === null) {
    $activeKeyId = null;
    $activeKeySecret = null;
}

$prevKeyId = $getEnv('WON_PROJECT_HMAC_PREVIOUS_KEY_ID')
    ?? (isset($privateConfig['WON_PROJECT_HMAC_PREVIOUS_KEY_ID']) && is_string($privateConfig['WON_PROJECT_HMAC_PREVIOUS_KEY_ID'])
        ? $privateConfig['WON_PROJECT_HMAC_PREVIOUS_KEY_ID']
        : null);
$prevKeyId = is_string($prevKeyId) ? trim($prevKeyId) : null;
if ($prevKeyId === '') {
    $prevKeyId = null;
}

$prevKeySecret = $getEnv('WON_PROJECT_HMAC_PREVIOUS_SECRET')
    ?? (isset($privateConfig['WON_PROJECT_HMAC_PREVIOUS_SECRET']) && is_string($privateConfig['WON_PROJECT_HMAC_PREVIOUS_SECRET'])
        ? $privateConfig['WON_PROJECT_HMAC_PREVIOUS_SECRET']
        : null);
$prevKeySecret = is_string($prevKeySecret) && trim($prevKeySecret) !== '' ? trim($prevKeySecret) : null;

$rawPrevAcceptUntil = $getEnv('WON_PROJECT_HMAC_PREVIOUS_ACCEPT_UNTIL')
    ?? ($privateConfig['WON_PROJECT_HMAC_PREVIOUS_ACCEPT_UNTIL'] ?? null);

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

// Fail-closed: partial or invalid previous configuration is invalidated
if ($prevKeyId === null || $prevKeySecret === null || $prevAcceptUntil === null) {
    $prevKeyId = null;
    $prevKeySecret = null;
    $prevAcceptUntil = null;
}

$rawSkew = $getEnv('WON_PROJECT_MAX_TIMESTAMP_SKEW_SECONDS')
    ?? ($privateConfig['WON_PROJECT_MAX_TIMESTAMP_SKEW_SECONDS'] ?? null);

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
} elseif (isset($privateConfig['WON_PROJECT_CLIENT_ROLES']) && is_array($privateConfig['WON_PROJECT_CLIENT_ROLES'])) {
    $clientRoles = $privateConfig['WON_PROJECT_CLIENT_ROLES'];
}

$clients = [];
if ($activeKeyId !== null && $activeKeySecret !== null) {
    $clients[$activeKeyId] = $activeKeySecret;
}

return [
    'WON_PROJECT_HMAC_ACTIVE_KEY_ID' => $activeKeyId,
    'WON_PROJECT_HMAC_ACTIVE_SECRET' => $activeKeySecret,
    'WON_PROJECT_HMAC_PREVIOUS_KEY_ID' => $prevKeyId,
    'WON_PROJECT_HMAC_PREVIOUS_SECRET' => $prevKeySecret,
    'WON_PROJECT_HMAC_PREVIOUS_ACCEPT_UNTIL' => $prevAcceptUntil,
    'WON_PROJECT_MAX_TIMESTAMP_SKEW_SECONDS' => $maxTimestampSkew,
    'WON_PROJECT_PRIVATE_CONFIG' => $privateConfigFile,
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
