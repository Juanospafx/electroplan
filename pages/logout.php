<?php
declare(strict_types=1);

/**
 * Electroplan Local Logout.
 *
 * Destroys only the local application session, clears the session cookie safely,
 * and returns to local login.
 * Strictly performs no network access and calls no central identity provider endpoints.
 */

require_once __DIR__ . '/../core/auth/SsoSession.php';

use Electroplan\Auth\SsoSession;

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    SsoSession::startHardenedSession();
}

$isCli = php_sapi_name() === 'cli';
$logoutRedirectUrl = 'login.php';

// Require POST method for state change
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($requestMethod !== 'POST') {
    if (!headers_sent() && !$isCli) {
        http_response_code(405);
        header('Allow: POST');
        header('Location: ' . $logoutRedirectUrl);
        exit;
    }
    return [
        'success' => false,
        'redirect_url' => $logoutRedirectUrl,
        'error' => 'Method Not Allowed: Logout requires POST.',
    ];
}

// Require valid session-bound CSRF token
$csrfToken = $_POST['csrf_token'] ?? null;
if (!SsoSession::validateCsrfToken(is_string($csrfToken) ? $csrfToken : null)) {
    if (!headers_sent() && !$isCli) {
        http_response_code(403);
        header('Location: ' . $logoutRedirectUrl . '?error=csrf');
        exit;
    }
    return [
        'success' => false,
        'redirect_url' => $logoutRedirectUrl,
        'error' => 'Invalid or missing CSRF token.',
    ];
}

// Clear all local and federation session state and safely invalidate cookie
$cookieOptions = SsoSession::logout();

$logoutRedirectUrl = 'login.php';

if (!headers_sent() && php_sapi_name() !== 'cli') {
    header('Location: ' . $logoutRedirectUrl);
    exit;
}

return [
    'success' => true,
    'redirect_url' => $logoutRedirectUrl,
    'cookie' => $cookieOptions,
];