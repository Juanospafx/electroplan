<?php
/**
 * Runtime Test Suite: Electroplan Local Authorization Policy Boundary (TASK-0260).
 *
 * Validates:
 * 1. Isolated doubles only: zero real database or network connections.
 * 2. Active linked identities for each accepted Electroplan role (admin, technician, viewer).
 * 3. Inactive central user fails closed.
 * 4. Missing link and unmapped identity fail closed.
 * 5. Inactive link (active = 0) fails closed.
 * 6. Unknown, missing, empty, or misspelled ('viwer') local roles fail closed without default/fallback roles.
 * 7. Central-role elevation attempts strictly blocked: central roles never elevate or grant local access.
 * 8. Sanitized, generic error messages that never leak rejected roles or internal details.
 * 9. Integration contract with SsoSession::establish.
 * 10. Static security checks for forbidden terms and fallback-to-admin anti-patterns.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once dirname(__DIR__) . '/core/auth/IdentityProfile.php';
require_once dirname(__DIR__) . '/core/auth/FederatedIdentityRepository.php';
require_once dirname(__DIR__) . '/core/auth/SsoSession.php';
require_once dirname(__DIR__) . '/core/auth/SsoAuthorization.php';

use Electroplan\Auth\IdentityProfile;
use Electroplan\Auth\FederatedIdentityRepository;
use Electroplan\Auth\SsoSession;
use Electroplan\Auth\SsoAuthorization;

class AuthorizationRuntimeTestRunner
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
class MockIsolatedAuthStatement extends PDOStatement
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
class MockIsolatedAuthPdo extends PDO
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
    public function exec(string $statement): int|false
    {
        return 0;
    }

    #[\ReturnTypeWillChange]
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $normalized = trim($query);

        // INSERT INTO users
        if (stripos($normalized, 'INSERT INTO users') === 0) {
            return new MockIsolatedAuthStatement([], function (?array $params) {
                if ($params !== null) {
                    $id = (int)$params[0];
                    $this->users[$id] = [
                        'id' => $id,
                        'username' => (string)$params[1],
                        'password' => (string)$params[2],
                        'role' => (string)$params[3],
                        'active' => isset($params[4]) ? (int)$params[4] : 1,
                    ];
                }
                return [];
            });
        }

        // INSERT INTO sso_identity_links
        if (stripos($normalized, 'INSERT INTO sso_identity_links') === 0) {
            return new MockIsolatedAuthStatement([], function (?array $params) {
                if ($params !== null) {
                    $id = $this->linkAutoIncrement++;
                    if (isset($params[':user_id'])) {
                        $this->links[] = [
                            'id' => $id,
                            'user_id' => (int)$params[':user_id'],
                            'provider' => (string)$params[':provider'],
                            'hub_user_id' => (string)$params[':hub_user_id'],
                            'active' => (int)$params[':active'],
                        ];
                    } else {
                        $this->links[] = [
                            'id' => $id,
                            'user_id' => (int)$params[0],
                            'provider' => (string)$params[1],
                            'hub_user_id' => (string)$params[2],
                            'active' => (int)$params[3],
                        ];
                    }
                }
                return [];
            });
        }

        // SELECT ... FROM sso_identity_links l INNER JOIN users u ...
        // Simulates u.active = 1 filter requiring an active local user
        if (stripos($normalized, 'FROM sso_identity_links l') !== false && stripos($normalized, 'INNER JOIN users') !== false) {
            return new MockIsolatedAuthStatement([], function (?array $params) {
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
                            $userActive = isset($user['active']) ? (int)$user['active'] : 1;
                            if ($userActive === 1) {
                                return [[
                                    'user_id' => $user['id'],
                                    'username' => $user['username'],
                                    'role' => $user['role'],
                                    'user_active' => $userActive,
                                    'link_id' => $link['id'],
                                    'provider' => $link['provider'],
                                    'hub_user_id' => $link['hub_user_id'],
                                    'active' => $link['active'],
                                ]];
                            }
                        }
                    }
                }
                return [];
            });
        }

        return new MockIsolatedAuthStatement([]);
    }
}

$runner = new AuthorizationRuntimeTestRunner();
echo "--- Iniciando validacion runtime de Electroplan Local Authorization Boundary ---\n\n";

// =========================================================================
// SUITE 1: ISOLATED DOUBLES & BOUNDARY SETUP
// =========================================================================
echo "=== Suite 1: Isolated doubles & boundary setup ===\n";

$pdo = new MockIsolatedAuthPdo();
$driverName = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$runner->assert($driverName === 'isolated_double', 'Zero real database access: uses isolated in-memory PDO double');

// Seed test users: covers admin, technician, viewer, unknown role, misspelled role, empty role, inactive user
$userStmt = $pdo->prepare("INSERT INTO users (id, username, password, role) VALUES (?, ?, ?, ?)");
$userStmt->execute([1, 'carlos_tech', 'hash1', 'technician']);
$userStmt->execute([2, 'maria_admin', 'hash2', 'admin']);
$userStmt->execute([3, 'juan_viewer', 'hash3', 'viewer']);
$userStmt->execute([4, 'ana_unknown', 'hash4', 'superadmin']);
$userStmt->execute([5, 'pedro_misspelled', 'hash5', 'viwer']);
$userStmt->execute([6, 'luis_empty', 'hash6', '']);
// Inactive local user with valid role (technician)
$userStmt->execute([7, 'diego_inactive_local', 'hash7', 'technician', 0]);

// Seed test links: covers active links, inactive links, cross-provider, orphan links
$linkStmt = $pdo->prepare("INSERT INTO sso_identity_links (user_id, provider, hub_user_id, active) VALUES (?, ?, ?, ?)");
$linkStmt->execute([1, 'central_hub', 'hub-tech-101', 1]);
$linkStmt->execute([2, 'central_hub', 'hub-admin-202', 1]);
$linkStmt->execute([3, 'central_hub', 'hub-viewer-303', 1]);
$linkStmt->execute([1, 'central_hub', 'hub-tech-inactive', 0]);
$linkStmt->execute([2, 'central_hub', 'hub-admin-inactive', 0]);
$linkStmt->execute([4, 'central_hub', 'hub-unknown-404', 1]);
$linkStmt->execute([5, 'central_hub', 'hub-misspelled-505', 1]);
$linkStmt->execute([6, 'central_hub', 'hub-empty-606', 1]);
$linkStmt->execute([9999, 'central_hub', 'hub-orphan-999', 1]);
$linkStmt->execute([1, 'secondary_hub', 'sec-tech-101', 1]);
// Active link for inactive local user 7
$linkStmt->execute([7, 'central_hub', 'hub-tech-inactive-local-707', 1]);

$repository = new FederatedIdentityRepository($pdo);
$auth = new SsoAuthorization($repository);
$runner->assert($auth->getRepository() === $repository, 'SsoAuthorization holds repository dependency');

// =========================================================================
// SUITE 2: ACTIVE LINKED IDENTITIES FOR EACH ACCEPTED LOCAL ROLE
// =========================================================================
echo "\n=== Suite 2: Active linked identities for each accepted local role ===\n";

// 2.1 Local role: technician
$techProfile = IdentityProfile::fromClaims([
    'hub_user_id' => 'hub-tech-101',
    'provider' => 'central_hub',
    'active' => true,
    'roles' => ['user'],
]);
$resTech = $auth->authorize($techProfile);
$runner->assert($resTech['success'] === true, 'Authorization succeeds for active technician');
$runner->assert($resTech['error'] === null, 'Error is null on successful authorization');
$runner->assert($resTech['identity']['role'] === 'technician', 'Returned role preserves local role "technician"');
$runner->assert($resTech['identity']['user_id'] === 1, 'Returned user_id matches local user 1');
$runner->assert($resTech['identity']['username'] === 'carlos_tech', 'Returned username matches local user');
$runner->assert($resTech['identity']['provider'] === 'central_hub', 'Returned provider is central_hub');
$runner->assert($resTech['identity']['hub_user_id'] === 'hub-tech-101', 'Returned hub_user_id is hub-tech-101');

// 2.2 Local role: admin
$adminProfile = IdentityProfile::fromClaims([
    'hub_user_id' => 'hub-admin-202',
    'provider' => 'central_hub',
    'active' => true,
    'roles' => ['central_user'],
]);
$resAdmin = $auth->authorize($adminProfile);
$runner->assert($resAdmin['success'] === true, 'Authorization succeeds for active admin');
$runner->assert($resAdmin['identity']['role'] === 'admin', 'Returned role preserves local role "admin"');
$runner->assert($resAdmin['identity']['user_id'] === 2, 'Returned user_id matches local user 2');
$runner->assert($resAdmin['identity']['username'] === 'maria_admin', 'Returned username matches local user');

// 2.3 Local role: viewer
$viewerProfile = IdentityProfile::fromClaims([
    'hub_user_id' => 'hub-viewer-303',
    'provider' => 'central_hub',
    'active' => true,
    'roles' => [],
]);
$resViewer = $auth->authorize($viewerProfile);
$runner->assert($resViewer['success'] === true, 'Authorization succeeds for active viewer');
$runner->assert($resViewer['identity']['role'] === 'viewer', 'Returned role preserves local role "viewer"');
$runner->assert($resViewer['identity']['user_id'] === 3, 'Returned user_id matches local user 3');

// 2.4 Integration check with SsoSession::establish
$_SESSION = [];
$sessionEstablished = SsoSession::establish($resTech['identity']);
$runner->assert($sessionEstablished === true, 'Authorized identity passes SsoSession::establish contract');
$runner->assert($_SESSION['role'] === 'technician', 'SsoSession records exact local role');
$runner->assert($_SESSION['user_id'] === 1, 'SsoSession records exact user_id');

// =========================================================================
// SUITE 3: INACTIVE CENTRAL USER FAILS CLOSED
// =========================================================================
echo "\n=== Suite 3: Inactive central user fails closed ===\n";

// 3.1 Inactive central user with admin role locally
$inactiveAdminProfile = IdentityProfile::fromClaims([
    'hub_user_id' => 'hub-admin-202',
    'provider' => 'central_hub',
    'active' => false,
    'roles' => ['admin'],
]);
$resInactiveAdmin = $auth->authorize($inactiveAdminProfile);
$runner->assert($resInactiveAdmin['success'] === false, 'Inactive central user fails closed (active = false)');
$runner->assert($resInactiveAdmin['identity'] === null, 'Inactive central user returns null identity');
$runner->assert(is_string($resInactiveAdmin['error']), 'Inactive central user returns sanitized error');

// 3.2 Inactive central user with technician role locally
$inactiveTechProfile = IdentityProfile::fromClaims([
    'hub_user_id' => 'hub-tech-101',
    'provider' => 'central_hub',
    'active' => false,
]);
$resInactiveTech = $auth->authorize($inactiveTechProfile);
$runner->assert($resInactiveTech['success'] === false, 'Inactive central technician fails closed');
$runner->assert($resInactiveTech['identity'] === null, 'Inactive central technician returns null identity');

// =========================================================================
// SUITE 4: MISSING AND ABSENT IDENTITY PROFILE FAILS CLOSED
// =========================================================================
echo "\n=== Suite 4: Missing and absent identity profile fails closed ===\n";

// 4.1 Null profile
$resNull = $auth->authorize(null);
$runner->assert($resNull['success'] === false, 'Null profile fails closed');
$runner->assert($resNull['identity'] === null, 'Null profile returns null identity');
$runner->assert(is_string($resNull['error']), 'Null profile returns sanitized error');

// 4.2 Array passed instead of IdentityProfile
$resArray = $auth->authorize(['hub_user_id' => 'hub-tech-101', 'provider' => 'central_hub']);
$runner->assert($resArray['success'] === false, 'Raw array fails closed');

// 4.3 Profile missing provider
$noProviderProfile = new IdentityProfile('hub-tech-101', 'email@test.com', 'Name', 'usr', true, [], null);
$resNoProvider = $auth->authorize($noProviderProfile);
$runner->assert($resNoProvider['success'] === false, 'Profile without provider fails closed');

// 4.4 Profile with whitespace provider
$wsProviderProfile = new IdentityProfile('hub-tech-101', 'email@test.com', 'Name', 'usr', true, [], '   ');
$resWsProvider = $auth->authorize($wsProviderProfile);
$runner->assert($resWsProvider['success'] === false, 'Profile with whitespace provider fails closed');

// =========================================================================
// SUITE 5: MISSING, INACTIVE AND ORPHAN FEDERATED IDENTITY LINKS
// =========================================================================
echo "\n=== Suite 5: Missing, inactive and orphan federated identity links ===\n";

// 5.1 Missing link (unmapped hub user)
$unmappedProfile = IdentityProfile::fromClaims([
    'hub_user_id' => 'hub-unmapped-user-999',
    'provider' => 'central_hub',
    'active' => true,
    'roles' => ['admin'],
]);
$resUnmapped = $auth->authorize($unmappedProfile);
$runner->assert($resUnmapped['success'] === false, 'Unmapped hub user fails closed');
$runner->assert($resUnmapped['identity'] === null, 'Unmapped hub user returns null identity');
$runner->assert(is_string($resUnmapped['error']), 'Unmapped hub user returns sanitized error');

// 5.2 Inactive link (active = 0)
$inactiveLinkProfile = IdentityProfile::fromClaims([
    'hub_user_id' => 'hub-tech-inactive',
    'provider' => 'central_hub',
    'active' => true,
]);
$resInactiveLink = $auth->authorize($inactiveLinkProfile);
$runner->assert($resInactiveLink['success'] === false, 'Inactive link (active = 0) fails closed');
$runner->assert($resInactiveLink['identity'] === null, 'Inactive link returns null identity');

// 5.3 Inactive link for local admin
$inactiveAdminLinkProfile = IdentityProfile::fromClaims([
    'hub_user_id' => 'hub-admin-inactive',
    'provider' => 'central_hub',
    'active' => true,
    'roles' => ['admin'],
]);
$resInactiveAdminLink = $auth->authorize($inactiveAdminLinkProfile);
$runner->assert($resInactiveAdminLink['success'] === false, 'Inactive link for local admin fails closed');
$runner->assert($resInactiveAdminLink['identity'] === null, 'Inactive admin link returns null identity');

// 5.4 Orphan link (link points to non-existent local user)
$orphanProfile = IdentityProfile::fromClaims([
    'hub_user_id' => 'hub-orphan-999',
    'provider' => 'central_hub',
    'active' => true,
]);
$resOrphan = $auth->authorize($orphanProfile);
$runner->assert($resOrphan['success'] === false, 'Orphan link (missing local user) fails closed');

// 5.5 Provider mismatch (cross-provider isolation)
$wrongProviderProfile = IdentityProfile::fromClaims([
    'hub_user_id' => 'hub-tech-101',
    'provider' => 'secondary_hub',
    'active' => true,
]);
$resWrongProvider = $auth->authorize($wrongProviderProfile);
$runner->assert($resWrongProvider['success'] === false, 'Cross-provider query fails closed');

// 5.6 Inactive local user fails closed even when central identity is active (active-central / inactive-local denial)
$inactiveLocalProfile = IdentityProfile::fromClaims([
    'hub_user_id' => 'hub-tech-inactive-local-707',
    'provider' => 'central_hub',
    'active' => true,
    'roles' => ['admin'],
]);
$resInactiveLocal = $auth->authorize($inactiveLocalProfile);
$runner->assert($resInactiveLocal['success'] === false, 'Active central identity with inactive local user fails closed (active-central / inactive-local denial)');
$runner->assert($resInactiveLocal['identity'] === null, 'Inactive local user returns null identity');
$runner->assert(is_string($resInactiveLocal['error']) && trim($resInactiveLocal['error']) !== '', 'Inactive local user returns sanitized non-empty error');
$runner->assert($auth->isAuthorized($inactiveLocalProfile) === false, 'isAuthorized returns false for inactive local user');
$runner->assert($auth->authorizeIdentity($inactiveLocalProfile) === null, 'authorizeIdentity returns null for inactive local user');

// 5.7 Inactive central identity AND inactive local user fails closed
$inactiveBothProfile = IdentityProfile::fromClaims([
    'hub_user_id' => 'hub-tech-inactive-local-707',
    'provider' => 'central_hub',
    'active' => false,
]);
$resInactiveBoth = $auth->authorize($inactiveBothProfile);
$runner->assert($resInactiveBoth['success'] === false, 'Inactive central and inactive local user fails closed');
$runner->assert($resInactiveBoth['identity'] === null, 'Inactive central and inactive local user returns null identity');

// 5.8 Missing or invalid local user active state fails closed
$mockRepoMissingActive = new class extends FederatedIdentityRepository {
    public function __construct() {}
    public function findLinkedUser(string $provider, string $hubUserId): ?array {
        return [
            'user_id' => 1,
            'id' => 1,
            'username' => 'missing_active_user',
            'role' => 'technician',
            'provider' => 'central_hub',
            'hub_user_id' => 'hub-missing-active',
            'link_id' => 10,
        ];
    }
};
$authMissingActive = new SsoAuthorization($mockRepoMissingActive);
$profileMissingActive = IdentityProfile::fromClaims([
    'hub_user_id' => 'hub-missing-active',
    'provider' => 'central_hub',
    'active' => true,
]);
$resMissingActive = $authMissingActive->authorize($profileMissingActive);
$runner->assert($resMissingActive['success'] === false, 'Missing local active state fails closed');
$runner->assert($resMissingActive['identity'] === null, 'Missing local active state returns null identity');

$mockRepoInvalidActive = new class extends FederatedIdentityRepository {
    public function __construct() {}
    public function findLinkedUser(string $provider, string $hubUserId): ?array {
        return [
            'user_id' => 1,
            'id' => 1,
            'username' => 'invalid_active_user',
            'role' => 'technician',
            'provider' => 'central_hub',
            'hub_user_id' => 'hub-invalid-active',
            'link_id' => 11,
            'user_active' => 'corrupted_state',
        ];
    }
};
$authInvalidActive = new SsoAuthorization($mockRepoInvalidActive);
$profileInvalidActive = IdentityProfile::fromClaims([
    'hub_user_id' => 'hub-invalid-active',
    'provider' => 'central_hub',
    'active' => true,
]);
$resInvalidActive = $authInvalidActive->authorize($profileInvalidActive);
$runner->assert($resInvalidActive['success'] === false, 'Invalid local active state fails closed');
$runner->assert($resInvalidActive['identity'] === null, 'Invalid local active state returns null identity');

// =========================================================================
// SUITE 6: UNKNOWN, MISSPELLED AND EMPTY LOCAL ROLES FAIL CLOSED (NO FALLBACK)
// =========================================================================
echo "\n=== Suite 6: Unknown, misspelled and empty local roles fail closed ===\n";

// 6.1 Unknown local role: 'superadmin'
$unknownRoleProfile = IdentityProfile::fromClaims([
    'hub_user_id' => 'hub-unknown-404',
    'provider' => 'central_hub',
    'active' => true,
    'roles' => ['admin'],
]);
$resUnknownRole = $auth->authorize($unknownRoleProfile);
$runner->assert($resUnknownRole['success'] === false, 'Unknown local role "superadmin" fails closed');
$runner->assert($resUnknownRole['identity'] === null, 'Unknown local role returns null identity');
$runner->assert($resUnknownRole['error'] === SsoAuthorization::ERROR_INVALID_ROLE, 'Unknown local role returns sanitized error');

// 6.2 Misspelled local role: 'viwer' (NEVER normalized to viewer, NEVER gets fallback)
$misspelledProfile = IdentityProfile::fromClaims([
    'hub_user_id' => 'hub-misspelled-505',
    'provider' => 'central_hub',
    'active' => true,
]);
$resMisspelled = $auth->authorize($misspelledProfile);
$runner->assert($resMisspelled['success'] === false, 'Misspelled role "viwer" fails closed (no normalization)');
$runner->assert($resMisspelled['identity'] === null, 'Misspelled role returns null identity');

// 6.3 Empty local role in database
$emptyRoleProfile = IdentityProfile::fromClaims([
    'hub_user_id' => 'hub-empty-606',
    'provider' => 'central_hub',
    'active' => true,
]);
$resEmptyRole = $auth->authorize($emptyRoleProfile);
$runner->assert($resEmptyRole['success'] === false, 'Empty local role fails closed');
$runner->assert($resEmptyRole['identity'] === null, 'Empty local role returns null identity');

// =========================================================================
// SUITE 7: CENTRAL-ROLE ELEVATION ATTEMPTS STRICTLY BLOCKED
// =========================================================================
echo "\n=== Suite 7: Central-role elevation attempts strictly blocked ===\n";

// 7.1 Central claims have 'admin', but local role is 'technician'
$elevateTechProfile = IdentityProfile::fromClaims([
    'hub_user_id' => 'hub-tech-101',
    'provider' => 'central_hub',
    'active' => true,
    'roles' => ['admin', 'superadmin', 'root', 'billing_manager'],
]);
$resElevateTech = $auth->authorize($elevateTechProfile);
$runner->assert($resElevateTech['success'] === true, 'Authorization succeeds without elevation');
$runner->assert($resElevateTech['identity']['role'] === 'technician', 'Technician local role is preserved; central "admin" role ignored');
$runner->assert($resElevateTech['identity']['role'] !== 'admin', 'Central admin claim does NOT elevate technician to admin');

// 7.2 Central claims have 'admin', but local role is 'viewer'
$elevateViewerProfile = IdentityProfile::fromClaims([
    'hub_user_id' => 'hub-viewer-303',
    'provider' => 'central_hub',
    'active' => true,
    'roles' => ['admin'],
]);
$resElevateViewer = $auth->authorize($elevateViewerProfile);
$runner->assert($resElevateViewer['success'] === true, 'Authorization succeeds without elevation');
$runner->assert($resElevateViewer['identity']['role'] === 'viewer', 'Viewer local role is preserved; central "admin" role ignored');
$runner->assert($resElevateViewer['identity']['role'] !== 'admin', 'Central admin claim does NOT elevate viewer to admin');

// 7.3 Central claims have 'admin', but local role is unknown ('superadmin')
$elevateUnknownProfile = IdentityProfile::fromClaims([
    'hub_user_id' => 'hub-unknown-404',
    'provider' => 'central_hub',
    'active' => true,
    'roles' => ['admin'],
]);
$resElevateUnknown = $auth->authorize($elevateUnknownProfile);
$runner->assert($resElevateUnknown['success'] === false, 'Central admin cannot grant access when local role is unknown');
$runner->assert($resElevateUnknown['identity'] === null, 'No identity returned when local role is unknown');

// 7.4 Central claims have arbitrary elevated privileges in claims map
$elevateClaimsProfile = IdentityProfile::fromClaims([
    'hub_user_id' => 'hub-viewer-303',
    'provider' => 'central_hub',
    'active' => true,
    'roles' => ['admin'],
    'is_admin' => true,
    'permissions' => ['all', 'delete', 'write'],
]);
$resElevateClaims = $auth->authorize($elevateClaimsProfile);
$runner->assert($resElevateClaims['identity']['role'] === 'viewer', 'Raw central claims cannot alter local database role');

// =========================================================================
// SUITE 8: SANITIZED GENERIC ERROR MESSAGES & NO SECRET / ROLE LEAKAGE
// =========================================================================
echo "\n=== Suite 8: Sanitized generic error messages & zero leakage ===\n";

$errorCases = [
    $resInactiveAdmin['error'],
    $resNull['error'],
    $resUnmapped['error'],
    $resInactiveLink['error'],
    $resUnknownRole['error'],
    $resMisspelled['error'],
    $resEmptyRole['error'],
    $resInactiveLocal['error'],
    $resMissingActive['error'],
    $resInvalidActive['error'],
];

$leakedTerms = [
    'superadmin',
    'viwer',
    'carlos_tech',
    'maria_admin',
    'hub-tech-101',
    'SELECT',
    'users',
    'sso_identity_links',
    'password',
    'token',
    'secret',
];

$leakDetected = false;
foreach ($errorCases as $errMsg) {
    if (!is_string($errMsg) || trim($errMsg) === '') {
        $leakDetected = true;
        break;
    }
    foreach ($leakedTerms as $term) {
        if (stripos($errMsg, $term) !== false) {
            $leakDetected = true;
            fwrite(STDERR, "[FAIL] Leaked term in error message: {$term} in '{$errMsg}'\n");
        }
    }
}
$runner->assert(!$leakDetected, 'All error messages are generic, sanitized, and leak zero internal values or roles');

// =========================================================================
// SUITE 9: CONVENIENCE HELPERS AND CLASS ALIAS
// =========================================================================
echo "\n=== Suite 9: Convenience helpers and class alias ===\n";

// 9.1 authorizeIdentity
$idDirect = $auth->authorizeIdentity($techProfile);
$runner->assert(is_array($idDirect) && $idDirect['role'] === 'technician', 'authorizeIdentity returns identity array on success');

$idDirectFail = $auth->authorizeIdentity($unmappedProfile);
$runner->assert($idDirectFail === null, 'authorizeIdentity returns null on failure');

// 9.2 resolveAndAuthorize
$idAlias = $auth->resolveAndAuthorize($techProfile);
$runner->assert(is_array($idAlias) && $idAlias['role'] === 'technician', 'resolveAndAuthorize returns identity array on success');

// 9.3 isAuthorized
$runner->assert($auth->isAuthorized($techProfile) === true, 'isAuthorized returns true for valid active identity');
$runner->assert($auth->isAuthorized($unmappedProfile) === false, 'isAuthorized returns false for unmapped identity');
$runner->assert($auth->isAuthorized($inactiveTechProfile) === false, 'isAuthorized returns false for inactive identity');

// 9.4 Static helper authorizeProfile
$resStatic = SsoAuthorization::authorizeProfile($techProfile, $repository);
$runner->assert($resStatic['success'] === true && $resStatic['identity']['role'] === 'technician', 'Static authorizeProfile succeeds');

// 9.5 Global class alias
$runner->assert(class_exists('SsoAuthorization', false), 'Global class alias SsoAuthorization is defined');

// =========================================================================
// SUITE 10: STATIC SECURITY CONTRACT & FORBIDDEN REFERENCES
// =========================================================================
echo "\n=== Suite 10: Static security contract & forbidden references ===\n";

$forbiddenTerms = [
    implode('', ['open', 'id']),
    implode('', ['id_', 'token']),
    implode('', ['non', 'ce']),
    implode('', ['oi', 'dc']),
];

$filesToCheck = [
    dirname(__DIR__) . '/core/auth/SsoAuthorization.php',
    dirname(__DIR__) . '/db/migrations/20261015_sso_local_user_active.sql',
    dirname(__DIR__) . '/tests/sso_local_user_active_schema.test.php',
    __FILE__,
];

foreach ($filesToCheck as $filePath) {
    $content = file_get_contents($filePath);
    $baseName = basename($filePath);
    $foundForbidden = false;
    foreach ($forbiddenTerms as $term) {
        if (stripos($content, $term) !== false) {
            $foundForbidden = true;
            fwrite(STDERR, "[FAIL] Forbidden term found in {$baseName}: {$term}\n");
        }
    }
    $runner->assert(!$foundForbidden, "No functional forbidden references in {$baseName}");
}

// Check forbidden fallback-to-admin patterns in core/auth/SsoAuthorization.php
$authFileContent = file_get_contents(dirname(__DIR__) . '/core/auth/SsoAuthorization.php');
$forbiddenFallbackPatterns = [
    '/\?\?\s*[\'"]admin[\'"]/i' => 'Null-coalesce fallback to literal "admin"',
    '/\?:\s*[\'"]admin[\'"]/i' => 'Ternary fallback to literal "admin"',
    '/\b(default|fallback)[_\w]*\s*(?:=>|:|=)\s*[\'"]admin[\'"]/i' => 'Default/fallback configuration with "admin"',
    '/\bdefault\s*:\s*(?:return\s+)?[\'"]admin[\'"]/i' => 'Switch default returning "admin"',
    '/(?<![=!<>])=\s*[\'"]admin[\'"]/i' => 'Direct assignment of literal "admin"',
];

$patternViolation = false;
foreach ($forbiddenFallbackPatterns as $pattern => $desc) {
    if (preg_match($pattern, $authFileContent)) {
        $patternViolation = true;
        fwrite(STDERR, "[FAIL] Forbidden fallback pattern found in SsoAuthorization.php: {$desc}\n");
    }
}
$runner->assert(!$patternViolation, 'SsoAuthorization contains zero forbidden fallback-to-admin patterns');

exit($runner->summary());
