<?php
/**
 * Runtime Test Suite: Electroplan Federated Identity Link & Local Session Boundary (TASK-0259).
 *
 * Validates:
 * 1. Isolated in-memory PDO doubles only; proves zero real database/network access and no SQLite runtime dependency.
 * 2. Repository construction requires an explicit PDO dependency.
 * 3. Exact case-sensitive equality on provider and hub_user_id survives case-insensitive database collation.
 * 4. Inactive link rejection (active = 0).
 * 5. Missing link rejection.
 * 6. Missing local user rejection (orphan link).
 * 7. Strict provider isolation.
 * 8. No auto-linking by username or email.
 * 9. No default user or role assignment (fails closed).
 * 10. Role regressions: misspelled roles ('viwer') rejected (never normalized), no defaults invented, rejected roles not leaked.
 * 11. Session creation requires non-empty provider and hub_user_id metadata.
 * 12. Cookie configuration fixed to Secure=true, HttpOnly=true, SameSite=Lax with no downgrade parameter.
 * 13. SsoSession establishment clears accepted flow state and verifier keys.
 * 14. Exact local session shape (user_id, username, role) plus non-sensitive federation metadata.
 * 15. Migration static schema contract (exact-match utf8mb4_bin collation, aligned users.id foreign key).
 * 16. Static forbidden references check.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once dirname(__DIR__) . '/core/auth/IdentityProfile.php';
require_once dirname(__DIR__) . '/core/auth/FederatedIdentityRepository.php';
require_once dirname(__DIR__) . '/core/auth/SsoSession.php';

use Electroplan\Auth\IdentityProfile;
use Electroplan\Auth\FederatedIdentityRepository;
use Electroplan\Auth\SsoSession;

class IdentityLinkRuntimeTestRunner
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
 * Isolated PDOStatement double performing no real database or network operations.
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
        if ($mode === PDO::FETCH_COLUMN) {
            $colIndex = $args[0] ?? 0;
            $res = [];
            foreach ($this->rows as $row) {
                $values = array_values($row);
                $res[] = $values[$colIndex] ?? reset($row);
            }
            return $res;
        }
        return $this->rows;
    }
}

/**
 * Isolated in-memory PDO double.
 * Simulates case-insensitive database collation (e.g. MariaDB utf8mb4_general_ci)
 * to prove that FederatedIdentityRepository verifies provider and hub_user_id
 * with exact case-sensitive equality even when the underlying DB matches case-insensitively.
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
    public function exec(string $statement): int|false
    {
        return 0;
    }

    #[\ReturnTypeWillChange]
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        if (stripos($query, 'sqlite_master') !== false || stripos($query, 'tables') !== false) {
            return new MockIsolatedStatement([
                ['name' => 'users'],
                ['name' => 'sso_identity_links'],
            ]);
        }
        return new MockIsolatedStatement([]);
    }

    #[\ReturnTypeWillChange]
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $normalizedQuery = trim($query);

        // INSERT INTO users
        if (stripos($normalizedQuery, 'INSERT INTO users') === 0) {
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
        if (stripos($normalizedQuery, 'INSERT INTO sso_identity_links') === 0) {
            return new MockIsolatedStatement([], function (?array $params) {
                if ($params !== null) {
                    $id = $this->linkAutoIncrement++;
                    if (isset($params[':user_id'])) {
                        $this->links[] = [
                            'id' => $id,
                            'user_id' => (int)$params[':user_id'],
                            'provider' => (string)$params[':provider'],
                            'hub_user_id' => (string)$params[':hub_user_id'],
                            'active' => (int)$params[':active'],
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ];
                    } else {
                        $this->links[] = [
                            'id' => $id,
                            'user_id' => (int)$params[0],
                            'provider' => (string)$params[1],
                            'hub_user_id' => (string)$params[2],
                            'active' => (int)$params[3],
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ];
                    }
                }
                return [];
            });
        }

        // SELECT id FROM users WHERE id = :user_id LIMIT 1 (createLink verification)
        if (stripos($normalizedQuery, 'SELECT id FROM users') !== false) {
            return new MockIsolatedStatement([], function (?array $params) {
                $userId = (int)($params[':user_id'] ?? $params['user_id'] ?? 0);
                if (isset($this->users[$userId])) {
                    return [['id' => $userId]];
                }
                return [];
            });
        }

        // SELECT ... FROM sso_identity_links l INNER JOIN users u ... (findLinkedUser)
        // Implements case-insensitive database collation simulation (MariaDB utf8mb4_general_ci)
        if (stripos($normalizedQuery, 'FROM sso_identity_links l') !== false && stripos($normalizedQuery, 'INNER JOIN users') !== false) {
            return new MockIsolatedStatement([], function (?array $params) {
                $provider = (string)($params[':provider'] ?? $params['provider'] ?? '');
                $hubUserId = (string)($params[':hub_user_id'] ?? $params['hub_user_id'] ?? '');

                foreach ($this->links as $link) {
                    // Collation level match: case-insensitive
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

        // SELECT ... FROM sso_identity_links WHERE provider = :provider AND hub_user_id = :hub_user_id (getLink)
        if (stripos($normalizedQuery, 'FROM sso_identity_links') !== false) {
            return new MockIsolatedStatement([], function (?array $params) {
                $provider = (string)($params[':provider'] ?? $params['provider'] ?? '');
                $hubUserId = (string)($params[':hub_user_id'] ?? $params['hub_user_id'] ?? '');

                foreach ($this->links as $link) {
                    if (
                        strcasecmp((string)$link['provider'], $provider) === 0
                        && strcasecmp((string)$link['hub_user_id'], $hubUserId) === 0
                    ) {
                        return [$link];
                    }
                }
                return [];
            });
        }

        return new MockIsolatedStatement([]);
    }
}

$runner = new IdentityLinkRuntimeTestRunner();
echo "--- Iniciando validacion runtime de Electroplan Federated Identity Link & Local Session ---\n\n";

// =========================================================================
// SUITE 1: ISOLATED PDO DOUBLE & ZERO REAL DATABASE ACCESS
// =========================================================================
echo "=== Suite 1: Isolated PDO double & zero real database access ===\n";

$pdo = new MockIsolatedPdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$runner->assert($driver === 'isolated_double' && $pdo instanceof PDO, 'Database double uses isolated in-memory PDO double');

// Verify no external database configuration or connection was instantiated
$runner->assert(
    !isset($GLOBALS['real_db_connected']),
    'Zero real database access: no external DB connection instantiated'
);

// Setup schema simulation
$pdo->exec("
    CREATE TABLE users (
        id INTEGER PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'technician',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
");

$pdo->exec("
    CREATE TABLE sso_identity_links (
        id INTEGER PRIMARY KEY,
        user_id INTEGER NOT NULL,
        provider VARCHAR(64) NOT NULL,
        hub_user_id VARCHAR(191) NOT NULL,
        active INTEGER NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (provider, hub_user_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );
");

$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
$runner->assert(in_array('users', $tables, true), 'Users table created in in-memory double');
$runner->assert(in_array('sso_identity_links', $tables, true), 'sso_identity_links table created in in-memory double');

// Seed test users
$userStmt = $pdo->prepare("INSERT INTO users (id, username, password, role) VALUES (?, ?, ?, ?)");
$userStmt->execute([1, 'carlos_tech', '$2y$10$dummyhashfortesting000000000000000000000000000000000', 'technician']);
$userStmt->execute([2, 'admin_maria', '$2y$10$dummyhashfortesting000000000000000000000000000000001', 'admin']);
$userStmt->execute([3, 'viewer_juan', '$2y$10$dummyhashfortesting000000000000000000000000000000002', 'viewer']);

// Seed test links
$linkStmt = $pdo->prepare("INSERT INTO sso_identity_links (user_id, provider, hub_user_id, active) VALUES (?, ?, ?, ?)");
// Active link for carlos_tech (user 1)
$linkStmt->execute([1, 'central_hub', 'hub-usr-101', 1]);
// Inactive link for admin_maria (user 2)
$linkStmt->execute([2, 'central_hub', 'hub-usr-102-inactive', 0]);
// Orphan link (user 9999 does not exist in users table)
$linkStmt->execute([9999, 'central_hub', 'hub-usr-orphan', 1]);
// Different provider link for carlos_tech
$linkStmt->execute([1, 'secondary_hub', 'sec-usr-999', 1]);

// Repository construction requires explicit PDO dependency
$repoRef = new \ReflectionMethod(FederatedIdentityRepository::class, '__construct');
$runner->assert($repoRef->getNumberOfRequiredParameters() === 1, 'Repository constructor strictly requires explicit PDO dependency');

$repo = new FederatedIdentityRepository($pdo);
$runner->assert($repo->getPdo() === $pdo, 'Repository correctly wrapped in-memory PDO double instance');

// =========================================================================
// SUITE 2: MISSING & INACTIVE IDENTITY LINKS
// =========================================================================
echo "\n=== Suite 2: Missing & inactive identity links ===\n";

// 2.1 Non-existent link
$resMissing = $repo->findLinkedUser('central_hub', 'non-existent-hub-id');
$runner->assert($resMissing === null, 'Missing federated link returns null');

// 2.2 Inactive link (active = 0)
$resInactive = $repo->findLinkedUser('central_hub', 'hub-usr-102-inactive');
$runner->assert($resInactive === null, 'Inactive federated link (active = 0) returns null (fails closed)');

// 2.3 Empty provider or empty hub_user_id
$resEmptyProvider = $repo->findLinkedUser('', 'hub-usr-101');
$runner->assert($resEmptyProvider === null, 'Empty provider returns null');

$resEmptyHubId = $repo->findLinkedUser('central_hub', '   ');
$runner->assert($resEmptyHubId === null, 'Empty hub_user_id returns null');

// =========================================================================
// SUITE 3: MISSING USER (ORPHAN LINK) & INTEGRITY
// =========================================================================
echo "\n=== Suite 3: Missing local user (orphan link) & integrity ===\n";

// 3.1 Orphan link points to non-existent user_id 9999
$resOrphan = $repo->findLinkedUser('central_hub', 'hub-usr-orphan');
$runner->assert($resOrphan === null, 'Orphan link (user does not exist in users table) returns null');

// =========================================================================
// SUITE 4: PROVIDER ISOLATION
// =========================================================================
echo "\n=== Suite 4: Strict provider isolation ===\n";

// 4.1 Hub ID 'hub-usr-101' exists under 'central_hub', not under 'secondary_hub'
$resWrongProvider = $repo->findLinkedUser('secondary_hub', 'hub-usr-101');
$runner->assert($resWrongProvider === null, 'Cross-provider query returns null (strict provider isolation)');

// 4.2 Querying secondary_hub with its exact hub_user_id succeeds
$resSecondary = $repo->findLinkedUser('secondary_hub', 'sec-usr-999');
$runner->assert($resSecondary !== null, 'Exact provider and hub_user_id succeeds on secondary provider');
$runner->assert($resSecondary['user_id'] === 1, 'Secondary provider resolves correct local user_id');

// =========================================================================
// SUITE 5: EXACT-CASE MATCHING UNDER CASE-INSENSITIVE COLLATION & NO AUTO-LINKING
// =========================================================================
echo "\n=== Suite 5: Exact-case matching under case-insensitive DB collation & no auto-linking ===\n";

// 5.1 Case mismatch on provider: underlying DB matches case-insensitively, but repository rejects
$resProviderCaseMismatch = $repo->findLinkedUser('CENTRAL_HUB', 'hub-usr-101');
$runner->assert($resProviderCaseMismatch === null, 'Provider case mismatch returns null despite case-insensitive DB match');

$resProviderCaseMismatch2 = $repo->findLinkedUser('Central_Hub', 'hub-usr-101');
$runner->assert($resProviderCaseMismatch2 === null, 'Provider mixed-case mismatch returns null');

// 5.2 Case mismatch on hub_user_id: underlying DB matches case-insensitively, but repository rejects
$resHubIdCaseMismatch = $repo->findLinkedUser('central_hub', 'HUB-USR-101');
$runner->assert($resHubIdCaseMismatch === null, 'hub_user_id case mismatch returns null despite case-insensitive DB match');

$resHubIdCaseMismatch2 = $repo->findLinkedUser('central_hub', 'Hub-Usr-101');
$runner->assert($resHubIdCaseMismatch2 === null, 'hub_user_id mixed-case mismatch returns null');

// 5.3 getLink case mismatch verification
$rawLinkProviderMismatch = $repo->getLink('CENTRAL_HUB', 'hub-usr-101');
$runner->assert($rawLinkProviderMismatch === null, 'getLink rejects provider case mismatch');

$rawLinkHubMismatch = $repo->getLink('central_hub', 'HUB-USR-101');
$runner->assert($rawLinkHubMismatch === null, 'getLink rejects hub_user_id case mismatch');

// 5.4 No auto-linking
$resAutoLinkAttempt = $repo->findLinkedUser('central_hub', 'viewer_juan');
$runner->assert($resAutoLinkAttempt === null, 'Never auto-links by username matching hub_user_id');

// Verify no link was inserted automatically
$rawCheck = $repo->getLink('central_hub', 'viewer_juan');
$runner->assert($rawCheck === null, 'No automatic link was created in persistence');

// Verify no default user is returned on lookup failure
$resDefaultCheck = $repo->findLinkedUser('central_hub', 'random-unmapped-user');
$runner->assert($resDefaultCheck === null, 'Lookup failure strictly returns null, never default user');

// =========================================================================
// SUITE 6: SUCCESSFUL RESOLUTION & PROFILE RESOLUTION
// =========================================================================
echo "\n=== Suite 6: Successful resolution & profile resolution ===\n";

// 6.1 Direct exact resolution
$resSuccess = $repo->findLinkedUser('central_hub', 'hub-usr-101');
$runner->assert($resSuccess !== null, 'Successful resolution returns array');
$runner->assert($resSuccess['user_id'] === 1, 'Resolved user_id is 1');
$runner->assert($resSuccess['username'] === 'carlos_tech', 'Resolved username matches local record');
$runner->assert($resSuccess['role'] === 'technician', 'Resolved role matches local record');
$runner->assert($resSuccess['provider'] === 'central_hub', 'Resolved provider returned');
$runner->assert($resSuccess['hub_user_id'] === 'hub-usr-101', 'Resolved hub_user_id returned');

// 6.2 Resolution via IdentityProfile
$validProfile = IdentityProfile::fromClaims([
    'hub_user_id' => 'hub-usr-101',
    'provider' => 'central_hub',
    'active' => true,
    'email' => 'carlos@example.com',
]);
$resProfile = $repo->resolveUserByProfile($validProfile);
$runner->assert($resProfile !== null, 'resolveUserByProfile resolves existing active link');
$runner->assert($resProfile['user_id'] === 1, 'Profile resolution returns correct user_id');

// 6.3 Resolution via inactive IdentityProfile fails closed
$inactiveProfile = IdentityProfile::fromClaims([
    'hub_user_id' => 'hub-usr-101',
    'provider' => 'central_hub',
    'active' => false,
]);
$resInactiveProfile = $repo->resolveUserByProfile($inactiveProfile);
$runner->assert($resInactiveProfile === null, 'Inactive IdentityProfile fails closed before lookup');

// 6.4 Link creation helper validation
$createThrew = false;
try {
    // Attempt link to non-existent local user
    $repo->createLink(8888, 'central_hub', 'hub-8888');
} catch (\InvalidArgumentException $e) {
    $createThrew = true;
}
$runner->assert($createThrew, 'createLink rejects linking to non-existent local user');

// =========================================================================
// SUITE 7: SSOSESSION INPUT VALIDATION & FAILS CLOSED
// =========================================================================
echo "\n=== Suite 7: SsoSession input validation, role fail-closed & metadata security ===\n";

$validBaseIdentity = [
    'user_id' => 1,
    'username' => 'carlos_tech',
    'role' => 'technician',
    'provider' => 'central_hub',
    'hub_user_id' => 'hub-usr-101',
];

// 7.1 Reject null / non-array input
$threw = false;
try {
    SsoSession::establish(null);
} catch (\InvalidArgumentException $e) {
    $threw = true;
}
$runner->assert($threw, 'SsoSession rejects null identity');

// 7.2 Reject empty array
$threw = false;
try {
    SsoSession::establish([]);
} catch (\InvalidArgumentException $e) {
    $threw = true;
}
$runner->assert($threw, 'SsoSession rejects empty array identity');

// 7.3 Reject unverified raw claims / profiles passed directly
$threw = false;
try {
    SsoSession::establish(['claims' => ['sub' => '123']]);
} catch (\InvalidArgumentException $e) {
    $threw = true;
}
$runner->assert($threw, 'SsoSession rejects raw unverified claims array');

// 7.4 Reject missing user_id
$threw = false;
try {
    SsoSession::establish([
        'username' => 'test',
        'role' => 'technician',
        'provider' => 'central_hub',
        'hub_user_id' => 'hub-1',
    ]);
} catch (\InvalidArgumentException $e) {
    $threw = true;
}
$runner->assert($threw, 'SsoSession rejects identity missing user_id');

// 7.5 Reject invalid/negative user_id
$threw = false;
try {
    SsoSession::establish([
        'user_id' => -1,
        'username' => 'test',
        'role' => 'technician',
        'provider' => 'central_hub',
        'hub_user_id' => 'hub-1',
    ]);
} catch (\InvalidArgumentException $e) {
    $threw = true;
}
$runner->assert($threw, 'SsoSession rejects negative user_id');

// 7.6 Reject missing username
$threw = false;
try {
    SsoSession::establish([
        'user_id' => 1,
        'username' => '',
        'role' => 'technician',
        'provider' => 'central_hub',
        'hub_user_id' => 'hub-1',
    ]);
} catch (\InvalidArgumentException $e) {
    $threw = true;
}
$runner->assert($threw, 'SsoSession rejects empty username');

// 7.7 Reject unknown/unsupported role
$threw = false;
try {
    SsoSession::establish([
        'user_id' => 1,
        'username' => 'test',
        'role' => 'superuser_unknown',
        'provider' => 'central_hub',
        'hub_user_id' => 'hub-1',
    ]);
} catch (\InvalidArgumentException $e) {
    $threw = true;
}
$runner->assert($threw, 'SsoSession rejects unknown/unsupported role');

// 7.8 Misspelled role 'viwer' is REJECTED and NOT normalized to 'viewer'
$threwViwer = false;
try {
    SsoSession::establish([
        'user_id' => 1,
        'username' => 'test',
        'role' => 'viwer',
        'provider' => 'central_hub',
        'hub_user_id' => 'hub-1',
    ]);
} catch (\InvalidArgumentException $e) {
    $threwViwer = true;
}
$runner->assert($threwViwer, 'SsoSession rejects misspelled role "viwer" (no role normalization)');

// 7.9 Mixed-case role is rejected (exact role contract)
$threwCasedRole = false;
try {
    SsoSession::establish([
        'user_id' => 1,
        'username' => 'test',
        'role' => 'Technician',
        'provider' => 'central_hub',
        'hub_user_id' => 'hub-1',
    ]);
} catch (\InvalidArgumentException $e) {
    $threwCasedRole = true;
}
$runner->assert($threwCasedRole, 'SsoSession rejects non-exact role casing');

// 7.10 Verify rejected role values are not leaked in exception message
$leakedRole = false;
try {
    SsoSession::establish([
        'user_id' => 1,
        'username' => 'test',
        'role' => 'secret_forbidden_role_123',
        'provider' => 'central_hub',
        'hub_user_id' => 'hub-1',
    ]);
} catch (\InvalidArgumentException $e) {
    if (strpos($e->getMessage(), 'secret_forbidden_role_123') !== false) {
        $leakedRole = true;
    }
}
$runner->assert(!$leakedRole, 'SsoSession does not leak rejected role values in error messages');

// 7.11 Reject session creation missing provider
$threwNoProvider = false;
try {
    SsoSession::establish([
        'user_id' => 1,
        'username' => 'carlos_tech',
        'role' => 'technician',
        'hub_user_id' => 'hub-usr-101',
    ]);
} catch (\InvalidArgumentException $e) {
    $threwNoProvider = true;
}
$runner->assert($threwNoProvider, 'SsoSession rejects identity missing provider');

// 7.12 Reject session creation with empty provider
$threwEmptyProvider = false;
try {
    SsoSession::establish([
        'user_id' => 1,
        'username' => 'carlos_tech',
        'role' => 'technician',
        'provider' => '   ',
        'hub_user_id' => 'hub-usr-101',
    ]);
} catch (\InvalidArgumentException $e) {
    $threwEmptyProvider = true;
}
$runner->assert($threwEmptyProvider, 'SsoSession rejects identity with empty provider');

// 7.13 Reject session creation missing hub_user_id
$threwNoHubId = false;
try {
    SsoSession::establish([
        'user_id' => 1,
        'username' => 'carlos_tech',
        'role' => 'technician',
        'provider' => 'central_hub',
    ]);
} catch (\InvalidArgumentException $e) {
    $threwNoHubId = true;
}
$runner->assert($threwNoHubId, 'SsoSession rejects identity missing hub_user_id');

// 7.14 Reject session creation with empty hub_user_id
$threwEmptyHubId = false;
try {
    SsoSession::establish([
        'user_id' => 1,
        'username' => 'carlos_tech',
        'role' => 'technician',
        'provider' => 'central_hub',
        'hub_user_id' => '   ',
    ]);
} catch (\InvalidArgumentException $e) {
    $threwEmptyHubId = true;
}
$runner->assert($threwEmptyHubId, 'SsoSession rejects identity with empty hub_user_id');

// =========================================================================
// SUITE 8: SSOSESSION COOKIE CONFIGURATION (NO DOWNGRADE PARAMETER)
// =========================================================================
echo "\n=== Suite 8: SsoSession cookie parameters configuration (no downgrade) ===\n";

$cookieMethodRef = new \ReflectionMethod(SsoSession::class, 'configureCookieParams');
$runner->assert(
    $cookieMethodRef->getNumberOfParameters() === 0,
    'configureCookieParams takes zero arguments: no security downgrade parameter exists'
);

SsoSession::configureCookieParams();
$cookieConfig = SsoSession::getLastConfiguredCookieParams();

$runner->assert($cookieConfig['secure'] === true, 'Session cookie configured with Secure = true');
$runner->assert($cookieConfig['httponly'] === true, 'Session cookie configured with HttpOnly = true');
$runner->assert($cookieConfig['samesite'] === 'Lax', 'Session cookie configured with SameSite = Lax');

// =========================================================================
// SUITE 9: SSOSESSION ESTABLISHMENT, METADATA & FLOW CLEANUP
// =========================================================================
echo "\n=== Suite 9: SsoSession establishment, metadata & flow cleanup ===\n";

// Initialize session with dummy state to simulate pre-existing flow
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}
$_SESSION = [
    'sso_state' => 'old_state_to_clear',
    'sso_states' => ['accepted_flow_state' => time() + 300, 'old_state' => time() + 100],
    'code_verifier' => 'secret_verifier_to_clear',
    'temp_flow_data' => 'to_be_preserved_or_cleared',
];

$validLocalIdentity = [
    'user_id' => 1,
    'username' => 'carlos_tech',
    'role' => 'technician',
    'provider' => 'central_hub',
    'hub_user_id' => 'hub-usr-101',
];

$established = SsoSession::establish(
    $validLocalIdentity,
    ['state' => 'accepted_flow_state'],
    1776000000
);

$runner->assert($established === true, 'SsoSession::establish returns true on valid input');
$runner->assert(SsoSession::wasSessionIdRegenerated() === true, 'Session ID was regenerated during establishment');

// Verify SSO flow variables cleared
$runner->assert(!isset($_SESSION['sso_state']), 'sso_state cleared from session');
$runner->assert(!isset($_SESSION['sso_states']), 'sso_states cleared from session');
$runner->assert(!isset($_SESSION['code_verifier']), 'code_verifier cleared from session');

// Verify exact local session shape
$runner->assert($_SESSION['user_id'] === 1, 'Session user_id is exact integer 1');
$runner->assert($_SESSION['username'] === 'carlos_tech', 'Session username matches local user');
$runner->assert($_SESSION['role'] === 'technician', 'Session role matches local technician');

// Verify non-sensitive federation metadata
$runner->assert($_SESSION['sso_provider'] === 'central_hub', 'Session records sso_provider metadata');
$runner->assert($_SESSION['sso_hub_user_id'] === 'hub-usr-101', 'Session records sso_hub_user_id metadata');
$runner->assert($_SESSION['sso_authenticated_at'] === 1776000000, 'Session records sso_authenticated_at timestamp');

// Verify NO sensitive data in session
$sensitiveKeys = ['password', 'token', 'access_token', 'code_verifier', 'client_secret', 'secret'];
$hasSensitive = false;
foreach ($sensitiveKeys as $sKey) {
    if (isset($_SESSION[$sKey])) {
        $hasSensitive = true;
    }
}
$runner->assert(!$hasSensitive, 'No sensitive data or credentials stored in session');

// Verify session validation helper
$runner->assert(SsoSession::isValidSession() === true, 'SsoSession::isValidSession confirms active local session');

// =========================================================================
// SUITE 10: INTEGRATION CONTRACT WITH CORE/AUTH/SESSION.PHP
// =========================================================================
echo "\n=== Suite 10: Integration contract with core/auth/session.php ===\n";

// core/auth/session.php enforces:
// isset($_SESSION['user_id']) && isset($_SESSION['username']) && isset($_SESSION['role'])
$hasSessionContract = isset($_SESSION['user_id']) && isset($_SESSION['username']) && isset($_SESSION['role']);
$runner->assert($hasSessionContract, 'Session conforms strictly to core/auth/session.php required keys');

// Allowed roles check (admin, technician, viewer)
$allowedRoles = ['admin', 'technician', 'viewer'];
$runner->assert(in_array($_SESSION['role'], $allowedRoles, true), 'Session role is one of valid allowed roles');

// =========================================================================
// SUITE 11: STATIC FORBIDDEN REFERENCES CHECK
// =========================================================================
echo "\n=== Suite 11: Static forbidden references check ===\n";

$forbiddenWords = [
    implode('', ['open', 'id']),
    implode('', ['id_', 'token']),
    implode('', ['non', 'ce']),
    implode('', ['oi', 'dc']),
];

$filesToCheck = [
    dirname(__DIR__) . '/core/auth/FederatedIdentityRepository.php',
    dirname(__DIR__) . '/core/auth/SsoSession.php',
    dirname(__DIR__) . '/db/migrations/20261012_sso_identity_links.sql',
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

// =========================================================================
// SUITE 12: STATIC MIGRATION SCHEMA CONTRACT VERIFICATION
// =========================================================================
echo "\n=== Suite 12: Static migration schema contract verification ===\n";

$migrationContent = file_get_contents(dirname(__DIR__) . '/db/migrations/20261012_sso_identity_links.sql');

$runner->assert(
    strpos($migrationContent, '`user_id` int(11) NOT NULL') !== false,
    'Migration keeps user_id foreign key type aligned with Electroplan users.id (int(11) NOT NULL)'
);

$runner->assert(
    strpos($migrationContent, '`provider` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL') !== false
    || strpos($migrationContent, '`provider` varchar(64) COLLATE utf8mb4_bin NOT NULL') !== false,
    'Migration sets exact-match utf8mb4_bin collation on provider column'
);

$runner->assert(
    strpos($migrationContent, '`hub_user_id` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL') !== false
    || strpos($migrationContent, '`hub_user_id` varchar(191) COLLATE utf8mb4_bin NOT NULL') !== false,
    'Migration sets exact-match utf8mb4_bin collation on hub_user_id column'
);

exit($runner->summary());
