<?php
// db.php

if (isset($injectedPdo) && $injectedPdo instanceof PDO) {
    $pdo = $injectedPdo;
    return;
}
if (isset($pdo) && $pdo instanceof PDO) {
    return;
}

$host = 'localhost';
$db   = 'brightro_electroplan_v2';
$user = '';
$pass = '';
$port = '3306';
$charset = 'utf8mb4';

$localConfig = dirname(__DIR__, 2) . '/config.local.php';
if (file_exists($localConfig)) {
    $cfg = @include $localConfig;
    if (is_array($cfg)) {
        $host = $cfg['host'] ?? $host;
        $db   = $cfg['name'] ?? ($cfg['database'] ?? ($cfg['db'] ?? $db));
        $user = $cfg['user'] ?? ($cfg['username'] ?? $user);
        $pass = $cfg['password'] ?? ($cfg['pass'] ?? $pass);
        $port = $cfg['port'] ?? $port;
        $charset = $cfg['charset'] ?? $charset;
    }
}

$isPrivateConfigDefined = (getenv('ELECTROPLAN_DB_PRIVATE_CONFIG') !== false)
    || isset($_ENV['ELECTROPLAN_DB_PRIVATE_CONFIG'])
    || isset($_SERVER['ELECTROPLAN_DB_PRIVATE_CONFIG']);

if ($isPrivateConfigDefined) {
    $privateConfigPath = null;
    $rawEnv = getenv('ELECTROPLAN_DB_PRIVATE_CONFIG');
    if ($rawEnv !== false && trim((string)$rawEnv) !== '') {
        $privateConfigPath = trim((string)$rawEnv);
    } elseif (isset($_ENV['ELECTROPLAN_DB_PRIVATE_CONFIG']) && is_scalar($_ENV['ELECTROPLAN_DB_PRIVATE_CONFIG']) && trim((string)$_ENV['ELECTROPLAN_DB_PRIVATE_CONFIG']) !== '') {
        $privateConfigPath = trim((string)$_ENV['ELECTROPLAN_DB_PRIVATE_CONFIG']);
    } elseif (isset($_SERVER['ELECTROPLAN_DB_PRIVATE_CONFIG']) && is_scalar($_SERVER['ELECTROPLAN_DB_PRIVATE_CONFIG']) && trim((string)$_SERVER['ELECTROPLAN_DB_PRIVATE_CONFIG']) !== '') {
        $privateConfigPath = trim((string)$_SERVER['ELECTROPLAN_DB_PRIVATE_CONFIG']);
    }

    if ($privateConfigPath === null || !file_exists($privateConfigPath) || !is_file($privateConfigPath)) {
        throw new \RuntimeException('Database configuration error: configuration file not found or unreadable');
    }

    $realFilePath = realpath($privateConfigPath);
    if ($realFilePath === false) {
        throw new \RuntimeException('Database configuration error: configuration file not found or unreadable');
    }

    $roots = [];
    $repoRoot = realpath(dirname(__DIR__, 2));
    if ($repoRoot !== false) {
        $roots[] = $repoRoot;
    }

    $docRootRaw = null;
    if (isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT']) && trim($_SERVER['DOCUMENT_ROOT']) !== '') {
        $docRootRaw = trim($_SERVER['DOCUMENT_ROOT']);
    } elseif (getenv('DOCUMENT_ROOT') !== false) {
        $envDocRoot = trim((string)getenv('DOCUMENT_ROOT'));
        if ($envDocRoot !== '') {
            $docRootRaw = $envDocRoot;
        }
    }

    if ($docRootRaw !== null) {
        $docRoot = realpath($docRootRaw);
        if ($docRoot !== false) {
            $roots[] = $docRoot;
        } else {
            $roots[] = $docRootRaw;
        }
    }

    $pathsToCheck = [$realFilePath];
    $dir = dirname($privateConfigPath);
    $realDir = realpath($dir);
    if ($realDir !== false) {
        $pathsToCheck[] = $realDir . DIRECTORY_SEPARATOR . basename($privateConfigPath);
    }

    $isPathInside = static function (string $path, string $root): bool {
        $normPath = str_replace('\\', '/', $path);
        $normRoot = rtrim(str_replace('\\', '/', $root), '/');

        if (DIRECTORY_SEPARATOR === '\\' || PHP_OS_FAMILY === 'Windows') {
            $normPath = strtolower($normPath);
            $normRoot = strtolower($normRoot);
        }

        if ($normPath === $normRoot) {
            return true;
        }

        $prefix = $normRoot . '/';
        if (function_exists('str_starts_with')) {
            return str_starts_with($normPath, $prefix);
        }
        return strncmp($normPath, $prefix, strlen($prefix)) === 0;
    };

    foreach ($roots as $root) {
        foreach ($pathsToCheck as $checkPath) {
            if ($isPathInside($checkPath, $root)) {
                throw new \RuntimeException('Database configuration error: configuration file in disallowed location');
            }
        }
    }

    if (!is_readable($privateConfigPath)) {
        throw new \RuntimeException('Database configuration error: configuration file not found or unreadable');
    }

    try {
        $loaded = (static function (string $filePath) {
            return require $filePath;
        })($privateConfigPath);
    } catch (\Throwable $e) {
        throw new \RuntimeException('Database configuration error: invalid configuration file format');
    }

    if (!is_array($loaded)) {
        throw new \RuntimeException('Database configuration error: invalid configuration file format');
    }

    $host = $loaded['host'] ?? ($loaded['DB_HOST'] ?? $host);
    $db   = $loaded['name'] ?? ($loaded['database'] ?? ($loaded['db'] ?? ($loaded['DB_NAME'] ?? $db)));
    $user = $loaded['user'] ?? ($loaded['username'] ?? ($loaded['DB_USER'] ?? $user));
    $pass = $loaded['password'] ?? ($loaded['pass'] ?? ($loaded['DB_PASS'] ?? $pass));
    $port = $loaded['port'] ?? ($loaded['DB_PORT'] ?? $port);
    $charset = $loaded['charset'] ?? ($loaded['DB_CHARSET'] ?? $charset);
}

if (class_exists('DatabaseConfig') && method_exists('DatabaseConfig', 'load')) {
    $config = DatabaseConfig::load();
    if (is_array($config)) {
        $host = $config['host'] ?? $host;
        $db   = $config['name'] ?? ($config['db'] ?? $db);
        $user = $config['user'] ?? $user;
        $pass = $config['password'] ?? ($config['pass'] ?? $pass);
        $port = $config['port'] ?? $port;
        $charset = $config['charset'] ?? $charset;
    }
}

$getEnvVal = static function (string $key): ?string {
    $val = getenv($key);
    if ($val !== false && trim((string)$val) !== '') {
        return trim((string)$val);
    }
    if (isset($_ENV[$key]) && is_scalar($_ENV[$key]) && trim((string)$_ENV[$key]) !== '') {
        return trim((string)$_ENV[$key]);
    }
    if (isset($_SERVER[$key]) && is_scalar($_SERVER[$key]) && trim((string)$_SERVER[$key]) !== '') {
        return trim((string)$_SERVER[$key]);
    }
    return null;
};

if ($getEnvVal('DB_HOST') !== null) {
    $host = $getEnvVal('DB_HOST');
}
if ($getEnvVal('DB_NAME') !== null) {
    $db = $getEnvVal('DB_NAME');
}
if ($getEnvVal('DB_USER') !== null) {
    $user = $getEnvVal('DB_USER');
}
if ($getEnvVal('DB_PASS') !== null) {
    $pass = $getEnvVal('DB_PASS');
}
if ($getEnvVal('DB_PORT') !== null) {
    $port = $getEnvVal('DB_PORT');
}
if ($getEnvVal('DB_CHARSET') !== null) {
    $charset = $getEnvVal('DB_CHARSET');
}

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    if (class_exists('DatabaseConfig') && method_exists('DatabaseConfig', 'createPdo')) {
        $pdo = DatabaseConfig::createPdo($dsn, $user, $pass, $options);
    } else {
        $pdo = new PDO($dsn, $user, $pass, $options);
    }
} catch (\Throwable $e) {
    throw new \PDOException('Database connection failed');
}