<?php
declare(strict_types=1);

/**
 * Common fail-closed database configuration loader for Electroplan.
 *
 * Precedence:
 * 1. Environment variables (getenv, $_ENV, $_SERVER)
 * 2. Private PHP configuration file outside repo/document root
 * 3. Fail-closed defaults with sanitized error messages
 */
class DatabaseConfig
{
    /**
     * @var (callable(string, string, string, array<int, mixed>): mixed)|null
     */
    private static $pdoFactory = null;

    /**
     * Loads and validates database configuration.
     *
     * @return array{
     *     host: string,
     *     port: int,
     *     name: string,
     *     user: string,
     *     password: string,
     *     charset: string,
     *     ELECTROPLAN_DB_HOST: string,
     *     ELECTROPLAN_DB_PORT: int,
     *     ELECTROPLAN_DB_NAME: string,
     *     ELECTROPLAN_DB_USER: string,
     *     ELECTROPLAN_DB_PASSWORD: string
     * }
     * @throws \RuntimeException on missing, unreadable, malformed, or incomplete configuration.
     */
    public static function load(): array
    {
        $isPrivateConfigDefined = (getenv('ELECTROPLAN_DB_PRIVATE_CONFIG') !== false)
            || isset($_ENV['ELECTROPLAN_DB_PRIVATE_CONFIG'])
            || isset($_SERVER['ELECTROPLAN_DB_PRIVATE_CONFIG']);

        $privateConfig = [];

        if ($isPrivateConfigDefined) {
            $privateConfigPath = self::getEnvValue('ELECTROPLAN_DB_PRIVATE_CONFIG');

            if ($privateConfigPath === null || $privateConfigPath === '' || !file_exists($privateConfigPath) || !is_file($privateConfigPath) || !is_readable($privateConfigPath)) {
                throw new \RuntimeException('Database configuration error: configuration file not found or unreadable');
            }

            if (self::isDisallowedLocation($privateConfigPath)) {
                throw new \RuntimeException('Database configuration error: configuration file in disallowed location');
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

            $privateConfig = $loaded;
        }

        $host = self::getEnvValue('ELECTROPLAN_DB_HOST')
            ?? self::getFileValue($privateConfig, ['ELECTROPLAN_DB_HOST', 'DB_HOST', 'host']);

        $portRaw = self::getEnvValue('ELECTROPLAN_DB_PORT')
            ?? self::getFileValue($privateConfig, ['ELECTROPLAN_DB_PORT', 'DB_PORT', 'port']);

        $name = self::getEnvValue('ELECTROPLAN_DB_NAME')
            ?? self::getFileValue($privateConfig, ['ELECTROPLAN_DB_NAME', 'DB_NAME', 'name', 'database']);

        $user = self::getEnvValue('ELECTROPLAN_DB_USER')
            ?? self::getFileValue($privateConfig, ['ELECTROPLAN_DB_USER', 'DB_USER', 'user', 'username']);

        $password = self::getEnvValue('ELECTROPLAN_DB_PASSWORD')
            ?? self::getFileValue($privateConfig, ['ELECTROPLAN_DB_PASSWORD', 'DB_PASSWORD', 'password', 'pass']);

        // Check complete absence: no private config file defined and all env variables missing
        if (!$isPrivateConfigDefined && $host === null && $portRaw === null && $name === null && $user === null && $password === null) {
            throw new \RuntimeException('Database configuration error: configuration is missing');
        }

        // Validate port
        $port = null;
        if ($portRaw !== null && is_numeric($portRaw)) {
            $intPort = (int)$portRaw;
            if ($intPort > 0 && $intPort <= 65535) {
                $port = $intPort;
            }
        }

        // Fail-closed if any required parameter is missing or invalid
        if ($host === null || $port === null || $name === null || $user === null || $password === null) {
            throw new \RuntimeException('Database configuration error: incomplete configuration');
        }

        return [
            'host' => $host,
            'port' => $port,
            'name' => $name,
            'user' => $user,
            'password' => $password,
            'charset' => 'utf8mb4',
            'ELECTROPLAN_DB_HOST' => $host,
            'ELECTROPLAN_DB_PORT' => $port,
            'ELECTROPLAN_DB_NAME' => $name,
            'ELECTROPLAN_DB_USER' => $user,
            'ELECTROPLAN_DB_PASSWORD' => $password,
        ];
    }

    /**
     * Builds MySQL PDO DSN from configuration.
     *
     * @param array<string, mixed> $config
     */
    public static function getDsn(array $config): string
    {
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 3306;
        $name = $config['name'] ?? '';
        $charset = $config['charset'] ?? 'utf8mb4';

        return "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
    }

    /**
     * Creates a PDO instance or invokes the test factory if set.
     *
     * @param array<int, mixed> $options
     * @return mixed PDO instance or mocked connection
     */
    public static function createPdo(string $dsn, string $user, string $password, array $options = [])
    {
        if (self::$pdoFactory !== null) {
            return (self::$pdoFactory)($dsn, $user, $password, $options);
        }

        return new \PDO($dsn, $user, $password, $options);
    }

    /**
     * Injects a custom factory for creating PDO instances (useful for testing without a real database).
     *
     * @param (callable(string, string, string, array<int, mixed>): mixed)|null $factory
     */
    public static function setPdoFactory(?callable $factory): void
    {
        self::$pdoFactory = $factory;
    }

    /**
     * Resets injected factory state.
     */
    public static function reset(): void
    {
        self::$pdoFactory = null;
    }

    /**
     * Extracts a scalar environment variable value from getenv, $_ENV, or $_SERVER.
     */
    private static function getEnvValue(string $key): ?string
    {
        $val = getenv($key);
        if ($val !== false) {
            $val = trim((string)$val);
            if ($val !== '') {
                return $val;
            }
        }
        if (isset($_ENV[$key]) && is_scalar($_ENV[$key])) {
            $val = trim((string)$_ENV[$key]);
            if ($val !== '') {
                return $val;
            }
        }
        if (isset($_SERVER[$key]) && is_scalar($_SERVER[$key])) {
            $val = trim((string)$_SERVER[$key]);
            if ($val !== '') {
                return $val;
            }
        }
        return null;
    }

    /**
     * Extracts a scalar value from a config array using multiple candidate keys.
     *
     * @param array<string, mixed> $config
     * @param list<string> $keys
     */
    private static function getFileValue(array $config, array $keys): ?string
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $config) && is_scalar($config[$k])) {
                $val = trim((string)$config[$k]);
                if ($val !== '') {
                    return $val;
                }
            }
        }
        return null;
    }

    /**
     * Checks if a configuration file path resides inside repository or document root.
     */
    private static function isDisallowedLocation(string $filePath): bool
    {
        $realFilePath = realpath($filePath);
        if ($realFilePath === false) {
            return false;
        }

        $roots = [];

        // Repository root (2 levels up from core/config)
        $repoRoot = realpath(dirname(__DIR__, 2));
        if ($repoRoot !== false) {
            $roots[] = $repoRoot;
        }

        // Document root from $_SERVER or getenv, if determinable
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
            }
        }

        $pathsToCheck = [$realFilePath];
        $dir = dirname($filePath);
        $realDir = realpath($dir);
        if ($realDir !== false) {
            $pathsToCheck[] = $realDir . DIRECTORY_SEPARATOR . basename($filePath);
        }

        foreach ($roots as $root) {
            foreach ($pathsToCheck as $checkPath) {
                if (self::isPathInside($checkPath, $root)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Checks if a path is located inside a root directory.
     */
    private static function isPathInside(string $path, string $root): bool
    {
        $normPath = str_replace('\\', '/', $path);
        $normRoot = rtrim(str_replace('\\', '/', $root), '/');

        if (DIRECTORY_SEPARATOR === '\\' || PHP_OS_FAMILY === 'Windows') {
            $normPath = strtolower($normPath);
            $normRoot = strtolower($normRoot);
        }

        if ($normPath === $normRoot) {
            return true;
        }

        return str_starts_with($normPath, $normRoot . '/');
    }
}
