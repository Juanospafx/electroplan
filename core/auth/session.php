<?php
// core/auth/session.php
if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
    session_start();
}

function normalizeRole($role): string {
    $r = strtolower(trim((string)$role));
    $allowed = ['admin', 'technician', 'viewer'];
    if (!in_array($r, $allowed, true)) {
        return '';
    }
    return $r;
}

function electroplan_is_authenticated(?array $session = null): bool {
    $source = $session ?? ($_SESSION ?? []);
    if (!is_array($source)) {
        return false;
    }

    $userId = $source['user_id'] ?? null;
    $username = $source['username'] ?? null;
    $rawRole = $source['role'] ?? null;

    if ((!is_int($userId) && !(is_string($userId) && ctype_digit($userId))) || (int)$userId <= 0) {
        return false;
    }
    if (!is_string($username) || trim($username) === '') {
        return false;
    }
    if (!is_string($rawRole) || !in_array(strtolower(trim($rawRole)), ['admin', 'technician', 'viewer'], true)) {
        return false;
    }

    return true;
}

function electroplan_is_api_request(?array $server = null): bool {
    $source = $server ?? ($_SERVER ?? []);
    if (!is_array($source)) {
        return false;
    }

    $allowedEndpoints = [
        'api/api.php',
        'api/search.php',
        'api/create_project.php',
        'task_manager/api.php',
    ];

    $candidateKeys = ['SCRIPT_FILENAME', 'SCRIPT_NAME', 'PHP_SELF'];
    foreach ($candidateKeys as $key) {
        if (!isset($source[$key]) || !is_string($source[$key])) {
            continue;
        }

        $raw = trim($source[$key]);
        if ($raw === '') {
            continue;
        }

        $normalized = str_replace('\\', '/', strtolower($raw));
        $path = explode('?', $normalized, 2)[0];
        $path = explode('#', $path, 2)[0];

        foreach ($allowedEndpoints as $endpoint) {
            $pattern = '#(?:^|/)' . preg_quote($endpoint, '#') . '(?:/|$)#i';
            if (preg_match($pattern, $path)) {
                return true;
            }
        }
    }

    return false;
}

function electroplan_api_unauthorized(): void {
    $GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] = null;
    $GLOBALS['__ELECTROPLAN_API_UNAUTHORIZED__'] = true;
    $GLOBALS['__ELECTROPLAN_API_STATUS_CODE__'] = 401;

    $handler = $GLOBALS['__ELECTROPLAN_API_UNAUTHORIZED_HANDLER__'] ?? null;
    if (is_callable($handler)) {
        $handler();
    } else {
        if (!headers_sent()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
        } else {
            @http_response_code(401);
        }
        echo json_encode([
            'status' => 'error',
            'error' => 'Unauthorized',
            'message' => 'Unauthorized',
        ]);
    }

    if (PHP_SAPI !== 'cli') {
        exit;
    }
}

function electroplan_page_redirect(string $path): void {
    $allowed = [
        '/electroplan/pages/sso-login.php',
        '/electroplan/pages/index.php',
    ];
    if (!in_array($path, $allowed, true)) {
        throw new InvalidArgumentException('Unsupported Electroplan page redirect.');
    }

    $GLOBALS['__ELECTROPLAN_API_UNAUTHORIZED__'] = false;
    $GLOBALS['__ELECTROPLAN_PAGE_REDIRECT__'] = $path;
    $handler = $GLOBALS['__ELECTROPLAN_PAGE_REDIRECT_HANDLER__'] ?? null;
    if (is_callable($handler)) {
        $handler($path);
    } elseif (!headers_sent()) {
        header('Location: ' . $path, true, 302);
    }

    if (PHP_SAPI !== 'cli') {
        exit;
    }
}

function electroplan_enforce_session(?array $session = null, ?array $server = null): bool {
    if (!electroplan_is_authenticated($session)) {
        if (isset($_SESSION)) {
            $_SESSION = [];
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        if (electroplan_is_api_request($server)) {
            electroplan_api_unauthorized();
            return false;
        }

        electroplan_page_redirect('/electroplan/pages/sso-login.php');
        return false;
    }

    if (isset($_SESSION) && is_array($_SESSION) && isset($_SESSION['role'])) {
        $_SESSION['role'] = normalizeRole($_SESSION['role']);
    }

    return true;
}

// Verificamos no solo el ID, sino tambien el username y el role.
// Si falta CUALQUIERA de los tres, cerramos sesion y mandamos al login o 401 si es API.
if (!electroplan_enforce_session($_SESSION ?? null, $_SERVER ?? null)) {
    return;
}

// Opcional: Funcion para chequear roles especificos
function requireRole($role) {
    $required = normalizeRole($role);
    if ($required === '' || ($_SESSION['role'] !== $required && $_SESSION['role'] !== 'admin')) {
        die("Acceso denegado: Se requieren permisos de " . ($required !== '' ? $required : 'rol válido'));
    }
}
