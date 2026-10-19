<?php
/**
 * Contract Test Suite: Electroplan Private Database Configuration (TASK-0350).
 *
 * Validates:
 * 1. External PHP array configuration loaded only when ELECTROPLAN_DB_PRIVATE_CONFIG is explicitly configured.
 * 2. External configuration file must be a regular, readable file outside repository and document root.
 * 3. Environment variables (DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT, DB_CHARSET) retain highest precedence.
 * 4. Compatibility preserved with config.local.php and injected PDO test seams.
 * 5. Fail-closed behavior with sanitized errors revealing no path, DSN, username, or password.
 * 6. Contract test covering valid config, precedence, missing/invalid files, and secret redaction.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Autonomous test runner
class ElectroplanPrivateDbConfigTestRunner
{
    private int $assertions = 0;
    private int $failures = 0;

    public function assert(bool $condition, string $message): void
    {
        $this->assertions++;
        if (!$condition) {
            $this->failures++;
            fwrite(STDERR, "[FAIL] {$message}\n");
        } else {
            echo "[PASS] {$message}\n";
        }
    }

    public function summary(): int
    {
        echo "\nResumen: {$this->assertions} aserciones ejecutadas, {$this->failures} fallos.\n";
        return $this->failures === 0 ? 0 : 1;
    }
}

// Injected test double for PDO to avoid real network/database calls
class MockPdoDouble extends \PDO
{
    public function __construct()
    {
        try {
            parent::__construct('sqlite::memory:');
        } catch (\Throwable $e) {
            // Fallback for environments without sqlite PDO driver
        }
    }
}

// DatabaseConfig test seam to capture connection parameters without opening real connections
class DatabaseConfig
{
    public static ?array $mockLoadConfig = null;
    public static ?string $lastDsn = null;
    public static ?string $lastUser = null;
    public static ?string $lastPass = null;
    public static ?array $lastOptions = null;
    public static ?\PDO $pdoToReturn = null;
    public static ?\Throwable $exceptionToThrow = null;
    public static int $createPdoCallCount = 0;

    public static function reset(): void
    {
        self::$mockLoadConfig = null;
        self::$lastDsn = null;
        self::$lastUser = null;
        self::$lastPass = null;
        self::$lastOptions = null;
        self::$pdoToReturn = null;
        self::$exceptionToThrow = null;
        self::$createPdoCallCount = 0;
    }

    public static function load(): array
    {
        return self::$mockLoadConfig ?? [];
    }

    public static function createPdo(string $dsn, string $user, string $pass, array $options): \PDO
    {
        self::$createPdoCallCount++;
        self::$lastDsn = $dsn;
        self::$lastUser = $user;
        self::$lastPass = $pass;
        self::$lastOptions = $options;

        if (self::$exceptionToThrow !== null) {
            throw self::$exceptionToThrow;
        }

        return self::$pdoToReturn ?? new MockPdoDouble();
    }
}

$runner = new ElectroplanPrivateDbConfigTestRunner();
echo "--- Iniciando validación de Electroplan Private DB Config (TASK-0350) ---\n\n";

$connectionFilePath = dirname(__DIR__) . '/core/db/connection.php';

// Helper to run connection.php with isolated environment variables
$runConnection = function (
    array $env = [],
    array $server = [],
    ?\PDO $injectedPdoParam = null,
    $exceptionOrHook = null
) use ($connectionFilePath): array {
    $preExistingException = DatabaseConfig::$exceptionToThrow;
    DatabaseConfig::reset();

    if ($exceptionOrHook instanceof \Throwable) {
        DatabaseConfig::$exceptionToThrow = $exceptionOrHook;
    } elseif (is_callable($exceptionOrHook)) {
        $exceptionOrHook();
    } elseif ($preExistingException !== null) {
        DatabaseConfig::$exceptionToThrow = $preExistingException;
    }

    $origEnv = $_ENV;
    $origServer = $_SERVER;

    $managedKeys = [
        'ELECTROPLAN_DB_PRIVATE_CONFIG',
        'DB_HOST',
        'DB_NAME',
        'DB_USER',
        'DB_PASS',
        'DB_PORT',
        'DB_CHARSET',
        'DOCUMENT_ROOT',
    ];

    foreach ($managedKeys as $k) {
        putenv($k);
        unset($_ENV[$k], $_SERVER[$k]);
    }

    foreach ($env as $k => $v) {
        if ($v === null) {
            putenv($k);
            unset($_ENV[$k]);
        } else {
            putenv("{$k}={$v}");
            $_ENV[$k] = $v;
        }
    }

    foreach ($server as $k => $v) {
        if ($v === null) {
            unset($_SERVER[$k]);
        } else {
            $_SERVER[$k] = $v;
        }
    }

    $injectedPdo = $injectedPdoParam;
    $pdo = null;
    $caughtException = null;

    try {
        require $connectionFilePath;
    } catch (\Throwable $e) {
        $caughtException = $e;
    }

    // Restore environment
    foreach ($managedKeys as $k) {
        putenv($k);
    }
    $_ENV = $origEnv;
    $_SERVER = $origServer;

    return [
        'pdo' => $pdo ?? null,
        'exception' => $caughtException,
        'lastDsn' => DatabaseConfig::$lastDsn,
        'lastUser' => DatabaseConfig::$lastUser,
        'lastPass' => DatabaseConfig::$lastPass,
        'lastOptions' => DatabaseConfig::$lastOptions,
        'callCount' => DatabaseConfig::$createPdoCallCount,
    ];
};

// =========================================================================
// SUITE 1: INJECTED PDO SEAM COMPATIBILITY
// =========================================================================
echo "=== Suite 1: Injected PDO seam compatibility ===\n";

$mockInjected = new MockPdoDouble();
$resInjected = $runConnection([], [], $mockInjected);

$runner->assert($resInjected['exception'] === null, 'No exception thrown when injected PDO is provided');
$runner->assert($resInjected['pdo'] === $mockInjected, 'Injected PDO is returned directly');
$runner->assert($resInjected['callCount'] === 0, 'DatabaseConfig::createPdo is not called when injected PDO is provided');

// =========================================================================
// SUITE 2: DEFAULT BEHAVIOR WHEN PRIVATE CONFIG IS NOT CONFIGURED
// =========================================================================
echo "\n=== Suite 2: Default behavior without private config ===\n";

$resDefaults = $runConnection([]);
$runner->assert($resDefaults['exception'] === null, 'No exception thrown under default unconfigured state');
$runner->assert($resDefaults['callCount'] === 1, 'createPdo called once with defaults');
$runner->assert($resDefaults['lastDsn'] === 'mysql:host=localhost;port=3306;dbname=brightro_electroplan_v2;charset=utf8mb4', 'Default DSN matches expected fallbacks');
$runner->assert($resDefaults['lastUser'] === '', 'Default username is empty');
$runner->assert($resDefaults['lastPass'] === '', 'Default password is empty');

// Environment variables override defaults when private config is not configured
$resEnvDefaults = $runConnection([
    'DB_HOST' => 'env-only-host.test',
    'DB_NAME' => 'env_only_db',
    'DB_USER' => 'env_only_user',
    'DB_PASS' => 'env_only_pass',
    'DB_PORT' => '3308',
    'DB_CHARSET' => 'utf8',
]);
$runner->assert($resEnvDefaults['exception'] === null, 'Environment variables applied cleanly without private config');
$runner->assert($resEnvDefaults['lastDsn'] === 'mysql:host=env-only-host.test;port=3308;dbname=env_only_db;charset=utf8', 'DSN reflects environment variable overrides');
$runner->assert($resEnvDefaults['lastUser'] === 'env_only_user', 'Username reflects environment variable override');
$runner->assert($resEnvDefaults['lastPass'] === 'env_only_pass', 'Password reflects environment variable override');

// =========================================================================
// SUITE 3: VALID EXTERNAL PRIVATE DATABASE CONFIGURATION
// =========================================================================
echo "\n=== Suite 3: Valid external private database configuration ===\n";

$tempDir = sys_get_temp_dir();
$validConfigFile = $tempDir . DIRECTORY_SEPARATOR . 'electroplan_private_db_' . uniqid('', true) . '.php';

$validConfigContent = "<?php\nreturn [\n"
    . "    'host' => 'private-db-host.internal',\n"
    . "    'database' => 'private_app_db',\n"
    . "    'user' => 'priv_app_user',\n"
    . "    'password' => 'Pr1v@te_Db_P@ssword!',\n"
    . "    'port' => 3307,\n"
    . "    'charset' => 'utf8mb4',\n"
    . "];\n";

file_put_contents($validConfigFile, $validConfigContent);

try {
    $resValid = $runConnection(['ELECTROPLAN_DB_PRIVATE_CONFIG' => $validConfigFile]);

    $runner->assert($resValid['exception'] === null, 'Valid external private config loads without exception');
    $runner->assert($resValid['callCount'] === 1, 'createPdo called once for valid private config');
    $runner->assert($resValid['lastDsn'] === 'mysql:host=private-db-host.internal;port=3307;dbname=private_app_db;charset=utf8mb4', 'DSN loaded from external private config');
    $runner->assert($resValid['lastUser'] === 'priv_app_user', 'Username loaded from external private config');
    $runner->assert($resValid['lastPass'] === 'Pr1v@te_Db_P@ssword!', 'Password loaded from external private config');
} finally {
    @unlink($validConfigFile);
}

// Alternate key naming in private config
$altConfigFile = $tempDir . DIRECTORY_SEPARATOR . 'electroplan_private_db_alt_' . uniqid('', true) . '.php';
$altConfigContent = "<?php\nreturn [\n"
    . "    'DB_HOST' => 'alt-host.internal',\n"
    . "    'name' => 'alt_db_name',\n"
    . "    'username' => 'alt_user_name',\n"
    . "    'pass' => 'alt_secret_pass',\n"
    . "    'DB_PORT' => '3309',\n"
    . "    'DB_CHARSET' => 'latin1',\n"
    . "];\n";

file_put_contents($altConfigFile, $altConfigContent);

try {
    $resAlt = $runConnection(['ELECTROPLAN_DB_PRIVATE_CONFIG' => $altConfigFile]);

    $runner->assert($resAlt['exception'] === null, 'Alternate key format loads without exception');
    $runner->assert($resAlt['lastDsn'] === 'mysql:host=alt-host.internal;port=3309;dbname=alt_db_name;charset=latin1', 'DSN reflects alternate key mappings');
    $runner->assert($resAlt['lastUser'] === 'alt_user_name', 'Username reflects alternate key mapping (username)');
    $runner->assert($resAlt['lastPass'] === 'alt_secret_pass', 'Password reflects alternate key mapping (pass)');
} finally {
    @unlink($altConfigFile);
}

// =========================================================================
// SUITE 4: ENVIRONMENT VARIABLES PRECEDENCE OVER PRIVATE CONFIG
// =========================================================================
echo "\n=== Suite 4: Environment precedence over private config ===\n";

$precedenceConfigFile = $tempDir . DIRECTORY_SEPARATOR . 'electroplan_private_db_prec_' . uniqid('', true) . '.php';
file_put_contents($precedenceConfigFile, $validConfigContent);

try {
    // Partial override: DB_HOST and DB_PASS overridden by environment
    $resPartialOverride = $runConnection([
        'ELECTROPLAN_DB_PRIVATE_CONFIG' => $precedenceConfigFile,
        'DB_HOST' => 'override-host.env',
        'DB_PASS' => 'override-pass.env',
    ]);

    $runner->assert($resPartialOverride['exception'] === null, 'Precedence test runs without exception');
    $runner->assert($resPartialOverride['lastDsn'] === 'mysql:host=override-host.env;port=3307;dbname=private_app_db;charset=utf8mb4', 'DB_HOST from environment overrides private config, while port and db are retained');
    $runner->assert($resPartialOverride['lastUser'] === 'priv_app_user', 'DB_USER retained from private config when unset in environment');
    $runner->assert($resPartialOverride['lastPass'] === 'override-pass.env', 'DB_PASS from environment overrides private config password');

    // Total override: all database environment variables provided
    $resTotalOverride = $runConnection([
        'ELECTROPLAN_DB_PRIVATE_CONFIG' => $precedenceConfigFile,
        'DB_HOST' => 'full-env-host.test',
        'DB_NAME' => 'full_env_db',
        'DB_USER' => 'full_env_user',
        'DB_PASS' => 'full_env_pass',
        'DB_PORT' => '3310',
        'DB_CHARSET' => 'ascii',
    ]);

    $runner->assert($resTotalOverride['lastDsn'] === 'mysql:host=full-env-host.test;port=3310;dbname=full_env_db;charset=ascii', 'Environment retains highest precedence for all DSN fields');
    $runner->assert($resTotalOverride['lastUser'] === 'full_env_user', 'Environment retains highest precedence for username');
    $runner->assert($resTotalOverride['lastPass'] === 'full_env_pass', 'Environment retains highest precedence for password');
} finally {
    @unlink($precedenceConfigFile);
}

// =========================================================================
// SUITE 5: LOCATION RESTRICTIONS (REPOSITORY AND DOCUMENT ROOT)
// =========================================================================
echo "\n=== Suite 5: Location restrictions fail closed ===\n";

// 5.1 File inside repository root (temporary harmless PHP array fixture created under tests/)
$resRepoLoc = null;
$repoFixtureFile = __DIR__ . DIRECTORY_SEPARATOR . 'repo_fixture_' . uniqid('', true) . '.php';
file_put_contents($repoFixtureFile, "<?php\nreturn ['host' => 'disallowed-repo-host'];\n");

try {
    $resRepoLoc = $runConnection(['ELECTROPLAN_DB_PRIVATE_CONFIG' => $repoFixtureFile]);

    $repoExc = is_array($resRepoLoc) ? ($resRepoLoc['exception'] ?? null) : null;
    $runner->assert($repoExc instanceof \RuntimeException, 'Rejects private config path inside repository root');
    $runner->assert($repoExc instanceof \Throwable && strpos($repoExc->getMessage(), 'disallowed location') !== false, 'Exception specifies disallowed location error');
} finally {
    @unlink($repoFixtureFile);
}

// 5.2 File inside DOCUMENT_ROOT
$docRootDir = $tempDir . DIRECTORY_SEPARATOR . 'mock_doc_root_' . uniqid('', true);
mkdir($docRootDir);
$docRootConfigFile = $docRootDir . DIRECTORY_SEPARATOR . 'secret_db.php';
file_put_contents($docRootConfigFile, "<?php return ['host' => 'docroot-host'];\n");

$resDocRootLoc = null;
try {
    $resDocRootLoc = $runConnection(
        ['ELECTROPLAN_DB_PRIVATE_CONFIG' => $docRootConfigFile],
        ['DOCUMENT_ROOT' => $docRootDir]
    );

    $docRootExc = is_array($resDocRootLoc) ? ($resDocRootLoc['exception'] ?? null) : null;
    $runner->assert($docRootExc instanceof \RuntimeException, 'Rejects private config path inside DOCUMENT_ROOT');
    $runner->assert($docRootExc instanceof \Throwable && strpos($docRootExc->getMessage(), 'disallowed location') !== false, 'Exception specifies disallowed location for DOCUMENT_ROOT');
} finally {
    @unlink($docRootConfigFile);
    @rmdir($docRootDir);
}

// 5.3 Error message sanitization: disallowed location error contains no path
$repoExc = is_array($resRepoLoc) ? ($resRepoLoc['exception'] ?? null) : null;
$runner->assert($repoExc instanceof \Throwable && strpos($repoExc->getMessage(), $repoFixtureFile) === false, 'Disallowed location error does not leak repository file path');
$runner->assert($repoExc instanceof \Throwable && strpos($repoExc->getMessage(), 'tests') === false, 'Disallowed location error does not leak relative path segments');

// =========================================================================
// SUITE 6: MISSING AND UNREADABLE FILES FAIL CLOSED
// =========================================================================
echo "\n=== Suite 6: Missing and unreadable files fail closed ===\n";

// 6.1 Non-existent file
$missingPath = $tempDir . DIRECTORY_SEPARATOR . 'non_existent_db_config_' . uniqid('', true) . '.php';
$resMissing = $runConnection(['ELECTROPLAN_DB_PRIVATE_CONFIG' => $missingPath]);
$missingExc = is_array($resMissing) ? ($resMissing['exception'] ?? null) : null;

$runner->assert($missingExc instanceof \RuntimeException, 'Missing private config file throws RuntimeException');
$runner->assert($missingExc instanceof \Throwable && strpos($missingExc->getMessage(), 'not found or unreadable') !== false, 'Exception specifies not found or unreadable');
$runner->assert($missingExc instanceof \Throwable && strpos($missingExc->getMessage(), $missingPath) === false, 'Missing file error does not reveal missing path in message');

// 6.2 Directory path instead of regular file
$resDir = $runConnection(['ELECTROPLAN_DB_PRIVATE_CONFIG' => $tempDir]);
$dirExc = is_array($resDir) ? ($resDir['exception'] ?? null) : null;
$runner->assert($dirExc instanceof \RuntimeException, 'Directory path throws RuntimeException');
$runner->assert($dirExc instanceof \Throwable && strpos($dirExc->getMessage(), 'not found or unreadable') !== false, 'Directory path rejected with not found or unreadable');
$runner->assert($dirExc instanceof \Throwable && strpos($dirExc->getMessage(), $tempDir) === false, 'Directory error does not reveal path in message');

// 6.3 Empty string path
$resEmpty = $runConnection(['ELECTROPLAN_DB_PRIVATE_CONFIG' => '']);
$emptyExc = is_array($resEmpty) ? ($resEmpty['exception'] ?? null) : null;
$runner->assert($emptyExc instanceof \RuntimeException, 'Empty string path throws RuntimeException');
$runner->assert($emptyExc instanceof \Throwable && strpos($emptyExc->getMessage(), 'not found or unreadable') !== false, 'Empty path rejected with not found or unreadable');

// =========================================================================
// SUITE 7: INVALID FILE FORMAT FAILS CLOSED
// =========================================================================
echo "\n=== Suite 7: Invalid file format fails closed ===\n";

// 7.1 Non-array return (returns boolean true)
$nonArrayFile = $tempDir . DIRECTORY_SEPARATOR . 'invalid_format_' . uniqid('', true) . '.php';
file_put_contents($nonArrayFile, "<?php\nreturn true;\n");

try {
    $resNonArray = $runConnection(['ELECTROPLAN_DB_PRIVATE_CONFIG' => $nonArrayFile]);
    $nonArrayExc = is_array($resNonArray) ? ($resNonArray['exception'] ?? null) : null;
    $runner->assert($nonArrayExc instanceof \RuntimeException, 'Non-array return throws RuntimeException');
    $runner->assert($nonArrayExc instanceof \Throwable && strpos($nonArrayExc->getMessage(), 'invalid configuration file format') !== false, 'Exception specifies invalid configuration file format');
    $runner->assert($nonArrayExc instanceof \Throwable && strpos($nonArrayExc->getMessage(), $nonArrayFile) === false, 'Non-array format error does not reveal path');
} finally {
    @unlink($nonArrayFile);
}

// 7.2 File throwing exception during include
$throwingFile = $tempDir . DIRECTORY_SEPARATOR . 'throwing_config_' . uniqid('', true) . '.php';
file_put_contents($throwingFile, "<?php\nthrow new \\Exception('Private file internal secret syntax explosion');\n");

try {
    $resThrowing = $runConnection(['ELECTROPLAN_DB_PRIVATE_CONFIG' => $throwingFile]);
    $throwingExc = is_array($resThrowing) ? ($resThrowing['exception'] ?? null) : null;
    $runner->assert($throwingExc instanceof \RuntimeException, 'Throwing config throws RuntimeException');
    $runner->assert($throwingExc instanceof \Throwable && strpos($throwingExc->getMessage(), 'invalid configuration file format') !== false, 'Throwing config caught and sanitized as invalid configuration file format');
    $runner->assert($throwingExc instanceof \Throwable && strpos($throwingExc->getMessage(), 'Private file internal secret') === false, 'No message from inner exception leaked in message');
    $runner->assert($throwingExc instanceof \Throwable && $throwingExc->getPrevious() === null, 'No previous exception chained that could leak inner details');
} finally {
    @unlink($throwingFile);
}

// =========================================================================
// SUITE 8: SECRET REDACTION ON CONNECTION FAILURE
// =========================================================================
echo "\n=== Suite 8: Secret redaction on connection failure ===\n";

// Sensitive credentials that must never be exposed
$sensitiveUser = 'super_secret_db_admin_user';
$sensitivePass = 'TopSecret_P@ssw0rd#2026!';
$sensitiveDsn = "mysql:host=secret-db.internal;port=3306;dbname=classified_db;charset=utf8mb4";

$failingConfigFile = $tempDir . DIRECTORY_SEPARATOR . 'failing_conn_' . uniqid('', true) . '.php';
file_put_contents($failingConfigFile, "<?php\nreturn [\n"
    . "    'host' => 'secret-db.internal',\n"
    . "    'database' => 'classified_db',\n"
    . "    'user' => '{$sensitiveUser}',\n"
    . "    'password' => '{$sensitivePass}',\n"
    . "];\n");

try {
    // Configure DatabaseConfig to simulate PDO connection error with sensitive driver details after helper reset
    $driverLeakMessage = "SQLSTATE[HY000] [1045] Access denied for user '{$sensitiveUser}'@'secret-db.internal' (using password: {$sensitivePass}) at {$sensitiveDsn}";
    $simulatedException = new \PDOException($driverLeakMessage);

    $resConnFail = $runConnection(
        ['ELECTROPLAN_DB_PRIVATE_CONFIG' => $failingConfigFile],
        [],
        null,
        $simulatedException
    );

    $connExc = is_array($resConnFail) ? ($resConnFail['exception'] ?? null) : null;
    $runner->assert($connExc instanceof \PDOException, 'Connection failure throws PDOException');
    $runner->assert($connExc instanceof \Throwable && $connExc->getMessage() === 'Database connection failed', 'Exception message is sanitized to generic string');

    // Deep check for leaks in message and string representation
    $exceptionStr = $connExc instanceof \Throwable ? (string)$connExc : '';
    $runner->assert($connExc instanceof \Throwable && strpos($exceptionStr, $sensitivePass) === false, 'Exception reveals no password');
    $runner->assert($connExc instanceof \Throwable && strpos($exceptionStr, $sensitiveUser) === false, 'Exception reveals no username');
    $runner->assert($connExc instanceof \Throwable && strpos($exceptionStr, $sensitiveDsn) === false, 'Exception reveals no DSN');
    $runner->assert($connExc instanceof \Throwable && strpos($exceptionStr, $failingConfigFile) === false, 'Exception reveals no private config path');
    $runner->assert($connExc instanceof \Throwable && $connExc->getPrevious() === null, 'Exception has no chained previous exception');
} finally {
    @unlink($failingConfigFile);
}

exit($runner->summary());
