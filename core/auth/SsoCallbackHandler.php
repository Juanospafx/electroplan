<?php
declare(strict_types=1);

namespace Electroplan\Auth;

/**
 * Electroplan OAuth Callback Boundary Handler.
 *
 * Implements the callback boundary:
 * 1. Validates and consumes single-use state and PKCE verifier.
 * 2. Rejects missing, invalid, expired or replayed state and missing authorization code.
 * 3. Enforces PKCE S256.
 * 4. Passes code and verifier server-to-server to the accepted identity client.
 * 5. Requires a valid non-empty userinfo-derived identity profile.
 * 6. Fails closed with generic sanitized external messages without leaking
 *    codes, tokens, verifiers, state, secrets, or HTTP detail.
 * 7. Creates no local session, performs no persistence, and assigns no roles.
 */
class SsoCallbackHandler
{
    public const ERROR_GENERIC = 'Authentication failed. Please try again.';
    public const ERROR_INVALID_STATE = 'Authentication failed: invalid or expired state parameter.';
    public const ERROR_INVALID_CODE = 'Authentication failed: missing or invalid authorization code.';
    public const ERROR_MISSING_VERIFIER = 'Authentication failed: missing code verifier.';
    public const ERROR_PROVIDER_REJECTED = 'Authentication failed: identity provider rejected the request.';
    public const ERROR_TOKEN_EXCHANGE = 'Authentication failed during token exchange.';
    public const ERROR_INVALID_PROFILE = 'Authentication failed: invalid user profile received.';
    public const ERROR_USER_INACTIVE = 'Authentication failed: user account is inactive or disabled.';

    private SsoFlow $flow;
    private ?IdentityProviderClient $client;

    /**
     * @param SsoFlow|IdentityProviderClient|null $flow
     * @param IdentityProviderClient|SsoFlow|null $client
     * @param array<string, mixed> $config
     */
    public function __construct(
        $flow = null,
        $client = null,
        array $config = []
    ) {
        if ($flow instanceof IdentityProviderClient && !($client instanceof IdentityProviderClient)) {
            $this->client = $flow;
            $this->flow = ($client instanceof SsoFlow) ? $client : new SsoFlow();
        } else {
            $this->flow = ($flow instanceof SsoFlow) ? $flow : new SsoFlow();
            $this->client = ($client instanceof IdentityProviderClient) ? $client : null;
        }

        if ($this->client === null) {
            if (empty($config) && class_exists('\\SsoConfig')) {
                try {
                    $config = \SsoConfig::load();
                } catch (\Throwable $e) {
                    $config = [];
                }
            }
            if (class_exists('\\Electroplan\\Auth\\OAuthIdentityProviderClient')) {
                $this->client = new OAuthIdentityProviderClient($config);
            }
        }
    }

    /**
     * Handles the OAuth callback.
     *
     * @param array<string, mixed> $params Callback parameters (e.g. $_GET)
     * @param int|null $currentTime Optional timestamp for deterministic testing
     * @return array{success: bool, profile: ?IdentityProfile, error: ?string}
     */
    public function handle(array $params = [], ?int $currentTime = null): array
    {
        // 1. Check for incoming identity provider errors
        if (!empty($params['error'])) {
            $this->flow->clearFlowSession();
            return $this->failure(self::ERROR_PROVIDER_REJECTED);
        }

        // 2. State parameter must be present and non-empty
        if (empty($params['state']) || !is_string($params['state']) || trim($params['state']) === '') {
            $this->flow->clearFlowSession();
            return $this->failure(self::ERROR_INVALID_STATE);
        }

        $state = trim($params['state']);

        // 3. Validate state (single-use anti-replay and expiration check)
        // Consumes state immediately. If invalid/expired, clears verifier as well.
        if (!$this->flow->validateState($state, $currentTime)) {
            $this->flow->clearFlowSession();
            return $this->failure(self::ERROR_INVALID_STATE);
        }

        // 4. Authorization code must be present and non-empty
        if (empty($params['code']) || !is_string($params['code']) || trim($params['code']) === '') {
            $this->flow->clearFlowSession();
            return $this->failure(self::ERROR_INVALID_CODE);
        }
        $code = trim($params['code']);

        // 5. Consume PKCE code_verifier (single-use)
        $codeVerifier = $this->flow->consumeCodeVerifier();
        if ($codeVerifier === null || trim($codeVerifier) === '') {
            $this->flow->clearFlowSession();
            return $this->failure(self::ERROR_MISSING_VERIFIER);
        }

        // 6. Ensure identity client is configured
        if ($this->client === null) {
            $this->flow->clearFlowSession();
            return $this->failure(self::ERROR_GENERIC);
        }

        // 7. Server-to-server exchange and userinfo profile retrieval
        try {
            $profile = $this->client->authenticate($code, $codeVerifier);
        } catch (\Throwable $e) {
            $this->flow->clearFlowSession();
            return $this->failure(self::ERROR_TOKEN_EXCHANGE);
        }

        // 8. Require valid, non-empty userinfo-derived identity profile
        if (!($profile instanceof IdentityProfile)) {
            $this->flow->clearFlowSession();
            return $this->failure(self::ERROR_INVALID_PROFILE);
        }

        if (trim($profile->getId()) === '') {
            $this->flow->clearFlowSession();
            return $this->failure(self::ERROR_INVALID_PROFILE);
        }

        // 9. Fail closed if user is not active
        if (!$profile->isActive()) {
            $this->flow->clearFlowSession();
            return $this->failure(self::ERROR_USER_INACTIVE);
        }

        // Ensure session has no leftover flow state
        $this->flow->clearFlowSession();

        // 10. Return sanitized outcome without creating local session,
        // without persistence, and without role assignment.
        return [
            'success' => true,
            'profile' => $profile,
            'error' => null,
        ];
    }

    /**
     * Alias for handle.
     *
     * @param array<string, mixed> $params
     * @param int|null $currentTime
     * @return array{success: bool, profile: ?IdentityProfile, error: ?string}
     */
    public function handleCallback(array $params = [], ?int $currentTime = null): array
    {
        return $this->handle($params, $currentTime);
    }

    /**
     * Alias for handle.
     *
     * @param array<string, mixed> $params
     * @param int|null $currentTime
     * @return array{success: bool, profile: ?IdentityProfile, error: ?string}
     */
    public function process(array $params = [], ?int $currentTime = null): array
    {
        return $this->handle($params, $currentTime);
    }

    public function getFlow(): SsoFlow
    {
        return $this->flow;
    }

    public function getClient(): ?IdentityProviderClient
    {
        return $this->client;
    }

    /**
     * Generates sanitized failure outcome.
     *
     * @param string $message
     * @return array{success: false, profile: null, error: string}
     */
    private function failure(string $message): array
    {
        return [
            'success' => false,
            'profile' => null,
            'error' => $message,
        ];
    }
}

if (!class_exists('SsoCallbackHandler', false)) {
    class_alias(\Electroplan\Auth\SsoCallbackHandler::class, 'SsoCallbackHandler');
}
