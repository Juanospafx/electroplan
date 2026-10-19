<?php
/**
 * Runtime Test Suite: Electroplan OAuth Callback Boundary (TASK-0254).
 *
 * Validates:
 * 1. State validation and single-use consumption (missing, invalid, expired, replayed).
 * 2. Authorization code and PKCE verifier consumption.
 * 3. Server-to-server exchange with accepted confidential OAuth2 client using PKCE S256.
 * 4. Userinfo-derived identity profile validation (active, valid ID).
 * 5. Sanitized failure outcomes without leaking secrets, tokens, codes, verifiers, or HTTP details.
 * 6. Local session isolation: creates no session, performs no persistence or role assignment.
 * 7. Injected doubles only: zero network or database access.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once dirname(__DIR__) . '/config/SsoConfig.php';
require_once dirname(__DIR__) . '/core/auth/IdentityProfile.php';
require_once dirname(__DIR__) . '/core/auth/IdentityProviderClient.php';
require_once dirname(__DIR__) . '/core/auth/OAuthIdentityProviderClient.php';
require_once dirname(__DIR__) . '/core/auth/SsoFlow.php';
require_once dirname(__DIR__) . '/core/auth/SsoCallbackHandler.php';
require_once dirname(__DIR__) . '/core/auth/SsoSession.php';

use Electroplan\Auth\IdentityProfile;
use Electroplan\Auth\IdentityProviderClient;
use Electroplan\Auth\SsoCallbackHandler;
use Electroplan\Auth\SsoFlow;
use Electroplan\Auth\SsoSession;

// Autonomous test runner
class CallbackRuntimeTestRunner
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

// Injected test double for IdentityProviderClient
class MockIdentityClient implements IdentityProviderClient
{
    public ?string $lastCode = null;
    public ?string $lastVerifier = null;
    public ?IdentityProfile $profileToReturn = null;
    public ?\Throwable $exceptionToThrow = null;
    public int $authenticateCallCount = 0;

    public function __construct(?IdentityProfile $profileToReturn = null, ?\Throwable $exceptionToThrow = null)
    {
        $this->profileToReturn = $profileToReturn;
        $this->exceptionToThrow = $exceptionToThrow;
    }

    public function getAuthorizationUrl(array $options = []): string
    {
        return 'https://hub.example.com/oauth/authorize';
    }

    public function authenticate(string $code, string $codeVerifier): IdentityProfile
    {
        $this->authenticateCallCount++;
        $this->lastCode = $code;
        $this->lastVerifier = $codeVerifier;

        if ($this->exceptionToThrow !== null) {
            throw $this->exceptionToThrow;
        }

        if ($this->profileToReturn === null) {
            throw new \RuntimeException('No profile configured in mock double.');
        }

        return $this->profileToReturn;
    }

    public function fetchProfile(string $code, string $codeVerifier): IdentityProfile
    {
        return $this->authenticate($code, $codeVerifier);
    }
}

$runner = new CallbackRuntimeTestRunner();
echo "--- Iniciando validación runtime de Electroplan OAuth Callback Boundary ---\n\n";

// Helper to seed session with state and PKCE verifier
$seedFlowSession = function (SsoFlow $flow, int $ttl = 300, ?int $currentTime = null): array {
    $state = $flow->generateState($ttl, $currentTime);
    $pkce = $flow->generatePkce('S256');
    $verifier = $_SESSION['code_verifier'] ?? null;
    return [
        'state' => $state,
        'challenge' => $pkce['code_challenge'],
        'verifier' => $verifier,
    ];
};

// Helper to create valid active mock profile
$createMockProfile = function (string $id = 'hub-user-12345', bool $active = true): IdentityProfile {
    return IdentityProfile::fromClaims([
        'hub_user_id' => $id,
        'email' => 'user@example.com',
        'name' => 'Test User',
        'active' => $active,
        'roles' => ['technician'],
        'provider' => 'central_hub',
    ]);
};

// =========================================================================
// SUITE 1: STATE VALIDATION AND SINGLE-USE CONSUMPTION
// =========================================================================
echo "=== Suite 1: State validation and single-use consumption ===\n";

// 1.1 Missing state parameter
$_SESSION = [];
$flow = new SsoFlow();
$seed = $seedFlowSession($flow);
$mockClient = new MockIdentityClient($createMockProfile());
$handler = new SsoCallbackHandler($flow, $mockClient);

$resMissingState = $handler->handle(['code' => 'auth_code_xyz']);
$runner->assert($resMissingState['success'] === false, 'Callback rejects missing state parameter');
$runner->assert($resMissingState['profile'] === null, 'Missing state returns null profile');
$runner->assert(is_string($resMissingState['error']), 'Missing state returns sanitized error string');
$runner->assert(empty($_SESSION['code_verifier']), 'Missing state consumes/clears verifier from session');

// 1.2 Invalid / tampered state
$_SESSION = [];
$seed = $seedFlowSession($flow);
$resInvalidState = $handler->handle(['state' => 'tampered_state_val', 'code' => 'auth_code_xyz']);
$runner->assert($resInvalidState['success'] === false, 'Callback rejects invalid/tampered state');
$runner->assert(empty($_SESSION['code_verifier']), 'Invalid state eliminates verifier from session');

// 1.3 Expired state
$_SESSION = [];
$now = time();
$seed = $seedFlowSession($flow, 300, $now);
$resExpired = $handler->handle(['state' => $seed['state'], 'code' => 'auth_code_xyz'], $now + 301);
$runner->assert($resExpired['success'] === false, 'Callback rejects expired state parameter');
$runner->assert(empty($_SESSION['code_verifier']), 'Expired state eliminates verifier from session');

// 1.4 Replayed state (anti-replay single use)
$_SESSION = [];
$seed = $seedFlowSession($flow);
$mockClient1 = new MockIdentityClient($createMockProfile());
$handler1 = new SsoCallbackHandler($flow, $mockClient1);

$resFirst = $handler1->handle(['state' => $seed['state'], 'code' => 'auth_code_xyz']);
$runner->assert($resFirst['success'] === true, 'First callback attempt with valid state succeeds');

// Second attempt with identical state
$resReplay = $handler1->handle(['state' => $seed['state'], 'code' => 'auth_code_xyz']);
$runner->assert($resReplay['success'] === false, 'Replayed state is strictly rejected (single-use anti-replay)');

// =========================================================================
// SUITE 2: AUTHORIZATION CODE AND VERIFIER VALIDATION
// =========================================================================
echo "\n=== Suite 2: Authorization code and verifier validation ===\n";

// 2.1 Missing authorization code
$_SESSION = [];
$seed = $seedFlowSession($flow);
$mockClient2 = new MockIdentityClient($createMockProfile());
$handler2 = new SsoCallbackHandler($flow, $mockClient2);

$resMissingCode = $handler2->handle(['state' => $seed['state']]);
$runner->assert($resMissingCode['success'] === false, 'Callback rejects missing authorization code');
$runner->assert(empty($_SESSION['code_verifier']), 'Missing code consumes/clears verifier');
$runner->assert($flow->validateState($seed['state']) === false, 'State was consumed even when code was missing');

// 2.2 Empty authorization code string
$_SESSION = [];
$seed = $seedFlowSession($flow);
$resEmptyCode = $handler2->handle(['state' => $seed['state'], 'code' => '   ']);
$runner->assert($resEmptyCode['success'] === false, 'Callback rejects empty authorization code');
$runner->assert(empty($_SESSION['code_verifier']), 'Empty code clears verifier');

// 2.3 Missing verifier in session
$_SESSION = [];
$stateOnly = $flow->generateState(300);
unset($_SESSION['code_verifier']);
$resNoVerifier = $handler2->handle(['state' => $stateOnly, 'code' => 'auth_code_xyz']);
$runner->assert($resNoVerifier['success'] === false, 'Callback rejects when verifier is missing in session');

// =========================================================================
// SUITE 3: SERVER-TO-SERVER EXCHANGE AND PKCE S256
// =========================================================================
echo "\n=== Suite 3: Server-to-server exchange and PKCE S256 ===\n";

$_SESSION = [];
$seed = $seedFlowSession($flow);
$storedVerifier = $seed['verifier'];
$mockClient3 = new MockIdentityClient($createMockProfile());
$handler3 = new SsoCallbackHandler($flow, $mockClient3);

$resS2S = $handler3->handle(['state' => $seed['state'], 'code' => 'secret_code_abc123']);
$runner->assert($resS2S['success'] === true, 'Server-to-server flow succeeds with valid parameters');
$runner->assert($mockClient3->authenticateCallCount === 1, 'Client authenticate called exactly once');
$runner->assert($mockClient3->lastCode === 'secret_code_abc123', 'Authorization code passed to client');
$runner->assert($mockClient3->lastVerifier === $storedVerifier, 'Exact PKCE code_verifier passed to client');

// =========================================================================
// SUITE 4: PROVIDER ERRORS AND TOKEN EXCHANGE FAILURES
// =========================================================================
echo "\n=== Suite 4: Provider errors and token exchange failures ===\n";

// 4.1 Provider error in query parameters
$_SESSION = [];
$seed = $seedFlowSession($flow);
$mockClient4 = new MockIdentityClient($createMockProfile());
$handler4 = new SsoCallbackHandler($flow, $mockClient4);

$resProviderErr = $handler4->handle(['error' => 'access_denied', 'state' => $seed['state']]);
$runner->assert($resProviderErr['success'] === false, 'Callback rejects provider error parameter');
$runner->assert(empty($_SESSION['code_verifier']), 'Provider error clears session verifier');

// 4.2 Central disabled and app unauthorized provider errors
$_SESSION = [];
$seed = $seedFlowSession($flow);
$resCentralDisabled = $handler4->handle(['error' => 'central_disabled', 'state' => $seed['state']]);
$runner->assert($resCentralDisabled['success'] === false, 'Callback rejects central_disabled error');

$_SESSION = [];
$seed = $seedFlowSession($flow);
$resAppUnauthorized = $handler4->handle(['error' => 'app_unauthorized', 'state' => $seed['state']]);
$runner->assert($resAppUnauthorized['success'] === false, 'Callback rejects app_unauthorized error');

// 4.3 Token exchange error
$_SESSION = [];
$seed = $seedFlowSession($flow);
$failingClient = new MockIdentityClient(null, new \RuntimeException('Connection refused by token server'));
$handlerFailing = new SsoCallbackHandler($flow, $failingClient);

$resTokenErr = $handlerFailing->handle(['state' => $seed['state'], 'code' => 'code_123']);
$runner->assert($resTokenErr['success'] === false, 'Callback fails closed on token exchange failure');
$runner->assert(empty($_SESSION['code_verifier']), 'Session verifier cleared on exchange failure');

// 4.4 Sanitization of error messages (no secrets, tokens, codes, verifiers or HTTP details leaked)
$sensitiveTokens = ['secret_code_abc123', 'access_token', 'client_secret', 'Connection refused', 'Bearer', 'HTTP'];
$hasLeak = false;
foreach ($sensitiveTokens as $secret) {
    if (stripos((string)$resTokenErr['error'], $secret) !== false) {
        $hasLeak = true;
    }
}
$runner->assert(!$hasLeak, 'Token exchange error message is generic and sanitized (no leaked details)');

// =========================================================================
// SUITE 5: USERINFO IDENTITY PROFILE VALIDATION
// =========================================================================
echo "\n=== Suite 5: Userinfo identity profile validation ===\n";

// 5.1 Empty user ID in profile
$_SESSION = [];
$seed = $seedFlowSession($flow);
// Build profile with empty id reflection
$emptyIdProfile = new IdentityProfile('', 'user@example.com', 'Name', null, true);
$emptyClient = new MockIdentityClient($emptyIdProfile);
$handlerEmpty = new SsoCallbackHandler($flow, $emptyClient);

$resEmptyId = $handlerEmpty->handle(['state' => $seed['state'], 'code' => 'code_123']);
$runner->assert($resEmptyId['success'] === false, 'Fails closed when userinfo ID is empty');

// 5.2 Inactive user account (active === false)
$_SESSION = [];
$seed = $seedFlowSession($flow);
$inactiveProfile = $createMockProfile('hub-user-999', false);
$inactiveClient = new MockIdentityClient($inactiveProfile);
$handlerInactive = new SsoCallbackHandler($flow, $inactiveClient);

$resInactive = $handlerInactive->handle(['state' => $seed['state'], 'code' => 'code_123']);
$runner->assert($resInactive['success'] === false, 'Fails closed when user account is inactive or disabled');

// 5.3 Valid active profile
$_SESSION = [];
$seed = $seedFlowSession($flow);
$activeProfile = $createMockProfile('hub-user-valid-1', true);
$activeClient = new MockIdentityClient($activeProfile);
$handlerActive = new SsoCallbackHandler($flow, $activeClient);

$resActive = $handlerActive->handle(['state' => $seed['state'], 'code' => 'code_valid']);
$runner->assert($resActive['success'] === true, 'Succeeds when userinfo profile is valid and active');
$runner->assert($resActive['profile'] instanceof IdentityProfile, 'Returns valid IdentityProfile instance');
$runner->assert($resActive['profile']->getId() === 'hub-user-valid-1', 'Profile user ID matches expected');

// =========================================================================
// SUITE 6: SESSION BOUNDARY, PERSISTENCE, AND ROLES ISOLATION
// =========================================================================
echo "\n=== Suite 6: Session boundary, persistence, and roles isolation ===\n";

// Verify callback creates NO local session, performs NO persistence, assigns NO roles
$forbiddenSessionKeys = ['user_id', 'user', 'logged_in', 'authenticated', 'role', 'roles'];
$foundSessionKey = false;
foreach ($forbiddenSessionKeys as $fKey) {
    if (isset($_SESSION[$fKey])) {
        $foundSessionKey = true;
    }
}
$runner->assert(!$foundSessionKey, 'Callback creates no local session variables');

// Verify flow session state is clean
$runner->assert(empty($_SESSION['sso_states']), 'sso_states cleared after handling callback');
$runner->assert(empty($_SESSION['code_verifier']), 'code_verifier cleared after handling callback');

// =========================================================================
// SUITE 7: STATIC FORBIDDEN REFERENCES CHECK
// =========================================================================
echo "\n=== Suite 7: Static forbidden references check ===\n";

$forbiddenWords = [
    implode('', ['open', 'id']),
    implode('', ['id_', 'token']),
    implode('', ['non', 'ce']),
    implode('', ['oi', 'dc']),
];

$filesToCheck = [
    dirname(__DIR__) . '/core/auth/SsoCallbackHandler.php',
    dirname(__DIR__) . '/pages/sso-callback.php',
];

foreach ($filesToCheck as $filePath) {
    $content = file_get_contents($filePath);
    $baseName = basename($filePath);
    $foundForbidden = false;
    foreach ($forbiddenWords as $term) {
        if (stripos($content, $term) !== false) {
            $foundForbidden = true;
            fwrite(STDERR, "[FAIL] Forbidden reference found in {$baseName}: {$term}\n");
        }
    }
    $runner->assert(!$foundForbidden, "No functional forbidden references in {$baseName}");
}

// =========================================================================
// SUITE 8: PAGES/SSO-CALLBACK.PHP ENDPOINT INTEGRATION
// =========================================================================
echo "\n=== Suite 8: pages/sso-callback.php endpoint integration ===\n";

// 8.1 Successful callback via page require
$_SESSION = [];
$seed = $seedFlowSession($flow);
$pageMockProfile = $createMockProfile('hub-page-user-1', true);
$pageMockClient = new MockIdentityClient($pageMockProfile);
$injectedCallbackHandler = new SsoCallbackHandler($flow, $pageMockClient);
$queryParams = ['state' => $seed['state'], 'code' => 'page_code_123'];

$pageResult = require dirname(__DIR__) . '/pages/sso-callback.php';

$runner->assert(is_array($pageResult), 'pages/sso-callback.php returns array outcome');
$runner->assert($pageResult['success'] === true, 'pages/sso-callback.php succeeds with valid parameters');
$runner->assert($pageResult['profile'] instanceof IdentityProfile, 'pages/sso-callback.php returns IdentityProfile');

// 8.2 Failed callback via page require
$_SESSION = [];
$seed = $seedFlowSession($flow);
$injectedCallbackHandler = new SsoCallbackHandler($flow, $pageMockClient);
$queryParams = ['error' => 'access_denied', 'state' => $seed['state']];

$pageFailResult = require dirname(__DIR__) . '/pages/sso-callback.php';
$runner->assert($pageFailResult['success'] === false, 'pages/sso-callback.php fails closed on error parameter');
$runner->assert($pageFailResult['profile'] === null, 'pages/sso-callback.php returns null profile on failure');
$runner->assert(empty($_SESSION['sso_state']), 'Provider error path clears sso_state transient');
$runner->assert(empty($_SESSION['sso_states']), 'Provider error path clears sso_states transient');
$runner->assert(empty($_SESSION['code_verifier']), 'Provider error path clears code_verifier transient');

// 8.3 Transient cleanup on missing state parameter
$_SESSION = [];
$seed = $seedFlowSession($flow);
$queryParams = ['code' => 'some_code_123'];
$pageMissingState = require dirname(__DIR__) . '/pages/sso-callback.php';
$runner->assert($pageMissingState['success'] === false, 'pages/sso-callback.php fails closed on missing state');
$runner->assert(empty($_SESSION['sso_state']), 'Missing state path clears sso_state');
$runner->assert(empty($_SESSION['sso_states']), 'Missing state path clears sso_states');
$runner->assert(empty($_SESSION['code_verifier']), 'Missing state path clears code_verifier');

// 8.4 Transient cleanup on invalid/tampered state
$_SESSION = [];
$seed = $seedFlowSession($flow);
$queryParams = ['state' => 'tampered_state_value', 'code' => 'some_code_123'];
$pageTampered = require dirname(__DIR__) . '/pages/sso-callback.php';
$runner->assert($pageTampered['success'] === false, 'pages/sso-callback.php fails closed on tampered state');
$runner->assert(empty($_SESSION['sso_state']), 'Tampered state path clears sso_state');
$runner->assert(empty($_SESSION['sso_states']), 'Tampered state path clears sso_states');
$runner->assert(empty($_SESSION['code_verifier']), 'Tampered state path clears code_verifier');

// 8.5 Transient cleanup on expired state
$_SESSION = [];
$now = time();
$seedExp = $seedFlowSession($flow, 300, $now);
$injectedCurrentTime = $now + 305;
$queryParams = ['state' => $seedExp['state'], 'code' => 'some_code_123'];
$pageExpired = require dirname(__DIR__) . '/pages/sso-callback.php';
$runner->assert($pageExpired['success'] === false, 'pages/sso-callback.php fails closed on expired state');
$runner->assert(empty($_SESSION['sso_state']), 'Expired state path clears sso_state');
$runner->assert(empty($_SESSION['sso_states']), 'Expired state path clears sso_states');
$runner->assert(empty($_SESSION['code_verifier']), 'Expired state path clears code_verifier');
$injectedCurrentTime = null;

// 8.6 Transient cleanup on missing authorization code
$_SESSION = [];
$seed = $seedFlowSession($flow);
$queryParams = ['state' => $seed['state']];
$pageMissingCode = require dirname(__DIR__) . '/pages/sso-callback.php';
$runner->assert($pageMissingCode['success'] === false, 'pages/sso-callback.php fails closed on missing authorization code');
$runner->assert(empty($_SESSION['sso_state']), 'Missing code path clears sso_state');
$runner->assert(empty($_SESSION['sso_states']), 'Missing code path clears sso_states');
$runner->assert(empty($_SESSION['code_verifier']), 'Missing code path clears code_verifier');

// 8.7 Hardened session cookies configured before session_start and non-relaxable
$cookieParams = SsoSession::getLastConfiguredCookieParams();
$runner->assert(isset($cookieParams['secure']) && $cookieParams['secure'] === true, 'SsoSession enforces Secure=true cookie parameter');
$runner->assert(isset($cookieParams['httponly']) && $cookieParams['httponly'] === true, 'SsoSession enforces HttpOnly=true cookie parameter');
$runner->assert(isset($cookieParams['samesite']) && $cookieParams['samesite'] === 'Lax', 'SsoSession enforces SameSite=Lax cookie parameter');

// 8.8 Zero standalone cookies for state or verifier
$runner->assert(!isset($_COOKIE['state']) && !isset($_COOKIE['sso_state']) && !isset($_COOKIE['code_verifier']), 'Zero standalone cookies for state or verifier');

exit($runner->summary());
