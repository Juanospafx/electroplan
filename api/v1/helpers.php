<?php
declare(strict_types=1);

function get_correlation_id(): string
{
    if (isset($GLOBALS['API_CORRELATION_ID']) && is_string($GLOBALS['API_CORRELATION_ID'])) {
        return $GLOBALS['API_CORRELATION_ID'];
    }

    $header = get_header_value('X-Correlation-Id');
    if ($header !== null && preg_match('/^[a-zA-Z0-9._-]{8,128}\z/', $header)) {
        $cid = $header;
    } else {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        $cid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    $GLOBALS['API_CORRELATION_ID'] = $cid;
    return $cid;
}

function set_correlation_id(?string $correlationId): void
{
    $GLOBALS['API_CORRELATION_ID'] = $correlationId;
}

function json_response(int $status, array $payload): void
{
    $cid = get_correlation_id();
    if (!isset($payload['correlation_id'])) {
        if (isset($payload['ok'])) {
            $ordered = ['ok' => $payload['ok'], 'correlation_id' => $cid];
            foreach ($payload as $k => $v) {
                if ($k !== 'ok') {
                    $ordered[$k] = $v;
                }
            }
            $payload = $ordered;
        } else {
            $payload['correlation_id'] = $cid;
        }
    } else {
        $cid = (string)$payload['correlation_id'];
    }

    http_response_code($status);
    header('Content-Type: application/json');
    header('X-Correlation-Id: ' . $cid);
    echo json_encode($payload);
    exit;
}

function ok_response($data, ?array $meta = null, int $status = 200): void
{
    $payload = [
        'ok' => true,
        'correlation_id' => get_correlation_id(),
        'data' => $data,
    ];
    if ($meta !== null) {
        $payload['meta'] = $meta;
    }
    json_response($status, $payload);
}

function error_response(string $code, string $message, $details = null, int $status = 400): void
{
    $error = ['code' => $code, 'message' => $message];
    if ($details !== null) {
        $error['details'] = $details;
    }
    json_response($status, [
        'ok' => false,
        'correlation_id' => get_correlation_id(),
        'error' => $error,
    ]);
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function get_raw_body(): string
{
    if (isset($GLOBALS['RAW_REQUEST_BODY']) && is_string($GLOBALS['RAW_REQUEST_BODY'])) {
        return $GLOBALS['RAW_REQUEST_BODY'];
    }
    $raw = file_get_contents('php://input');
    $normalized = $raw === false ? '' : $raw;
    $GLOBALS['RAW_REQUEST_BODY'] = $normalized;
    return $normalized;
}

function get_header_value(string $name): ?string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    if (isset($_SERVER[$key])) {
        return (string)$_SERVER[$key];
    }
    if (isset($_SERVER[$name])) {
        return (string)$_SERVER[$name];
    }
    foreach ($_SERVER as $k => $v) {
        if (strcasecmp((string)$k, $key) === 0 || strcasecmp((string)$k, $name) === 0) {
            return (string)$v;
        }
    }
    return null;
}

function require_int($value): ?int
{
    if (is_numeric($value)) {
        $intVal = (int)$value;
        return $intVal > 0 ? $intVal : null;
    }
    return null;
}

function require_string($value): ?string
{
    if (!is_string($value)) return null;
    $trim = trim($value);
    return $trim === '' ? null : $trim;
}

function get_request_path(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH);
    return $path ?: '/';
}

function get_request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function set_client_context(string $clientId, string $role): void
{
    $GLOBALS['API_CLIENT_ID'] = $clientId;
    $GLOBALS['API_CLIENT_ROLE'] = $role;
}

function get_client_id(): ?string
{
    return $GLOBALS['API_CLIENT_ID'] ?? null;
}

function get_client_role(): ?string
{
    return $GLOBALS['API_CLIENT_ROLE'] ?? null;
}

function require_client_role(string $role): void
{
    if (get_client_role() !== $role) {
        error_response('FORBIDDEN', 'Insufficient privileges', null, 403);
    }
}

function require_client_roles(array $roles): void
{
    if (!in_array(get_client_role(), $roles, true)) {
        error_response('FORBIDDEN', 'Insufficient privileges', null, 403);
    }
}
