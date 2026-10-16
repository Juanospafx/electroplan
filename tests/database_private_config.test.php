<?php
declare(strict_types=1);

/**
 * Test suite for Electroplan externalized database configuration.
 *
 * Covers:
 * - Absence (missing configuration) fails closed
 * - Nonexistent file path fails closed
 * - Malformed file (syntax error or non-array return) fails closed
 * - Incomplete configuration fails closed
 * - Valid configuration from file
 * - Valid configuration from environment
 * - Environment precedence over private config file
 * - Both consumers (core/db/connection.php and panel_schedule/.../database.php)
 * - Sanitized error messages (no credentials or sensitive information leaked)
 * - Deployment template verification
 */

require_once __DIR__ . '/../core/config/DatabaseConfig.php';

$testsPassed = 0;
$testsFailed = 0;

function runTest(string $name, callable $fn): void
{
    global $testsPassed, $testsFailed;
    try {
        cleanTestEnvironment();
        $fn();
        cleanTestEnvironment();
        echo "[PASS] {$name}\n";
        $testsPassed++;
    } catch (\Throwable $e) {
        cleanTestEnvironment();
        echo "[FAIL] {$name}: {$e->getMessage()}\n";
        echo "       At: {$e->getFile()}:{$e->getLine()}\n";
        $testsFailed++;
    }
}

function cleanTestEnvironment(): void
{
    $keys = [
        'ELECTROPLAN_DB_HOST',
        'ELECTROPLAN_DB_PORT',
        'ELECTROPLAN_DB_NAME',
        'ELECTROPLAN_DB_USER',
        'ELECTROPLAN_DB_PASSWORD',
        'ELECTROPLAN_DB_PRIVATE_CONFIG',
        'DOCUMENT_ROOT',
    ];

    foreach ($keys as $k) {
        putenv($k);
        unset($_ENV[$k], $_SERVER[$k]);
    }

    DatabaseConfig::reset();
}

function setTestEnv(string $key, string $value): void
{
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

function assertTrue(bool $condition, string $msg = 'Assertion failed'): void
{
    if (!$condition) {
        throw new \RuntimeException($msg);
    }
}

function assertEquals(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        $expectedStr = var_export($expected, true);
        $actualStr = var_export($actual, true);
        throw new \RuntimeException($msg ?: "Expected {$expectedStr}, got {$actualStr}");
    }
}

function assertSanitized(string $message): void
{
    assertTrue(
        str_starts_with($message, 'Database configuration error:'),
        "Error message should follow sanitized format, got: {$message}"
    );
    $forbiddenWords = ['secret', 'password', 'pass', 'fictitious_secret', 'fictitious_user'];
    $lower = strtolower($message);
    foreach ($forbiddenWords as $word) {
        assertTrue(
            !str_contains($lower, $word),
            "Sanitized message must not contain sensitive leak '{$word}': {$message}"
        );
    }
}

// 1. Ausencia: Sin variables de entorno ni archivo privado
runTest('Absence of configuration fails closed with sanitized message', function () {
    try {
        DatabaseConfig::load();
        throw new \RuntimeException('Expected exception was not thrown');
    } catch (\RuntimeException $e) {
        assertSanitized($e->getMessage());
        assertEquals('Database configuration error: configuration is missing', $e->getMessage());
    }
});

// 2. Ruta inexistente en ELECTROPLAN_DB_PRIVATE_CONFIG
runTest('Nonexistent private config file path fails closed', function () {
    $nonexistentPath = sys_get_temp_dir() . '/definitely_missing_' . uniqid() . '.php';
    setTestEnv('ELECTROPLAN_DB_PRIVATE_CONFIG', $nonexistentPath);

    try {
        DatabaseConfig::load();
        throw new \RuntimeException('Expected exception was not thrown');
    } catch (\RuntimeException $e) {
        assertSanitized($e->getMessage());
        assertEquals('Database configuration error: configuration file not found or unreadable', $e->getMessage());
    }
});

// 3. Archivo mal formado (no devuelve array)
runTest('Malformed file (non-array return) fails closed', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'db_test_malformed_');
    file_put_contents($tmpFile, '<?php return "string_instead_of_array";');
    setTestEnv('ELECTROPLAN_DB_PRIVATE_CONFIG', $tmpFile);

    try {
        try {
            DatabaseConfig::load();
            throw new \RuntimeException('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            assertSanitized($e->getMessage());
            assertEquals('Database configuration error: invalid configuration file format', $e->getMessage());
        }
    } finally {
        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }
    }
});

// 4. Archivo mal formado (error sintáctico de evaluación)
runTest('Malformed file (evaluation error) fails closed', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'db_test_syntax_');
    file_put_contents($tmpFile, '<?php throw new \Exception("parse or load fail");');
    setTestEnv('ELECTROPLAN_DB_PRIVATE_CONFIG', $tmpFile);

    try {
        try {
            DatabaseConfig::load();
            throw new \RuntimeException('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            assertSanitized($e->getMessage());
            assertEquals('Database configuration error: invalid configuration file format', $e->getMessage());
        }
    } finally {
        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }
    }
});

// 5. Configuración incompleta en archivo (faltan claves requeridas)
runTest('Incomplete configuration in file fails closed', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'db_test_incomplete_');
    file_put_contents($tmpFile, '<?php return ["ELECTROPLAN_DB_HOST" => "127.0.0.1"];');
    setTestEnv('ELECTROPLAN_DB_PRIVATE_CONFIG', $tmpFile);

    try {
        try {
            DatabaseConfig::load();
            throw new \RuntimeException('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            assertSanitized($e->getMessage());
            assertEquals('Database configuration error: incomplete configuration', $e->getMessage());
        }
    } finally {
        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }
    }
});

// 6. Configuración incompleta en entorno
runTest('Incomplete configuration in environment fails closed', function () {
    setTestEnv('ELECTROPLAN_DB_HOST', '127.0.0.1');
    setTestEnv('ELECTROPLAN_DB_PORT', '3306');
    setTestEnv('ELECTROPLAN_DB_NAME', 'testdb');
    // Faltan USER y PASSWORD

    try {
        DatabaseConfig::load();
        throw new \RuntimeException('Expected exception was not thrown');
    } catch (\RuntimeException $e) {
        assertSanitized($e->getMessage());
        assertEquals('Database configuration error: incomplete configuration', $e->getMessage());
    }
});

// 7. Carga de variables válidas desde archivo privado
runTest('Valid configuration loaded from private file', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'db_test_valid_');
    $content = <<<'PHP'
<?php
return [
    'ELECTROPLAN_DB_HOST' => '192.168.1.100',
    'ELECTROPLAN_DB_PORT' => '3307',
    'ELECTROPLAN_DB_NAME' => 'test_file_db',
    'ELECTROPLAN_DB_USER' => 'test_file_user',
    'ELECTROPLAN_DB_PASSWORD' => 'dummy_file_pass',
];
PHP;
    file_put_contents($tmpFile, $content);
    setTestEnv('ELECTROPLAN_DB_PRIVATE_CONFIG', $tmpFile);

    try {
        $config = DatabaseConfig::load();
        assertEquals('192.168.1.100', $config['host']);
        assertEquals(3307, $config['port']);
        assertEquals('test_file_db', $config['name']);
        assertEquals('test_file_user', $config['user']);
        assertEquals('dummy_file_pass', $config['password']);
        assertEquals('mysql:host=192.168.1.100;port=3307;dbname=test_file_db;charset=utf8mb4', DatabaseConfig::getDsn($config));
    } finally {
        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }
    }
});

// 8. Carga de variables válidas desde variables de entorno
runTest('Valid configuration loaded from environment', function () {
    setTestEnv('ELECTROPLAN_DB_HOST', '10.0.0.2');
    setTestEnv('ELECTROPLAN_DB_PORT', '3308');
    setTestEnv('ELECTROPLAN_DB_NAME', 'test_env_db');
    setTestEnv('ELECTROPLAN_DB_USER', 'test_env_user');
    setTestEnv('ELECTROPLAN_DB_PASSWORD', 'dummy_env_pass');

    $config = DatabaseConfig::load();
    assertEquals('10.0.0.2', $config['host']);
    assertEquals(3308, $config['port']);
    assertEquals('test_env_db', $config['name']);
    assertEquals('test_env_user', $config['user']);
    assertEquals('dummy_env_pass', $config['password']);
    assertEquals('mysql:host=10.0.0.2;port=3308;dbname=test_env_db;charset=utf8mb4', DatabaseConfig::getDsn($config));
});

// 9. Precedencia: Variables de entorno sobreescriben valores de archivo
runTest('Environment variables overrule private config file values', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'db_test_precedence_');
    $content = <<<'PHP'
<?php
return [
    'ELECTROPLAN_DB_HOST' => 'from_file_host',
    'ELECTROPLAN_DB_PORT' => '3306',
    'ELECTROPLAN_DB_NAME' => 'from_file_name',
    'ELECTROPLAN_DB_USER' => 'from_file_user',
    'ELECTROPLAN_DB_PASSWORD' => 'from_file_pass',
];
PHP;
    file_put_contents($tmpFile, $content);
    setTestEnv('ELECTROPLAN_DB_PRIVATE_CONFIG', $tmpFile);

    // Overwrite only host and password via environment
    setTestEnv('ELECTROPLAN_DB_HOST', 'from_env_host');
    setTestEnv('ELECTROPLAN_DB_PASSWORD', 'from_env_pass');

    try {
        $config = DatabaseConfig::load();
        assertEquals('from_env_host', $config['host']);
        assertEquals(3306, $config['port']);
        assertEquals('from_file_name', $config['name']);
        assertEquals('from_file_user', $config['user']);
        assertEquals('from_env_pass', $config['password']);
    } finally {
        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }
    }
});

// 10. Consumidor: core/db/connection.php sin base real
runTest('Consumer core/db/connection.php uses common loader without real DB', function () {
    setTestEnv('ELECTROPLAN_DB_HOST', 'db.local');
    setTestEnv('ELECTROPLAN_DB_PORT', '3306');
    setTestEnv('ELECTROPLAN_DB_NAME', 'app_db');
    setTestEnv('ELECTROPLAN_DB_USER', 'app_user');
    setTestEnv('ELECTROPLAN_DB_PASSWORD', 'app_pass');

    $captured = null;
    DatabaseConfig::setPdoFactory(function (string $dsn, string $user, string $pass, array $options) use (&$captured) {
        $captured = [
            'dsn' => $dsn,
            'user' => $user,
            'pass' => $pass,
            'options' => $options,
        ];
        return new class {
            public string $type = 'mock_core_pdo';
        };
    });

    // Execute consumer script
    require __DIR__ . '/../core/db/connection.php';

    assertTrue($captured !== null, 'PDO factory was not invoked by core/db/connection.php');
    assertEquals('mysql:host=db.local;port=3306;dbname=app_db;charset=utf8mb4', $captured['dsn']);
    assertEquals('app_user', $captured['user']);
    assertEquals('app_pass', $captured['pass']);
    assertTrue(isset($pdo) && is_object($pdo) && $pdo->type === 'mock_core_pdo');
});

// 11. Consumidor: panel_schedule/public_html/app/config/database.php sin base real
runTest('Consumer panel_schedule database.php uses common loader without real DB', function () {
    setTestEnv('ELECTROPLAN_DB_HOST', 'panel.local');
    setTestEnv('ELECTROPLAN_DB_PORT', '3309');
    setTestEnv('ELECTROPLAN_DB_NAME', 'panel_db');
    setTestEnv('ELECTROPLAN_DB_USER', 'panel_user');
    setTestEnv('ELECTROPLAN_DB_PASSWORD', 'panel_pass');

    $captured = null;
    DatabaseConfig::setPdoFactory(function (string $dsn, string $user, string $pass, array $options) use (&$captured) {
        $captured = [
            'dsn' => $dsn,
            'user' => $user,
            'pass' => $pass,
            'options' => $options,
        ];
        return new class {
            public string $type = 'mock_panel_pdo';
        };
    });

    // Execute consumer script
    $returnedPdo = require __DIR__ . '/../panel_schedule/public_html/app/config/database.php';

    assertTrue($captured !== null, 'PDO factory was not invoked by panel_schedule database.php');
    assertEquals('mysql:host=panel.local;port=3309;dbname=panel_db;charset=utf8mb4', $captured['dsn']);
    assertEquals('panel_user', $captured['user']);
    assertEquals('panel_pass', $captured['pass']);
    assertTrue(is_object($returnedPdo) && $returnedPdo->type === 'mock_panel_pdo');
});

// 12. Consumidores fallan cerrado ante ausencia de configuración
runTest('Both consumers fail closed when configuration is absent', function () {
    // core/db/connection.php fails closed
    $coreFailed = false;
    try {
        require __DIR__ . '/../core/db/connection.php';
    } catch (\RuntimeException $e) {
        $coreFailed = true;
        assertSanitized($e->getMessage());
    }
    assertTrue($coreFailed, 'core/db/connection.php must fail closed on missing config');

    // panel_schedule database.php fails closed
    $panelFailed = false;
    try {
        require __DIR__ . '/../panel_schedule/public_html/app/config/database.php';
    } catch (\RuntimeException $e) {
        $panelFailed = true;
        assertSanitized($e->getMessage());
    }
    assertTrue($panelFailed, 'panel_schedule database.php must fail closed on missing config');
});

// 13. Plantilla example contiene sólo placeholders no utilizables
runTest('Deployment template exists and contains only unusable placeholders', function () {
    $examplePath = __DIR__ . '/../docs/deployment/database.private.php.example';
    assertTrue(file_exists($examplePath), 'database.private.php.example must exist');

    $templateConfig = require $examplePath;
    assertTrue(is_array($templateConfig), 'Template must return an array');

    assertEquals('db.example.invalid', $templateConfig['ELECTROPLAN_DB_HOST']);
    assertEquals('3306', $templateConfig['ELECTROPLAN_DB_PORT']);
    assertEquals('electroplan_db_placeholder', $templateConfig['ELECTROPLAN_DB_NAME']);
    assertEquals('electroplan_user_placeholder', $templateConfig['ELECTROPLAN_DB_USER']);
    assertEquals('replace_with_secure_password_placeholder', $templateConfig['ELECTROPLAN_DB_PASSWORD']);

    // Verify template contains only placeholder values and fictitious markers
    $content = file_get_contents($examplePath);
    assertTrue(str_contains($content, 'db.example.invalid'), 'Template must use example.invalid domain');
    assertTrue(str_contains($content, 'electroplan_user_placeholder'), 'Template must contain user placeholder marker');
    assertTrue(str_contains($content, 'replace_with_secure_password_placeholder'), 'Template must contain password placeholder marker');
    assertTrue(!str_contains($content, 'fictitious_live_user_marker'), 'Template must not contain real credentials');
    assertTrue(!str_contains($content, 'fictitious_live_password_marker'), 'Template must not contain real password');
});

// 14. Archivo privado dentro del repositorio es rechazado sin revelar rutas
runTest('Private config file inside repository is rejected without revealing routes', function () {
    $insideRepoFile = __DIR__ . '/test_disallowed_repo_' . uniqid() . '.php';
    file_put_contents($insideRepoFile, '<?php return ["ELECTROPLAN_DB_HOST" => "127.0.0.1"];');
    setTestEnv('ELECTROPLAN_DB_PRIVATE_CONFIG', $insideRepoFile);

    try {
        try {
            DatabaseConfig::load();
            throw new \RuntimeException('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            assertSanitized($e->getMessage());
            assertEquals('Database configuration error: configuration file in disallowed location', $e->getMessage());
            assertTrue(!str_contains($e->getMessage(), $insideRepoFile), 'Error message must not reveal file path');
            assertTrue(!str_contains($e->getMessage(), 'tests'), 'Error message must not reveal repository path elements');
        }
    } finally {
        if (file_exists($insideRepoFile)) {
            unlink($insideRepoFile);
        }
    }
});

// 15. Archivo privado dentro de document root es rechazado sin revelar rutas
runTest('Private config file inside document root is rejected without revealing routes', function () {
    $tmpDocRoot = sys_get_temp_dir() . '/test_docroot_' . uniqid();
    mkdir($tmpDocRoot);
    $insideDocRootFile = $tmpDocRoot . '/test_disallowed_docroot.php';
    file_put_contents($insideDocRootFile, '<?php return ["ELECTROPLAN_DB_HOST" => "127.0.0.1"];');

    setTestEnv('DOCUMENT_ROOT', $tmpDocRoot);
    setTestEnv('ELECTROPLAN_DB_PRIVATE_CONFIG', $insideDocRootFile);

    try {
        try {
            DatabaseConfig::load();
            throw new \RuntimeException('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            assertSanitized($e->getMessage());
            assertEquals('Database configuration error: configuration file in disallowed location', $e->getMessage());
            assertTrue(!str_contains($e->getMessage(), $insideDocRootFile), 'Error message must not reveal file path');
            assertTrue(!str_contains($e->getMessage(), $tmpDocRoot), 'Error message must not reveal document root path');
        }
    } finally {
        if (file_exists($insideDocRootFile)) {
            unlink($insideDocRootFile);
        }
        if (is_dir($tmpDocRoot)) {
            rmdir($tmpDocRoot);
        }
    }
});

// 16. Fallo PDO sanitizado en consumidor core/db/connection.php
runTest('Consumer core/db/connection.php presents generic error on PDO failure and sanitizes leaks', function () {
    setTestEnv('ELECTROPLAN_DB_HOST', 'fictitious-host.internal');
    setTestEnv('ELECTROPLAN_DB_PORT', '3306');
    setTestEnv('ELECTROPLAN_DB_NAME', 'fictitious_db');
    setTestEnv('ELECTROPLAN_DB_USER', 'fictitious_user');
    setTestEnv('ELECTROPLAN_DB_PASSWORD', 'fictitious_secret_pass');

    DatabaseConfig::setPdoFactory(function (string $dsn, string $user, string $pass) {
        throw new \PDOException("SQLSTATE[HY000] [1045] Access denied for user '{$user}' with dsn '{$dsn}' in /secret/path/to/script.php", 1045);
    });

    $failed = false;
    try {
        require __DIR__ . '/../core/db/connection.php';
    } catch (\PDOException $e) {
        $failed = true;
        assertEquals('Database connection failed', $e->getMessage());
        assertEquals(0, $e->getCode());
        assertTrue($e->getPrevious() === null, 'Original PDO exception must not be propagated');
        $msg = $e->getMessage();
        assertTrue(!str_contains($msg, 'fictitious-host.internal'), 'DSN/host must not be leaked');
        assertTrue(!str_contains($msg, 'fictitious_user'), 'Username must not be leaked');
        assertTrue(!str_contains($msg, 'fictitious_secret_pass'), 'Password must not be leaked');
        assertTrue(!str_contains($msg, '1045'), 'Original code must not be leaked');
        assertTrue(!str_contains($msg, '/secret/path/'), 'File path must not be leaked');
    }
    assertTrue($failed, 'core/db/connection.php must throw generic sanitized PDOException');
});

// 17. Fallo PDO sanitizado en consumidor panel_schedule database.php
runTest('Consumer panel_schedule database.php presents generic error on PDO failure and sanitizes leaks', function () {
    setTestEnv('ELECTROPLAN_DB_HOST', 'panel-fictitious.internal');
    setTestEnv('ELECTROPLAN_DB_PORT', '3306');
    setTestEnv('ELECTROPLAN_DB_NAME', 'panel_fictitious_db');
    setTestEnv('ELECTROPLAN_DB_USER', 'panel_fictitious_user');
    setTestEnv('ELECTROPLAN_DB_PASSWORD', 'panel_fictitious_secret_pass');

    DatabaseConfig::setPdoFactory(function (string $dsn, string $user, string $pass) {
        throw new \PDOException("SQLSTATE[HY000] [1045] Access denied for user '{$user}' with dsn '{$dsn}' in /secret/path/to/panel.php", 1045);
    });

    $failed = false;
    try {
        require __DIR__ . '/../panel_schedule/public_html/app/config/database.php';
    } catch (\PDOException $e) {
        $failed = true;
        assertEquals('Database connection failed', $e->getMessage());
        assertEquals(0, $e->getCode());
        assertTrue($e->getPrevious() === null, 'Original PDO exception must not be propagated');
        $msg = $e->getMessage();
        assertTrue(!str_contains($msg, 'panel-fictitious.internal'), 'DSN/host must not be leaked');
        assertTrue(!str_contains($msg, 'panel_fictitious_user'), 'Username must not be leaked');
        assertTrue(!str_contains($msg, 'panel_fictitious_secret_pass'), 'Password must not be leaked');
        assertTrue(!str_contains($msg, '1045'), 'Original code must not be leaked');
        assertTrue(!str_contains($msg, '/secret/path/'), 'File path must not be leaked');
    }
    assertTrue($failed, 'panel_schedule database.php must throw generic sanitized PDOException');
});

echo "\nSummary: {$testsPassed} passed, {$testsFailed} failed.\n";

if ($testsFailed > 0) {
    exit(1);
}

exit(0);
