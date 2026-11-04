<?php
/**
 * Focused Contract & Runtime Test Suite: Electroplan Login SSO Entry Redirect (TASK-0408).
 *
 * Acceptance criteria:
 * 1. A focused test specifies the redirect before implementation.
 * 2. With SSO enabled, anonymous GET and HEAD requests to pages/login.php return 302
 *    to /electroplan/pages/sso-login.php before database bootstrap or HTML output.
 * 3. Exactly one recovery opt-in is supported: query parameter local=1; no aliases or
 *    truthy variants are accepted.
 * 4. No production branch depends on injectedPdo, injectedDirectEntry or another test-only
 *    value to decide redirect behavior.
 * 5. Requests carrying an SSO error render the sanitized local fallback instead of looping.
 * 6. POST authentication and SSO-disabled behavior remain unchanged.
 * 7. Existing runtime tests explicitly request local=1 only where they intentionally
 *    render the SSO-enabled local form.
 * 8. The focused test and complete SSO aggregate suite pass.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$projectRoot = dirname(__DIR__);
$loginPagePath = $projectRoot . '/pages/login.php';

require_once $projectRoot . '/config/SsoConfig.php';
require_once $projectRoot . '/core/auth/SsoSession.php';

class SsoLoginEntryRedirectRunner
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

    public function summary(): int
    {
        echo "\nResumen: {$this->assertions} aserciones ejecutadas, {$this->failures} fallos.\n";
        if ($this->failures > 0) {
            foreach ($this->failureDetails as $detail) {
                fwrite(STDERR, " - {$detail}\n");
            }
            return 1;
        }
        return 0;
    }
}

$runner = new SsoLoginEntryRedirectRunner();

echo "================================================================================\n";
echo " Electroplan Login SSO Entry Guard Contract Suite (TASK-0408)\n";
echo "================================================================================\n\n";

// Helper PDO double for local authentication tests
class MockEntryTestPdo extends PDO
{
    public function __construct() {}
    #[\ReturnTypeWillChange]
    public function setAttribute(int $attribute, mixed $value): bool { return true; }
    #[\ReturnTypeWillChange]
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new MockEntryTestStatement();
    }
}

class MockEntryTestStatement extends PDOStatement
{
    public function __construct() {}
    #[\ReturnTypeWillChange]
    public function execute(?array $params = null): bool { return true; }
    #[\ReturnTypeWillChange]
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return [
            'id' => 1,
            'username' => 'test_user',
            'password' => password_hash('ValidPassword123!', PASSWORD_BCRYPT),
            'role' => 'technician',
            'active' => 1,
        ];
    }
}

$mockPdo = new MockEntryTestPdo();

$enabledConfig = [
    'enabled' => true,
    'hub_base_url' => 'https://hub.example.com',
    'client_id' => 'electroplan-client-id',
    'client_secret' => 'electroplan-secret-key',
    'redirect_uri' => 'https://app.example.com/pages/sso-callback.php',
    'authorize_url' => 'https://hub.example.com/oauth/authorize',
    'token_url' => 'https://hub.example.com/oauth/token',
    'userinfo_url' => 'https://hub.example.com/oauth/userinfo',
    'scopes' => ['profile', 'email'],
    'timeout' => 10,
    'SSO_ENABLED' => true,
];

// =============================================================================
// SUITE 1: STATIC CONTRACT & SOURCE AUDIT
// =============================================================================
echo "--- 1. Inspección estática del código y contratos de pages/login.php ---\n";

$loginSource = (string) file_get_contents($loginPagePath);

// 1.1 Fixed target redirect path
$runner->assert(
    str_contains($loginSource, "'/electroplan/pages/sso-login.php'"),
    'login.php targets the canonical SSO initiation path /electroplan/pages/sso-login.php'
);

// 1.2 Strict equality on local=1
$runner->assert(
    (bool) preg_match('/(?:\$_GET\[[\'"]local[\'"]\]|local)\s*===\s*[\'"]1[\'"]/', $loginSource),
    'login.php uses strict comparison === "1" for query parameter local'
);

// 1.3 Absence of recovery aliases or truthy variants
$forbiddenAliases = ['recovery', 'local_recovery', 'force_local', 'bypass_sso', 'skip_sso'];
$aliasFound = false;
foreach ($forbiddenAliases as $alias) {
    if (preg_match('/[\'"]' . preg_quote($alias, '/') . '[\'"]/', $loginSource)) {
        $aliasFound = true;
        fwrite(STDERR, "  [FAIL] Forbidden recovery alias found: {$alias}\n");
    }
}
$runner->assert(!$aliasFound, 'login.php contains zero recovery aliases (no recovery, local_recovery, force_local)');

// 1.4 No test seams decide production redirect behavior
$hasInjectedPdoSeam = (bool) preg_match('/(?:if|&&|\|\|)[^;]*injectedPdo[^;]*(?:Location|sso-login|redirect)/i', $loginSource);
$hasDirectEntrySeam = (bool) preg_match('/injectedDirectEntry/i', $loginSource);
$runner->assert(!$hasInjectedPdoSeam, 'login.php redirect decision does not depend on injectedPdo seam');
$runner->assert(!$hasDirectEntrySeam, 'login.php contains zero references to injectedDirectEntry');

// 1.5 Guard executes before database bootstrap and HTML output
$guardPos = strpos($loginSource, '/electroplan/pages/sso-login.php');
$dbBootstrapPos = strpos($loginSource, 'core/db/connection.php');
$htmlPos = strpos($loginSource, '<!DOCTYPE html>');

$runner->assert(
    $guardPos !== false && $dbBootstrapPos !== false && $guardPos < $dbBootstrapPos,
    'SSO redirect guard executes strictly before database bootstrap (connection.php)'
);
$runner->assert(
    $guardPos !== false && $htmlPos !== false && $guardPos < $htmlPos,
    'SSO redirect guard executes strictly before HTML output'
);

// =============================================================================
// SUITE 2: ANONYMOUS GET AND HEAD REQUESTS WITH SSO ENABLED
// =============================================================================
echo "\n--- 2. Validando redirección 302 en GET y HEAD anónimos con SSO habilitado ---\n";

// 2.1 Anonymous GET request redirects to sso-login.php with 302 and zero HTML
$_SESSION = [];
$_GET = [];
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$injectedSsoConfig = $enabledConfig;
unset($injectedSsoEnabled, $pdo);

$GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] = null;
$GLOBALS['__ELECTROPLAN_REDIRECT_STATUS__'] = null;
if (!headers_sent()) {
    http_response_code(200);
}

ob_start();
require $loginPagePath;
$outputGet = ob_get_clean();

$runner->assert(
    $GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] === '/electroplan/pages/sso-login.php',
    'Anonymous GET with SSO enabled sets redirect target to /electroplan/pages/sso-login.php'
);
$runner->assert(
    $GLOBALS['__ELECTROPLAN_REDIRECT_STATUS__'] === 302 || http_response_code() === 302,
    'Anonymous GET with SSO enabled sets HTTP 302 status code'
);
$runner->assert(
    $outputGet === '',
    'Anonymous GET with SSO enabled yields zero HTML output before redirect'
);
$runner->assert(
    !isset($pdo),
    'Anonymous GET with SSO enabled does not initialize database connection ($pdo remains unset)'
);

// 2.2 Anonymous HEAD request redirects to sso-login.php with 302 and zero HTML
$_SESSION = [];
$_GET = [];
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'HEAD';
$injectedSsoConfig = $enabledConfig;
unset($injectedSsoEnabled, $pdo);

$GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] = null;
$GLOBALS['__ELECTROPLAN_REDIRECT_STATUS__'] = null;
if (!headers_sent()) {
    http_response_code(200);
}

ob_start();
require $loginPagePath;
$outputHead = ob_get_clean();

$runner->assert(
    $GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] === '/electroplan/pages/sso-login.php',
    'Anonymous HEAD with SSO enabled sets redirect target to /electroplan/pages/sso-login.php'
);
$runner->assert(
    $GLOBALS['__ELECTROPLAN_REDIRECT_STATUS__'] === 302 || http_response_code() === 302,
    'Anonymous HEAD with SSO enabled sets HTTP 302 status code'
);
$runner->assert(
    $outputHead === '',
    'Anonymous HEAD with SSO enabled yields zero HTML output'
);

// =============================================================================
// SUITE 3: SINGLE RECOVERY OPT-IN (local=1 STRICT) & REJECTION OF VARIANTS
// =============================================================================
echo "\n--- 3. Validando opt-in único local=1 y rechazo estricto de variantes ---\n";

// 3.1 local=1 explicitly bypasses redirect and renders the local form
$_SESSION = [];
$_GET = ['local' => '1'];
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$injectedSsoConfig = $enabledConfig;
$injectedPdo = $mockPdo;
unset($injectedSsoEnabled);

$GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] = null;
$GLOBALS['__ELECTROPLAN_REDIRECT_STATUS__'] = null;

ob_start();
require $loginPagePath;
$outputLocal = ob_get_clean();

$runner->assert(
    $GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] === null,
    'GET with local=1 does not trigger SSO redirect'
);
$runner->assert(
    strpos($outputLocal, 'name="username"') !== false,
    'GET with local=1 renders username input field'
);
$runner->assert(
    strpos($outputLocal, 'name="password"') !== false,
    'GET with local=1 renders password input field'
);
$runner->assert(
    strpos($outputLocal, 'ssoLoginBtn') !== false,
    'GET with local=1 and SSO enabled renders SSO button'
);

// 3.2 Rejected truthy variants and aliases MUST still redirect to SSO
$invalidVariants = [
    ['local' => 'true', 'desc' => 'local=true'],
    ['local' => 'yes', 'desc' => 'local=yes'],
    ['local' => 'on', 'desc' => 'local=on'],
    ['local' => '2', 'desc' => 'local=2'],
    ['local' => '0', 'desc' => 'local=0'],
    ['local' => '-1', 'desc' => 'local=-1'],
    ['recovery' => '1', 'desc' => 'recovery=1'],
    ['local_recovery' => '1', 'desc' => 'local_recovery=1'],
    ['force_local' => '1', 'desc' => 'force_local=1'],
];

foreach ($invalidVariants as $case) {
    $_SESSION = [];
    $_GET = $case;
    unset($_GET['desc']);
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $injectedSsoConfig = $enabledConfig;
    unset($injectedSsoEnabled, $injectedPdo, $pdo);

    $GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] = null;
    $GLOBALS['__ELECTROPLAN_REDIRECT_STATUS__'] = null;

    ob_start();
    require $loginPagePath;
    $outVariant = ob_get_clean();

    $runner->assert(
        $GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] === '/electroplan/pages/sso-login.php',
        "Invalid variant {$case['desc']} does not bypass SSO redirect (still redirects to sso-login.php)"
    );
    $runner->assert(
        $outVariant === '',
        "Invalid variant {$case['desc']} produces zero HTML output"
    );
}

// =============================================================================
// SUITE 4: SSO ERROR FALLBACK (LOOP PREVENTION)
// =============================================================================
echo "\n--- 4. Validando fallback local con error de SSO para evitar loops ---\n";

// 4.1 Request carrying error parameter renders sanitized fallback instead of redirecting
$_SESSION = [];
$_GET = ['error' => 'server_error'];
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$injectedSsoConfig = $enabledConfig;
$injectedPdo = $mockPdo;
unset($injectedSsoEnabled);

$GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] = null;
$GLOBALS['__ELECTROPLAN_REDIRECT_STATUS__'] = null;

ob_start();
require $loginPagePath;
$outputError = ob_get_clean();

$runner->assert(
    $GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] === null,
    'Request carrying SSO error does not trigger redirect (loop prevention)'
);
$runner->assert(
    strpos($outputError, 'Authentication failed. Please try again.') !== false,
    'Request carrying SSO error displays sanitized generic error message'
);
$runner->assert(
    strpos($outputError, 'server_error') === false,
    'Request carrying SSO error does not reflect raw error parameter into HTML'
);
$runner->assert(
    strpos($outputError, 'name="username"') !== false,
    'Request carrying SSO error renders local login form as fallback'
);

// 4.2 Empty error parameter does NOT bypass redirect
$_SESSION = [];
$_GET = ['error' => ''];
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$injectedSsoConfig = $enabledConfig;
unset($injectedSsoEnabled, $injectedPdo, $pdo);

$GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] = null;
$GLOBALS['__ELECTROPLAN_REDIRECT_STATUS__'] = null;

ob_start();
require $loginPagePath;
$outputEmptyError = ob_get_clean();

$runner->assert(
    $GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] === '/electroplan/pages/sso-login.php',
    'Empty error parameter does not bypass redirect'
);

// =============================================================================
// SUITE 5: POST AUTHENTICATION & SSO-DISABLED BEHAVIOR UNCHANGED
// =============================================================================
echo "\n--- 5. Validando autenticación POST y comportamiento con SSO deshabilitado ---\n";

// 5.1 SSO Disabled: Anonymous GET renders local form without redirect
$_SESSION = [];
$_GET = [];
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$injectedSsoEnabled = false;
$injectedPdo = $mockPdo;
unset($injectedSsoConfig);

$GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] = null;
$GLOBALS['__ELECTROPLAN_REDIRECT_STATUS__'] = null;

ob_start();
require $loginPagePath;
$outputDisabled = ob_get_clean();

$runner->assert(
    $GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] === null,
    'SSO disabled: Anonymous GET does not redirect'
);
$runner->assert(
    strpos($outputDisabled, 'name="username"') !== false,
    'SSO disabled: Anonymous GET renders username field'
);
$runner->assert(
    strpos($outputDisabled, 'ssoLoginBtn') === false,
    'SSO disabled: Anonymous GET does not render SSO button'
);

// 5.2 POST authentication is unchanged and does not redirect to sso-login.php
$_SESSION = [];
SsoSession::resetLifecycle();
$token = SsoSession::getCsrfToken();
$_GET = [];
$_POST = [
    'username' => 'test_user',
    'password' => 'ValidPassword123!',
    'csrf_token' => $token,
];
$_SERVER['REQUEST_METHOD'] = 'POST';
$injectedSsoConfig = $enabledConfig;
$injectedPdo = $mockPdo;
unset($injectedSsoEnabled);

$GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] = null;
$GLOBALS['__ELECTROPLAN_REDIRECT_STATUS__'] = null;

ob_start();
require $loginPagePath;
$outputPost = ob_get_clean();

$runner->assert(
    $GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] !== '/electroplan/pages/sso-login.php',
    'POST request with SSO enabled never redirects to sso-login.php'
);
$runner->assert(
    ($_SESSION['user_id'] ?? null) === 1,
    'POST authentication successfully establishes session user_id'
);
$runner->assert(
    ($_SESSION['username'] ?? null) === 'test_user',
    'POST authentication successfully establishes username'
);
$runner->assert(
    ($_SESSION['role'] ?? null) === 'technician',
    'POST authentication successfully establishes role'
);

// 5.3 Authenticated user: already-valid session does not trigger anonymous redirect
$_SESSION = [
    'user_id' => 1,
    'username' => 'test_user',
    'role' => 'technician',
];
$_GET = [];
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$injectedSsoConfig = $enabledConfig;
$injectedPdo = $mockPdo;
unset($injectedSsoEnabled);

$GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] = null;
$GLOBALS['__ELECTROPLAN_REDIRECT_STATUS__'] = null;

ob_start();
require $loginPagePath;
$outputAuth = ob_get_clean();

$runner->assert(
    $GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] !== '/electroplan/pages/sso-login.php',
    'Already authenticated session does not trigger anonymous SSO redirect'
);

// Cleanup globals
$_SESSION = [];
$_GET = [];
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
unset($injectedSsoConfig, $injectedSsoEnabled, $injectedPdo, $pdo);
$GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] = null;
$GLOBALS['__ELECTROPLAN_REDIRECT_STATUS__'] = null;

exit($runner->summary());
