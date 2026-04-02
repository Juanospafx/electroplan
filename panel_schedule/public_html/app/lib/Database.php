<?php
declare(strict_types=1);

namespace App\Lib;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo) {
            return self::$pdo;
        }
        $config = require BASE_PATH . '/app/config/database.php';

        if ($config instanceof PDO) {
            self::$pdo = $config;
            return self::$pdo;
        }

        $db = $config['db'];
        $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            self::$pdo = new PDO($dsn, $db['user'], $db['pass'], $options);
        } catch (PDOException $e) {
            http_response_code(500);
            echo 'Database connection failed';
            exit;
        }

        return self::$pdo;
    }
}
