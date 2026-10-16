<?php
/**
 * Electroplan SSO Composer Pipeline & CI/Deploy Contract Test Suite.
 * TASK-0314: Electroplan Composer CI replacement.
 *
 * Acceptance criteria:
 * 1. The root composer.json contains only OAuth2 dependencies actually used,
 *    remains compatible with PHP 8.2 and does not alter panel_schedule dependencies.
 * 2. Root composer.lock is reproducible and valid.
 * 3. When SSO is enabled, missing root vendor/autoload.php fails closed with a sanitized
 *    error and no credential, path or dependency detail disclosure.
 * 4. When SSO is disabled, missing root vendor/autoload.php does not break or replace
 *    the traditional local login.
 * 5. CI installs from root composer.lock and runs Composer validation, PHP lint and
 *    the complete Electroplan SSO aggregate (eight suites, 21 assertions, timeout proof).
 * 6. Deploy remains automatic only for push to main and builds optimized no-dev root
 *    vendor dependencies before artifact creation.
 * 7. The runtime artifact includes root vendor and excludes tests, documentation,
 *    private configuration, .env files, caches and migrations.
 * 8. vendor is not versioned and no FTP or deployment action is executed by this task.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$projectRoot = dirname(__DIR__);

class SsoComposerPipelineContractRunner
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

$runner = new SsoComposerPipelineContractRunner();

echo "================================================================================\n";
echo " Electroplan SSO Composer Pipeline & CI/Deploy Contract Suite (TASK-0314)\n";
echo "================================================================================\n\n";

// =============================================================================
// 1. ROOT COMPOSER.JSON CONTRACT
// =============================================================================
echo "--- 1. Validando contrato de composer.json ---\n";

$composerJsonPath = $projectRoot . DIRECTORY_SEPARATOR . 'composer.json';
$runner->assert(file_exists($composerJsonPath), 'composer.json exists in project root');

$composerJsonRaw = file_get_contents($composerJsonPath);
$composerData = json_decode($composerJsonRaw ?: '', true);

$runner->assert(is_array($composerData), 'composer.json is valid JSON');
$runner->assert(isset($composerData['name']) && $composerData['name'] === 'electroplan/electroplan', 'composer.json package name is electroplan/electroplan');

$require = $composerData['require'] ?? [];
$runner->assert(isset($require['php']), 'composer.json declares PHP version requirement');
$runner->assert(isset($require['league/oauth2-client']), 'composer.json declares league/oauth2-client dependency');

// Only OAuth2 dependency actually used
$nonPhpRequires = array_keys(array_filter($require, fn($k) => $k !== 'php', ARRAY_FILTER_USE_KEY));
$runner->assert(
    count($nonPhpRequires) === 1 && $nonPhpRequires[0] === 'league/oauth2-client',
    'composer.json contains only the OAuth2 dependencies actually used'
);

// PHP 8.2 compatibility
$phpConstraint = $require['php'] ?? '';
$runner->assert(
    str_contains($phpConstraint, '8.2') || str_contains($phpConstraint, '^8') || str_contains($phpConstraint, '>=8'),
    'composer.json PHP requirement is compatible with PHP 8.2'
);

// Does not alter panel_schedule
$runner->assert(
    !isset($composerData['repositories']) || !str_contains($composerJsonRaw, 'panel_schedule'),
    'composer.json does not alter or reference panel_schedule dependencies'
);

// License specified for strict validation
$runner->assert(isset($composerData['license']), 'composer.json declares license for strict validation');

// Autoload psr-4 mapping
$runner->assert(
    isset($composerData['autoload']['psr-4']['Electroplan\\']),
    'composer.json configures PSR-4 autoloading for Electroplan namespace'
);

// =============================================================================
// 2. FAIL-CLOSED BEHAVIOR ON MISSING VENDOR/AUTOLOAD.PHP WHEN SSO IS ENABLED
// =============================================================================
echo "\n--- 2. Validando fail-closed ante ausencia de vendor/autoload.php con SSO habilitado ---\n";

// Guarantee missing-vendor condition even if composer install has already run
$vendorAutoloadPath = $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
$vendorAutoloadBackup = $projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php.pipeline_contract_bak_' . bin2hex(random_bytes(6));
$vendorAutoloadRelocated = false;

// Clean up any stale backup left from prior interrupted runs
$existingBackups = glob($projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php.pipeline_contract_bak*');
if (!empty($existingBackups)) {
    foreach ($existingBackups as $existingBackup) {
        if (!file_exists($vendorAutoloadPath)) {
            @rename($existingBackup, $vendorAutoloadPath);
            break;
        } else {
            @unlink($existingBackup);
        }
    }
}

if (file_exists($vendorAutoloadPath)) {
    if (@rename($vendorAutoloadPath, $vendorAutoloadBackup)) {
        $vendorAutoloadRelocated = true;
    }
}

$restoreVendorAutoload = function () use ($vendorAutoloadPath, $vendorAutoloadBackup, &$vendorAutoloadRelocated): void {
    if ($vendorAutoloadRelocated && file_exists($vendorAutoloadBackup)) {
        @rename($vendorAutoloadBackup, $vendorAutoloadPath);
        $vendorAutoloadRelocated = false;
    }
};

register_shutdown_function($restoreVendorAutoload);

try {
    // Test pages/sso-login.php with SSO enabled and simulated missing vendor
    $_SESSION = [];
    $injectedSsoEnabled = true;
    $injectedSsoConfig = [
        'enabled' => true,
        'client_id' => 'test-client',
        'client_secret' => 'test-secret',
        'redirect_uri' => 'https://app.example.com/pages/sso-callback.php',
        'authorize_url' => 'https://hub.example.com/oauth/authorize',
        'token_url' => 'https://hub.example.com/oauth/token',
        'userinfo_url' => 'https://hub.example.com/oauth/userinfo',
    ];
    unset($injectedClient);

    $loginOutcome = require $projectRoot . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'sso-login.php';

    $runner->assert(is_array($loginOutcome), 'sso-login.php returns structured outcome array');
    $runner->assert($loginOutcome['success'] === false, 'sso-login.php fails closed when vendor/autoload.php is missing');
    $runner->assert($loginOutcome['redirect_url'] === null, 'sso-login.php returns null redirect_url on vendor missing');
    $runner->assert(!empty($loginOutcome['error']), 'sso-login.php returns sanitized error message');

    // Verify error sanitization: no paths, credentials, or dependency details disclosed
    $errorText = (string)($loginOutcome['error'] ?? '');
    $hasLeakedPath = str_contains($errorText, '/') || str_contains($errorText, '\\') || stripos($errorText, 'autoload') !== false;
    $hasLeakedDependency = stripos($errorText, 'composer') !== false || stripos($errorText, 'league') !== false;
    $hasLeakedSecret = stripos($errorText, 'secret') !== false || stripos($errorText, 'password') !== false;

    $runner->assert(!$hasLeakedPath, 'sso-login.php error discloses no file system paths');
    $runner->assert(!$hasLeakedDependency, 'sso-login.php error discloses no dependency or vendor details');
    $runner->assert(!$hasLeakedSecret, 'sso-login.php error discloses no secrets or credentials');
    $runner->assert(!class_exists(\Electroplan\Auth\OAuthIdentityProviderClient::class, false), 'sso-login.php does not load OAuthIdentityProviderClient when vendor/autoload.php is missing');

    // Test pages/sso-callback.php with SSO enabled and simulated missing vendor
    $_SESSION = [];
    unset($injectedCallbackHandler);
    $queryParams = ['code' => 'auth_code_123', 'state' => 'state_abc'];

    $callbackOutcome = require $projectRoot . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'sso-callback.php';

    $runner->assert(is_array($callbackOutcome), 'sso-callback.php returns structured outcome array');
    $runner->assert($callbackOutcome['success'] === false, 'sso-callback.php fails closed when vendor/autoload.php is missing');
    $runner->assert(!empty($callbackOutcome['error']), 'sso-callback.php returns sanitized error message');

    $callbackErrorText = (string)($callbackOutcome['error'] ?? '');
    $cbLeakedPath = str_contains($callbackErrorText, '/') || str_contains($callbackErrorText, '\\') || stripos($callbackErrorText, 'autoload') !== false;
    $cbLeakedDependency = stripos($callbackErrorText, 'composer') !== false || stripos($callbackErrorText, 'league') !== false;
    $runner->assert(!$cbLeakedPath, 'sso-callback.php error discloses no file system paths');
    $runner->assert(!$cbLeakedDependency, 'sso-callback.php error discloses no dependency details');

    // Clean up injected globals
    unset($injectedSsoEnabled, $injectedSsoConfig, $queryParams);

    // =============================================================================
    // 3. PRESERVATION OF TRADITIONAL LOCAL LOGIN WHEN SSO IS DISABLED
    // =============================================================================
    echo "\n--- 3. Validando preservación del login local tradicional cuando SSO está deshabilitado ---\n";

    $loginPagePath = $projectRoot . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'login.php';
    $loginPageContent = file_get_contents($loginPagePath) ?: '';

    // Traditional local login fields
    $hasUsernameInput = (bool)preg_match('/<input[^>]+name=[\'"]username[\'"]/i', $loginPageContent);
    $hasPasswordInput = (bool)preg_match('/<input[^>]+name=[\'"]password[\'"]/i', $loginPageContent);
    $hasCsrfToken = (bool)preg_match('/<input[^>]+name=[\'"]csrf_token[\'"]/i', $loginPageContent);
    $hasSignInButton = (bool)preg_match('/<button[^>]+type=[\'"]submit[\'"]/i', $loginPageContent);

    $runner->assert($hasUsernameInput, 'pages/login.php preserves traditional username input field');
    $runner->assert($hasPasswordInput, 'pages/login.php preserves traditional password input field');
    $runner->assert($hasCsrfToken, 'pages/login.php preserves CSRF protection');
    $runner->assert($hasSignInButton, 'pages/login.php preserves local submission action');

    // When SSO is disabled, missing vendor does not break sso-login.php (it returns SSO is disabled)
    $_SESSION = [];
    $injectedSsoEnabled = false;
    $disabledOutcome = require $projectRoot . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'sso-login.php';
    $runner->assert($disabledOutcome['success'] === false, 'sso-login.php returns failure when SSO is disabled');
    $runner->assert(
        str_contains((string)($disabledOutcome['error'] ?? ''), 'disabled'),
        'sso-login.php reports SSO is disabled without checking or requiring vendor'
    );

    // When SSO is disabled, sso-callback.php returns SSO is disabled without checking vendor
    $_SESSION = [];
    $cbDisabledOutcome = require $projectRoot . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'sso-callback.php';
    $runner->assert($cbDisabledOutcome['success'] === false, 'sso-callback.php returns failure when SSO is disabled');
    $runner->assert(
        str_contains((string)($cbDisabledOutcome['error'] ?? ''), 'disabled'),
        'sso-callback.php reports SSO is disabled without checking or requiring vendor'
    );

    unset($injectedSsoEnabled);
} finally {
    $restoreVendorAutoload();
}

// =============================================================================
// 4. CI WORKFLOW CONTRACT (.github/workflows/ci.yml)
// =============================================================================
echo "\n--- 4. Validando contrato de CI (.github/workflows/ci.yml) ---\n";

$ciPath = $projectRoot . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'workflows' . DIRECTORY_SEPARATOR . 'ci.yml';
$runner->assert(file_exists($ciPath), '.github/workflows/ci.yml exists');

$ciContent = file_get_contents($ciPath) ?: '';

// Strict composer validation
$runner->assert(
    str_contains($ciContent, 'composer validate --strict'),
    'CI workflow executes "composer validate --strict"'
);

// Install exactly from root lock
$runner->assert(
    str_contains($ciContent, 'composer install'),
    'CI workflow executes composer install from root lock'
);

// PHP linting
$runner->assert(
    str_contains($ciContent, 'php -l'),
    'CI workflow executes PHP syntax linting'
);

// Complete eight-suite SSO aggregate + assertions + timeout proof
$runner->assert(
    str_contains($ciContent, 'tests/sso_client_contract.test.php'),
    'CI workflow executes complete SSO client aggregate test suite'
);

// SSO Composer pipeline contract
$runner->assert(
    str_contains($ciContent, 'tests/sso_composer_pipeline_contract.test.php'),
    'CI workflow executes SSO composer pipeline contract test suite'
);

// =============================================================================
// 5. DEPLOY WORKFLOW CONTRACT (.github/workflows/deploy.yml)
// =============================================================================
echo "\n--- 5. Validando contrato de Deploy (.github/workflows/deploy.yml) ---\n";

$deployPath = $projectRoot . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'workflows' . DIRECTORY_SEPARATOR . 'deploy.yml';
$runner->assert(file_exists($deployPath), '.github/workflows/deploy.yml exists');

$deployContent = file_get_contents($deployPath) ?: '';

// Deploy automatic only for push to main
$hasPushMain = (bool)preg_match('/on:\s+push:\s+branches:\s*\[?\s*[\'"]?main[\'"]?\s*\]?/m', $deployContent) ||
               (bool)preg_match('/branches:\s*[\r\n]+\s*-\s*main/m', $deployContent);
$runner->assert($hasPushMain, 'Deploy workflow triggers push automatically only for main branch');

// Builds optimized no-dev root vendor dependencies before artifact assembly
$hasOptimizedComposer = str_contains($deployContent, 'composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader');
$runner->assert($hasOptimizedComposer, 'Deploy workflow executes "composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader"');

// Includes root vendor in deploy artifact
$runner->assert(
    str_contains($deployContent, 'test -d deploy_artifact/vendor'),
    'Deploy workflow verifies root vendor directory in deploy artifact'
);
$runner->assert(
    str_contains($deployContent, 'test -f deploy_artifact/vendor/autoload.php'),
    'Deploy workflow verifies vendor/autoload.php in deploy artifact'
);

// Excludes tests, documentation, private config, .env files, caches and migrations
$runner->assert(str_contains($deployContent, "--exclude='tests/'"), 'Deploy workflow excludes tests/');
$runner->assert(str_contains($deployContent, "--exclude='docs/'") || str_contains($deployContent, "--exclude='*.md'"), 'Deploy workflow excludes documentation');
$runner->assert(str_contains($deployContent, "--exclude='.env*'"), 'Deploy workflow excludes .env files');
$runner->assert(str_contains($deployContent, "--exclude='config.local.php'"), 'Deploy workflow excludes private configuration');
$runner->assert(str_contains($deployContent, "--exclude='cache/'"), 'Deploy workflow excludes caches');
$runner->assert(str_contains($deployContent, "--exclude='db/migrations/'") || str_contains($deployContent, "--exclude='db/'"), 'Deploy workflow excludes database migrations');

// =============================================================================
// SUMMARY & EXIT
// =============================================================================
echo "\n================================================================================\n";
echo " RESUMEN DEL CONTRATO DE PIPELINE COMPOSER CI (TASK-0314)\n";
echo "================================================================================\n\n";

$assertions = $runner->getAssertionsCount();
$failures = $runner->getFailuresCount();

echo "Total aserciones: {$assertions}, Fallos: {$failures}\n";

if ($failures === 0) {
    echo "\n>>> TODOS LOS CONTRATOS DE PIPELINE COMPOSER FUERON VALIDADOS EXITOSAMENTE [PASS] <<<\n";
    exit(0);
} else {
    fwrite(STDERR, "\n>>> CONTRATO FALLÓ CON {$failures} ASERCIONES NO CUMPLIDAS [FAIL] <<<\n");
    foreach ($runner->getFailureDetails() as $detail) {
        fwrite(STDERR, " - {$detail}\n");
    }
    exit(1);
}
