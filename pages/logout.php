<?php
declare(strict_types=1);

/**
 * Electroplan Local Logout.
 *
 * Destroys only the local application session, clears the session cookie safely,
 * and returns to local login.
 * Strictly performs no network access and calls no central identity provider endpoints.
 */

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

// Clear all session variables
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

// Safely clear session cookie if cookies are used
if (ini_get('session.use_cookies')) {
    if (!headers_sent()) {
        setcookie(
            session_name(),
            '',
            $cookieOptions
        );
    }
}

// Destroy session
if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
    @session_destroy();
}

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