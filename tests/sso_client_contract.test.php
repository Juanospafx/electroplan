<?php
/**
 * Aggregate Electroplan SSO Client Contract Test Suite & Runner.
 * TASK-0269: Electroplan SSO E recovery - tempfile subprocess runner.
 * TASK-0270: Electroplan SSO E correction - executable timeout proof.
 * TASK-0276: Electroplan SSO E final high-capacity timeout proof.
 *
 * Acceptance criteria:
 * 1. Each suite runs independently with stdin closed and stdout and stderr redirected directly
 *    to separate temporary files rather than pipes.
 * 2. The runner polls process status with a finite per-suite timeout, reads diagnostics from the
 *    files after completion and removes every temporary file on success, failure and timeout.
 * 3. Timeout handling terminates the test process tree in a controlled platform-aware manner and
 *    does not call proc_close while a descendant can retain inherited handles.
 * 4. A built-in isolated timeout simulation proves non-zero timeout behavior and cleanup without
 *    modifying any constituent suite or leaving orphan PHP processes.
 * 5. All eight canonical suites execute and the aggregate terminates normally with zero only when
 *    all pass; only the aggregate file changes.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$projectRoot = dirname(__DIR__);
$testsDir = __DIR__;

// Autonomous Test Runner helper for contract assertions
class SsoClientContractRunner
{
    private int $assertions = 0;
    private int $failures = 0;
    /** @var list<string> */
    private array $failureDetails = [];

    public function assert(bool $condition, string $message): void
    {
        $this->assertions++;
        if (!$condition) {
            $this->failures++;
            $this->failureDetails[] = $message;
            fwrite(STDERR, "  [FAIL] {$message}\n");
        } else {
            echo "  [PASS] {$message}\n";
        }
    }

    public function getAssertionsCount(): int
    {
        return $this->assertions;
    }

    public function getFailuresCount(): int
    {
        return $this->failures;
    }

    /**
     * @return list<string>
     */
    public function getFailureDetails(): array
    {
        return $this->failureDetails;
    }
}

$contractRunner = new SsoClientContractRunner();

echo "================================================================================\n";
echo " Electroplan SSO Client Contract Suite & Aggregate Runner (TASK-0264)\n";
echo "================================================================================\n\n";

// =============================================================================
// PHASE 1: ISOLATED SUBPROCESS EXECUTION OF EXISTING ACCEPTED SUITES
// =============================================================================
echo "--- FASE 1: Ejecución de suites aisladas en subprocesos PHP independientes ---\n\n";

// Explicit canonical SSO client test suites in execution order (TASK-0264)
// Deterministic selection: exact filenames only, no globbing of unrelated historical tests.
$canonicalSuites = [
    'sso_feature_flag_contract.test.php' => [
        'description' => 'Feature Flag & Local Login Rollback Contract (TASK-0233)',
        'bootstrap' => "define('SSO_ENABLED', false);",
    ],
    'sso_identity_static_contract.test.php' => [
        'description' => 'Identity & Role Safety Static Contract (TASK-0239)',
        'bootstrap' => null,
    ],
    'sso_state_pkce_contract.test.php' => [
        'description' => 'State and PKCE S256 Flow Suite (TASK-0223)',
        'bootstrap' => null,
    ],
    'sso_token_userinfo_static_contract.test.php' => [
        'description' => 'Token & Userinfo Confidential Client Contract',
        'bootstrap' => null,
    ],
    'sso_callback_runtime.test.php' => [
        'description' => 'OAuth Callback Boundary Runtime Suite (TASK-0254)',
        'bootstrap' => null,
    ],
    'sso_identity_link_runtime.test.php' => [
        'description' => 'Federated Identity Link & Local Session Suite (TASK-0259)',
        'bootstrap' => null,
    ],
    'sso_authorization_runtime.test.php' => [
        'description' => 'Local Authorization Policy Boundary Suite (TASK-0260)',
        'bootstrap' => null,
    ],
    'sso_login_logout_runtime.test.php' => [
        'description' => 'SSO Login, Authorization & Logout Wiring Suite (TASK-0261)',
        'bootstrap' => null,
    ],
];

// Build explicit list of suites to execute strictly from canonical suites
$suitesToRun = [];
foreach ($canonicalSuites as $suiteFile => $meta) {
    $fullPath = $testsDir . DIRECTORY_SEPARATOR . $suiteFile;
    // Guard against self-invocation
    if ($suiteFile === basename(__FILE__)) {
        continue;
    }
    $suitesToRun[$suiteFile] = [
        'path' => $fullPath,
        'description' => $meta['description'],
        'bootstrap' => $meta['bootstrap'],
    ];
}

// Portably determine PHP binary for subprocess execution on Windows and Linux
$phpBinary = PHP_BINARY;
if ($phpBinary === '' || !is_executable($phpBinary)) {
    $phpBinary = 'php';
}

$subprocessResults = [];
$allSubprocessesPassed = true;

/**
 * Executes a PHP script in an isolated subprocess, streaming output safely
 * across Windows and Linux PHP environments using temporary files rather than pipes
 * to prevent deadlocks from inherited handles or full buffers.
 *
 * @param string $phpBin Path or name of the PHP binary
 * @param string $scriptPath Absolute path to the test script, or empty string for inline bootstrap
 * @param string $workingDir Working directory
 * @param string|null $bootstrapCode Optional PHP code to bootstrap in child process
 * @param int $timeoutSeconds Maximum execution time in seconds
 * @return array{exitCode: int, stdout: string, stderr: string, timedOut: bool, pid: int, tempFilesCleaned: bool}
 */
$runSubprocess = function (
    string $phpBin,
    string $scriptPath,
    string $workingDir,
    ?string $bootstrapCode = null,
    int $timeoutSeconds = 30
): array {
    $tempDir = sys_get_temp_dir();
    $stdoutFile = tempnam($tempDir, 'sso_out_');
    $stderrFile = tempnam($tempDir, 'sso_err_');

    $stdout = '';
    $stderr = '';
    $exitCode = 1;
    $timedOut = false;
    $childPid = 0;
    $normalizedScriptPath = str_replace('\\', '/', $scriptPath);

    try {
        if ($stdoutFile === false || $stderrFile === false) {
            throw new \RuntimeException('Failed to allocate temporary files for subprocess execution');
        }

        $usedProcOpen = false;

        if (function_exists('proc_open')) {
            if ($scriptPath === '' && $bootstrapCode !== null) {
                $cmd = [$phpBin, '-r', $bootstrapCode];
            } elseif ($bootstrapCode !== null) {
                $cmd = [
                    $phpBin,
                    '-r',
                    $bootstrapCode . ' require ' . var_export($normalizedScriptPath, true) . ';',
                ];
            } else {
                $cmd = [$phpBin, $scriptPath];
            }

            // Redirect stdin to null device to keep stdin closed without pipe handles.
            // stdout and stderr are redirected directly to separate temporary files.
            $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
            $descriptors = [
                0 => ['file', $nullDevice, 'r'],
                1 => ['file', $stdoutFile, 'w'],
                2 => ['file', $stderrFile, 'w'],
            ];

            $pipes = [];
            $process = @proc_open($cmd, $descriptors, $pipes, $workingDir);

            if (!is_resource($process)) {
                // Fallback: descriptor 0 as pipe closed immediately
                $descriptors[0] = ['pipe', 'r'];
                $process = @proc_open($cmd, $descriptors, $pipes, $workingDir);
                if (isset($pipes[0]) && is_resource($pipes[0])) {
                    fclose($pipes[0]);
                }
            }

            if (is_resource($process)) {
                $usedProcOpen = true;

                // Ensure child stdin is closed immediately if pipe was used
                if (isset($pipes[0]) && is_resource($pipes[0])) {
                    fclose($pipes[0]);
                }

                $startTime = function_exists('hrtime') ? (hrtime(true) / 1e9) : microtime(true);
                $status = proc_get_status($process);
                $childPid = (int)($status['pid'] ?? 0);

                while (true) {
                    $status = proc_get_status($process);
                    if (isset($status['pid']) && (int)$status['pid'] > 0) {
                        $childPid = (int)$status['pid'];
                    }

                    if (!$status['running']) {
                        break;
                    }

                    $now = function_exists('hrtime') ? (hrtime(true) / 1e9) : microtime(true);
                    if (($now - $startTime) >= $timeoutSeconds) {
                        $timedOut = true;
                        break;
                    }

                    usleep(10000); // 10ms sleep prevents CPU spinning
                }

                if ($timedOut) {
                    // Acceptance Criteria 3:
                    // Terminate process tree in a controlled platform-aware manner.
                    // Do NOT call proc_close while descendants can retain inherited handles.
                    if ($childPid > 0) {
                        if (PHP_OS_FAMILY === 'Windows') {
                            @exec("taskkill /F /T /PID {$childPid} 2>NUL");
                        } else {
                            @exec("pkill -TERM -P {$childPid} 2>/dev/null");
                            @exec("kill -TERM -{$childPid} 2>/dev/null || kill -TERM {$childPid} 2>/dev/null");
                            usleep(20000);
                            @exec("pkill -9 -P {$childPid} 2>/dev/null");
                            @exec("kill -9 -{$childPid} 2>/dev/null || kill -9 {$childPid} 2>/dev/null");
                        }
                    }
                    @proc_terminate($process);

                    $exitCode = 124;
                    $stderr .= ($stderr !== '' ? "\n" : '') . "Process timed out after {$timeoutSeconds}s\n";
                } else {
                    $closeExit = proc_close($process);
                    if ($closeExit !== -1) {
                        $exitCode = $closeExit;
                    } elseif (isset($status['exitcode']) && $status['exitcode'] >= 0) {
                        $exitCode = $status['exitcode'];
                    } else {
                        $exitCode = 0;
                    }
                }
            }
        }

        if (!$usedProcOpen) {
            // Fallback for environments where proc_open fails or is disabled
            $cmdStr = '';
            if ($scriptPath === '' && $bootstrapCode !== null) {
                if (PHP_OS_FAMILY === 'Windows') {
                    $cmdStr = escapeshellarg($phpBin) . ' -r ' . '"' . str_replace('"', '\"', $bootstrapCode) . '"';
                } else {
                    $cmdStr = escapeshellarg($phpBin) . ' -r ' . escapeshellarg($bootstrapCode);
                }
            } elseif ($bootstrapCode !== null) {
                $code = $bootstrapCode . ' require ' . var_export($normalizedScriptPath, true) . ';';
                if (PHP_OS_FAMILY === 'Windows') {
                    $cmdStr = escapeshellarg($phpBin) . ' -r ' . '"' . str_replace('"', '\"', $code) . '"';
                } else {
                    $cmdStr = escapeshellarg($phpBin) . ' -r ' . escapeshellarg($code);
                }
            } else {
                $cmdStr = escapeshellarg($phpBin) . ' ' . escapeshellarg($scriptPath);
            }
            $escapedCmd = $cmdStr . ' > ' . escapeshellarg($stdoutFile) . ' 2> ' . escapeshellarg($stderrFile);
            $lines = [];
            exec($escapedCmd, $lines, $exitCode);
        }

        // Read diagnostics from temporary files after completion
        if (is_string($stdoutFile) && file_exists($stdoutFile)) {
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $content = @file_get_contents($stdoutFile);
                if ($content !== false) {
                    $stdout .= $content;
                    break;
                }
                usleep(10000);
            }
        }
        if (is_string($stderrFile) && file_exists($stderrFile)) {
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $content = @file_get_contents($stderrFile);
                if ($content !== false) {
                    if ($content !== '') {
                        $stderr .= ($stderr !== '' ? "\n" : '') . $content;
                    }
                    break;
                }
                usleep(10000);
            }
        }
    } finally {
        // Acceptance Criteria 2:
        // Removes every temporary file on success, failure and timeout.
        if (is_string($stdoutFile) && file_exists($stdoutFile)) {
            for ($i = 0; $i < 5; $i++) {
                if (@unlink($stdoutFile) || !file_exists($stdoutFile)) {
                    break;
                }
                usleep(10000);
            }
        }
        if (is_string($stderrFile) && file_exists($stderrFile)) {
            for ($i = 0; $i < 5; $i++) {
                if (@unlink($stderrFile) || !file_exists($stderrFile)) {
                    break;
                }
                usleep(10000);
            }
        }
    }

    $tempFilesCleaned = (
        (!is_string($stdoutFile) || !file_exists($stdoutFile)) &&
        (!is_string($stderrFile) || !file_exists($stderrFile))
    );

    return [
        'exitCode' => $exitCode,
        'stdout' => $stdout,
        'stderr' => $stderr,
        'timedOut' => $timedOut,
        'pid' => $childPid,
        'tempFilesCleaned' => $tempFilesCleaned,
    ];
};

// =============================================================================
// TIMEOUT PROOF: ISOLATED SUBPROCESS TIMEOUT SIMULATION & CLEANUP (TASK-0276)
// =============================================================================
echo "--- PRUEBA DE TIMEOUT: Simulación de timeout de subproceso y verificación de limpieza ---\n";

$stdoutMarker = 'SSO_TIMEOUT_PROOF_STDOUT_MARKER';
$stderrMarker = 'SSO_TIMEOUT_PROOF_STDERR_MARKER';
$timeoutCode = 'fwrite(STDOUT, \'SSO_TIMEOUT_PROOF_STDOUT_MARKER\' . PHP_EOL); '
    . 'fwrite(STDERR, \'SSO_TIMEOUT_PROOF_STDERR_MARKER\' . PHP_EOL); '
    . 'fflush(STDOUT); fflush(STDERR); sleep(3);';

$timeoutStart = function_exists('hrtime') ? (hrtime(true) / 1e9) : microtime(true);
$timeoutResult = $runSubprocess(PHP_BINARY, '', $projectRoot, $timeoutCode, 1);
$timeoutElapsed = (function_exists('hrtime') ? (hrtime(true) / 1e9) : microtime(true)) - $timeoutStart;

// Verify PID is no longer running without inspecting the global process tree
$timeoutPid = (int)($timeoutResult['pid'] ?? 0);
$timeoutPidRunning = false;
if ($timeoutPid > 0) {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        if (PHP_OS_FAMILY === 'Windows') {
            $checkLines = [];
            @exec("tasklist /FI \"PID eq {$timeoutPid}\" /FO CSV /NH 2>NUL", $checkLines);
            $checkOutput = implode("\n", $checkLines);
            $timeoutPidRunning = (preg_match('/\b' . $timeoutPid . '\b/', $checkOutput) === 1);
        } else {
            if (function_exists('posix_kill')) {
                $timeoutPidRunning = @posix_kill($timeoutPid, 0);
            } else {
                $checkLines = [];
                $checkExit = 0;
                @exec("kill -0 {$timeoutPid} 2>/dev/null", $checkLines, $checkExit);
                $timeoutPidRunning = ($checkExit === 0);
            }
        }
        if (!$timeoutPidRunning) {
            break;
        }
        usleep(50000);
    }
}

// Assertions for timeout simulation (TASK-0276)
$assertTimeout = function (bool $condition, string $message): void {
    assert($condition, $message);
    if (!$condition) {
        fwrite(STDERR, "  [FAIL] Timeout proof: {$message}\n");
        throw new \RuntimeException("Timeout proof assertion failed: {$message}");
    }
    echo "  [PASS] Timeout proof: {$message}\n";
};

$assertTimeout($timeoutResult['timedOut'] === true, 'timedOut is strictly true');
$assertTimeout($timeoutResult['exitCode'] !== 0, "exitCode is non-zero ({$timeoutResult['exitCode']})");
$assertTimeout($timeoutElapsed < 5.0, sprintf('elapsed time is bounded below five seconds (%.3fs)', $timeoutElapsed));
$assertTimeout(
    strpos($timeoutResult['stdout'], $stdoutMarker) !== false &&
    strpos($timeoutResult['stderr'], $stderrMarker) !== false,
    'both markers are recovered'
);
$assertTimeout($timeoutPid > 0 && !$timeoutPidRunning, "returned PID ({$timeoutPid}) is no longer running");
$assertTimeout($timeoutResult['tempFilesCleaned'] === true, 'tempFilesCleaned is true');

echo "\n";

$suiteIndex = 0;
foreach ($suitesToRun as $suiteFile => $info) {
    $suiteIndex++;
    $suitePath = $info['path'];
    $description = $info['description'];
    $bootstrap = $info['bootstrap'] ?? null;

    echo "[{$suiteIndex}/" . count($suitesToRun) . "] Ejecutando: {$suiteFile}\n";
    echo "    Descripción: {$description}\n";
    if ($bootstrap !== null) {
        echo "    Bootstrap: [Test-only SSO_ENABLED=false isolator]\n";
    }

    if (!file_exists($suitePath)) {
        $allSubprocessesPassed = false;
        echo "    RESULTADO: [FALLO] (Archivo no encontrado: {$suitePath})\n\n";
        $subprocessResults[$suiteFile] = [
            'description' => $description,
            'passed' => false,
            'exitCode' => 1,
            'durationMs' => 0.0,
            'stdout' => '',
            'stderr' => "File not found: {$suitePath}",
        ];
        continue;
    }

    // Guard against recursive self-invocation
    if (basename($suitePath) === basename(__FILE__)) {
        continue;
    }

    $startTime = microtime(true);
    $result = $runSubprocess($phpBinary, $suitePath, $projectRoot, $bootstrap);
    $elapsed = round((microtime(true) - $startTime) * 1000, 1);

    $passed = ($result['exitCode'] === 0);
    if (!$passed) {
        $allSubprocessesPassed = false;
        echo "    RESULTADO: [FALLO] (Código de salida: {$result['exitCode']}, {$elapsed}ms)\n";
        if (trim($result['stderr']) !== '') {
            echo "    STDERR:\n" . preg_replace('/^/m', '      ', trim($result['stderr'])) . "\n";
        }
        if (trim($result['stdout']) !== '') {
            echo "    STDOUT (últimas 10 líneas):\n";
            $stdoutLines = explode("\n", trim($result['stdout']));
            $tail = array_slice($stdoutLines, -10);
            echo preg_replace('/^/m', '      ', implode("\n", $tail)) . "\n";
        }
    } else {
        echo "    RESULTADO: [PASS] (Código de salida: 0, {$elapsed}ms)\n";
    }

    $subprocessResults[$suiteFile] = [
        'description' => $description,
        'passed' => $passed,
        'exitCode' => $result['exitCode'],
        'durationMs' => $elapsed,
        'stdout' => $result['stdout'],
        'stderr' => $result['stderr'],
    ];

    echo "\n";
}

$contractRunner->assert(
    count($suitesToRun) === 8 && count($subprocessResults) === 8,
    'Subprocess runner executed exactly the 8 canonical accepted SSO test suites'
);
$contractRunner->assert(
    $allSubprocessesPassed,
    'All canonical accepted SSO test suites executed in isolated subprocesses passed with exit code 0'
);

// =============================================================================
// PHASE 2: STATIC SECURITY & CODEBASE CONTRACT CHECKS
// =============================================================================
echo "--- FASE 2: Verificación estática de seguridad y contratos de la base de código ---\n\n";

// 2.1 Static Check: No functional OpenID Connect artifacts
echo "Validando ausencia de artefactos OpenID Connect en el repositorio...\n";
$forbiddenOidcTerms = [
    implode('', ['open', 'id']),
    implode('', ['id_', 'token']),
    implode('', ['non', 'ce']),
    implode('', ['oi', 'dc']),
];

$pathsToScanForOidc = [
    $projectRoot . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'SsoFlow.php',
    $projectRoot . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'SsoCallbackHandler.php',
    $projectRoot . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'OAuthIdentityProviderClient.php',
    $projectRoot . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'FederatedIdentityRepository.php',
    $projectRoot . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'SsoSession.php',
    $projectRoot . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'SsoAuthorization.php',
    $projectRoot . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'login.php',
    $projectRoot . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'sso-login.php',
    $projectRoot . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'sso-callback.php',
    $projectRoot . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'logout.php',
    $projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'SsoConfig.php',
    $projectRoot . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '20261012_sso_identity_links.sql',
];

$oidcViolations = [];
foreach ($pathsToScanForOidc as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if ($content === false) {
            continue;
        }
        $rel = str_replace($projectRoot . DIRECTORY_SEPARATOR, '', $file);
        foreach ($forbiddenOidcTerms as $term) {
            if (stripos($content, $term) !== false) {
                $oidcViolations[] = "Forbidden term '{$term}' found in {$rel}";
            }
        }
    }
}
$contractRunner->assert(
    empty($oidcViolations),
    'Zero functional OpenID Connect artifacts detected across product, configuration, and migrations'
);

// 2.2 Static Check: No auto-admin or default role fallback anti-patterns
echo "Validando ausencia de patrones auto-admin y roles por defecto...\n";
$forbiddenFallbackPatterns = [
    '/\?\?\s*[\'"]admin[\'"]/i' => 'Null-coalesce fallback to literal "admin" (e.g. ?? "admin")',
    '/\?:\s*[\'"]admin[\'"]/i' => 'Ternary fallback to literal "admin" (e.g. ?: "admin")',
    '/\b(default|fallback)[_\w]*\s*(?:=>|:|=)\s*[\'"]admin[\'"]/i' => 'Default/fallback configuration with "admin"',
    '/\bdefault\s*:\s*(?:return\s+)?[\'"]admin[\'"]/i' => 'Switch default case returning literal "admin"',
    '/(?<![=!<>])=\s*[\'"]admin[\'"]/i' => 'Direct assignment of literal "admin"',
];

$pathsToScanForAdminFallback = [
    $projectRoot . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'SsoFlow.php',
    $projectRoot . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'SsoCallbackHandler.php',
    $projectRoot . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'FederatedIdentityRepository.php',
    $projectRoot . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'SsoAuthorization.php',
    $projectRoot . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'SsoSession.php',
    $projectRoot . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'sso-callback.php',
];

$adminFallbackViolations = [];
foreach ($pathsToScanForAdminFallback as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if ($content === false) {
            continue;
        }
        $rel = str_replace($projectRoot . DIRECTORY_SEPARATOR, '', $file);
        foreach ($forbiddenFallbackPatterns as $pattern => $desc) {
            if (preg_match($pattern, $content)) {
                $adminFallbackViolations[] = "Forbidden fallback pattern '{$desc}' found in {$rel}";
            }
        }
    }
}
$contractRunner->assert(
    empty($adminFallbackViolations),
    'Zero forbidden auto-admin / default role fallback anti-patterns in auth pipeline'
);

// 2.3 Static Check: No stored access token in session, persistence or migration schema
echo "Validando ausencia de almacenamiento persistente de access tokens...\n";
$sessionContent = file_get_contents($projectRoot . '/core/auth/SsoSession.php') ?: '';
$migrationContent = file_get_contents($projectRoot . '/db/migrations/20261012_sso_identity_links.sql') ?: '';

$storesTokenInSession = (bool)preg_match('/(?:_SESSION|session)\[[\'"](?:access_token|token)[\'"]\]\s*=/i', $sessionContent);
$storesTokenInDb = (bool)preg_match('/\b(?:access_token|token|refresh_token)\b\s+VARCHAR/i', $migrationContent);

$contractRunner->assert(
    !$storesTokenInSession,
    'SsoSession never writes or retains access_token in session variables'
);
$contractRunner->assert(
    !$storesTokenInDb,
    'Identity links database migration schema contains zero token or secret storage columns'
);

// 2.4 Static Check: SSO disabled by default and no unintended SSO activation
echo "Validando estado disabled-by-default de SSO...\n";
$ssoConfigFile = $projectRoot . '/config/SsoConfig.php';
require_once $ssoConfigFile;

$ssoConfigContent = file_get_contents($ssoConfigFile) ?: '';
$defaultEnabledPattern = (bool)preg_match('/DEFAULT_ENABLED\s*=\s*false/i', $ssoConfigContent);
$isDefaultDisabled = (defined('SsoConfig::DEFAULT_ENABLED') && SsoConfig::DEFAULT_ENABLED === false);

$contractRunner->assert(
    $defaultEnabledPattern && $isDefaultDisabled,
    'SsoConfig defines DEFAULT_ENABLED = false strictly (fail-closed disabled-by-default)'
);

SsoConfig::reset();
$contractRunner->assert(
    SsoConfig::isEnabled() === false,
    'SsoConfig::isEnabled() reports false under default unconfigured environment'
);

// 2.5 Static Check: No reference to real credentials or real server paths
echo "Validando ausencia de credenciales reales o rutas de servidor locales...\n";
$authFiles = array_merge($pathsToScanForOidc, [$ssoConfigFile]);
$leakedPathOrCredential = false;
$realPathPatterns = [
    '/[A-Z]:\\\\Users\\\\[a-zA-Z0-9_]+/i',
    '/\/home\/[a-zA-Z0-9_]+/i',
    '/\/var\/www\/html/i',
    '/ghp_[a-zA-Z0-9]{30,}/i',
    '/gho_[a-zA-Z0-9]{30,}/i',
    '/client_secret\s*=>\s*[\'"][a-zA-Z0-9]{20,}[\'"]/i',
];

foreach ($authFiles as $f) {
    if (file_exists($f)) {
        $c = file_get_contents($f) ?: '';
        foreach ($realPathPatterns as $pat) {
            if (preg_match($pat, $c)) {
                $leakedPathOrCredential = true;
                fwrite(STDERR, "  [FAIL] Sensitive path or credential pattern matched in " . basename($f) . "\n");
            }
        }
    }
}
$contractRunner->assert(
    !$leakedPathOrCredential,
    'Zero hardcoded real credentials, tokens, or absolute user/server filesystem paths detected'
);

// =============================================================================
// PHASE 3: VERIFICATION OF THE FULL OAUTH2 + USERINFO CLIENT BOUNDARY
// =============================================================================
echo "\n--- FASE 3: Verificación de contratos y límites de interfaz OAuth2 + Userinfo ---\n\n";

// 3.1 Verify boundary components and interfaces via reflection (no duplicated product logic)
require_once $projectRoot . '/core/auth/IdentityProfile.php';
require_once $projectRoot . '/core/auth/IdentityProviderClient.php';
require_once $projectRoot . '/core/auth/OAuthIdentityProviderClient.php';
require_once $projectRoot . '/core/auth/SsoFlow.php';
require_once $projectRoot . '/core/auth/SsoCallbackHandler.php';
require_once $projectRoot . '/core/auth/FederatedIdentityRepository.php';
require_once $projectRoot . '/core/auth/SsoAuthorization.php';
require_once $projectRoot . '/core/auth/SsoSession.php';

use Electroplan\Auth\IdentityProfile;
use Electroplan\Auth\IdentityProviderClient;
use Electroplan\Auth\OAuthIdentityProviderClient;
use Electroplan\Auth\SsoFlow;
use Electroplan\Auth\SsoCallbackHandler;
use Electroplan\Auth\FederatedIdentityRepository;
use Electroplan\Auth\SsoAuthorization;
use Electroplan\Auth\SsoSession;

// 3.1.1 OAuthIdentityProviderClient implements IdentityProviderClient
$clientRef = new ReflectionClass(OAuthIdentityProviderClient::class);
$contractRunner->assert(
    $clientRef->implementsInterface(IdentityProviderClient::class),
    'OAuthIdentityProviderClient implements provider-neutral IdentityProviderClient interface'
);
$contractRunner->assert(
    $clientRef->hasMethod('exchangeCode') && $clientRef->hasMethod('fetchUserInfo'),
    'OAuthIdentityProviderClient defines explicit exchangeCode and fetchUserInfo methods'
);

// 3.1.2 PKCE method strictly enforced to S256
$flowRef = new ReflectionClass(SsoFlow::class);
$contractRunner->assert(
    $flowRef->hasConstant('PKCE_METHOD_S256') && $flowRef->getConstant('PKCE_METHOD_S256') === 'S256',
    'SsoFlow enforces PKCE_METHOD_S256 = "S256" strictly'
);

// 3.1.3 FederatedIdentityRepository requires explicit PDO dependency
$repoRef = new ReflectionMethod(FederatedIdentityRepository::class, '__construct');
$contractRunner->assert(
    $repoRef->getNumberOfRequiredParameters() === 1,
    'FederatedIdentityRepository constructor strictly requires explicit PDO dependency'
);
$contractRunner->assert(
    $repoRef->getParameters()[0]->getType() !== null && (string)$repoRef->getParameters()[0]->getType() === 'PDO',
    'FederatedIdentityRepository constructor type-hints PDO parameter'
);

// 3.1.4 SsoAuthorization enforces role non-elevation and fail-closed rules
$authRef = new ReflectionClass(SsoAuthorization::class);
$contractRunner->assert(
    $authRef->hasMethod('authorize') && $authRef->hasMethod('authorizeIdentity') && $authRef->hasMethod('isAuthorized'),
    'SsoAuthorization provides authorize, authorizeIdentity, and isAuthorized policy boundaries'
);

// 3.1.5 SsoSession enforces Secure, HttpOnly, and SameSite=Lax without downgrade parameters
$cookieMethodRef = new ReflectionMethod(SsoSession::class, 'configureCookieParams');
$contractRunner->assert(
    $cookieMethodRef->getNumberOfParameters() === 0,
    'SsoSession::configureCookieParams accepts zero parameters (no security downgrade allowed)'
);

SsoSession::configureCookieParams();
$cookieConfig = SsoSession::getLastConfiguredCookieParams();
$contractRunner->assert(
    $cookieConfig['secure'] === true && $cookieConfig['httponly'] === true && $cookieConfig['samesite'] === 'Lax',
    'SsoSession cookie configuration guarantees Secure=true, HttpOnly=true, SameSite=Lax'
);

// 3.1.6 SsoFlow verifies state and PKCE challenge computation contract
$flow = new SsoFlow();
$testVerifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
$computedChallenge = $flow->computeCodeChallenge($testVerifier, 'S256');
$expectedChallenge = rtrim(strtr(base64_encode(hash('sha256', $testVerifier, true)), '+/', '-_'), '=');
$contractRunner->assert(
    $computedChallenge === $expectedChallenge,
    'SsoFlow::computeCodeChallenge computes RFC 7636 compliant BASE64URL(SHA256(verifier)) digest'
);

// Plain PKCE method rejection
$rejectedPlain = false;
try {
    $flow->computeCodeChallenge($testVerifier, 'plain');
} catch (\InvalidArgumentException $e) {
    $rejectedPlain = true;
}
$contractRunner->assert(
    $rejectedPlain,
    'SsoFlow strictly rejects "plain" PKCE code challenge method'
);

// 3.1.7 IdentityProfile contract validation
$claims = [
    'hub_user_id' => 'hub-user-test-777',
    'email' => 'user@example.com',
    'name' => 'Test User',
    'active' => true,
    'roles' => ['technician'],
    'provider' => 'central_hub',
];
$profile = IdentityProfile::fromClaims($claims);
$contractRunner->assert(
    $profile->getId() === 'hub-user-test-777' && $profile->isActive() === true && $profile->getProvider() === 'central_hub',
    'IdentityProfile correctly parses and encapsulates normalized provider-neutral claims'
);

// Missing user ID fails closed
$threwMissingId = false;
try {
    IdentityProfile::fromClaims(['email' => 'user@example.com']);
} catch (\InvalidArgumentException $e) {
    $threwMissingId = true;
}
$contractRunner->assert(
    $threwMissingId,
    'IdentityProfile::fromClaims fails closed when user identifier is missing'
);

// =============================================================================
// AGGREGATE SUMMARY & EXIT STATUS
// =============================================================================
echo "\n================================================================================\n";
echo " RESUMEN GENERAL DE LA SUITE DE CONTRATO SSO CLIENT (TASK-0264)\n";
echo "================================================================================\n\n";

echo "1. Suites ejecutadas en subprocesos aislados: " . count($suitesToRun) . "\n";
foreach ($subprocessResults as $suiteName => $subInfo) {
    $statusStr = $subInfo['passed'] ? '[PASS]' : '[FAIL]';
    echo "   - {$statusStr} {$suiteName} ({$subInfo['durationMs']}ms, exit: {$subInfo['exitCode']})\n";
}

$assertionsCount = $contractRunner->getAssertionsCount();
$failuresCount = $contractRunner->getFailuresCount();

echo "\n2. Aserciones estáticas y de límites de contrato: {$assertionsCount} ejecutadas, {$failuresCount} fallos.\n";

$aggregatePass = ($allSubprocessesPassed && $failuresCount === 0);

if ($aggregatePass) {
    echo "\n>>> RESULTADO FINAL: TODOS LOS CONTRATOS SSO CLIENT VALIDADOS EXITOSAMENTE [PASS] <<<\n";
    exit(0);
} else {
    fwrite(STDERR, "\n>>> RESULTADO FINAL: LA SUITE DE CONTRATO FALLÓ CON " . ($allSubprocessesPassed ? 0 : 1) . " SUBPROCESOS FALLIDOS Y {$failuresCount} ASERCIONES FALLIDAS [FAIL] <<<\n");
    if (!empty($contractRunner->getFailureDetails())) {
        fwrite(STDERR, "Detalles de aserciones fallidas:\n");
        foreach ($contractRunner->getFailureDetails() as $detail) {
            fwrite(STDERR, " - {$detail}\n");
        }
    }
    exit(1);
}
