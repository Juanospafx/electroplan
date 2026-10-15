<?php
declare(strict_types=1);

/**
 * Electroplan Single Sign-On (SSO) Initiation Endpoint.
 *
 * Initiates the confidential OAuth2 authorization flow:
 * 1. Fails closed if SSO is disabled or configuration is missing/invalid.
 * 2. Generates cryptographically secure state and PKCE S256 challenge via SsoFlow.
 * 3. Builds authorization URL using accepted confidential client.
 * 4. Redirects strictly to the configured HTTPS authorize endpoint.
 *
 * Security:
 * - Uses PKCE S256 exclusively.
 * - OAuth2 + userinfo only: profile, email scopes. Zero forbidden protocol behavior.
 * - Stores state and code_verifier strictly in session; never leaks verifier.
 */

require_once __DIR__ . '/../config/SsoConfig.php';
require_once __DIR__ . '/../core/auth/IdentityProfile.php';
require_once __DIR__ . '/../core/auth/IdentityProviderClient.php';
require_once __DIR__ . '/../core/auth/OAuthIdentityProviderClient.php';
require_once __DIR__ . '/../core/auth/SsoFlow.php';
require_once __DIR__ . '/../core/auth/SsoSession.php';

use Electroplan\Auth\SsoFlow;
use Electroplan\Auth\OAuthIdentityProviderClient;
use Electroplan\Auth\IdentityProviderClient;
use Electroplan\Auth\SsoSession;

SsoSession::startHardenedSession();

// Resolve configuration and enabled flag
$config = null;
if (isset($injectedSsoConfig) && is_array($injectedSsoConfig)) {
    $config = $injectedSsoConfig;
    $ssoEnabled = !empty($config['enabled']) || !empty($config['SSO_ENABLED']);
} elseif (isset($injectedSsoEnabled)) {
    $ssoEnabled = (bool)$injectedSsoEnabled;
    if ($ssoEnabled && class_exists('\SsoConfig')) {
        try {
            $config = \SsoConfig::load();
        } catch (\Throwable $e) {
            $config = null;
        }
    }
} else {
    $ssoEnabled = class_exists('\SsoConfig') && \SsoConfig::isEnabled();
    if ($ssoEnabled) {
        try {
            $config = \SsoConfig::load();
        } catch (\Throwable $e) {
            $config = null;
        }
    }
}

// Fail closed if SSO is disabled or configuration is unavailable
if (!$ssoEnabled || $config === null) {
    SsoSession::clearTransients();
    if (!headers_sent() && php_sapi_name() !== 'cli') {
        http_response_code(403);
    }
    return [
        'success' => false,
        'redirect_url' => null,
        'error' => 'SSO is disabled or not configured.',
    ];
}

// Initialize SsoFlow and IdentityProviderClient
$flow = (isset($injectedFlow) && $injectedFlow instanceof SsoFlow)
    ? $injectedFlow
    : new SsoFlow();

// Generate high-entropy state and PKCE S256
$currentTime = $injectedCurrentTime ?? null;
$state = $flow->generateState(300, $currentTime);
$pkce = $flow->generatePkce(SsoFlow::PKCE_METHOD_S256);
$flow->validateAuthorizationParams($pkce);

// Resolve identity provider client
$client = null;
if (isset($injectedClient) && $injectedClient instanceof IdentityProviderClient) {
    $client = $injectedClient;
} elseif (class_exists(OAuthIdentityProviderClient::class)) {
    try {
        $client = new OAuthIdentityProviderClient($config);
    } catch (\Throwable $e) {
        $client = null;
    }
}

if ($client === null) {
    SsoSession::clearTransients();
    if (!headers_sent() && php_sapi_name() !== 'cli') {
        http_response_code(500);
    }
    return [
        'success' => false,
        'redirect_url' => null,
        'error' => 'Identity client initialization failed.',
    ];
}

// Build authorization URL with PKCE S256 parameters
$authOptions = [
    'state' => $state,
    'code_challenge' => $pkce['code_challenge'],
    'code_challenge_method' => SsoFlow::PKCE_METHOD_S256,
    'scope' => $config['scopes'] ?? ['profile', 'email'],
];

try {
    $authorizeUrl = $client->getAuthorizationUrl($authOptions);
} catch (\Throwable $e) {
    SsoSession::clearTransients();
    if (!headers_sent() && php_sapi_name() !== 'cli') {
        http_response_code(500);
    }
    return [
        'success' => false,
        'redirect_url' => null,
        'error' => 'Failed to build authorization URL.',
    ];
}

// Enforce that authorize URL is strictly HTTPS
$scheme = parse_url($authorizeUrl, PHP_URL_SCHEME);
if (!is_string($scheme) || strtolower($scheme) !== 'https') {
    SsoSession::clearTransients();
    if (!headers_sent() && php_sapi_name() !== 'cli') {
        http_response_code(500);
    }
    return [
        'success' => false,
        'redirect_url' => null,
        'error' => 'Authorize endpoint must be a valid HTTPS URL.',
    ];
}

if (!headers_sent() && php_sapi_name() !== 'cli') {
    header('Location: ' . $authorizeUrl);
    exit;
}

return [
    'success' => true,
    'redirect_url' => $authorizeUrl,
    'state' => $state,
    'code_challenge' => $pkce['code_challenge'],
    'code_challenge_method' => SsoFlow::PKCE_METHOD_S256,
    'error' => null,
];
