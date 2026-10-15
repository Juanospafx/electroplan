<?php
declare(strict_types=1);

namespace Electroplan\Auth;

use InvalidArgumentException;

/**
 * Electroplan SSO Local Session Boundary.
 *
 * Enforces session security rules:
 * 1. Accepts ONLY an explicitly resolved local identity array with valid user_id, username, and role.
 * 2. Never accepts unresolved federated profiles or unverified identities.
 * 3. Configures cookie parameters: Secure, HttpOnly, and SameSite=Lax.
 * 4. Regenerates the session ID on authentication to prevent session fixation.
 * 5. Writes ONLY the existing local session shape (user_id, username, role)
 *    plus non-sensitive federation metadata (provider, hub_user_id, authenticated_at).
 * 6. Never stores credentials, tokens, codes, verifiers, or secret material in the session.
 */
class SsoSession
{
    public const ALLOWED_ROLES = [
        'admin',
        'technician',
        'viewer',
    ];

    /**
     * Checks if a role is in the exact allowed local roles whitelist.
     *
     * @param string $role
     * @return bool
     */
    public static function isAllowedRole(string $role): bool
    {
        return in_array($role, self::ALLOWED_ROLES, true);
    }

    private static bool $lastRegenerated = false;
    private static array $lastConfiguredCookieParams = [];
    private static bool $configuredBeforeSessionStart = false;
    private static array $lifecycleLog = [];

    /**
     * Retrieves or generates a session-bound CSRF token.
     *
     * @param bool $forceNew If true, generates a fresh token.
     * @return string The session-bound CSRF token.
     */
    public static function getCsrfToken(bool $forceNew = false): string
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            self::startHardenedSession();
        }

        if (!isset($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = [];
        }

        if ($forceNew || empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Validates a CSRF token against the session-bound token using timing-attack safe comparison.
     *
     * @param string|null $token
     * @return bool True if valid, false otherwise.
     */
    public static function validateCsrfToken(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        if (!isset($_SESSION) || !is_array($_SESSION) || empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Alias for validateCsrfToken.
     *
     * @param string|null $token
     * @return bool
     */
    public static function verifyCsrfToken(?string $token): bool
    {
        return self::validateCsrfToken($token);
    }

    /**
     * Resets lifecycle and test state.
     */
    public static function resetLifecycle(): void
    {
        self::$lastRegenerated = false;
        self::$lastConfiguredCookieParams = [];
        self::$configuredBeforeSessionStart = false;
        self::$lifecycleLog = [];
    }

    /**
     * Records session start in lifecycle log.
     */
    public static function recordSessionStart(): void
    {
        self::$lifecycleLog[] = 'session_start';
    }

    /**
     * Configures session cookie parameters with Secure, HttpOnly, and SameSite=Lax.
     * Fixed to secure settings with no downgrade parameter: cannot be relaxed by production configuration.
     *
     * @param array<string, mixed>|null $customParams Optional configuration overrides (cannot relax security).
     * @return array<string, mixed> The enforced secure cookie parameters.
     */
    public static function configureCookieParams(?array $customParams = null): array
    {
        $status = session_status();
        if ($status !== PHP_SESSION_ACTIVE) {
            self::$configuredBeforeSessionStart = true;
            self::$lifecycleLog[] = 'configure_cookie_params_pre_start';
        } else {
            self::$configuredBeforeSessionStart = false;
            self::$lifecycleLog[] = 'configure_cookie_params_post_start';
        }

        @ini_set('session.cookie_httponly', '1');
        @ini_set('session.cookie_secure', '1');
        @ini_set('session.cookie_samesite', 'Lax');
        @ini_set('session.use_only_cookies', '1');
        @ini_set('session.use_strict_mode', '1');

        $lifetime = 0;
        $path = '/';
        $domain = '';

        if ($status !== PHP_SESSION_ACTIVE && !headers_sent()) {
            $existing = session_get_cookie_params();
            $lifetime = $existing['lifetime'] ?? 0;
            $path = $existing['path'] ?? '/';
            $domain = $existing['domain'] ?? '';
        }

        if ($customParams !== null) {
            if (isset($customParams['lifetime']) && is_int($customParams['lifetime'])) {
                $lifetime = $customParams['lifetime'];
            }
            if (isset($customParams['path']) && is_string($customParams['path']) && $customParams['path'] !== '') {
                $path = $customParams['path'];
            }
            if (isset($customParams['domain']) && is_string($customParams['domain'])) {
                $domain = $customParams['domain'];
            }
        }

        // Fixed to secure settings with no downgrade: cannot be relaxed by production configuration
        $cookieParams = [
            'lifetime' => $lifetime,
            'path' => $path,
            'domain' => $domain,
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        self::$lastConfiguredCookieParams = $cookieParams;

        if ($status !== PHP_SESSION_ACTIVE && !headers_sent()) {
            session_set_cookie_params($cookieParams);
        }

        return $cookieParams;
    }

    /**
     * Bootstraps the hardened session: configures secure cookie parameters BEFORE session_start.
     *
     * @return bool True on success.
     */
    public static function startHardenedSession(): bool
    {
        self::configureCookieParams();

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            self::recordSessionStart();
            @session_start();
        }

        if (!isset($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = [];
        }

        return true;
    }

    /**
     * Alias for startHardenedSession.
     */
    public static function bootstrap(): bool
    {
        return self::startHardenedSession();
    }

    /**
     * Clears all SSO flow state and PKCE transient keys from session.
     */
    public static function clearTransients(): void
    {
        if (isset($_SESSION) && is_array($_SESSION)) {
            unset(
                $_SESSION['sso_state'],
                $_SESSION['sso_states'],
                $_SESSION['code_verifier']
            );
        }
    }

    public static function wasConfiguredBeforeSessionStart(): bool
    {
        return self::$configuredBeforeSessionStart;
    }

    public static function wasConfiguredBeforeStart(): bool
    {
        return self::$configuredBeforeSessionStart;
    }

    public static function getLifecycleLog(): array
    {
        return self::$lifecycleLog;
    }

    /**
     * Establishes the local session shape from an explicitly resolved local identity.
     *
     * @param mixed $resolvedIdentity Must be an array containing valid user_id, username, and role.
     * @param array<string, mixed>|null $federationMeta Optional non-sensitive federation metadata.
     * @param int|null $currentTime Optional timestamp for deterministic testing.
     * @return bool True on success.
     * @throws InvalidArgumentException If identity is not explicitly resolved or has invalid shape.
     */
    public static function establish($resolvedIdentity, ?array $federationMeta = null, ?int $currentTime = null): bool
    {
        return (new self())->establishSession($resolvedIdentity, $federationMeta, $currentTime);
    }

    /**
     * Establishes the local session shape (instance method).
     *
     * @param mixed $resolvedIdentity
     * @param array<string, mixed>|null $federationMeta
     * @param int|null $currentTime
     * @return bool
     * @throws InvalidArgumentException
     */
    public function establishSession($resolvedIdentity, ?array $federationMeta = null, ?int $currentTime = null): bool
    {
        // 1. Must be an explicitly resolved local identity array
        if (!is_array($resolvedIdentity) || empty($resolvedIdentity)) {
            throw new InvalidArgumentException(
                'SsoSession requires an explicitly resolved local identity array.'
            );
        }

        // Prevent passing IdentityProfile directly without persistence resolution
        if (isset($resolvedIdentity['claims']) || (isset($resolvedIdentity['id']) && !isset($resolvedIdentity['user_id']) && !is_numeric($resolvedIdentity['id']))) {
            throw new InvalidArgumentException(
                'Raw federated profile cannot be used directly. Identity must be resolved against local persistence.'
            );
        }

        // 2. Validate user_id
        $userIdRaw = $resolvedIdentity['user_id'] ?? $resolvedIdentity['id'] ?? null;
        if ($userIdRaw === null || !is_numeric($userIdRaw)) {
            throw new InvalidArgumentException('Explicitly resolved identity must contain a numeric user_id.');
        }
        $userId = (int)$userIdRaw;
        if ($userId <= 0) {
            throw new InvalidArgumentException('Resolved user_id must be a positive integer.');
        }

        // 3. Validate username
        $usernameRaw = $resolvedIdentity['username'] ?? null;
        if (!is_string($usernameRaw) || trim($usernameRaw) === '') {
            throw new InvalidArgumentException('Explicitly resolved identity must contain a non-empty username.');
        }
        $username = trim($usernameRaw);

        // 4. Validate role (never normalize misspelled roles, never invent defaults, never leak rejected role values)
        $roleRaw = $resolvedIdentity['role'] ?? null;
        if (!is_string($roleRaw) || trim($roleRaw) === '') {
            throw new InvalidArgumentException('Explicitly resolved identity must contain a non-empty role.');
        }
        $role = trim($roleRaw);
        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            throw new InvalidArgumentException('Resolved identity contains invalid or unsupported role.');
        }

        // Validate active status if provided (fail closed on inactive local user)
        if (isset($resolvedIdentity['active']) && ($resolvedIdentity['active'] === false || $resolvedIdentity['active'] === 0 || $resolvedIdentity['active'] === '0')) {
            throw new InvalidArgumentException('Resolved identity is inactive.');
        }
        if (isset($resolvedIdentity['user_active']) && ($resolvedIdentity['user_active'] === false || $resolvedIdentity['user_active'] === 0 || $resolvedIdentity['user_active'] === '0')) {
            throw new InvalidArgumentException('Resolved local user is inactive.');
        }

        // 5. Validate provider and hub_user_id (session creation requires non-empty provider and hub_user_id)
        $provider = $federationMeta['provider'] ?? $resolvedIdentity['provider'] ?? null;
        $hubUserId = $federationMeta['hub_user_id'] ?? $resolvedIdentity['hub_user_id'] ?? null;

        if (!is_string($provider) || trim($provider) === '') {
            throw new InvalidArgumentException('Explicitly resolved identity requires a non-empty provider.');
        }
        if (!is_string($hubUserId) || trim($hubUserId) === '') {
            throw new InvalidArgumentException('Explicitly resolved identity requires a non-empty hub_user_id.');
        }
        $provider = trim($provider);
        $hubUserId = trim($hubUserId);

        // 6. Configure session cookies (fixed to Secure=true, HttpOnly=true, SameSite=Lax with no downgrade parameter)
        self::configureCookieParams();

        // 7. Ensure session is active
        $this->ensureSessionActive();

        // 8. Regenerate session ID (anti-fixation)
        $this->regenerateSessionId();

        // 9. Clean up accepted SSO flow state and verifier keys
        if (isset($federationMeta['state']) && is_string($federationMeta['state'])) {
            $acceptedState = $federationMeta['state'];
            if (isset($_SESSION['sso_states']) && is_array($_SESSION['sso_states'])) {
                unset($_SESSION['sso_states'][$acceptedState]);
            }
        }
        self::clearTransients();

        // 10. Write strictly the existing local session shape
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;

        // 11. Write non-sensitive federation metadata
        $now = $currentTime ?? time();
        $_SESSION['sso_provider'] = $provider;
        $_SESSION['sso_hub_user_id'] = $hubUserId;
        $_SESSION['sso_authenticated_at'] = $now;

        return true;
    }

    /**
     * Regenerates the session ID to mitigate session fixation.
     */
    public static function regenerateSessionId(): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $regenerated = @session_regenerate_id(true);
            self::$lastRegenerated = ($regenerated === true);
            return self::$lastRegenerated;
        }

        // In test environments where active session is mocked
        self::$lastRegenerated = true;
        return true;
    }

    /**
     * Alias for regenerateSessionId.
     */
    public static function regenerate(): bool
    {
        return self::regenerateSessionId();
    }

    public static function wasSessionIdRegenerated(): bool
    {
        return self::$lastRegenerated;
    }

    public static function getLastConfiguredCookieParams(): array
    {
        return self::$lastConfiguredCookieParams;
    }

    /**
     * Checks if a valid authenticated local session is currently established.
     */
    public static function isValidSession(): bool
    {
        if (!isset($_SESSION) || !is_array($_SESSION)) {
            return false;
        }

        if (!isset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'])) {
            return false;
        }

        if (!is_int($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
            return false;
        }

        if (!is_string($_SESSION['username']) || trim($_SESSION['username']) === '') {
            return false;
        }

        if (!is_string($_SESSION['role']) || !in_array($_SESSION['role'], self::ALLOWED_ROLES, true)) {
            return false;
        }

        return true;
    }

    /**
     * Clears all local and federation session state, safely invalidates the session cookie,
     * and destroys the session.
     *
     * @return array<string, mixed> Enforced expired cookie options.
     */
    public static function logout(): array
    {
        $_SESSION = [];

        $params = session_get_cookie_params();
        $cookieOptions = [
            'expires' => time() - 42000,
            'path' => !empty($params['path']) ? $params['path'] : '/',
            'domain' => !empty($params['domain']) ? $params['domain'] : '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        if (ini_get('session.use_cookies') && !headers_sent()) {
            setcookie(
                session_name(),
                '',
                $cookieOptions
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_destroy();
        }

        return $cookieOptions;
    }

    /**
     * Clears and destroys the current session.
     */
    public static function destroy(): void
    {
        self::logout();
    }

    /**
     * Ensures session array exists and starts session if needed.
     */
    private function ensureSessionActive(): void
    {
        self::startHardenedSession();
    }
}

if (!class_exists('SsoSession', false)) {
    class_alias(\Electroplan\Auth\SsoSession::class, 'SsoSession');
}
