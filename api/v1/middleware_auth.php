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

    $activeKeyId = isset($config['WON_PROJECT_HMAC_ACTIVE_KEY_ID']) && is_string($config['WON_PROJECT_HMAC_ACTIVE_KEY_ID'])
        ? trim($config['WON_PROJECT_HMAC_ACTIVE_KEY_ID'])
        : (isset($config['ACTIVE_KEY_ID']) && is_string($config['ACTIVE_KEY_ID']) ? trim($config['ACTIVE_KEY_ID']) : null);
    if ($activeKeyId === '') {
        $activeKeyId = null;
    }

    $activeKeySecret = isset($config['WON_PROJECT_HMAC_ACTIVE_SECRET']) && is_string($config['WON_PROJECT_HMAC_ACTIVE_SECRET']) && trim($config['WON_PROJECT_HMAC_ACTIVE_SECRET']) !== ''
        ? trim($config['WON_PROJECT_HMAC_ACTIVE_SECRET'])
        : (isset($config['ACTIVE_KEY_SECRET']) && is_string($config['ACTIVE_KEY_SECRET']) && trim($config['ACTIVE_KEY_SECRET']) !== '' ? trim($config['ACTIVE_KEY_SECRET']) : null);

    $prevKeyId = isset($config['WON_PROJECT_HMAC_PREVIOUS_KEY_ID']) && is_string($config['WON_PROJECT_HMAC_PREVIOUS_KEY_ID'])
        ? trim($config['WON_PROJECT_HMAC_PREVIOUS_KEY_ID'])
        : (isset($config['PREVIOUS_KEY_ID']) && is_string($config['PREVIOUS_KEY_ID']) ? trim($config['PREVIOUS_KEY_ID']) : null);
    if ($prevKeyId === '') {
        $prevKeyId = null;
    }

    $prevKeySecret = isset($config['WON_PROJECT_HMAC_PREVIOUS_SECRET']) && is_string($config['WON_PROJECT_HMAC_PREVIOUS_SECRET']) && trim($config['WON_PROJECT_HMAC_PREVIOUS_SECRET']) !== ''
        ? trim($config['WON_PROJECT_HMAC_PREVIOUS_SECRET'])
        : (isset($config['PREVIOUS_KEY_SECRET']) && is_string($config['PREVIOUS_KEY_SECRET']) && trim($config['PREVIOUS_KEY_SECRET']) !== '' ? trim($config['PREVIOUS_KEY_SECRET']) : null);

    $rawUntil = $config['WON_PROJECT_HMAC_PREVIOUS_ACCEPT_UNTIL'] ?? ($config['PREVIOUS_ACCEPT_UNTIL'] ?? null);
    $prevAcceptUntil = null;
    if ($rawUntil !== null && $rawUntil !== '') {
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

    $activeConfigComplete = ($activeKeyId !== null && $activeKeyId !== '') && ($activeKeySecret !== null && $activeKeySecret !== '');
    if ($activeKeyId !== null && $activeKeyId !== '' && hash_equals($activeKeyId, $clientId)) {
        if ($activeConfigComplete) {
            $selectedSecret = $activeKeySecret;
        } else {
            error_response('UNAUTHORIZED', 'Invalid client', null, 401);
        }
    }

    if ($selectedSecret === null) {
        $prevConfigComplete = ($prevKeyId !== null && $prevKeyId !== '')
            && ($prevKeySecret !== null && $prevKeySecret !== '')
            && ($prevAcceptUntil !== null && $prevAcceptUntil > 0);

        if ($prevKeyId !== null && $prevKeyId !== '' && hash_equals($prevKeyId, $clientId)) {
            if ($prevConfigComplete && $now <= $prevAcceptUntil) {
                $selectedSecret = $prevKeySecret;
            } else {
                error_response('UNAUTHORIZED', 'Invalid client', null, 401);
            }
        }
    }

    if ($selectedSecret === null) {
        error_response('UNAUTHORIZED', 'Invalid client', null, 401);
    }

    if (!ctype_digit($timestamp)) {
        error_response('UNAUTHORIZED', 'Invalid timestamp', null, 401);
    }

    $ts = (int)$timestamp;
    $rawMaxSkew = $config['WON_PROJECT_MAX_TIMESTAMP_SKEW_SECONDS'] ?? ($config['MAX_TIMESTAMP_SKEW_SECONDS'] ?? ($config['MAX_TIMESTAMP_SKEW'] ?? 300));
    $maxSkew = is_numeric($rawMaxSkew) ? (int)$rawMaxSkew : 300;
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

    $role = 'service';
    if (isset($config['CLIENT_ROLES']) && is_array($config['CLIENT_ROLES']) && isset($config['CLIENT_ROLES'][$clientId]) && is_string($config['CLIENT_ROLES'][$clientId])) {
        $role = $config['CLIENT_ROLES'][$clientId];
    }
    set_client_context($clientId, $role);
}
