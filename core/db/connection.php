<?php
// db.php
require_once dirname(__DIR__) . '/config/DatabaseConfig.php';

$config = DatabaseConfig::load();
$host = $config['host'];
$db   = $config['name'];
$user = $config['user'];
$pass = $config['password'];
$port = $config['port'];
$charset = $config['charset'] ?? 'utf8mb4';

$dsn = DatabaseConfig::getDsn($config);
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = DatabaseConfig::createPdo($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException('Database connection failed');
}
?>