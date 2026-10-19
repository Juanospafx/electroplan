<?php
/**
 * Electroplan SSO Flow Component
 *
 * Implements state management, PKCE S256 generation/verification,
 * and callback parameter validation for Electroplan OAuth SSO integration.
 *
 * Strict isolation: No network/HTTP calls, no direct SQL/DB persistence,
 * no local user session creation, and no sensitive credential logging.
 */

declare(strict_types=1);

namespace Electroplan\Auth;

class SsoFlow
{
    /**
     * Contractual claim and parameter definitions for Electroplan Central Hub SSO.
     */
    public const CLAIM_HUB_USER_ID = 'hub_user_id';
    public const CLAIM_PROVIDER = 'provider';
    public const CLAIM_ACTIVE = 'active';
    public const CLAIM_ROLES = 'roles';

    /**
     * Contractual error constants representing central or authorization rejections.
     */
    public const ERROR_CENTRAL_DISABLED = 'central_disabled';
    public const ERROR_APP_UNAUTHORIZED = 'app_unauthorized';

    /**
     * Allowed PKCE code challenge method.
     */
    public const PKCE_METHOD_S256 = 'S256';

    /**
     * Generates a cryptographically secure, high-entropy OAuth state parameter.
     * The state is stored in session with a limited TTL for single-use consumption.
     *
     * @param int $ttl Lifetime in seconds (default 300s = 5 minutes)
     * @param int|null $currentTime Optional current timestamp for deterministic testing
     * @return string High-entropy URL-safe state string (>= 32 chars)
     */
    public function generateState(int $ttl = 300, ?int $currentTime = null): string
    {
        $this->ensureSession();

        $currentTime = $currentTime ?? time();
        $expiresAt = $currentTime + $ttl;

        // 32 cryptographically secure random bytes = 64 hex characters (URL-safe, > 32 chars)
        $state = bin2hex(random_bytes(32));

        if (!isset($_SESSION['sso_states']) || !is_array($_SESSION['sso_states'])) {
            $_SESSION['sso_states'] = [];
        }

        $_SESSION['sso_states'][$state] = $expiresAt;
        $_SESSION['sso_state'] = $state;

        return $state;
    }

    /**
     * Validates an incoming state parameter against the session using constant-time comparison.
     * Consumes the state immediately upon validation (anti-replay single-use).
     * Eliminates state and verifier if validation fails.
     *
     * @param string $state The state parameter to validate
     * @param int|null $currentTime Optional current timestamp for deterministic testing
     * @return bool True if the state was valid and within TTL, false otherwise
     */
    public function validateState(string $state, ?int $currentTime = null): bool
    {
        $this->ensureSession();

        if ($state === '') {
            return false;
        }

        $currentTime = $currentTime ?? time();
        $matched = false;
        $valid = false;

        if (isset($_SESSION['sso_states']) && is_array($_SESSION['sso_states'])) {
            foreach ($_SESSION['sso_states'] as $storedState => $expiresAt) {
                if (hash_equals((string)$storedState, $state)) {
                    $matched = true;
                    // Anti-replay: remove matching state immediately
                    unset($_SESSION['sso_states'][$storedState]);

                    if ($currentTime <= $expiresAt) {
                        $valid = true;
                    }
                    break;
                }
            }

            if (empty($_SESSION['sso_states'])) {
                unset($_SESSION['sso_states']);
            }
        }

        // Clean up scalar sso_state pointer if matching
        if (isset($_SESSION['sso_state']) && is_string($_SESSION['sso_state'])) {
            if (hash_equals($_SESSION['sso_state'], $state)) {
                $matched = true;
                unset($_SESSION['sso_state']);
            }
        }

        // If validation fails (either mismatched, expired, or tampered), eliminate verifier
        if (!$valid) {
            unset($_SESSION['code_verifier']);
        }

        return $valid;
    }

    /**
     * Generates a high-entropy PKCE code_verifier and calculates the S256 code_challenge.
     * Only the S256 method is allowed (plain is strictly rejected).
     * The code_verifier is stored strictly in session and never returned in public parameters.
     *
     * @param string $method PKCE code challenge method, must be strictly 'S256'
     * @return array{code_challenge: string, code_challenge_method: string} Public authorization params
     * @throws \InvalidArgumentException if method is not 'S256'
     */
    public function generatePkce(string $method = self::PKCE_METHOD_S256): array
    {
        if ($method !== self::PKCE_METHOD_S256) {
            throw new \InvalidArgumentException("PKCE method must be strictly S256. Method '{$method}' is not allowed.");
        }

        $this->ensureSession();

        // 48 random bytes encoded in base64url = 64 characters (RFC 7636 requires 43-128 chars)
        $rawBytes = random_bytes(48);
        $verifier = rtrim(strtr(base64_encode($rawBytes), '+/', '-_'), '=');

        // Store verifier exclusively in session
        $_SESSION['code_verifier'] = $verifier;

        // Compute BASE64URL(SHA256(verifier))
        $challenge = $this->computeCodeChallenge($verifier, self::PKCE_METHOD_S256);

        return [
            'code_challenge' => $challenge,
            'code_challenge_method' => self::PKCE_METHOD_S256,
        ];
    }

    /**
     * Computes the code_challenge from a code_verifier using BASE64URL(SHA256(verifier)).
     * Rejects plain or any non-S256 method.
     *
     * @param string $verifier The code_verifier string
     * @param string $method Must be strictly 'S256'
     * @return string URL-safe base64-encoded SHA256 digest without padding
     * @throws \InvalidArgumentException if method is not 'S256' or verifier is empty
     */
    public function computeCodeChallenge(string $verifier, string $method = self::PKCE_METHOD_S256): string
    {
        if ($method !== self::PKCE_METHOD_S256) {
            throw new \InvalidArgumentException("Code challenge method must be strictly S256. Method '{$method}' is not allowed.");
        }

        if ($verifier === '') {
            throw new \InvalidArgumentException('Code verifier cannot be empty.');
        }

        $hash = hash('sha256', $verifier, true);
        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }

    /**
     * Consumes and removes the code_verifier stored in session (single-use).
     *
     * @return string|null The verifier string, or null if none was stored or already consumed
     */
    public function consumeCodeVerifier(): ?string
    {
        $this->ensureSession();

        if (!isset($_SESSION['code_verifier']) || !is_string($_SESSION['code_verifier']) || $_SESSION['code_verifier'] === '') {
            return null;
        }

        $verifier = $_SESSION['code_verifier'];
        unset($_SESSION['code_verifier']);

        return $verifier;
    }

    /**
     * Validates incoming OAuth callback parameters from the authorization server.
     * Fails closed on any invalid, missing, or expired parameter.
     * State and verifier are consumed / cleared upon failure or completion.
     * No HTTP, SQL, or local session is created; errors do not leak sensitive parameters.
     *
     * @param array<string, mixed> $params Callback parameters (typically $_GET)
     * @param int|null $currentTime Optional timestamp for deterministic testing
     * @return array{code: string, code_verifier: string} Sanitized code and verifier
     * @throws \InvalidArgumentException|\RuntimeException on any validation failure
     */
    public function validateCallbackParams(array $params, ?int $currentTime = null): array
    {
        $this->ensureSession();

        // 1. Check for incoming OAuth errors
        if (!empty($params['error'])) {
            $this->clearFlowSession();
            $errorCode = is_string($params['error']) ? $params['error'] : 'oauth_error';
            // Fail closed on central_disabled, app_unauthorized, or other provider errors
            throw new \RuntimeException(
                'OAuth callback error received: ' . htmlspecialchars($errorCode, ENT_QUOTES, 'UTF-8')
            );
        }

        // 2. State validation: state must be present, non-empty, and valid
        if (empty($params['state']) || !is_string($params['state'])) {
            $this->clearFlowSession();
            throw new \InvalidArgumentException('Callback validation failed: state parameter is required.');
        }

        $state = $params['state'];
        if (!$this->validateState($state, $currentTime)) {
            $this->clearFlowSession();
            throw new \InvalidArgumentException('Callback validation failed: state is invalid, expired, or already used.');
        }

        // 3. Authorization code validation: code must be present and non-empty
        if (empty($params['code']) || !is_string($params['code']) || trim($params['code']) === '') {
            $this->clearFlowSession();
            throw new \InvalidArgumentException('Callback validation failed: code parameter is required.');
        }
        $code = trim($params['code']);

        // 4. Consume PKCE code_verifier (single-use)
        $verifier = $this->consumeCodeVerifier();
        if ($verifier === null || $verifier === '') {
            $this->clearFlowSession();
            throw new \RuntimeException('Callback validation failed: missing code verifier in session.');
        }

        return [
            'code' => $code,
            'code_verifier' => $verifier,
        ];
    }

    /**
     * Alias for validateCallbackParams.
     *
     * @param array<string, mixed> $params
     * @param int|null $currentTime
     * @return array{code: string, code_verifier: string}
     */
    public function validateCallback(array $params, ?int $currentTime = null): array
    {
        return $this->validateCallbackParams($params, $currentTime);
    }

    /**
     * Validates authorization parameters before redirecting to identity provider.
     * Plain challenge method or absent challenge/method fail closed.
     *
     * @param array<string, mixed> $params
     * @throws \InvalidArgumentException
     */
    public function validateAuthorizationParams(array $params): void
    {
        if (empty($params['code_challenge']) || !is_string($params['code_challenge'])) {
            throw new \InvalidArgumentException('Authorization parameter code_challenge is missing or empty.');
        }

        if (!isset($params['code_challenge_method']) || $params['code_challenge_method'] !== self::PKCE_METHOD_S256) {
            throw new \InvalidArgumentException('Authorization parameter code_challenge_method must be strictly S256.');
        }
    }

    /**
     * Contractual specification helper documenting expected identity attributes.
     * Note: Identity resolution is deferred to dependent tasks; this method validates
     * contract field requirements without performing persistence or assigning default roles.
     *
     * Contractual specifications:
     * - provider: Identification of identity provider (e.g. Central Hub).
     * - hub_user_id: Unique identifier of user in Central Hub.
     * - active: Account active status flag. If inactive (active === false) or central_disabled, reject access.
     * - local_user: Mapping to local user account in Electroplan.
     * - roles: Explicit roles granted to the user. No default or fallback privilege assignment;
     *   app_unauthorized should be signaled when user lacks required permissions.
     *
     * @param array<string, mixed> $identityClaims
     * @return array<string, mixed>
     */
    public function inspectIdentityClaims(array $identityClaims): array
    {
        // Rejection for inactive account or central_disabled
        if (isset($identityClaims['active']) && $identityClaims['active'] === false) {
            throw new \RuntimeException('User account is inactive or central_disabled.');
        }

        $provider = $identityClaims['provider'] ?? null;
        $hubUserId = $identityClaims['hub_user_id'] ?? null;
        $roles = $identityClaims['roles'] ?? [];
        $localUser = $identityClaims['local_user'] ?? null;

        return [
            'provider' => $provider,
            'hub_user_id' => $hubUserId,
            'active' => $identityClaims['active'] ?? true,
            'roles' => $roles,
            'local_user' => $localUser,
        ];
    }

    /**
     * Clears all SSO flow state and verifier data from session.
     */
    public function clearFlowSession(): void
    {
        $this->ensureSession();
        unset($_SESSION['sso_state']);
        unset($_SESSION['sso_states']);
        unset($_SESSION['code_verifier']);
    }

    /**
     * Ensures the session array exists in CLI and web environments.
     */
    private function ensureSession(): void
    {
        if (!isset($_SESSION) || !is_array($_SESSION)) {
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                @session_start();
            }
            if (!isset($_SESSION) || !is_array($_SESSION)) {
                $_SESSION = [];
            }
        }
    }
}

// Global class alias for non-namespaced invocations
if (!class_exists('SsoFlow', false)) {
    class_alias(\Electroplan\Auth\SsoFlow::class, 'SsoFlow');
}
