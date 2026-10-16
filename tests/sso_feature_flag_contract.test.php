<?php
/**
 * Test de contrato: SSO Feature Flag de Electroplan
 * TASK-0233
 *
 * Criterios de aceptacion:
 * 1. Crear el archivo antes de leer cualquier otro; no definir clases de producto,
 *    usuarios, passwords, hashes, tokens o secretos en el test.
 * 2. Si falta config/SsoConfig.php, fallar rojo inmediatamente con diagnostico;
 *    cuando exista, exigir SSO_ENABLED false por defecto.
 * 3. Inspeccionar pages/login.php real para exigir login local preservado y boton SSO
 *    condicionado al flag, sin red ni DB.
 */

$projectRoot = dirname(__DIR__);

// 1. Diagnóstico y fallo rojo inmediato si falta config/SsoConfig.php
$ssoConfigFile = $projectRoot . '/config/SsoConfig.php';
if (!file_exists($ssoConfigFile)) {
    fwrite(STDERR, "FALLO ROJO: Falta el archivo de configuracion requerido config/SsoConfig.php\n");
    exit(1);
}

// 2. Exigir SSO_ENABLED false por defecto
require_once $ssoConfigFile;

$ssoEnabledFound = false;
$ssoEnabledValue = null;

if (defined('SSO_ENABLED')) {
    $ssoEnabledFound = true;
    $ssoEnabledValue = constant('SSO_ENABLED');
} elseif (class_exists('SsoConfig', false)) {
    $reflection = new ReflectionClass('SsoConfig');
    if ($reflection->hasConstant('SSO_ENABLED')) {
        $ssoEnabledFound = true;
        $ssoEnabledValue = $reflection->getConstant('SSO_ENABLED');
    } elseif ($reflection->hasProperty('sso_enabled')) {
        $ssoEnabledFound = true;
        $prop = $reflection->getProperty('sso_enabled');
        $ssoEnabledValue = $prop->getValue();
    }
}

if (!$ssoEnabledFound) {
    $ssoContent = file_get_contents($ssoConfigFile);
    if (preg_match('/(?:define\s*\(\s*[\'"]SSO_ENABLED[\'"]\s*,\s*(false|true)[\'"]?\s*\)|const\s+SSO_ENABLED\s*=\s*(false|true))/i', $ssoContent, $matches)) {
        $ssoEnabledFound = true;
        $ssoEnabledValue = (strtolower($matches[1]) === 'true');
    }
}

if (!$ssoEnabledFound) {
    fwrite(STDERR, "FALLO: No se encontro la definicion de SSO_ENABLED en config/SsoConfig.php\n");
    exit(1);
}

if ($ssoEnabledValue !== false) {
    fwrite(STDERR, "FALLO: SSO_ENABLED debe ser false por defecto en config/SsoConfig.php\n");
    exit(1);
}

// 3. Inspeccionar pages/login.php real para login local preservado y boton SSO condicionado al flag
$loginPageFile = $projectRoot . '/pages/login.php';
if (!file_exists($loginPageFile)) {
    fwrite(STDERR, "FALLO: Falta el archivo requerido pages/login.php\n");
    exit(1);
}

$loginContent = file_get_contents($loginPageFile);

// Exigir login local preservado (campos de contraseña y usuario/identificador o formulario local)
$hasPasswordField = (bool) preg_match('/<input[^>]+type=[\'"]password[\'"]/i', $loginContent);
$hasUsernameField = (bool) preg_match('/<input[^>]+name=[\'"](?:username|usuario|user|email|login)[\'"]/i', $loginContent) ||
                    (bool) preg_match('/<input[^>]+type=[\'"](?:text|email)[\'"]/i', $loginContent);
$hasSubmitAction  = (bool) preg_match('/<button[^>]+type=[\'"]submit[\'"]|<input[^>]+type=[\'"]submit[\'"]|<form[^>]+method=[\'"]post[\'"]/i', $loginContent);

if (!$hasPasswordField || !$hasUsernameField || !$hasSubmitAction) {
    fwrite(STDERR, "FALLO: El login local no esta preservado en pages/login.php (faltan campos de autenticacion local)\n");
    exit(1);
}

// Exigir boton SSO condicionado al flag sin red ni DB
$hasFlagCondition = (bool) preg_match('/if\s*\([^)]*(?:SSO_ENABLED|SsoConfig)/i', $loginContent);
$hasSsoButton     = (bool) preg_match('/(?:sso|single sign-on)[\s\S]*?(?:button|<a|<input)/i', $loginContent) ||
                    (bool) preg_match('/(?:<button|<a|<input)[^>]*?(?:sso|single sign-on)/i', $loginContent);

if (!$hasFlagCondition) {
    fwrite(STDERR, "FALLO: pages/login.php no condiciona la visualizacion de SSO al flag SSO_ENABLED\n");
    exit(1);
}

if (!$hasSsoButton) {
    fwrite(STDERR, "FALLO: pages/login.php no contiene boton o enlace de SSO\n");
    exit(1);
}

echo "OK: Contrato de feature flag SSO y preservacion de login local validado correctamente.\n";
exit(0);
