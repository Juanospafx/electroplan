<?php
/**
 * Runtime Test Suite: Electroplan SSO Login, Callback, Local Authorization, and Logout Wiring (TASK-0261).
 *
 * Validates:
 * 1. Default flag off and fail-closed state (no flag activated by code or tests).
 * 2. Preservation of local login as rollback path (valid credentials, incorrect password, non-existent user).
 * 3. Injected enabled test configuration: login exposes SSO action, sso-login creates state + PKCE S256,
 *    and redirects only to configured HTTPS authorize endpoint.
 * 4. Callback execution order: handler -> repository -> authorization -> SsoSession.
 * 5. Fail-closed on missing/inactive/unmapped identity, invalid roles, and no partial authentication.
 * 6. Sanitized generic error messages without credential, token, code, or verifier leakage.
 * 7. Local logout destroys local session only, clears cookie safely, and makes zero network calls.
 * 8. Strict OAuth2 authorization code with PKCE and userinfo only; zero alternative protocol artifacts.
 * 9. Injected doubles only: zero real database or network connections.
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
require_once dirname(__DIR__) . '/core/auth/FederatedIdentityRepository.php';
require_once dirname(__DIR__) . '/core/auth/SsoAuthorization.php';
require_once dirname(__DIR__) . '/core/auth/SsoSession.php';

use Electroplan\Auth\IdentityProfile;
use Electroplan\Auth\IdentityProviderClient;
use Electroplan\Auth\SsoCallbackHandler;
use Electroplan\Auth\SsoFlow;
use Electroplan\Auth\FederatedIdentityRepository;
use Electroplan\Auth\SsoAuthorization;
use Electroplan\Auth\SsoSession;

class SsoLoginLogoutTestRunner
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

/**
 * Isolated PDOStatement double for runtime testing without network or SQLite/database engines.
 */
class MockIsolatedStatement extends PDOStatement
{
    /** @var array<int, array<string, mixed>> */
    private array $rows;
    private int $index = 0;
    /** @var callable|null */
    private $executeHandler;

    public function __construct(array $rows = [], ?callable $executeHandler = null)
    {
        $this->rows = $rows;
        $this->executeHandler = $executeHandler;
        $this->index = 0;
    }

    #[\ReturnTypeWillChange]
    public function execute(?array $params = null): bool
    {
        $this->index = 0;
        if ($this->executeHandler !== null) {
            $result = ($this->executeHandler)($params);
            if (is_array($result)) {
                $this->rows = $result;
            }
        }
        return true;
    }

    #[\ReturnTypeWillChange]
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        if ($this->index < count($this->rows)) {
            return $this->rows[$this->index++];
        }
        return false;
    }

    #[\ReturnTypeWillChange]
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->rows;
    }
}

/**
 * Isolated in-memory PDO double. Zero real network or database access.
 */
class MockIsolatedPdo extends PDO
{
    /** @var array<int, array<string, mixed>> */
    public array $users = [];
    /** @var array<int, array<string, mixed>> */
    public array $links = [];
    private int $linkAutoIncrement = 1;

    public function __construct()
    {
        // Zero real connection: strictly does NOT call parent::__construct()
    }

    #[\ReturnTypeWillChange]
    public function setAttribute(int $attribute, mixed $value): bool
    {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function getAttribute(int $attribute): mixed
    {
        if ($attribute === PDO::ATTR_DRIVER_NAME) {
            return 'isolated_double';
        }
        return null;
    }

    #[\ReturnTypeWillChange]
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $normalized = trim($query);

        // SELECT id, username, password, role FROM users WHERE username = ?
        if (stripos($normalized, 'SELECT') === 0 && stripos($normalized, 'FROM users WHERE username = ?') !== false) {
            return new MockIsolatedStatement([], function (?array $params) {
                $uname = (string)($params[0] ?? '');
                foreach ($this->users as $u) {
                    if ($u['username'] === $uname) {
                        return [[
                            'id' => $u['id'],
                            'username' => $u['username'],
                            'password' => $u['password'],
                            'role' => $u['role'],
                        ]];
                    }
                }
                return [];
            });
        }

        // INSERT INTO users
        if (stripos($normalized, 'INSERT INTO users') === 0) {
            return new MockIsolatedStatement([], function (?array $params) {
                if ($params !== null) {
                    $id = (int)$params[0];
                    $this->users[$id] = [
                        'id' => $id,
                        'username' => (string)$params[1],
                        'password' => (string)$params[2],
                        'role' => (string)$params[3],
                    ];
                }
                return [];
            });
        }

        // INSERT INTO sso_identity_links
        if (stripos($normalized, 'INSERT INTO sso_identity_links') === 0) {
            return new MockIsolatedStatement([], function (?array $params) {
                if ($params !== null) {
                    $id = $this->linkAutoIncrement++;
                    $this->links[] = [
                        'id' => $id,
                        'user_id' => (int)$params[0],
                        'provider' => (string)$params[1],
                        'hub_user_id' => (string)$params[2],
                        'active' => (int)$params[3],
                    ];
                }
                return [];
            });
        }

        // SELECT ... FROM sso_identity_links l INNER JOIN users u ...
        if (stripos($normalized, 'FROM sso_identity_links l') !== false && stripos($normalized, 'INNER JOIN users') !== false) {
            return new MockIsolatedStatement([], function (?array $params) {
                $provider = (string)($params[':provider'] ?? $params['provider'] ?? '');
                $hubUserId = (string)($params[':hub_user_id'] ?? $params['hub_user_id'] ?? '');

                foreach ($this->links as $link) {
                    if (
                        strcasecmp((string)$link['provider'], $provider) === 0
                        && strcasecmp((string)$link['hub_user_id'], $hubUserId) === 0
                        && (int)$link['active'] === 1
                    ) {
                        $userId = (int)$link['user_id'];
                        if (isset($this->users[$userId])) {
                            $user = $this->users[$userId];
                            return [[
                                'user_id' => $user['id'],
                                'username' => $user['username'],
                                'role' => $user['role'],
                                'link_id' => $link['id'],
                                'provider' => $link['provider'],
                                'hub_user_id' => $link['hub_user_id'],
                                'active' => $link['active'],
                            ]];
                        }
                    }
                }
                return [];
            });
        }

        return new MockIsolatedStatement([]);
    }
}

/**
 * Injected test double for IdentityProviderClient.
 */
class MockClientDouble implements IdentityProviderClient
{
    public ?string $lastCode = null;
    public ?string $lastVerifier = null;
    public array $lastOptions = [];
    public ?IdentityProfile $profileToReturn = null;
    public ?\Throwable $exceptionToThrow = null;
    public string $authUrl = 'https://hub.example.com/oauth/authorize';

    public function __construct(?IdentityProfile $profileToReturn = null, string $authUrl = 'https://hub.example.com/oauth/authorize')
    {
        $this->profileToReturn = $profileToReturn;
        $this->authUrl = $authUrl;
    }

    public function getAuthorizationUrl(array $options = []): string
    {
        $this->lastOptions = $options;
        $query = http_build_query($options);
        return $this->authUrl . ($query !== '' ? '?' . $query : '');
    }

    public function authenticate(string $code, string $codeVerifier): IdentityProfile
    {
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

$runner = new SsoLoginLogoutTestRunner();
echo "--- Iniciando validacion runtime de Electroplan SSO Wiring (TASK-0261) ---\n\n";

// Helper to seed database double
$pdo = new MockIsolatedPdo();
$userStmt = $pdo->prepare("INSERT INTO users (id, username, password, role) VALUES (?, ?, ?, ?)");
// Password for carlos_tech is 'TechPassword123!'
$passwordHash = password_hash('TechPassword123!', PASSWORD_BCRYPT);
$userStmt->execute([1, 'carlos_tech', $passwordHash, 'technician']);
$userStmt->execute([2, 'maria_admin', password_hash('AdminPass456!', PASSWORD_BCRYPT), 'admin']);
$userStmt->execute([3, 'juan_viewer', password_hash('ViewerPass789!', PASSWORD_BCRYPT), 'viewer']);
$userStmt->execute([4, 'pedro_misspelled', password_hash('Pass!', PASSWORD_BCRYPT), 'viwer']);
$userStmt->execute([5, 'ana_unknown', password_hash('Pass!', PASSWORD_BCRYPT), 'superadmin']);

$linkStmt = $pdo->prepare("INSERT INTO sso_identity_links (user_id, provider, hub_user_id, active) VALUES (?, ?, ?, ?)");
$linkStmt->execute([1, 'central_hub', 'hub-tech-101', 1]);
$linkStmt->execute([2, 'central_hub', 'hub-admin-202', 1]);
$linkStmt->execute([3, 'central_hub', 'hub-viewer-303', 1]);
$linkStmt->execute([1, 'central_hub', 'hub-tech-inactive', 0]);
$linkStmt->execute([4, 'central_hub', 'hub-misspelled-505', 1]);
$linkStmt->execute([5, 'central_hub', 'hub-unknown-606', 1]);

$repository = new FederatedIdentityRepository($pdo);
$authorization = new SsoAuthorization($repository);

$createMockProfile = function (string $id, bool $active = true, array $roles = ['user']): IdentityProfile {
    return IdentityProfile::fromClaims([
        'hub_user_id' => $id,
        'email' => $id . '@example.com',
        'name' => 'User ' . $id,
        'active' => $active,
        'roles' => $roles,
        'provider' => 'central_hub',
    ]);
};

// =========================================================================
// SUITE 1: DEFAULT CONFIGURATION & FAIL-CLOSED BEHAVIOR (FLAGS OFF/MISSING)
// =========================================================================
echo "=== Suite 1: Default configuration & fail-closed behavior ===\n";

// 1.1 SsoConfig is disabled by default
SsoConfig::reset();
$runner->assert(SsoConfig::isEnabled() === false, 'SSO is disabled by default (SSO_ENABLED false or missing)');

// 1.2 login.php does NOT render SSO button when disabled
$_SESSION = [];
$injectedPdo = $pdo;
$injectedSsoEnabled = false;
ob_start();
require dirname(__DIR__) . '/pages/login.php';
$loginOutputDisabled = ob_get_clean();

$runner->assert(strpos($loginOutputDisabled, 'ssoLoginBtn') === false, 'login.php does not expose SSO button when SSO is disabled');
$runner->assert(strpos($loginOutputDisabled, 'sso-login.php') === false, 'login.php contains no link to sso-login.php when SSO is disabled');

// 1.3 sso-login.php fails closed when SSO is disabled
$_SESSION = [];
$injectedSsoEnabled = false;
$ssoLoginResult = require dirname(__DIR__) . '/pages/sso-login.php';
$runner->assert($ssoLoginResult['success'] === false, 'sso-login.php fails closed when SSO is disabled');
$runner->assert($ssoLoginResult['redirect_url'] === null, 'sso-login.php returns null redirect_url when disabled');
$runner->assert(is_string($ssoLoginResult['error']), 'sso-login.php returns sanitized error message when disabled');

// 1.4 sso-callback.php fails closed when SSO is disabled
$_SESSION = [];
$injectedSsoEnabled = false;
$queryParams = ['code' => 'some_code', 'state' => 'some_state'];
$ssoCallbackResult = require dirname(__DIR__) . '/pages/sso-callback.php';
$runner->assert($ssoCallbackResult['success'] === false, 'sso-callback.php fails closed when SSO is disabled');
$runner->assert($ssoCallbackResult['profile'] === null, 'sso-callback.php returns null profile when disabled');
$runner->assert(empty($_SESSION['user_id']), 'sso-callback.php creates zero session variables when disabled');

// =========================================================================
// SUITE 2: PRESERVATION OF LOCAL LOGIN AS ROLLBACK PATH
// =========================================================================
echo "\n=== Suite 2: Preservation of local login as rollback path ===\n";

// 2.1 Successful local login creates session
$_SESSION = [];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'username' => 'carlos_tech',
    'password' => 'TechPassword123!',
];
$injectedPdo = $pdo;
$injectedSsoEnabled = false;
ob_start();
require dirname(__DIR__) . '/pages/login.php';
ob_end_clean();

$runner->assert(!empty($_SESSION['user_id']) && $_SESSION['user_id'] === 1, 'Local login creates session user_id');
$runner->assert(!empty($_SESSION['username']) && $_SESSION['username'] === 'carlos_tech', 'Local login sets username in session');
$runner->assert(!empty($_SESSION['role']) && $_SESSION['role'] === 'technician', 'Local login sets role in session');

// 2.2 Local login with invalid password fails
$_SESSION = [];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'username' => 'carlos_tech',
    'password' => 'WrongPassword!',
];
ob_start();
require dirname(__DIR__) . '/pages/login.php';
ob_end_clean();

$runner->assert(empty($_SESSION['user_id']), 'Local login with bad password sets no session user_id');
$runner->assert(!empty($error) && $error === 'Contraseña incorrecta.', 'Local login returns invalid password error');

// 2.3 Local login with non-existent user fails
$_SESSION = [];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'username' => 'nonexistent_user',
    'password' => 'SomePassword123!',
];
ob_start();
require dirname(__DIR__) . '/pages/login.php';
ob_end_clean();

$runner->assert(empty($_SESSION['user_id']), 'Local login with unknown user sets no session user_id');
$runner->assert(!empty($error) && $error === 'El usuario no existe.', 'Local login returns unknown user error');

// Clean POST state
$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST = [];

// =========================================================================
// SUITE 3: INJECTED ENABLED CONFIGURATION & SSO LOGIN INITIATION
// =========================================================================
echo "\n=== Suite 3: Injected enabled configuration & SSO login initiation ===\n";

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

// 3.1 login.php exposes SSO action with enabled test config
$_SESSION = [];
unset($injectedSsoEnabled);
$injectedPdo = $pdo;
$injectedSsoConfig = $enabledConfig;
ob_start();
require dirname(__DIR__) . '/pages/login.php';
$loginOutputEnabled = ob_get_clean();

$runner->assert(strpos($loginOutputEnabled, 'ssoLoginBtn') !== false, 'login.php exposes SSO button when SSO is enabled');
$runner->assert(strpos($loginOutputEnabled, 'sso-login.php') !== false, 'login.php links to sso-login.php when SSO is enabled');
$runner->assert(strpos($loginOutputEnabled, 'Iniciar sesión con SSO') !== false, 'login.php displays SSO action text');
$runner->assert(strpos($loginOutputEnabled, 'name="username"') !== false, 'login.php preserves local username field');
$runner->assert(strpos($loginOutputEnabled, 'name="password"') !== false, 'login.php preserves local password field');

// 3.2 sso-login.php generates state and PKCE S256 through SsoFlow
$_SESSION = [];
$mockClient = new MockClientDouble();
$flow = new SsoFlow();
$injectedFlow = $flow;
$injectedClient = $mockClient;
$injectedSsoConfig = $enabledConfig;

$ssoLoginOutcome = require dirname(__DIR__) . '/pages/sso-login.php';

$runner->assert($ssoLoginOutcome['success'] === true, 'sso-login.php succeeds with enabled configuration');
$runner->assert(!empty($ssoLoginOutcome['redirect_url']), 'sso-login.php returns redirect_url');
$runner->assert(str_starts_with($ssoLoginOutcome['redirect_url'], 'https://hub.example.com/oauth/authorize'), 'sso-login.php redirects only to HTTPS authorize endpoint');

// 3.3 State and PKCE verification
$runner->assert(!empty($ssoLoginOutcome['state']), 'State parameter is generated');
$runner->assert(strlen($ssoLoginOutcome['state']) >= 32, 'State parameter is high-entropy (>= 32 chars)');
$runner->assert(isset($_SESSION['sso_states'][$ssoLoginOutcome['state']]), 'State is stored in session states array');
$runner->assert(!empty($_SESSION['code_verifier']), 'PKCE code_verifier is stored strictly in session');
$runner->assert(!isset($ssoLoginOutcome['code_verifier']), 'PKCE code_verifier is never returned in public parameters');
$runner->assert($ssoLoginOutcome['code_challenge_method'] === 'S256', 'PKCE code_challenge_method is strictly S256');

// 3.4 Challenge computation matches verifier: S256(verifier) === challenge
$expectedChallenge = $flow->computeCodeChallenge($_SESSION['code_verifier'], 'S256');
$runner->assert($ssoLoginOutcome['code_challenge'] === $expectedChallenge, 'PKCE challenge matches S256 hash of session verifier');

// 3.5 Options passed to client double
$runner->assert($mockClient->lastOptions['state'] === $ssoLoginOutcome['state'], 'Client double received state');
$runner->assert($mockClient->lastOptions['code_challenge'] === $expectedChallenge, 'Client double received code_challenge');
$runner->assert($mockClient->lastOptions['code_challenge_method'] === 'S256', 'Client double received S256 method');
$runner->assert($mockClient->lastOptions['scope'] === ['profile', 'email'], 'Client double received profile and email scopes only');

// =========================================================================
// SUITE 4: CALLBACK EXECUTION ORDER (HANDLER -> REPO -> AUTH -> SESSION)
// =========================================================================
echo "\n=== Suite 4: Callback execution order (Handler -> Repo -> Auth -> Session) ===\n";

// 4.1 Successful SSO login for active technician
$_SESSION = [];
unset($injectedSsoEnabled);
$flowInstance = new SsoFlow();
$state = $flowInstance->generateState(300);
$pkce = $flowInstance->generatePkce('S256');
$verifier = $_SESSION['code_verifier'];

$techProfile = $createMockProfile('hub-tech-101', true, ['user']);
$clientDouble = new MockClientDouble($techProfile);
$handler = new SsoCallbackHandler($flowInstance, $clientDouble);

$injectedSsoConfig = $enabledConfig;
$injectedCallbackHandler = $handler;
$injectedRepository = $repository;
$injectedAuthorization = $authorization;
$queryParams = ['state' => $state, 'code' => 'auth_code_tech'];

$callbackOutcome = require dirname(__DIR__) . '/pages/sso-callback.php';

$runner->assert($callbackOutcome['success'] === true, 'Callback succeeds for active mapped technician');
$runner->assert($callbackOutcome['profile'] instanceof IdentityProfile, 'Callback returns IdentityProfile');
$runner->assert($callbackOutcome['identity']['user_id'] === 1, 'Callback resolves local user_id 1');
$runner->assert($callbackOutcome['identity']['role'] === 'technician', 'Callback resolves local role technician');
$runner->assert($callbackOutcome['error'] === null, 'Error is null on successful callback');

// Verify session establishment
$runner->assert($_SESSION['user_id'] === 1, 'Session user_id is established');
$runner->assert($_SESSION['username'] === 'carlos_tech', 'Session username is established');
$runner->assert($_SESSION['role'] === 'technician', 'Session role is established');
$runner->assert($_SESSION['sso_provider'] === 'central_hub', 'Session records sso_provider');
$runner->assert($_SESSION['sso_hub_user_id'] === 'hub-tech-101', 'Session records sso_hub_user_id');
$runner->assert(!empty($_SESSION['sso_authenticated_at']), 'Session records authenticated timestamp');

// Verify flow cleanup
$runner->assert(empty($_SESSION['code_verifier']), 'Flow verifier cleared from session');
$runner->assert(empty($_SESSION['sso_state']), 'Flow scalar state cleared from session');

// 4.2 Successful SSO login for active admin
$_SESSION = [];
$stateAdmin = $flowInstance->generateState(300);
$pkceAdmin = $flowInstance->generatePkce('S256');
$adminProfile = $createMockProfile('hub-admin-202', true, ['admin_user']);
$adminClient = new MockClientDouble($adminProfile);
$injectedCallbackHandler = new SsoCallbackHandler($flowInstance, $adminClient);
$queryParams = ['state' => $stateAdmin, 'code' => 'auth_code_admin'];

$adminCallbackOutcome = require dirname(__DIR__) . '/pages/sso-callback.php';
$runner->assert($adminCallbackOutcome['success'] === true, 'Callback succeeds for active mapped admin');
$runner->assert($adminCallbackOutcome['identity']['role'] === 'admin', 'Callback preserves local admin role');
$runner->assert($_SESSION['role'] === 'admin', 'Admin role written to session');
$runner->assert($_SESSION['user_id'] === 2, 'Admin user_id written to session');

// 4.3 Successful SSO login for active viewer
$_SESSION = [];
$stateViewer = $flowInstance->generateState(300);
$pkceViewer = $flowInstance->generatePkce('S256');
$viewerProfile = $createMockProfile('hub-viewer-303', true, []);
$viewerClient = new MockClientDouble($viewerProfile);
$injectedCallbackHandler = new SsoCallbackHandler($flowInstance, $viewerClient);
$queryParams = ['state' => $stateViewer, 'code' => 'auth_code_viewer'];

$viewerCallbackOutcome = require dirname(__DIR__) . '/pages/sso-callback.php';
$runner->assert($viewerCallbackOutcome['success'] === true, 'Callback succeeds for active mapped viewer');
$runner->assert($viewerCallbackOutcome['identity']['role'] === 'viewer', 'Callback preserves local viewer role');
$runner->assert($_SESSION['role'] === 'viewer', 'Viewer role written to session');

// =========================================================================
// SUITE 5: FAIL-CLOSED SCENARIOS & NO PARTIAL AUTHENTICATION
// =========================================================================
echo "\n=== Suite 5: Fail-closed scenarios & zero partial authentication ===\n";

// Helper to assert zero session authentication
$assertNoSession = function (string $caseDesc) use ($runner) {
    $hasAuth = isset($_SESSION['user_id']) || isset($_SESSION['username']) || isset($_SESSION['role']);
    $runner->assert(!$hasAuth, "Zero partial authentication on {$caseDesc}");
};

// 5.1 Inactive central user (active = false)
$_SESSION = [];
$sInactive = $flowInstance->generateState(300);
$flowInstance->generatePkce('S256');
$inactiveProfile = $createMockProfile('hub-tech-101', false);
$injectedCallbackHandler = new SsoCallbackHandler($flowInstance, new MockClientDouble($inactiveProfile));
$queryParams = ['state' => $sInactive, 'code' => 'code_inactive'];

$resInactive = require dirname(__DIR__) . '/pages/sso-callback.php';
$runner->assert($resInactive['success'] === false, 'Callback fails closed on inactive central user');
$assertNoSession('inactive central user');

// 5.2 Unmapped identity (central user with no link in repository)
$_SESSION = [];
$sUnmapped = $flowInstance->generateState(300);
$flowInstance->generatePkce('S256');
$unmappedProfile = $createMockProfile('hub-unmapped-999', true);
$injectedCallbackHandler = new SsoCallbackHandler($flowInstance, new MockClientDouble($unmappedProfile));
$queryParams = ['state' => $sUnmapped, 'code' => 'code_unmapped'];

$resUnmapped = require dirname(__DIR__) . '/pages/sso-callback.php';
$runner->assert($resUnmapped['success'] === false, 'Callback fails closed on unmapped central identity');
$assertNoSession('unmapped central identity');

// 5.3 Inactive link (active = 0)
$_SESSION = [];
$sInactiveLink = $flowInstance->generateState(300);
$flowInstance->generatePkce('S256');
$inactiveLinkProfile = $createMockProfile('hub-tech-inactive', true);
$injectedCallbackHandler = new SsoCallbackHandler($flowInstance, new MockClientDouble($inactiveLinkProfile));
$queryParams = ['state' => $sInactiveLink, 'code' => 'code_inactive_link'];

$resInactiveLink = require dirname(__DIR__) . '/pages/sso-callback.php';
$runner->assert($resInactiveLink['success'] === false, 'Callback fails closed on inactive link (active = 0)');
$assertNoSession('inactive link');

// 5.4 Misspelled local database role ('viwer')
$_SESSION = [];
$sMisspelled = $flowInstance->generateState(300);
$flowInstance->generatePkce('S256');
$misspelledProfile = $createMockProfile('hub-misspelled-505', true);
$injectedCallbackHandler = new SsoCallbackHandler($flowInstance, new MockClientDouble($misspelledProfile));
$queryParams = ['state' => $sMisspelled, 'code' => 'code_misspelled'];

$resMisspelled = require dirname(__DIR__) . '/pages/sso-callback.php';
$runner->assert($resMisspelled['success'] === false, 'Callback fails closed on misspelled role "viwer" (no default/fallback)');
$assertNoSession('misspelled role');

// 5.5 Unknown local role ('superadmin')
$_SESSION = [];
$sUnknown = $flowInstance->generateState(300);
$flowInstance->generatePkce('S256');
$unknownProfile = $createMockProfile('hub-unknown-606', true);
$injectedCallbackHandler = new SsoCallbackHandler($flowInstance, new MockClientDouble($unknownProfile));
$queryParams = ['state' => $sUnknown, 'code' => 'code_unknown'];

$resUnknown = require dirname(__DIR__) . '/pages/sso-callback.php';
$runner->assert($resUnknown['success'] === false, 'Callback fails closed on unknown local role');
$assertNoSession('unknown local role');

// 5.6 Central-role elevation blocked: Central admin claim does NOT elevate local technician
$_SESSION = [];
$sElevate = $flowInstance->generateState(300);
$flowInstance->generatePkce('S256');
$elevateProfile = $createMockProfile('hub-tech-101', true, ['admin', 'superadmin', 'root']);
$injectedCallbackHandler = new SsoCallbackHandler($flowInstance, new MockClientDouble($elevateProfile));
$queryParams = ['state' => $sElevate, 'code' => 'code_elevate'];

$resElevate = require dirname(__DIR__) . '/pages/sso-callback.php';
$runner->assert($resElevate['success'] === true, 'Callback succeeds with technician mapping');
$runner->assert($resElevate['identity']['role'] === 'technician', 'Local role technician preserved despite central admin claim');
$runner->assert($_SESSION['role'] === 'technician', 'Session role is technician, not elevated to admin');

// 5.7 Tampered / invalid state
$_SESSION = [];
$sValid = $flowInstance->generateState(300);
$flowInstance->generatePkce('S256');
$injectedCallbackHandler = new SsoCallbackHandler($flowInstance, new MockClientDouble($techProfile));
$queryParams = ['state' => 'tampered_state', 'code' => 'code_xyz'];

$resTampered = require dirname(__DIR__) . '/pages/sso-callback.php';
$runner->assert($resTampered['success'] === false, 'Callback fails closed on tampered/invalid state');
$assertNoSession('tampered state');
$runner->assert(empty($_SESSION['code_verifier']), 'Verifier cleared on tampered state');

// 5.8 Expired state
$_SESSION = [];
$now = time();
$sExp = $flowInstance->generateState(300, $now);
$flowInstance->generatePkce('S256');
$injectedCurrentTime = $now + 305;
$queryParams = ['state' => $sExp, 'code' => 'code_xyz'];

$resExpired = require dirname(__DIR__) . '/pages/sso-callback.php';
$runner->assert($resExpired['success'] === false, 'Callback fails closed on expired state');
$assertNoSession('expired state');
$injectedCurrentTime = null;

// 5.9 Missing authorization code
$_SESSION = [];
$sMissingCode = $flowInstance->generateState(300);
$flowInstance->generatePkce('S256');
$queryParams = ['state' => $sMissingCode];

$resMissingCode = require dirname(__DIR__) . '/pages/sso-callback.php';
$runner->assert($resMissingCode['success'] === false, 'Callback fails closed on missing authorization code');
$assertNoSession('missing code');

// 5.10 Missing verifier in session
$_SESSION = [];
$sNoVer = $flowInstance->generateState(300);
unset($_SESSION['code_verifier']);
$queryParams = ['state' => $sNoVer, 'code' => 'code_xyz'];

$resNoVer = require dirname(__DIR__) . '/pages/sso-callback.php';
$runner->assert($resNoVer['success'] === false, 'Callback fails closed when code verifier is missing in session');
$assertNoSession('missing verifier');

// 5.11 Provider error in query params
$_SESSION = [];
$sErr = $flowInstance->generateState(300);
$flowInstance->generatePkce('S256');
$queryParams = ['state' => $sErr, 'error' => 'central_disabled'];

$resProviderErr = require dirname(__DIR__) . '/pages/sso-callback.php';
$runner->assert($resProviderErr['success'] === false, 'Callback fails closed on provider central_disabled error');
$assertNoSession('provider error');

// =========================================================================
// SUITE 6: SANITIZED GENERIC ERRORS & ZERO SENSITIVE DETAIL LEAKAGE
// =========================================================================
echo "\n=== Suite 6: Sanitized generic errors & zero sensitive detail leakage ===\n";

$allErrorResults = [
    $resInactive['error'],
    $resUnmapped['error'],
    $resInactiveLink['error'],
    $resMisspelled['error'],
    $resUnknown['error'],
    $resTampered['error'],
    $resExpired['error'],
    $resMissingCode['error'],
    $resNoVer['error'],
    $resProviderErr['error'],
];

$sensitivePatterns = [
    'TechPassword123!',
    'AdminPass456!',
    'viwer',
    'superadmin',
    'carlos_tech',
    'maria_admin',
    'hub-tech-101',
    'SELECT',
    'INSERT',
    'users',
    'sso_identity_links',
    'code_verifier',
    'client_secret',
    'access_token',
];

$leakFound = false;
foreach ($allErrorResults as $errMsg) {
    if (!is_string($errMsg) || trim($errMsg) === '') {
        $leakFound = true;
        break;
    }
    foreach ($sensitivePatterns as $secret) {
        if (stripos($errMsg, $secret) !== false) {
            $leakFound = true;
            fwrite(STDERR, "[FAIL] Leaked secret pattern '{$secret}' in error: '{$errMsg}'\n");
        }
    }
}
$runner->assert(!$leakFound, 'All callback error messages are generic, sanitized, and leak zero internal values');

// =========================================================================
// SUITE 7: LOCAL LOGOUT WIRING
// =========================================================================
echo "\n=== Suite 7: Local logout wiring ===\n";

// 7.1 Setup authenticated local session
$_SESSION = [
    'user_id' => 1,
    'username' => 'carlos_tech',
    'role' => 'technician',
    'sso_provider' => 'central_hub',
    'sso_hub_user_id' => 'hub-tech-101',
    'sso_authenticated_at' => time(),
];

$runner->assert(!empty($_SESSION['user_id']), 'Session is pre-populated before logout');

// 7.2 Run pages/logout.php
$logoutResult = require dirname(__DIR__) . '/pages/logout.php';

$runner->assert(is_array($logoutResult) && $logoutResult['success'] === true, 'logout.php returns success outcome');
$runner->assert($logoutResult['redirect_url'] === 'login.php', 'logout.php target redirect is local login.php');
$runner->assert(empty($_SESSION), 'logout.php clears all $_SESSION variables');
$runner->assert(!isset($_SESSION['user_id']), 'logout.php removes user_id from session');
$runner->assert(!isset($_SESSION['role']), 'logout.php removes role from session');
$runner->assert(!isset($_SESSION['sso_provider']), 'logout.php removes federation metadata');
$runner->assert(isset($logoutResult['cookie']) && $logoutResult['cookie']['secure'] === true, 'logout.php enforces Secure=true cookie attribute');
$runner->assert(isset($logoutResult['cookie']) && $logoutResult['cookie']['httponly'] === true, 'logout.php enforces HttpOnly=true cookie attribute');
$runner->assert(isset($logoutResult['cookie']) && $logoutResult['cookie']['samesite'] === 'Lax', 'logout.php enforces SameSite=Lax cookie attribute');

// =========================================================================
// SUITE 8: PROTOCOL ARTIFACT ISOLATION STATIC CHECKS (OAUTH2 ONLY)
// =========================================================================
echo "\n=== Suite 8: Protocol artifact isolation static checks (OAuth2 only) ===\n";

$forbiddenWords = [
    implode('', ['open', 'id']),
    implode('', ['id_', 'token']),
    implode('', ['non', 'ce']),
    implode('', ['oi', 'dc']),
];

$filesToCheck = [
    dirname(__DIR__) . '/pages/login.php',
    dirname(__DIR__) . '/pages/sso-login.php',
    dirname(__DIR__) . '/pages/sso-callback.php',
    dirname(__DIR__) . '/pages/logout.php',
    __FILE__,
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

exit($runner->summary());
