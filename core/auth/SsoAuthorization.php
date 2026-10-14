<?php
declare(strict_types=1);

namespace Electroplan\Auth;

use InvalidArgumentException;

/**
 * Electroplan SSO Local Authorization Policy Boundary.
 *
 * Enforces strict fail-closed authorization rules:
 * 1. Accepts ONLY an active IdentityProfile with valid provider and hub_user_id.
 * 2. Resolves local identity exclusively via FederatedIdentityRepository.
 * 3. Requires an explicit, active federated link (active = 1) and an existing local user.
 * 4. Preserves the local database role exactly and accepts only the existing
 *    Electroplan roles: admin, technician, viewer.
 * 5. Strictly isolates central userinfo roles: central roles NEVER grant, elevate,
 *    or modify local access or permissions.
 * 6. Fails closed on absent, inactive, unmapped, or orphan identities.
 * 7. Rejects unknown, missing, empty, or misspelled roles (e.g. 'viwer');
 *    never normalizes invalid roles and never assigns default or fallback roles.
 * 8. Returns sanitized, generic error messages without leaking rejected roles,
 *    internal database details, tokens, or credentials.
 */
class SsoAuthorization
{
    public const ALLOWED_ROLES = [
        'admin',
        'technician',
        'viewer',
    ];

    public const ERROR_GENERIC = 'Authorization failed.';
    public const ERROR_INVALID_PROFILE = 'Authorization failed: invalid or missing identity profile.';
    public const ERROR_INACTIVE_PROFILE = 'Authorization failed: central user account is inactive.';
    public const ERROR_UNMAPPED_IDENTITY = 'Authorization failed: no active linked local account found.';
    public const ERROR_INVALID_ROLE = 'Authorization failed: local user role is invalid or unsupported.';

    private FederatedIdentityRepository $repository;

    /**
     * @param FederatedIdentityRepository $repository
     */
    public function __construct(FederatedIdentityRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Returns the underlying federated identity repository.
     *
     * @return FederatedIdentityRepository
     */
    public function getRepository(): FederatedIdentityRepository
    {
        return $this->repository;
    }

    /**
     * Authorizes an IdentityProfile against local persistence policy.
     *
     * @param mixed $profile
     * @return array{success: bool, identity: ?array<string, mixed>, user: ?array<string, mixed>, error: ?string}
     */
    public function authorize($profile): array
    {
        // 1. Require a valid IdentityProfile instance
        if (!($profile instanceof IdentityProfile)) {
            return $this->failure(self::ERROR_INVALID_PROFILE);
        }

        // 2. Central identity must be explicitly active (fail closed)
        if (!$profile->isActive()) {
            return $this->failure(self::ERROR_INACTIVE_PROFILE);
        }

        $hubUserId = $profile->getId();
        $provider = $profile->getProvider();

        // 3. Provider and hub_user_id must be non-empty strings
        if (trim($hubUserId) === '' || $provider === null || trim($provider) === '') {
            return $this->failure(self::ERROR_INVALID_PROFILE);
        }

        // 4. Resolve local user through the accepted federated identity repository
        $linkedUser = $this->repository->findLinkedUser($provider, $hubUserId);
        if ($linkedUser === null) {
            return $this->failure(self::ERROR_UNMAPPED_IDENTITY);
        }

        // 5. Validate local user record integrity
        $userIdRaw = $linkedUser['user_id'] ?? $linkedUser['id'] ?? null;
        if (!is_int($userIdRaw) && (!is_string($userIdRaw) || !is_numeric($userIdRaw))) {
            return $this->failure(self::ERROR_UNMAPPED_IDENTITY);
        }
        $userId = (int)$userIdRaw;
        if ($userId <= 0) {
            return $this->failure(self::ERROR_UNMAPPED_IDENTITY);
        }

        $usernameRaw = $linkedUser['username'] ?? null;
        if (!is_string($usernameRaw) || trim($usernameRaw) === '') {
            return $this->failure(self::ERROR_UNMAPPED_IDENTITY);
        }
        $username = trim($usernameRaw);

        // 6. Validate local database role
        $roleRaw = $linkedUser['role'] ?? null;
        if (!is_string($roleRaw) || trim($roleRaw) === '') {
            return $this->failure(self::ERROR_INVALID_ROLE);
        }
        $localRole = trim($roleRaw);

        // Must strictly match accepted Electroplan roles: admin, technician, viewer.
        // No normalization of misspelled roles (e.g. 'viwer'), no default or fallback assignment.
        if (!in_array($localRole, self::ALLOWED_ROLES, true)) {
            return $this->failure(self::ERROR_INVALID_ROLE);
        }

        // 7. Assemble authorized identity preserving the local database role exactly.
        // Central roles from IdentityProfile->getRoles() or claims are NEVER trusted
        // and NEVER used to elevate or grant local permissions.
        $authorizedIdentity = [
            'user_id' => $userId,
            'id' => $userId,
            'username' => $username,
            'role' => $localRole,
            'provider' => (string)($linkedUser['provider'] ?? $provider),
            'hub_user_id' => (string)($linkedUser['hub_user_id'] ?? $hubUserId),
            'link_id' => (int)($linkedUser['link_id'] ?? 0),
        ];

        return [
            'success' => true,
            'identity' => $authorizedIdentity,
            'user' => $authorizedIdentity,
            'error' => null,
        ];
    }

    /**
     * Resolves and authorizes an IdentityProfile, returning the authorized identity array or null.
     *
     * @param mixed $profile
     * @return array<string, mixed>|null
     */
    public function authorizeIdentity($profile): ?array
    {
        $result = $this->authorize($profile);
        return $result['success'] ? $result['identity'] : null;
    }

    /**
     * Alias for authorizeIdentity.
     *
     * @param mixed $profile
     * @return array<string, mixed>|null
     */
    public function resolveAndAuthorize($profile): ?array
    {
        return $this->authorizeIdentity($profile);
    }

    /**
     * Checks if an IdentityProfile is authorized under local policy.
     *
     * @param mixed $profile
     * @return bool
     */
    public function isAuthorized($profile): bool
    {
        $result = $this->authorize($profile);
        return $result['success'] === true;
    }

    /**
     * Static helper to authorize an IdentityProfile using a repository instance.
     *
     * @param mixed $profile
     * @param FederatedIdentityRepository $repository
     * @return array{success: bool, identity: ?array<string, mixed>, user: ?array<string, mixed>, error: ?string}
     */
    public static function authorizeProfile($profile, FederatedIdentityRepository $repository): array
    {
        return (new self($repository))->authorize($profile);
    }

    /**
     * Generates a sanitized failure outcome.
     *
     * @param string $message
     * @return array{success: false, identity: null, user: null, error: string}
     */
    private function failure(string $message): array
    {
        return [
            'success' => false,
            'identity' => null,
            'user' => null,
            'error' => $message,
        ];
    }
}

if (!class_exists('SsoAuthorization', false)) {
    class_alias(\Electroplan\Auth\SsoAuthorization::class, 'SsoAuthorization');
}
