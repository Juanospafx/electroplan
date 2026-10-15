<?php
declare(strict_types=1);

namespace Electroplan\Auth;

use PDO;
use InvalidArgumentException;

/**
 * Persistence repository for federated identity links in Electroplan.
 *
 * Enforces strict boundary rules:
 * 1. Resolves an existing local user ONLY through an exact match of (provider, hub_user_id).
 * 2. Matches ONLY active links (active = 1).
 * 3. Requires that the linked local user exists in the `users` table and is active (active = 1).
 * 4. Never auto-links or guesses links by username or email.
 * 5. Assigns no default user and no default role (fails closed).
 * 6. Uses prepared statements exclusively.
 * 7. Fails closed on missing or invalid active state without revealing SQL, DSN, or credentials.
 */
class FederatedIdentityRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }


    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Resolves an existing active local user from an exact active federated identity link.
     *
     * @param string $provider The identity provider identifier (e.g. 'central_hub')
     * @param string $hubUserId The unique identifier from the provider
     * @return array{user_id: int, id: int, username: string, role: string, active: bool, user_active: bool, provider: string, hub_user_id: string, link_id: int}|null
     */
    public function findLinkedUser(string $provider, string $hubUserId): ?array
    {
        $provider = trim($provider);
        $hubUserId = trim($hubUserId);

        // Fail closed on empty values
        if ($provider === '' || $hubUserId === '') {
            return null;
        }

        // Exact match on active link with inner join to existing active local user.
        // Prepared statements exclusively.
        // Never queries by email or username, never auto-links.
        $sql = 'SELECT u.id AS user_id, u.username, u.role, u.active AS user_active, l.id AS link_id, l.provider, l.hub_user_id, l.active '
             . 'FROM sso_identity_links l '
             . 'INNER JOIN users u ON u.id = l.user_id '
             . 'WHERE l.provider = :provider '
             . '  AND l.hub_user_id = :hub_user_id '
             . '  AND l.active = 1 '
             . '  AND u.active = 1 '
             . 'LIMIT 1';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':provider' => $provider,
                ':hub_user_id' => $hubUserId,
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }
        } catch (\Throwable $e) {
            // Fail closed on database error without revealing SQL, DSN or credentials
            return null;
        }

        // Verify provider plus hub_user_id with exact case-sensitive equality
        // even under a case-insensitive database collation
        if (!isset($row['provider'], $row['hub_user_id'])) {
            return null;
        }
        if ((string)$row['provider'] !== $provider || (string)$row['hub_user_id'] !== $hubUserId) {
            return null;
        }

        // Validate local user record integrity
        if (!isset($row['user_id'], $row['username'], $row['role'])) {
            return null;
        }

        $userId = (int)$row['user_id'];
        $username = trim((string)$row['username']);
        $role = trim((string)$row['role']);

        if ($userId <= 0 || $username === '' || $role === '') {
            return null;
        }

        // Validate local user active state (must be explicitly active: 1, '1', or true)
        // Missing, 0, false, null, or invalid values fail closed.
        if (!array_key_exists('user_active', $row)) {
            return null;
        }
        $rawUserActive = $row['user_active'];
        if ($rawUserActive !== 1 && $rawUserActive !== '1' && $rawUserActive !== true) {
            return null;
        }

        return [
            'user_id' => $userId,
            'id' => $userId,
            'username' => $username,
            'role' => $role,
            'active' => true,
            'user_active' => true,
            'provider' => (string)$row['provider'],
            'hub_user_id' => (string)$row['hub_user_id'],
            'link_id' => (int)$row['link_id'],
        ];
    }

    /**
     * Resolves local user using an IdentityProfile instance.
     *
     * @param IdentityProfile $profile
     * @return array{user_id: int, id: int, username: string, role: string, provider: string, hub_user_id: string, link_id: int}|null
     */
    public function resolveUserByProfile(IdentityProfile $profile): ?array
    {
        if (!$profile->isActive()) {
            return null;
        }

        $provider = $profile->getProvider();
        $hubUserId = $profile->getId();

        if ($provider === null || trim($provider) === '' || trim($hubUserId) === '') {
            return null;
        }

        return $this->findLinkedUser($provider, $hubUserId);
    }

    /**
     * Alias for findLinkedUser.
     *
     * @param string $provider
     * @param string $hubUserId
     * @return array{user_id: int, id: int, username: string, role: string, provider: string, hub_user_id: string, link_id: int}|null
     */
    public function resolveUser(string $provider, string $hubUserId): ?array
    {
        return $this->findLinkedUser($provider, $hubUserId);
    }

    /**
     * Alias for findLinkedUser.
     *
     * @param string $provider
     * @param string $hubUserId
     * @return array{user_id: int, id: int, username: string, role: string, provider: string, hub_user_id: string, link_id: int}|null
     */
    public function findByProviderAndHubUserId(string $provider, string $hubUserId): ?array
    {
        return $this->findLinkedUser($provider, $hubUserId);
    }

    /**
     * Alias for resolveUserByProfile.
     *
     * @param IdentityProfile $profile
     * @return array{user_id: int, id: int, username: string, role: string, provider: string, hub_user_id: string, link_id: int}|null
     */
    public function findUserByProfile(IdentityProfile $profile): ?array
    {
        return $this->resolveUserByProfile($profile);
    }

    /**
     * Helper to create an explicit link (used for seeding / management).
     * Does not auto-create users. Fails if user does not exist.
     *
     * @param int $userId Local user ID
     * @param string $provider Provider key
     * @param string $hubUserId External provider user ID
     * @param bool $active Active status flag
     * @return bool
     * @throws InvalidArgumentException
     */
    public function createLink(int $userId, string $provider, string $hubUserId, bool $active = true): bool
    {
        $provider = trim($provider);
        $hubUserId = trim($hubUserId);

        if ($userId <= 0 || $provider === '' || $hubUserId === '') {
            throw new InvalidArgumentException('Valid userId, provider and hubUserId are required.');
        }

        try {
            // Verify local user exists
            $userStmt = $this->pdo->prepare('SELECT id FROM users WHERE id = :user_id LIMIT 1');
            $userStmt->execute([':user_id' => $userId]);
            if (!$userStmt->fetch()) {
                throw new InvalidArgumentException('Cannot link to non-existent local user ID: ' . $userId);
            }

            $sql = 'INSERT INTO sso_identity_links (user_id, provider, hub_user_id, active) '
                 . 'VALUES (:user_id, :provider, :hub_user_id, :active)';

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':user_id' => $userId,
                ':provider' => $provider,
                ':hub_user_id' => $hubUserId,
                ':active' => $active ? 1 : 0,
            ]);
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // Fail closed on database error without revealing SQL, DSN or credentials
            return false;
        }
    }

    /**
     * Helper to retrieve raw link record for audit / verification.
     *
     * @param string $provider
     * @param string $hubUserId
     * @return array<string, mixed>|null
     */
    public function getLink(string $provider, string $hubUserId): ?array
    {
        $provider = trim($provider);
        $hubUserId = trim($hubUserId);

        if ($provider === '' || $hubUserId === '') {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, user_id, provider, hub_user_id, active, created_at, updated_at '
                . 'FROM sso_identity_links '
                . 'WHERE provider = :provider AND hub_user_id = :hub_user_id '
                . 'LIMIT 1'
            );
            $stmt->execute([
                ':provider' => $provider,
                ':hub_user_id' => $hubUserId,
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }

            // Verify provider plus hub_user_id with exact case-sensitive equality
            if (!isset($row['provider'], $row['hub_user_id'])) {
                return null;
            }
            if ((string)$row['provider'] !== $provider || (string)$row['hub_user_id'] !== $hubUserId) {
                return null;
            }

            return $row;
        } catch (\Throwable $e) {
            // Fail closed on database error without revealing SQL, DSN or credentials
            return null;
        }
    }
}

if (!class_exists('FederatedIdentityRepository', false)) {
    class_alias(\Electroplan\Auth\FederatedIdentityRepository::class, 'FederatedIdentityRepository');
}
