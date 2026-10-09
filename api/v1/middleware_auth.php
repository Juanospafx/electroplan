<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function verify_hmac_auth(array $config): void
{
    $clientId = get_header_value('X-Client-Id');
    if ($clientId === null || $clientId === '') {
        $clientId = get_header_value('X-Key-Id');
    }
    $timestamp = get_header_value('X-Timestamp');
    $signature = get_header_value('X-Signature');

    if ($clientId === null || $clientId === '' || $timestamp === null || $timestamp === '' || $signature === null || $signature === '') {
        error_response('UNAUTHORIZED', 'Missing authentication headers', null, 401);
    }

    $now = time();

    $activeKeyId = isset($config['ACTIVE_KEY_ID']) && is_string($config['ACTIVE_KEY_ID']) ? trim($config['ACTIVE_KEY_ID']) : null;
    $activeKeySecret = isset($config['ACTIVE_KEY_SECRET']) && is_string($config['ACTIVE_KEY_SECRET']) && $config['ACTIVE_KEY_SECRET'] !== '' ? $config['ACTIVE_KEY_SECRET'] : null;

    $prevKeyId = isset($config['PREVIOUS_KEY_ID']) && is_string($config['PREVIOUS_KEY_ID']) ? trim($config['PREVIOUS_KEY_ID']) : null;
    $prevKeySecret = isset($config['PREVIOUS_KEY_SECRET']) && is_string($config['PREVIOUS_KEY_SECRET']) && $config['PREVIOUS_KEY_SECRET'] !== '' ? $config['PREVIOUS_KEY_SECRET'] : null;

    $prevAcceptUntil = null;
    if (isset($config['PREVIOUS_ACCEPT_UNTIL'])) {
        $rawUntil = $config['PREVIOUS_ACCEPT_UNTIL'];
        if (is_int($rawUntil)) {
            $prevAcceptUntil = $rawUntil > 0 ? $rawUntil : null;
        } elseif (is_numeric($rawUntil)) {
            $intVal = (int)$rawUntil;
            $prevAcceptUntil = $intVal > 0 ? $intVal : null;
        } elseif (is_string($rawUntil)) {
            $parsed = strtotime($rawUntil);
            $prevAcceptUntil = ($parsed !== false && $parsed > 0) ? $parsed : null;
        }
    }

    $selectedSecret = null;

    $activeConfigComplete = ($activeKeyId !== null && $activeKeyId !== '') && ($activeKeySecret !== null);
    if ($activeConfigComplete && hash_equals($activeKeyId, $clientId)) {
        $selectedSecret = $activeKeySecret;
    }

    if ($selectedSecret === null) {
        $prevConfigComplete = ($prevKeyId !== null && $prevKeyId !== '')
            && ($prevKeySecret !== null)
            && ($prevAcceptUntil !== null);

        if ($prevKeyId !== null && $prevKeyId !== '' && hash_equals($prevKeyId, $clientId)) {
            if ($prevConfigComplete && $now <= $prevAcceptUntil) {
                $selectedSecret = $prevKeySecret;
            } else {
                error_response('UNAUTHORIZED', 'Invalid client', null, 401);
            }
        }
    }

    if ($selectedSecret === null && !$activeConfigComplete && isset($config['CLIENTS'][$clientId]) && is_string($config['CLIENTS'][$clientId])) {
        $selectedSecret = $config['CLIENTS'][$clientId];
    }

    if ($selectedSecret === null) {
        error_response('UNAUTHORIZED', 'Invalid client', null, 401);
    }

    if (!ctype_digit($timestamp)) {
        error_response('UNAUTHORIZED', 'Invalid timestamp', null, 401);
    }

    $ts = (int)$timestamp;
    $maxSkew = (int)($config['MAX_TIMESTAMP_SKEW_SECONDS'] ?? ($config['MAX_TIMESTAMP_SKEW'] ?? 300));
    if ($maxSkew <= 0) {
        $maxSkew = 300;
    }

    if (abs($now - $ts) > $maxSkew) {
        error_response('UNAUTHORIZED', 'Timestamp out of range', null, 401);
    }

    $method = get_request_method();
    $path = get_request_path();
    $rawBody = get_raw_body();

    $payload = $method . "\n" . $path . "\n" . $timestamp . "\n" . $rawBody;
    $expected = hash_hmac('sha256', $payload, $selectedSecret);

    if (!hash_equals($expected, $signature)) {
        error_response('UNAUTHORIZED', 'Invalid signature', null, 401);
    }

    $role = $config['CLIENT_ROLES'][$clientId] ?? 'service';
    set_client_context($clientId, $role);
}
