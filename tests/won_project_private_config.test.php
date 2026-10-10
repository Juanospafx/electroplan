<?php
declare(strict_types=1);

/**
 * Isolated regression tests for Electroplan HMAC private config loader and rotation.
 *
 * Verifies:
 * - Precedence: Environment variables > Private configuration file > Fail-closed defaults.
 * - Private configuration file path validation (non-existent, invalid, inside repo, inside doc root).
 * - Fail-closed handling of partial / malformed configuration.
 * - Active key authentication and role resolution.
 * - Previous key authentication within and outside rotation grace window.
 * - Timestamp skew enforcement and fail-closed defaults.
 * - Redaction of internal paths and sensitive secrets on error.
 */

// --- Test Assertions & Helpers ---

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new \RuntimeException("Assertion failed: $message");
    }
}

function assert_false(bool $condition, string $message): void
{
    if ($condition) {
        throw new \RuntimeException("Assertion failed: $message");
    }
}

function assert_equals($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        $expStr = is_scalar($expected) ? (string)$expected : json_encode($expected);
        $actStr = is_scalar($actual) ? (string)$actual : json_encode($actual);
        throw new \RuntimeException("Assertion failed: $message. Expected: '$expStr', got: '$actStr'");
    }
}

function assert_null($actual, string $message): void
{
    if ($actual !== null) {
        $actStr = is_scalar($actual) ? (string)$actual : json_encode($actual);
        throw new \RuntimeException("Assertion failed: $message. Expected null, got: '$actStr'");
    }
}

function assert_not_null($actual, string $message): void
{
    if ($actual === null) {
        throw new \RuntimeException("Assertion failed: $message. Expected non-null value, got null");
    }
}

function assert_no_leak(string $haystack, array $forbiddenTerms, string $context): void
{
    foreach ($forbiddenTerms as $term) {
        if ($term !== '' && stripos($haystack, (string)$term) !== false) {
            throw new \RuntimeException("$context: Leaked sensitive internal term '$term' in output");
        }
    }
}

function compute_hmac(string $method, string $path, string $timestamp, string $rawBody, string $secret): string
{
    $payload = $method . "\n" . $path . "\n" . $timestamp . "\n" . $rawBody;
    return hash_hmac('sha256', $payload, $secret);
}

function remove_dir_recursive(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path) && !is_link($path)) {
            remove_dir_recursive($path);
        } else {
            @chmod($path, 0777);
            @unlink($path);
        }
    }
    @chmod($dir, 0777);
    @rmdir($dir);
}

// --- Environment Setup ---

$repoRoot = realpath(__DIR__ . '/..');
if (!$repoRoot || !is_file($repoRoot . '/api/v1/config.php')) {
    throw new \RuntimeException('Repository root not located correctly from tests directory.');
}

$rawTempBase = sys_get_temp_dir();
$realTempBase = realpath($rawTempBase) ?: $rawTempBase;
$tempDir = $realTempBase . DIRECTORY_SEPARATOR . 'won_proj_cfg_test_' . bin2hex(random_bytes(8));
mkdir($tempDir, 0777, true);
$tempDir = realpath($tempDir) ?: $tempDir;

$cleanup = function () use (&$tempDir): void {
    if (is_string($tempDir) && is_dir($tempDir)) {
        for ($i = 0; $i < 5; $i++) {
            remove_dir_recursive($tempDir);
            if (!is_dir($tempDir)) {
                break;
            }
            usleep(25000);
        }
    }
};

register_shutdown_function($cleanup);

/**
 * Executes a PHP code snippet in a clean, isolated child process.
 */
function run_isolated(string $scriptCode, array $env = [], ?string $docRoot = null): array
{
    global $tempDir, $repoRoot;

    $scriptFile = $tempDir . DIRECTORY_SEPARATOR . 'test_case_' . bin2hex(random_bytes(8)) . '.php';
    file_put_contents($scriptFile, $scriptCode);

    $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $descriptors = [
        0 => ['file', $nullDevice, 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $procEnv = [];
    foreach ($_SERVER as $k => $v) {
        if (is_array($v) || is_object($v)) {
            continue;
        }
        $procEnv[(string)$k] = (string)$v;
    }
    foreach ($_ENV as $k => $v) {
        if (is_array($v) || is_object($v)) {
            continue;
        }
        if (!isset($procEnv[(string)$k])) {
            $procEnv[(string)$k] = (string)$v;
        }
    }

    // Clear tracked variables from inherited environment
    $trackedKeys = [
        'WON_PROJECT_PRIVATE_CONFIG',
        'WON_PROJECT_HMAC_ACTIVE_KEY_ID',
        'WON_PROJECT_HMAC_ACTIVE_SECRET',
        'WON_PROJECT_HMAC_PREVIOUS_KEY_ID',
        'WON_PROJECT_HMAC_PREVIOUS_SECRET',
        'WON_PROJECT_HMAC_PREVIOUS_ACCEPT_UNTIL',
        'WON_PROJECT_MAX_TIMESTAMP_SKEW_SECONDS',
        'DOCUMENT_ROOT',
    ];
    foreach ($trackedKeys as $k) {
        unset($procEnv[$k]);
    }

    // Apply custom test environment
    foreach ($env as $k => $v) {
        if ($v === null || is_array($v) || is_object($v)) {
            unset($procEnv[$k]);
        } else {
            $procEnv[(string)$k] = (string)$v;
        }
    }

    if ($docRoot !== null) {
        $procEnv['DOCUMENT_ROOT'] = $docRoot;
    }

    $cmd = [PHP_BINARY, $scriptFile];

    $proc = proc_open($cmd, $descriptors, $pipes, $repoRoot, $procEnv);
    if (!is_resource($proc)) {
        @unlink($scriptFile);
        throw new \RuntimeException("Failed to start PHP child process for isolated test.");
    }

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exitCode = proc_close($proc);
    @unlink($scriptFile);

    $json = json_decode((string)$stdout, true);

    return [
        'exit_code' => $exitCode,
        'stdout' => (string)$stdout,
        'stderr' => (string)$stderr,
        'json' => is_array($json) ? $json : null,
    ];
}

/**
 * Executes api/v1/config.php in an isolated environment.
 */
function test_load_config(array $env = [], ?string $docRoot = null): array
{
    global $repoRoot;
    $repoRootExport = var_export($repoRoot, true);

    $code = <<<PHP
<?php
declare(strict_types=1);
\$repoRoot = $repoRootExport;
try {
    \$config = require \$repoRoot . '/api/v1/config.php';
    echo json_encode(['status' => 'success', 'config' => \$config]);
} catch (\\Throwable \$e) {
    echo json_encode([
        'status' => 'error',
        'exception_class' => get_class(\$e),
        'message' => \$e->getMessage(),
    ]);
}
PHP;

    return run_isolated($code, $env, $docRoot);
}

/**
 * Executes api/v1/middleware_auth.php verify_hmac_auth() with specified config and headers.
 */
function test_verify_auth(
    array $config,
    array $headers,
    string $method = 'POST',
    string $path = '/api/v1/integrations/takeoff/won-projects',
    string $body = '{"test":true}'
): array {
    global $repoRoot;
    $repoRootExport = var_export($repoRoot, true);
    $configExport = var_export($config, true);
    $headersExport = var_export($headers, true);
    $methodExport = var_export($method, true);
    $pathExport = var_export($path, true);
    $bodyExport = var_export($body, true);

    $code = <<<PHP
<?php
declare(strict_types=1);
require_once $repoRootExport . '/api/v1/helpers.php';
require_once $repoRootExport . '/api/v1/middleware_auth.php';

\$config = $configExport;
\$headers = $headersExport;
\$_SERVER['REQUEST_METHOD'] = $methodExport;
\$_SERVER['REQUEST_URI'] = $pathExport;
\$GLOBALS['RAW_REQUEST_BODY'] = $bodyExport;

foreach (\$headers as \$k => \$v) {
    \$hk = 'HTTP_' . strtoupper(str_replace('-', '_', \$k));
    \$_SERVER[\$hk] = (string)\$v;
    \$_SERVER[\$k] = (string)\$v;
}

try {
    verify_hmac_auth(\$config);
    echo json_encode([
        'status' => 'success',
        'client_id' => get_client_id(),
        'client_role' => get_client_role(),
    ]);
} catch (\\Throwable \$e) {
    echo json_encode([
        'status' => 'exception',
        'exception_class' => get_class(\$e),
        'message' => \$e->getMessage(),
    ]);
}
PHP;

    return run_isolated($code);
}

// --- Test Suite Execution ---

try {

    // =========================================================================
    // GROUP 1: Defaults (Fail-Closed Default Configuration)
    // =========================================================================
    $resDefault = test_load_config([]);
    assert_equals(0, $resDefault['exit_code'], 'Group 1: Clean exit on defaults');
    assert_equals('success', $resDefault['json']['status'] ?? null, 'Group 1: Status must be success');

    $cfg1 = $resDefault['json']['config'];
    assert_null($cfg1['WON_PROJECT_HMAC_ACTIVE_KEY_ID'], 'Group 1: Active key id must default to null');
    assert_null($cfg1['WON_PROJECT_HMAC_ACTIVE_SECRET'], 'Group 1: Active secret must default to null');
    assert_null($cfg1['WON_PROJECT_HMAC_PREVIOUS_KEY_ID'], 'Group 1: Previous key id must default to null');
    assert_null($cfg1['WON_PROJECT_HMAC_PREVIOUS_SECRET'], 'Group 1: Previous secret must default to null');
    assert_null($cfg1['WON_PROJECT_HMAC_PREVIOUS_ACCEPT_UNTIL'], 'Group 1: Previous accept until must default to null');
    assert_equals(300, $cfg1['WON_PROJECT_MAX_TIMESTAMP_SKEW_SECONDS'], 'Group 1: Max timestamp skew must default to 300');
    assert_null($cfg1['WON_PROJECT_PRIVATE_CONFIG'], 'Group 1: Private config file must default to null');

    // Aliases
    assert_null($cfg1['ACTIVE_KEY_ID'], 'Group 1: ACTIVE_KEY_ID alias must default to null');
    assert_null($cfg1['ACTIVE_KEY_SECRET'], 'Group 1: ACTIVE_KEY_SECRET alias must default to null');
    assert_null($cfg1['PREVIOUS_KEY_ID'], 'Group 1: PREVIOUS_KEY_ID alias must default to null');
    assert_null($cfg1['PREVIOUS_KEY_SECRET'], 'Group 1: PREVIOUS_KEY_SECRET alias must default to null');
    assert_null($cfg1['PREVIOUS_ACCEPT_UNTIL'], 'Group 1: PREVIOUS_ACCEPT_UNTIL alias must default to null');
    assert_equals(300, $cfg1['MAX_TIMESTAMP_SKEW_SECONDS'], 'Group 1: MAX_TIMESTAMP_SKEW_SECONDS alias must default to 300');
    assert_equals(300, $cfg1['MAX_TIMESTAMP_SKEW'], 'Group 1: MAX_TIMESTAMP_SKEW alias must default to 300');
    assert_null($cfg1['PRIVATE_CONFIG'], 'Group 1: PRIVATE_CONFIG alias must default to null');
    assert_equals([], $cfg1['CLIENTS'], 'Group 1: CLIENTS must default to empty array');
    assert_equals([], $cfg1['CLIENT_ROLES'], 'Group 1: CLIENT_ROLES must default to empty array');

    // =========================================================================
    // GROUP 2: Private Configuration File Loading
    // =========================================================================
    $fileActiveId = 'file-active-' . bin2hex(random_bytes(4));
    $fileActiveSecret = bin2hex(random_bytes(24));
    $filePrevId = 'file-prev-' . bin2hex(random_bytes(4));
    $filePrevSecret = bin2hex(random_bytes(24));
    $fileAcceptUntil = time() + 7200;

    $privateFilePath = $tempDir . DIRECTORY_SEPARATOR . 'private_config_valid.php';
    $privateConfigArray = [
        'WON_PROJECT_HMAC_ACTIVE_KEY_ID' => $fileActiveId,
        'WON_PROJECT_HMAC_ACTIVE_SECRET' => $fileActiveSecret,
        'WON_PROJECT_HMAC_PREVIOUS_KEY_ID' => $filePrevId,
        'WON_PROJECT_HMAC_PREVIOUS_SECRET' => $filePrevSecret,
        'WON_PROJECT_HMAC_PREVIOUS_ACCEPT_UNTIL' => $fileAcceptUntil,
        'WON_PROJECT_MAX_TIMESTAMP_SKEW_SECONDS' => 120,
    ];
    file_put_contents($privateFilePath, "<?php\ndeclare(strict_types=1);\nreturn " . var_export($privateConfigArray, true) . ";\n");

    $resFile = test_load_config(['WON_PROJECT_PRIVATE_CONFIG' => $privateFilePath]);
    assert_equals('success', $resFile['json']['status'] ?? null, 'Group 2: Valid private config must load successfully');

    $cfg2 = $resFile['json']['config'];
    assert_equals($fileActiveId, $cfg2['WON_PROJECT_HMAC_ACTIVE_KEY_ID'], 'Group 2: Active key id from file');
    assert_equals($fileActiveSecret, $cfg2['WON_PROJECT_HMAC_ACTIVE_SECRET'], 'Group 2: Active secret from file');
    assert_equals($filePrevId, $cfg2['WON_PROJECT_HMAC_PREVIOUS_KEY_ID'], 'Group 2: Previous key id from file');
    assert_equals($filePrevSecret, $cfg2['WON_PROJECT_HMAC_PREVIOUS_SECRET'], 'Group 2: Previous secret from file');
    assert_equals($fileAcceptUntil, $cfg2['WON_PROJECT_HMAC_PREVIOUS_ACCEPT_UNTIL'], 'Group 2: Previous accept until from file');
    assert_equals(120, $cfg2['WON_PROJECT_MAX_TIMESTAMP_SKEW_SECONDS'], 'Group 2: Timestamp skew from file');
    assert_equals(realpath($privateFilePath), $cfg2['WON_PROJECT_PRIVATE_CONFIG'], 'Group 2: Private config realpath');
    assert_equals([$fileActiveId => $fileActiveSecret], $cfg2['CLIENTS'], 'Group 2: CLIENTS has active client');
    assert_equals([$fileActiveId => 'admin', $filePrevId => 'admin'], $cfg2['CLIENT_ROLES'], 'Group 2: CLIENT_ROLES has active and valid prev clients');

    // =========================================================================
    // GROUP 3: Precedence (Environment Variables Override File)
    // =========================================================================
    $envActiveId = 'env-active-' . bin2hex(random_bytes(4));
    $envActiveSecret = bin2hex(random_bytes(24));
    $envPrevId = 'env-prev-' . bin2hex(random_bytes(4));
    $envPrevSecret = bin2hex(random_bytes(24));
    $envAcceptUntil = time() + 14400;

    $resPrecedence = test_load_config([
        'WON_PROJECT_PRIVATE_CONFIG' => $privateFilePath,
        'WON_PROJECT_HMAC_ACTIVE_KEY_ID' => $envActiveId,
        'WON_PROJECT_HMAC_ACTIVE_SECRET' => $envActiveSecret,
        'WON_PROJECT_HMAC_PREVIOUS_KEY_ID' => $envPrevId,
        'WON_PROJECT_HMAC_PREVIOUS_SECRET' => $envPrevSecret,
        'WON_PROJECT_HMAC_PREVIOUS_ACCEPT_UNTIL' => (string)$envAcceptUntil,
        'WON_PROJECT_MAX_TIMESTAMP_SKEW_SECONDS' => '60',
    ]);
    assert_equals('success', $resPrecedence['json']['status'] ?? null, 'Group 3: Precedence config must load successfully');

    $cfg3 = $resPrecedence['json']['config'];
    assert_equals($envActiveId, $cfg3['WON_PROJECT_HMAC_ACTIVE_KEY_ID'], 'Group 3: Environment overrides file active id');
    assert_equals($envActiveSecret, $cfg3['WON_PROJECT_HMAC_ACTIVE_SECRET'], 'Group 3: Environment overrides file active secret');
    assert_equals($envPrevId, $cfg3['WON_PROJECT_HMAC_PREVIOUS_KEY_ID'], 'Group 3: Environment overrides file previous id');
    assert_equals($envPrevSecret, $cfg3['WON_PROJECT_HMAC_PREVIOUS_SECRET'], 'Group 3: Environment overrides file previous secret');
    assert_equals($envAcceptUntil, $cfg3['WON_PROJECT_HMAC_PREVIOUS_ACCEPT_UNTIL'], 'Group 3: Environment overrides file accept until');
    assert_equals(60, $cfg3['WON_PROJECT_MAX_TIMESTAMP_SKEW_SECONDS'], 'Group 3: Environment overrides file timestamp skew');
    assert_equals([$envActiveId => $envActiveSecret], $cfg3['CLIENTS'], 'Group 3: CLIENTS has env active client');

    // =========================================================================
    // GROUP 4: Invalid Private Config Path & Redaction
    // =========================================================================

    // 4a: Non-existent file
    $resNonExistent = test_load_config(['WON_PROJECT_PRIVATE_CONFIG' => $tempDir . '/non_existent_file.php']);
    assert_equals('error', $resNonExistent['json']['status'] ?? null, 'Group 4a: Non-existent file must throw');
    assert_equals('RuntimeException', $resNonExistent['json']['exception_class'] ?? null, 'Group 4a: Exception class RuntimeException');
    assert_equals('Configuration error', $resNonExistent['json']['message'] ?? null, 'Group 4a: Exception message exact match');
    assert_no_leak($resNonExistent['stdout'], ['non_existent_file', $tempDir], 'Group 4a: Path redaction in stdout');

    // 4b: Empty or whitespace path
    $resEmptyPath = test_load_config(['WON_PROJECT_PRIVATE_CONFIG' => '   ']);
    assert_equals('error', $resEmptyPath['json']['status'] ?? null, 'Group 4b: Whitespace path must throw');
    assert_equals('Configuration error', $resEmptyPath['json']['message'] ?? null, 'Group 4b: Message Configuration error');

    // 4c: Directory path instead of file
    $resDirPath = test_load_config(['WON_PROJECT_PRIVATE_CONFIG' => $tempDir]);
    assert_equals('error', $resDirPath['json']['status'] ?? null, 'Group 4c: Directory path must throw');
    assert_equals('Configuration error', $resDirPath['json']['message'] ?? null, 'Group 4c: Message Configuration error');

    // 4d: Path inside repository root
    $resInsideRepo = test_load_config(['WON_PROJECT_PRIVATE_CONFIG' => $repoRoot . '/api/v1/config.php']);
    assert_equals('error', $resInsideRepo['json']['status'] ?? null, 'Group 4d: Path inside repoRoot must throw');
    assert_equals('Configuration error', $resInsideRepo['json']['message'] ?? null, 'Group 4d: Message Configuration error');

    // 4e: Path is repository root itself
    $resRepoRootItself = test_load_config(['WON_PROJECT_PRIVATE_CONFIG' => $repoRoot]);
    assert_equals('error', $resRepoRootItself['json']['status'] ?? null, 'Group 4e: repoRoot path must throw');
    assert_equals('Configuration error', $resRepoRootItself['json']['message'] ?? null, 'Group 4e: Message Configuration error');

    // 4f: Path inside DOCUMENT_ROOT
    $docRootDir = $tempDir . DIRECTORY_SEPARATOR . 'mock_doc_root';
    mkdir($docRootDir, 0777, true);
    $docRootConfig = $docRootDir . DIRECTORY_SEPARATOR . 'leaked_config.php';
    file_put_contents($docRootConfig, "<?php\ndeclare(strict_types=1);\nreturn [];\n");

    $resDocRoot = test_load_config(['WON_PROJECT_PRIVATE_CONFIG' => $docRootConfig], $docRootDir);
    assert_equals('error', $resDocRoot['json']['status'] ?? null, 'Group 4f: Path inside DOCUMENT_ROOT must throw');
    assert_equals('Configuration error', $resDocRoot['json']['message'] ?? null, 'Group 4f: Message Configuration error');

    // 4g: Private file returns non-array (e.g. string or boolean)
    $nonArrayFilePath = $tempDir . DIRECTORY_SEPARATOR . 'non_array_config.php';
    file_put_contents($nonArrayFilePath, "<?php\ndeclare(strict_types=1);\nreturn 'invalid_string';\n");
    $resNonArray = test_load_config(['WON_PROJECT_PRIVATE_CONFIG' => $nonArrayFilePath]);
    assert_equals('error', $resNonArray['json']['status'] ?? null, 'Group 4g: Non-array return must throw');
    assert_equals('Configuration error', $resNonArray['json']['message'] ?? null, 'Group 4g: Message Configuration error');

    // 4h: Private file throws exception (verify sensitive message redaction)
    $throwingFilePath = $tempDir . DIRECTORY_SEPARATOR . 'throwing_config.php';
    $sensitiveMarker = 'secret_database_credential_marker_' . bin2hex(random_bytes(8));
    file_put_contents($throwingFilePath, "<?php\ndeclare(strict_types=1);\nthrow new \\Exception('$sensitiveMarker');\n");
    $resThrowing = test_load_config(['WON_PROJECT_PRIVATE_CONFIG' => $throwingFilePath]);
    assert_equals('error', $resThrowing['json']['status'] ?? null, 'Group 4h: Exception in private file caught and wrapped');
    assert_equals('Configuration error', $resThrowing['json']['message'] ?? null, 'Group 4h: Wrapped as Configuration error');
    assert_no_leak($resThrowing['stdout'], [$sensitiveMarker], 'Group 4h: Sensitive internal marker redacted from stdout');

    // =========================================================================
    // GROUP 5: Fail-Closed Partial Configuration
    // =========================================================================

    // 5a: Active key ID without secret -> both null
    $resPartialActive1 = test_load_config(['WON_PROJECT_HMAC_ACTIVE_KEY_ID' => 'active-only-key']);
    $cfg5a = $resPartialActive1['json']['config'];
    assert_null($cfg5a['WON_PROJECT_HMAC_ACTIVE_KEY_ID'], 'Group 5a: Partial active id invalidated to null');
    assert_null($cfg5a['WON_PROJECT_HMAC_ACTIVE_SECRET'], 'Group 5a: Missing active secret is null');
    assert_equals([], $cfg5a['CLIENTS'], 'Group 5a: CLIENTS empty on partial active config');
    assert_equals([], $cfg5a['CLIENT_ROLES'], 'Group 5a: CLIENT_ROLES empty on partial active config');

    // 5b: Active secret without key ID -> both null
    $resPartialActive2 = test_load_config(['WON_PROJECT_HMAC_ACTIVE_SECRET' => bin2hex(random_bytes(24))]);
    $cfg5b = $resPartialActive2['json']['config'];
    assert_null($cfg5b['WON_PROJECT_HMAC_ACTIVE_KEY_ID'], 'Group 5b: Missing active key id is null');
    assert_null($cfg5b['WON_PROJECT_HMAC_ACTIVE_SECRET'], 'Group 5b: Partial active secret invalidated to null');

    // 5c: Previous key ID and secret without accept until -> all previous null
    $resPartialPrev1 = test_load_config([
        'WON_PROJECT_HMAC_PREVIOUS_KEY_ID' => 'prev-only-key',
        'WON_PROJECT_HMAC_PREVIOUS_SECRET' => bin2hex(random_bytes(24)),
    ]);
    $cfg5c = $resPartialPrev1['json']['config'];
    assert_null($cfg5c['WON_PROJECT_HMAC_PREVIOUS_KEY_ID'], 'Group 5c: Previous id invalidated when accept until missing');
    assert_null($cfg5c['WON_PROJECT_HMAC_PREVIOUS_SECRET'], 'Group 5c: Previous secret invalidated when accept until missing');
    assert_null($cfg5c['WON_PROJECT_HMAC_PREVIOUS_ACCEPT_UNTIL'], 'Group 5c: Previous accept until null');

    // 5d: Previous key ID and accept until without secret -> all previous null
    $resPartialPrev2 = test_load_config([
        'WON_PROJECT_HMAC_PREVIOUS_KEY_ID' => 'prev-only-key',
        'WON_PROJECT_HMAC_PREVIOUS_ACCEPT_UNTIL' => (string)(time() + 3600),
    ]);
    $cfg5d = $resPartialPrev2['json']['config'];
    assert_null($cfg5d['WON_PROJECT_HMAC_PREVIOUS_KEY_ID'], 'Group 5d: Previous id invalidated when secret missing');
    assert_null($cfg5d['WON_PROJECT_HMAC_PREVIOUS_SECRET'], 'Group 5d: Previous secret null');
    assert_null($cfg5d['WON_PROJECT_HMAC_PREVIOUS_ACCEPT_UNTIL'], 'Group 5d: Previous accept until invalidated');

    // 5e: Previous secret and accept until without key ID -> all previous null
    $resPartialPrev3 = test_load_config([
        'WON_PROJECT_HMAC_PREVIOUS_SECRET' => bin2hex(random_bytes(24)),
        'WON_PROJECT_HMAC_PREVIOUS_ACCEPT_UNTIL' => (string)(time() + 3600),
    ]);
    $cfg5e = $resPartialPrev3['json']['config'];
    assert_null($cfg5e['WON_PROJECT_HMAC_PREVIOUS_KEY_ID'], 'Group 5e: Previous id null');
    assert_null($cfg5e['WON_PROJECT_HMAC_PREVIOUS_SECRET'], 'Group 5e: Previous secret invalidated when id missing');
    assert_null($cfg5e['WON_PROJECT_HMAC_PREVIOUS_ACCEPT_UNTIL'], 'Group 5e: Previous accept until invalidated');

    // 5f: Previous accept until is non-positive or unparseable -> all previous null
    $resPartialPrev4 = test_load_config([
        'WON_PROJECT_HMAC_PREVIOUS_KEY_ID' => 'prev-key',
        'WON_PROJECT_HMAC_PREVIOUS_SECRET' => bin2hex(random_bytes(24)),
        'WON_PROJECT_HMAC_PREVIOUS_ACCEPT_UNTIL' => '0',
    ]);
    $cfg5f = $resPartialPrev4['json']['config'];
    assert_null($cfg5f['WON_PROJECT_HMAC_PREVIOUS_KEY_ID'], 'Group 5f: Previous invalidated on accept until 0');

    $resPartialPrev5 = test_load_config([
        'WON_PROJECT_HMAC_PREVIOUS_KEY_ID' => 'prev-key',
        'WON_PROJECT_HMAC_PREVIOUS_SECRET' => bin2hex(random_bytes(24)),
        'WON_PROJECT_HMAC_PREVIOUS_ACCEPT_UNTIL' => 'unparseable-date-string',
    ]);
    $cfg5g = $resPartialPrev5['json']['config'];
    assert_null($cfg5g['WON_PROJECT_HMAC_PREVIOUS_KEY_ID'], 'Group 5g: Previous invalidated on unparseable date string');

    // =========================================================================
    // GROUP 6: Active Key Authentication
    // =========================================================================
    $activeKeyId = 'active-key-' . bin2hex(random_bytes(4));
    $activeSecret = bin2hex(random_bytes(24));
    $testConfig = [
        'WON_PROJECT_HMAC_ACTIVE_KEY_ID' => $activeKeyId,
        'WON_PROJECT_HMAC_ACTIVE_SECRET' => $activeSecret,
        'WON_PROJECT_MAX_TIMESTAMP_SKEW_SECONDS' => 300,
        'CLIENT_ROLES' => [$activeKeyId => 'admin'],
    ];

    $ts = time();
    $method = 'POST';
    $path = '/api/v1/integrations/takeoff/won-projects';
    $body = json_encode(['test' => 'contract-payload']);

    // 6a: Valid active key authentication
    $sigValid = compute_hmac($method, $path, (string)$ts, $body, $activeSecret);
    $resAuthActive = test_verify_auth($testConfig, [
        'X-Client-Id' => $activeKeyId,
        'X-Timestamp' => (string)$ts,
        'X-Signature' => $sigValid,
    ], $method, $path, $body);
    assert_equals('success', $resAuthActive['json']['status'] ?? null, 'Group 6a: Valid active key auth succeeds');
    assert_equals($activeKeyId, $resAuthActive['json']['client_id'] ?? null, 'Group 6a: Context client id matches');
    assert_equals('admin', $resAuthActive['json']['client_role'] ?? null, 'Group 6a: Context client role matches admin');

    // 6b: Legacy X-Key-Id header support
    $resAuthLegacyHeader = test_verify_auth($testConfig, [
        'X-Key-Id' => $activeKeyId,
        'X-Timestamp' => (string)$ts,
        'X-Signature' => $sigValid,
    ], $method, $path, $body);
    assert_equals('success', $resAuthLegacyHeader['json']['status'] ?? null, 'Group 6b: Legacy X-Key-Id header succeeds');
    assert_equals($activeKeyId, $resAuthLegacyHeader['json']['client_id'] ?? null, 'Group 6b: Legacy client id matches');

    // 6c: Invalid signature rejected
    $badSig = hash('sha256', 'tampered-signature-data');
    $resBadSig = test_verify_auth($testConfig, [
        'X-Client-Id' => $activeKeyId,
        'X-Timestamp' => (string)$ts,
        'X-Signature' => $badSig,
    ], $method, $path, $body);
    assert_false($resBadSig['json']['ok'] ?? true, 'Group 6c: Bad signature ok must be false');
    assert_equals('UNAUTHORIZED', $resBadSig['json']['error']['code'] ?? null, 'Group 6c: Error code UNAUTHORIZED');
    assert_equals('Invalid signature', $resBadSig['json']['error']['message'] ?? null, 'Group 6c: Message Invalid signature');

    // 6d: Missing authentication headers
    $resMissingH = test_verify_auth($testConfig, [
        'X-Client-Id' => $activeKeyId,
        'X-Timestamp' => (string)$ts,
    ], $method, $path, $body);
    assert_false($resMissingH['json']['ok'] ?? true, 'Group 6d: Missing signature ok must be false');
    assert_equals('UNAUTHORIZED', $resMissingH['json']['error']['code'] ?? null, 'Group 6d: Missing header code UNAUTHORIZED');
    assert_equals('Missing authentication headers', $resMissingH['json']['error']['message'] ?? null, 'Group 6d: Message Missing headers');

    // =========================================================================
    // GROUP 7: Previous Key Rotation - Within Window
    // =========================================================================
    $prevKeyId = 'prev-key-' . bin2hex(random_bytes(4));
    $prevSecret = bin2hex(random_bytes(24));
    $futureAcceptUntil = time() + 3600;

    $rotConfig = [
        'WON_PROJECT_HMAC_ACTIVE_KEY_ID' => $activeKeyId,
        'WON_PROJECT_HMAC_ACTIVE_SECRET' => $activeSecret,
        'WON_PROJECT_HMAC_PREVIOUS_KEY_ID' => $prevKeyId,
        'WON_PROJECT_HMAC_PREVIOUS_SECRET' => $prevSecret,
        'WON_PROJECT_HMAC_PREVIOUS_ACCEPT_UNTIL' => $futureAcceptUntil,
        'WON_PROJECT_MAX_TIMESTAMP_SKEW_SECONDS' => 300,
        'CLIENT_ROLES' => [
            $activeKeyId => 'admin',
            $prevKeyId => 'admin',
        ],
    ];

    // 7a: Previous key used within window
    $sigPrev = compute_hmac($method, $path, (string)$ts, $body, $prevSecret);
    $resPrevValid = test_verify_auth($rotConfig, [
        'X-Client-Id' => $prevKeyId,
        'X-Timestamp' => (string)$ts,
        'X-Signature' => $sigPrev,
    ], $method, $path, $body);
    assert_equals('success', $resPrevValid['json']['status'] ?? null, 'Group 7a: Previous key within window succeeds');
    assert_equals($prevKeyId, $resPrevValid['json']['client_id'] ?? null, 'Group 7a: Previous client id set');
    assert_equals('admin', $resPrevValid['json']['client_role'] ?? null, 'Group 7a: Previous client role set');

    // 7b: Previous key with ISO-8601 accept until format in future
    $isoFutureConfig = $rotConfig;
    $isoFutureConfig['WON_PROJECT_HMAC_PREVIOUS_ACCEPT_UNTIL'] = date('c', time() + 3600);
    $resIsoPrev = test_verify_auth($isoFutureConfig, [
        'X-Client-Id' => $prevKeyId,
        'X-Timestamp' => (string)$ts,
        'X-Signature' => $sigPrev,
    ], $method, $path, $body);
    assert_equals('success', $resIsoPrev['json']['status'] ?? null, 'Group 7b: ISO-8601 accept until succeeds');

    // =========================================================================
    // GROUP 8: Previous Key Rotation - Outside Window
    // =========================================================================
    $pastAcceptUntil = time() - 30; // Expired 30 seconds ago
    $expiredRotConfig = $rotConfig;
    $expiredRotConfig['WON_PROJECT_HMAC_PREVIOUS_ACCEPT_UNTIL'] = $pastAcceptUntil;

    // 8a: In config loader, expired previous key is excluded from CLIENT_ROLES
    $resExpiredConfigLoad = test_load_config([
        'WON_PROJECT_HMAC_ACTIVE_KEY_ID' => $activeKeyId,
        'WON_PROJECT_HMAC_ACTIVE_SECRET' => $activeSecret,
        'WON_PROJECT_HMAC_PREVIOUS_KEY_ID' => $prevKeyId,
        'WON_PROJECT_HMAC_PREVIOUS_SECRET' => $prevSecret,
        'WON_PROJECT_HMAC_PREVIOUS_ACCEPT_UNTIL' => (string)$pastAcceptUntil,
    ]);
    $loadedRoles = $resExpiredConfigLoad['json']['config']['CLIENT_ROLES'] ?? [];
    assert_false(isset($loadedRoles[$prevKeyId]), 'Group 8a: Expired previous key not in CLIENT_ROLES');

    // 8b: Auth with expired previous key rejected as Invalid client
    $resPrevExpired = test_verify_auth($expiredRotConfig, [
        'X-Client-Id' => $prevKeyId,
        'X-Timestamp' => (string)$ts,
        'X-Signature' => $sigPrev,
    ], $method, $path, $body);
    assert_false($resPrevExpired['json']['ok'] ?? true, 'Group 8b: Expired previous key ok must be false');
    assert_equals('UNAUTHORIZED', $resPrevExpired['json']['error']['code'] ?? null, 'Group 8b: Code UNAUTHORIZED');
    assert_equals('Invalid client', $resPrevExpired['json']['error']['message'] ?? null, 'Group 8b: Message Invalid client');

    // 8c: Auth with expired ISO-8601 string rejected as Invalid client
    $isoPastConfig = $rotConfig;
    $isoPastConfig['WON_PROJECT_HMAC_PREVIOUS_ACCEPT_UNTIL'] = '2020-01-01T00:00:00Z';
    $resIsoPast = test_verify_auth($isoPastConfig, [
        'X-Client-Id' => $prevKeyId,
        'X-Timestamp' => (string)$ts,
        'X-Signature' => $sigPrev,
    ], $method, $path, $body);
    assert_false($resIsoPast['json']['ok'] ?? true, 'Group 8c: Expired ISO string ok must be false');
    assert_equals('Invalid client', $resIsoPast['json']['error']['message'] ?? null, 'Group 8c: Message Invalid client');

    // =========================================================================
    // GROUP 9: Timestamp Skew & Formats
    // =========================================================================
    $skewConfig = [
        'WON_PROJECT_HMAC_ACTIVE_KEY_ID' => $activeKeyId,
        'WON_PROJECT_HMAC_ACTIVE_SECRET' => $activeSecret,
        'WON_PROJECT_MAX_TIMESTAMP_SKEW_SECONDS' => 60,
    ];

    // 9a: Timestamp within skew (30 seconds ago) succeeds
    $tsRecent = time() - 30;
    $sigRecent = compute_hmac($method, $path, (string)$tsRecent, $body, $activeSecret);
    $resRecent = test_verify_auth($skewConfig, [
        'X-Client-Id' => $activeKeyId,
        'X-Timestamp' => (string)$tsRecent,
        'X-Signature' => $sigRecent,
    ], $method, $path, $body);
    assert_equals('success', $resRecent['json']['status'] ?? null, 'Group 9a: Recent timestamp succeeds');

    // 9b: Timestamp beyond skew (90 seconds ago with max_skew 60) fails
    $tsOld = time() - 90;
    $sigOld = compute_hmac($method, $path, (string)$tsOld, $body, $activeSecret);
    $resOld = test_verify_auth($skewConfig, [
        'X-Client-Id' => $activeKeyId,
        'X-Timestamp' => (string)$tsOld,
        'X-Signature' => $sigOld,
    ], $method, $path, $body);
    assert_false($resOld['json']['ok'] ?? true, 'Group 9b: Old timestamp ok must be false');
    assert_equals('UNAUTHORIZED', $resOld['json']['error']['code'] ?? null, 'Group 9b: Code UNAUTHORIZED');
    assert_equals('Timestamp out of range', $resOld['json']['error']['message'] ?? null, 'Group 9b: Message Timestamp out of range');

    // 9c: Non-digit timestamp string rejected
    $resNonDigitTs = test_verify_auth($skewConfig, [
        'X-Client-Id' => $activeKeyId,
        'X-Timestamp' => 'not-a-timestamp',
        'X-Signature' => 'dummy',
    ], $method, $path, $body);
    assert_false($resNonDigitTs['json']['ok'] ?? true, 'Group 9c: Non-digit timestamp ok must be false');
    assert_equals('Invalid timestamp', $resNonDigitTs['json']['error']['message'] ?? null, 'Group 9c: Message Invalid timestamp');

    // =========================================================================
    // GROUP 10: Security & Output Redaction in Auth Responses
    // =========================================================================
    $sensitiveAuthTerms = [
        $activeSecret,
        $prevSecret,
        $tempDir,
        $privateFilePath,
    ];

    assert_no_leak($resBadSig['stdout'], $sensitiveAuthTerms, 'Group 10: Bad signature error output');
    assert_no_leak($resPrevExpired['stdout'], $sensitiveAuthTerms, 'Group 10: Expired key error output');
    assert_no_leak($resOld['stdout'], $sensitiveAuthTerms, 'Group 10: Skew error output');

    echo "All Electroplan HMAC private config and rotation regression tests passed successfully.\n";
    $cleanup();
    exit(0);

} catch (\Throwable $e) {
    echo "TEST FAILURE: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    $cleanup();
    exit(1);
} finally {
    $cleanup();
}
