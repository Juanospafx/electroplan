<?php
// db.php

if (isset($injectedPdo) && $injectedPdo instanceof PDO) {
    $pdo = $injectedPdo;
    return;
}
if (isset($pdo) && $pdo instanceof PDO) {
    return;
}

$host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? ($_SERVER['DB_HOST'] ?? 'localhost'));
$db   = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? ($_SERVER['DB_NAME'] ?? 'brightro_electroplan_v2'));
$user = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? ($_SERVER['DB_USER'] ?? ''));
$pass = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? ($_SERVER['DB_PASS'] ?? ''));
$port = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? ($_SERVER['DB_PORT'] ?? '3306'));
$charset = getenv('DB_CHARSET') ?: ($_ENV['DB_CHARSET'] ?? ($_SERVER['DB_CHARSET'] ?? 'utf8mb4'));

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

if (class_exists('DatabaseConfig')) {
    $config = DatabaseConfig::load();
    $host = $config['host'] ?? $host;
    $db   = $config['name'] ?? ($config['db'] ?? $db);
    $user = $config['user'] ?? $user;
    $pass = $config['password'] ?? ($config['pass'] ?? $pass);
    $port = $config['port'] ?? $port;
    $charset = $config['charset'] ?? $charset;
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
} catch (\PDOException $e) {
    throw new \PDOException('Database connection failed');
}
?>