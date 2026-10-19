<?php
declare(strict_types=1);

namespace Electroplan\Auth;

use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;

/**
 * OAuth2 Identity Provider Client for Electroplan.
 *
 * Implements authorization_code grant exchange with PKCE code_verifier,
 * confidential client authentication with client_secret, exact redirect_uri enforcement,
 * server-to-server token endpoint communication, and Bearer userinfo endpoint queries.
 *
 * Strict security constraints:
 * - No persistence or logging of tokens, codes, verifiers, state or secrets.
 * - Sanitized exceptions that never expose sensitive credentials.
 */
class OAuthIdentityProviderClient implements IdentityProviderClient
{
    /**
     * @var GenericProvider|null
     */
    private $provider = null;

    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private string $authorizeEndpoint;
    private string $tokenEndpoint;
    private string $userinfoEndpoint;
    private array $scopes;
    private int $timeout;

    /**
     * @param array<string, mixed> $config Configuration array
     * @param GenericProvider|null $provider Optional injected provider
     */
    public function __construct(array $config = [], $provider = null)
    {
        $this->clientId = (string)($config['client_id'] ?? $config['clientId'] ?? $config['SSO_CLIENT_ID'] ?? '');
        $this->clientSecret = (string)($config['client_secret'] ?? $config['clientSecret'] ?? $config['SSO_CLIENT_SECRET'] ?? '');
        $this->redirectUri = (string)($config['redirect_uri'] ?? $config['redirectUri'] ?? $config['SSO_REDIRECT_URI'] ?? '');
        $this->authorizeEndpoint = (string)($config['authorize_url'] ?? $config['urlAuthorize'] ?? $config['SSO_AUTHORIZE_URL'] ?? '');
        $this->tokenEndpoint = (string)($config['token_url'] ?? $config['urlAccessToken'] ?? $config['SSO_TOKEN_URL'] ?? '');
        $this->userinfoEndpoint = (string)($config['userinfo_url'] ?? $config['urlResourceOwnerDetails'] ?? $config['SSO_USERINFO_URL'] ?? '');
        $this->scopes = (array)($config['scopes'] ?? ['profile', 'email']);
        $this->timeout = (int)($config['timeout'] ?? $config['SSO_TIMEOUT'] ?? 10);

        if ($provider !== null) {
            $this->provider = $provider;
        }
    }

    /**
     * Static factory helper creating an instance from configuration array.
     *
     * @param array<string, mixed> $config
     * @return self
     */
    public static function fromConfig(array $config): self
    {
        return new self($config);
    }

    /**
     * Builds the authorization redirect URL.
     *
     * @param array<string, mixed> $options
     * @return string
     */
    public function getAuthorizationUrl(array $options = []): string
    {
        $provider = $this->getProvider();
        return $provider->getAuthorizationUrl($options);
    }

    /**
     * Intercambio directo server-to-server contra el token endpoint.
     *
     * Exchanges the authorization code and PKCE code_verifier for an access token
     * using grant_type authorization_code, client_secret, and exact redirect_uri.
     *
     * @param string $code Authorization code
     * @param string $codeVerifier PKCE code_verifier
     * @return AccessTokenInterface
     * @throws \InvalidArgumentException|\RuntimeException on failure
     */
    public function exchangeCode(string $code, string $codeVerifier): AccessTokenInterface
    {
        if (trim($code) === '') {
            throw new \InvalidArgumentException('Authorization code cannot be empty.');
        }

        if (trim($codeVerifier) === '') {
            throw new \InvalidArgumentException('PKCE verifier parameter cannot be empty.');
        }

        if ($this->redirectUri !== '' && !self::isHttpsUrl($this->redirectUri)) {
            throw new \RuntimeException('OAuth configuration error: redirect URI must be a valid HTTPS URL.');
        }

        $provider = $this->getProvider();

        try {
            // Direct server-to-server token exchange with grant_type authorization_code against /token endpoint
            $token = $provider->getAccessToken('authorization_code', [
                'code' => $code,
                'code_verifier' => $codeVerifier,
                'redirect_uri' => $this->redirectUri,
                'client_secret' => $this->clientSecret,
            ]);

            return $token;
        } catch (\Throwable $e) {
            // Sanitized error: never leak sensitive parameters or credentials
            throw new \RuntimeException('Token exchange failed during server-to-server request.');
        }
    }

    /**
     * Fetches user claims from the userinfo endpoint using the Bearer token.
     *
     * @param AccessTokenInterface|string $token
     * @return array<string, mixed>
     * @throws \RuntimeException
     */
    public function fetchUserInfo($token): array
    {
        $provider = $this->getProvider();

        try {
            if ($token instanceof AccessTokenInterface) {
                $resourceOwner = $provider->getResourceOwner($token);
                $claims = $resourceOwner->toArray();
            } else {
                if ($this->userinfoEndpoint !== '' && !self::isHttpsUrl($this->userinfoEndpoint)) {
                    throw new \RuntimeException('OAuth configuration error: userinfo URL must be a valid HTTPS URL.');
                }
                // Requesting userinfo endpoint with Authorization: Bearer <token>
                $request = $provider->getAuthenticatedRequest(
                    'GET',
                    $this->userinfoEndpoint,
                    $token
                );
                $claims = $provider->getParsedResponse($request);
            }

            if (!is_array($claims)) {
                throw new \RuntimeException('Invalid response format received from identity provider userinfo.');
            }

            return $claims;
        } catch (\Throwable $e) {
            // Sanitized exception
            throw new \RuntimeException('Failed to query identity provider userinfo.');
        }
    }

    /**
     * Alias for fetchUserInfo to fulfill userinfo query contract.
     *
     * @param AccessTokenInterface|string $token
     * @return array<string, mixed>
     */
    public function getUserInfo($token): array
    {
        return $this->fetchUserInfo($token);
    }

    /**
     * Authenticates using the authorization code and PKCE code_verifier,
     * exchanges them at the token endpoint, retrieves user claims from userinfo,
     * and returns a normalized provider-neutral IdentityProfile.
     *
     * @param string $code Authorization code
     * @param string $codeVerifier PKCE code_verifier
     * @return IdentityProfile
     */
    public function authenticate(string $code, string $codeVerifier): IdentityProfile
    {
        $token = $this->exchangeCode($code, $codeVerifier);
        $claims = $this->fetchUserInfo($token);

        return IdentityProfile::fromClaims($claims, 'central_hub');
    }

    /**
     * Retrieves the identity profile using authorization code and PKCE code_verifier.
     *
     * @param string $code Authorization code
     * @param string $codeVerifier PKCE code_verifier
     * @return IdentityProfile
     */
    public function fetchProfile(string $code, string $codeVerifier): IdentityProfile
    {
        return $this->authenticate($code, $codeVerifier);
    }

    /**
     * Validates whether a URL is a valid absolute HTTPS URL.
     */
    private static function isHttpsUrl(string $url): bool
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            return false;
        }

        if (!filter_var($trimmed, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = parse_url($trimmed, PHP_URL_SCHEME);
        if (!is_string($scheme) || strtolower($scheme) !== 'https') {
            return false;
        }

        $host = parse_url($trimmed, PHP_URL_HOST);
        return is_string($host) && trim($host) !== '';
    }

    /**
     * Validates that an endpoint URL is HTTPS.
     *
     * @param string $url
     * @param string $endpointName
     * @throws \RuntimeException
     */
    private function validateEndpoint(string $url, string $endpointName): void
    {
        if (!self::isHttpsUrl($url)) {
            throw new \RuntimeException("OAuth configuration error: {$endpointName} must be a valid HTTPS URL.");
        }
    }

    /**
     * Resolves or instantiates the League GenericProvider.
     *
     * @return GenericProvider
     * @throws \RuntimeException
     */
    private function getProvider(): GenericProvider
    {
        if ($this->provider === null) {
            if (!class_exists(GenericProvider::class)) {
                throw new \RuntimeException('OAuth provider component is not available.');
            }

            $this->validateEndpoint($this->authorizeEndpoint, 'authorize URL');
            $this->validateEndpoint($this->tokenEndpoint, 'token URL');
            $this->validateEndpoint($this->userinfoEndpoint, 'userinfo URL');
            $this->validateEndpoint($this->redirectUri, 'redirect URI');

            $this->provider = new GenericProvider([
                'clientId'                => $this->clientId,
                'clientSecret'            => $this->clientSecret,
                'redirectUri'             => $this->redirectUri,
                'urlAuthorize'            => $this->authorizeEndpoint,
                'urlAccessToken'          => $this->tokenEndpoint,
                'urlResourceOwnerDetails' => $this->userinfoEndpoint,
                'scopes'                  => $this->scopes,
                'timeout'                 => $this->timeout,
            ]);
        }

        return $this->provider;
    }
}

if (!class_exists('OAuthIdentityProviderClient', false)) {
    class_alias(\Electroplan\Auth\OAuthIdentityProviderClient::class, 'OAuthIdentityProviderClient');
}
