<?php
declare(strict_types=1);

/**
 * Electroplan OAuth Callback Endpoint.
 *
 * Boundary endpoint: consumes OAuth authorization code and PKCE verifier,
 * validates single-use state, exchanges token and queries userinfo server-to-server,
 * and returns sanitized outcomes to the later local-identity boundary.
 *
 * Strict security constraints:
 * - Creates no local session (no session login, no user persistence, no role assignment).
 * - Fails closed with sanitized messages (no secrets, tokens, codes, verifiers or HTTP detail).
 * - Uses only PKCE S256.
 */

require_once __DIR__ . '/../config/SsoConfig.php';
require_once __DIR__ . '/../core/auth/IdentityProfile.php';
require_once __DIR__ . '/../core/auth/IdentityProviderClient.php';
require_once __DIR__ . '/../core/auth/OAuthIdentityProviderClient.php';
require_once __DIR__ . '/../core/auth/SsoFlow.php';
require_once __DIR__ . '/../core/auth/SsoCallbackHandler.php';

use Electroplan\Auth\SsoCallbackHandler;

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}
if (!isset($_SESSION) || !is_array($_SESSION)) {
    $_SESSION = [];
}

if (isset($injectedCallbackHandler) && $injectedCallbackHandler instanceof SsoCallbackHandler) {
    $handler = $injectedCallbackHandler;
} else {
    $handler = new SsoCallbackHandler();
}

$params = $queryParams ?? $_GET ?? [];
$outcome = $handler->handle($params);

// Expose sanitized outcome to later local-identity boundary
$ssoOutcome = $outcome;

if (!$outcome['success']) {
    if (!headers_sent() && php_sapi_name() !== 'cli') {
        http_response_code(400);
    }
}

return $outcome;
