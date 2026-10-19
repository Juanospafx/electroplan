<?php
declare(strict_types=1);

/**
 * Test de contrato estático para OAuthIdentityProviderClient de Electroplan.
 *
 * Criterios de aceptación:
 * 1. Falla rojo si falta core/auth/OAuthIdentityProviderClient.php y no define adaptadores falsos.
 * 2. Exige authorization code, code_verifier, client secret, redirect URI exacta, intercambio server-to-server y userinfo.
 * 3. Rechaza persistencia/log de access_token, code, verifier, state y client_secret y exige errores sanitizados.
 */

$repoRoot = dirname(__DIR__);
$targetRelativePath = 'core/auth/OAuthIdentityProviderClient.php';
$targetFilePath = $repoRoot . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'OAuthIdentityProviderClient.php';

$failures = [];

// 1. Falla rojo si falta core/auth/OAuthIdentityProviderClient.php
// No definir adaptadores falsos ni mocks que encubran la ausencia del componente real.
if (!file_exists($targetFilePath)) {
    fwrite(STDERR, "[CONTRATO SSO] ERROR CRÍTICO: Falta el archivo obligatorio '{$targetRelativePath}'.\n");
    fwrite(STDERR, "[CONTRATO SSO] El contrato exige implementación real en el repositorio sin adaptadores falsos.\n");
    exit(1);
}

// Validar que el archivo sea legible y contenga código PHP
$content = file_get_contents($targetFilePath);
if ($content === false || trim($content) === '') {
    fwrite(STDERR, "[CONTRATO SSO] ERROR: El archivo '{$targetRelativePath}' está vacío o no se puede leer.\n");
    exit(1);
}

// Comprobación de clase definida
if (!preg_match('/class\s+OAuthIdentityProviderClient\b/', $content)) {
    $failures[] = "El archivo debe declarar la clase 'OAuthIdentityProviderClient'.";
}

// 2. Exige authorization code, code_verifier, client secret, redirect URI exacta, intercambio server-to-server y userinfo
$requiredElements = [
    'authorization_code' => [
        'patterns' => ['/authorization_code/i', '/\$code\b/', '/["\']code["\']/i'],
        'description' => 'Manejo de authorization code en el flujo de intercambio.'
    ],
    'code_verifier' => [
        'patterns' => ['/code_verifier/i', '/\$codeVerifier\b/i', '/\$verifier\b/i'],
        'description' => 'Manejo de PKCE code_verifier para intercambio seguro.'
    ],
    'client_secret' => [
        'patterns' => ['/client_secret/i', '/\$clientSecret\b/i'],
        'description' => 'Uso de client secret para autenticación de cliente confidencial.'
    ],
    'redirect_uri' => [
        'patterns' => ['/redirect_uri/i', '/\$redirectUri\b/i'],
        'description' => 'Validación y envío de redirect URI exacta.'
    ],
    'server_to_server_exchange' => [
        'patterns' => ['/curl_init\b/i', '/file_get_contents\b/i', '/stream_context_create\b/i', '/Guzzle/i', '/ClientInterface/i', '/token_endpoint/i', '/\/token\b/i', '/grant_type/i'],
        'description' => 'Intercambio directo server-to-server contra el token endpoint.'
    ],
    'userinfo_endpoint' => [
        'patterns' => ['/userinfo/i', '/getUserInfo\b/i', '/fetchUserInfo\b/i', '/Bearer\s+/i'],
        'description' => 'Consulta al endpoint de userinfo utilizando el access_token obtenido.'
    ]
];

foreach ($requiredElements as $key => $element) {
    $matched = false;
    foreach ($element['patterns'] as $pattern) {
        if (preg_match($pattern, $content)) {
            $matched = true;
            break;
        }
    }
    if (!$matched) {
        $failures[] = "Falta requisito obligatorio [{$key}]: {$element['description']}";
    }
}

// 3. Rechaza persistencia/log de access_token, code, verifier, state y client_secret y exige errores sanitizados.

// 3a. Prohibición de persistencia de secretos o tokens efímeros
$persistencePatterns = [
    'persistencia_sql' => '/(INSERT\s+INTO|UPDATE\s+|REPLACE\s+INTO).*(access_token|code_verifier|client_secret|\$verifier|\$token)/is',
    'persistencia_storage' => '/(file_put_contents|fwrite|session_write|cache->set|storage->save).*(access_token|code_verifier|client_secret|\$verifier)/is'
];

foreach ($persistencePatterns as $checkKey => $pattern) {
    if (preg_match($pattern, $content, $matches)) {
        $failures[] = "Violación de seguridad [{$checkKey}]: Se detectó persistencia no permitida de secretos/tokens: " . trim($matches[0]);
    }
}

// 3b. Prohibición de logging no sanitizado de credenciales y tokens
$loggingPatterns = [
    'log_access_token' => '/(error_log|syslog|logger->|Log::).*\$(access_token|accessToken|token)\b/i',
    'log_code' => '/(error_log|syslog|logger->|Log::).*\$(code|authorization_code)\b/i',
    'log_verifier' => '/(error_log|syslog|logger->|Log::).*\$(verifier|code_verifier|codeVerifier)\b/i',
    'log_state' => '/(error_log|syslog|logger->|Log::).*\$(state)\b/i',
    'log_client_secret' => '/(error_log|syslog|logger->|Log::).*\$(client_secret|clientSecret|secret)\b/i',
    'dump_secrets' => '/(var_dump|print_r|var_export)\s*\(\s*\$(access_token|accessToken|code|verifier|code_verifier|codeVerifier|state|client_secret|clientSecret)/i'
];

foreach ($loggingPatterns as $logKey => $pattern) {
    if (preg_match($pattern, $content, $matches)) {
        $failures[] = "Violación de privacidad [{$logKey}]: Se detectó logging o dump directo de datos sensibles: " . trim($matches[0]);
    }
}

// 3c. Exigencia de sanitización de errores (evitar filtrar respuestas crudas con tokens en excepciones)
if (preg_match('/throw\s+new\s+\w*Exception\s*\(\s*.*(access_token|code_verifier|client_secret)/i', $content, $matches)) {
    $failures[] = "Violación de sanitización de excepciones: Mensaje de excepción expone tokens o secretos: " . trim($matches[0]);
}

// Resultado final de la validación
if (!empty($failures)) {
    fwrite(STDERR, "[CONTRATO SSO] FALLO DE CONTRATO ESTÁTICO:\n");
    foreach ($failures as $idx => $failure) {
        fwrite(STDERR, "  " . ($idx + 1) . ". {$failure}\n");
    }
    exit(1);
}

echo "[CONTRATO SSO] OK: Todos los criterios del contrato estático token/userinfo fueron validados exitosamente.\n";
exit(0);
