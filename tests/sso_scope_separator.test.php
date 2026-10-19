<?php
/**
 * Contract Test Suite: Electroplan OAuth Scope Serialization (TASK-0352).
 *
 * Validates:
 * 1. GenericProvider is configured with a single-space scope separator.
 * 2. Generated authorization URL encodes profile and email as a space-delimited scope value, never comma-delimited.
 * 3. Token exchange, PKCE, client secret handling and redirect URI remain unchanged.
 * 4. Error sanitization and fail-closed HTTPS endpoint validation.
 */

declare(strict_types=1);

// Provide League OAuth2 Client contract stubs if running in an isolated worktree without vendor
namespace League\OAuth2\Client\Token {
    if (!interface_exists(AccessTokenInterface::class)) {
        interface AccessTokenInterface
        {
            public function getToken(): string;
        }
    }
}

namespace League\OAuth2\Client\Provider {
    use League\OAuth2\Client\Token\AccessTokenInterface;

    if (!class_exists(GenericProvider::class)) {
        class GenericProvider
        {
            protected string $clientId = '';
            protected string $clientSecret = '';
            protected string $redirectUri = '';
            protected string $urlAuthorize = '';
            protected string $urlAccessToken = '';
            protected string $urlResourceOwnerDetails = '';
            protected array $scopes = [];
            protected string $scopeSeparator = ',';
            protected int $timeout = 10;
            protected ?string $state = null;

            public ?array $lastAccessTokenParams = null;
            public ?string $lastGrant = null;

            public function __construct(array $options = [], array $collaborators = [])
            {
                $configurable = [
                    'clientId',
                    'clientSecret',
                    'redirectUri',
                    'urlAuthorize',
                    'urlAccessToken',
                    'urlResourceOwnerDetails',
                    'scopes',
                    'scopeSeparator',
                    'timeout',
                ];

                foreach ($configurable as $key) {
                    if (array_key_exists($key, $options)) {
                        $this->$key = $options[$key];
                    }
                }
            }

            public function getScopeSeparator(): string
            {
                return $this->scopeSeparator;
            }

            public function getAuthorizationUrl(array $options = []): string
            {
                $scopes = $options['scope'] ?? $this->scopes;
                if (is_array($scopes)) {
                    $scopeStr = implode($this->scopeSeparator, $scopes);
                } else {
                    $scopeStr = (string)$scopes;
                }

                $params = [
                    'response_type' => 'code',
                    'client_id'     => $this->clientId,
                    'redirect_uri'  => $this->redirectUri,
                    'scope'         => $scopeStr,
                    'state'         => $options['state'] ?? bin2hex(random_bytes(16)),
                ];

                if (isset($options['code_challenge'])) {
                    $params['code_challenge'] = $options['code_challenge'];
                }
                if (isset($options['code_challenge_method'])) {
                    $params['code_challenge_method'] = $options['code_challenge_method'];
                }

                return $this->urlAuthorize . '?' . http_build_query($params, '', '&', \PHP_QUERY_RFC3986);
            }

            public function getAccessToken($grant, array $options = []): AccessTokenInterface
            {
                $this->lastGrant = (string)$grant;
                $this->lastAccessTokenParams = $options;

                return new class implements AccessTokenInterface {
                    public function getToken(): string
                    {
                        return 'mock_access_token_xyz';
                    }
                };
            }

            public function getResourceOwner(AccessTokenInterface $token)
            {
                return new class {
                    public function toArray(): array
                    {
                        return [
                            'hub_user_id' => 'hub_usr_test_1',
                            'email'       => 'test@example.com',
                            'name'        => 'Test User',
                            'active'      => true,
                            'roles'       => ['technician'],
                        ];
                    }
                };
            }
        }
    }
}

namespace {

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Autoload dependencies if vendor is available in runtime
$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

require_once dirname(__DIR__) . '/core/auth/IdentityProviderClient.php';
require_once dirname(__DIR__) . '/core/auth/IdentityProfile.php';
require_once dirname(__DIR__) . '/core/auth/OAuthIdentityProviderClient.php';

use Electroplan\Auth\OAuthIdentityProviderClient;
use League\OAuth2\Client\Provider\GenericProvider;

// Autonomous test runner
class SsoScopeSeparatorTestRunner
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

$runner = new SsoScopeSeparatorTestRunner();
echo "--- Iniciando validación de serialización de scopes OAuth (TASK-0352) ---\n\n";

$validConfig = [
    'client_id'     => 'electroplan-test-client',
    'client_secret' => 'super-secret-key-electroplan-987!',
    'redirect_uri'  => 'https://electroplan.example.com/sso-callback',
    'authorize_url' => 'https://hub.example.com/oauth/authorize',
    'token_url'     => 'https://hub.example.com/oauth/token',
    'userinfo_url'  => 'https://hub.example.com/oauth/userinfo',
    'scopes'        => ['profile', 'email'],
    'timeout'       => 15,
];

// Helper to inspect the private GenericProvider from client instance
$getProvider = function (OAuthIdentityProviderClient $client): GenericProvider {
    $ref = new \ReflectionClass($client);
    $method = $ref->getMethod('getProvider');
    $method->setAccessible(true);
    return $method->invoke($client);
};

// =========================================================================
// SUITE 1: GENERICPROVIDER SINGLE-SPACE SCOPE SEPARATOR CONFIGURATION
// =========================================================================
echo "=== Suite 1: GenericProvider is configured with a single-space scope separator ===\n";

$client = new OAuthIdentityProviderClient($validConfig);
$provider = $getProvider($client);

$runner->assert(
    $provider instanceof GenericProvider,
    'getProvider() resolves a League GenericProvider instance'
);

// Inspect scopeSeparator directly on the GenericProvider instance
$refClass = new \ReflectionClass($provider);
$scopeSepValue = null;
if ($refClass->hasProperty('scopeSeparator')) {
    $prop = $refClass->getProperty('scopeSeparator');
    $prop->setAccessible(true);
    $scopeSepValue = $prop->getValue($provider);
} elseif (method_exists($provider, 'getScopeSeparator')) {
    $scopeSepValue = $provider->getScopeSeparator();
}

$runner->assert(
    $scopeSepValue === ' ',
    'GenericProvider property scopeSeparator is strictly a single space (" ")'
);
$runner->assert(
    $scopeSepValue !== ',',
    'GenericProvider scopeSeparator is never comma (",")'
);

// =========================================================================
// SUITE 2: AUTHORIZATION URL ENCODING OF SCOPES
// =========================================================================
echo "\n=== Suite 2: Authorization URL encodes scopes with space delimiter, never comma ===\n";

// 2.1 Default scopes (profile and email)
$authUrlDefault = $client->getAuthorizationUrl([
    'state'                 => 'state_xyz123',
    'code_challenge'        => 'challenge_abc456',
    'code_challenge_method' => 'S256',
]);

$queryDefaultStr = parse_url($authUrlDefault, PHP_URL_QUERY) ?? '';
parse_str($queryDefaultStr, $queryParamsDefault);

$runner->assert(
    isset($queryParamsDefault['scope']),
    'Authorization URL contains scope query parameter'
);
$runner->assert(
    $queryParamsDefault['scope'] === 'profile email',
    'Decoded scope query parameter contains "profile email" space-delimited'
);
$runner->assert(
    strpos($authUrlDefault, 'profile,email') === false && strpos($authUrlDefault, 'profile%2Cemail') === false,
    'Authorization URL never contains comma-delimited scope (no "," or "%2C")'
);
$runner->assert(
    strpos($authUrlDefault, 'profile%20email') !== false || strpos($authUrlDefault, 'profile+email') !== false,
    'Authorization URL encodes space as RFC-compatible %20 or +'
);

// 2.2 Explicit array scope options in getAuthorizationUrl
$authUrlExplicit = $client->getAuthorizationUrl([
    'state'                 => 'state_explicit',
    'code_challenge'        => 'challenge_explicit',
    'code_challenge_method' => 'S256',
    'scope'                 => ['profile', 'email'],
]);

$queryExplicitStr = parse_url($authUrlExplicit, PHP_URL_QUERY) ?? '';
parse_str($queryExplicitStr, $queryParamsExplicit);

$runner->assert(
    $queryParamsExplicit['scope'] === 'profile email',
    'Explicit array scopes ["profile", "email"] serialize to "profile email"'
);
$runner->assert(
    strpos($authUrlExplicit, 'profile,email') === false && strpos($authUrlExplicit, 'profile%2Cemail') === false,
    'Explicit array scopes URL contains no comma'
);

// 2.3 Multiple custom scopes
$authUrlMulti = $client->getAuthorizationUrl([
    'state'                 => 'state_multi',
    'code_challenge'        => 'challenge_multi',
    'code_challenge_method' => 'S256',
    'scope'                 => ['profile', 'email', 'custom_scope'],
]);

$queryMultiStr = parse_url($authUrlMulti, PHP_URL_QUERY) ?? '';
parse_str($queryMultiStr, $queryParamsMulti);

$runner->assert(
    $queryParamsMulti['scope'] === 'profile email custom_scope',
    'Multiple scopes serialize as space-delimited list'
);
$runner->assert(
    strpos($authUrlMulti, ',') === false && strpos($authUrlMulti, '%2C') === false,
    'Multiple scopes URL contains zero commas'
);

// =========================================================================
// SUITE 3: TOKEN EXCHANGE, PKCE, CLIENT SECRET AND REDIRECT URI INVARIANCE
// =========================================================================
echo "\n=== Suite 3: Token exchange, PKCE, client secret, and redirect URI remain unchanged ===\n";

// 3.1 PKCE code_challenge and code_challenge_method preserved in authorization URL
$runner->assert(
    $queryParamsDefault['code_challenge'] === 'challenge_abc456',
    'PKCE code_challenge parameter is preserved verbatim'
);
$runner->assert(
    $queryParamsDefault['code_challenge_method'] === 'S256',
    'PKCE code_challenge_method parameter is preserved as S256'
);

// 3.2 Redirect URI preserved verbatim in authorization URL
$runner->assert(
    $queryParamsDefault['redirect_uri'] === 'https://electroplan.example.com/sso-callback',
    'Configured HTTPS redirect URI is preserved verbatim in authorization URL'
);

// 3.3 Client ID preserved in authorization URL
$runner->assert(
    $queryParamsDefault['client_id'] === 'electroplan-test-client',
    'Client ID is preserved in authorization URL'
);

// 3.4 Client secret is NEVER leaked in authorization URL
$runner->assert(
    strpos($authUrlDefault, 'super-secret-key-electroplan-987!') === false,
    'Client secret is never leaked in authorization URL'
);

// 3.5 Token exchange sends code, code_verifier, redirect_uri, and client_secret
$exchangeClient = new OAuthIdentityProviderClient($validConfig);
$token = $exchangeClient->exchangeCode('auth_code_live_777', 'pkce_verifier_secret_888');
$runner->assert(
    $token instanceof \League\OAuth2\Client\Token\AccessTokenInterface,
    'exchangeCode returns AccessTokenInterface on valid exchange'
);

$underlyingProvider = $getProvider($exchangeClient);
if (isset($underlyingProvider->lastAccessTokenParams)) {
    $runner->assert(
        $underlyingProvider->lastGrant === 'authorization_code',
        'Token exchange uses grant_type authorization_code'
    );
    $runner->assert(
        $underlyingProvider->lastAccessTokenParams['code'] === 'auth_code_live_777',
        'Authorization code passed to token endpoint'
    );
    $runner->assert(
        $underlyingProvider->lastAccessTokenParams['code_verifier'] === 'pkce_verifier_secret_888',
        'PKCE code_verifier passed to token endpoint'
    );
    $runner->assert(
        $underlyingProvider->lastAccessTokenParams['redirect_uri'] === 'https://electroplan.example.com/sso-callback',
        'Exact redirect_uri passed to token endpoint'
    );
    $runner->assert(
        $underlyingProvider->lastAccessTokenParams['client_secret'] === 'super-secret-key-electroplan-987!',
        'Client secret passed securely to server-to-server token endpoint'
    );
}

// 3.6 exchangeCode input validation
$emptyCodeCaught = false;
try {
    $exchangeClient->exchangeCode('  ', 'pkce_verifier_secret_888');
} catch (\InvalidArgumentException $e) {
    $emptyCodeCaught = true;
}
$runner->assert($emptyCodeCaught, 'exchangeCode rejects empty authorization code');

$emptyVerifierCaught = false;
try {
    $exchangeClient->exchangeCode('auth_code_live_777', '  ');
} catch (\InvalidArgumentException $e) {
    $emptyVerifierCaught = true;
}
$runner->assert($emptyVerifierCaught, 'exchangeCode rejects empty PKCE code_verifier');

// =========================================================================
// SUITE 4: SECURITY AND ERROR SANITIZATION
// =========================================================================
echo "\n=== Suite 4: Security constraints and error sanitization ===\n";

// 4.1 Non-HTTPS redirect URI rejection
$nonHttpsConfig = array_merge($validConfig, [
    'redirect_uri' => 'http://insecure.example.com/callback',
]);
$nonHttpsClient = new OAuthIdentityProviderClient($nonHttpsConfig);
$nonHttpsCaught = false;
try {
    $nonHttpsClient->exchangeCode('code_123', 'verifier_456');
} catch (\RuntimeException $e) {
    $nonHttpsCaught = true;
    $runner->assert(
        strpos($e->getMessage(), 'redirect URI must be a valid HTTPS URL') !== false,
        'Rejects non-HTTPS redirect URI'
    );
}
$runner->assert($nonHttpsCaught, 'Fails closed on insecure HTTP redirect URI');

// 4.2 Non-HTTPS authorize URL rejection in getProvider
$insecureAuthClient = new OAuthIdentityProviderClient(array_merge($validConfig, [
    'authorize_url' => 'http://insecure.hub.example.com/oauth/authorize',
]));
$insecureAuthCaught = false;
try {
    $insecureAuthClient->getAuthorizationUrl();
} catch (\RuntimeException $e) {
    $insecureAuthCaught = true;
    $runner->assert(
        strpos($e->getMessage(), 'authorize URL must be a valid HTTPS URL') !== false,
        'Rejects non-HTTPS authorize URL'
    );
}
$runner->assert($insecureAuthCaught, 'Fails closed on insecure HTTP authorize URL');

exit($runner->summary());
}
