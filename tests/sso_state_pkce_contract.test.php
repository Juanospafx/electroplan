<?php
/**
 * Test de contrato: State de un solo uso y PKCE S256 para Electroplan SsoFlow.
 *
 * TASK-0223: Contrato mínimo state y PKCE Electroplan.
 *
 * Criterios de aceptación:
 * 1. State aleatorio, expiración corta, comparación segura, consumo único y eliminación tras callback.
 * 2. PKCE verifier de alta entropía guardado sólo en sesión, challenge BASE64URL(SHA256(verifier)),
 *    método S256; rechazo explícito de método plain.
 * 3. Determinista, sin red ni base de datos.
 * 4. Único rojo esperado: ausencia de core/auth/SsoFlow.php o contrato aún no implementado.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Mini harness de aserciones autónomo y determinista
class SsoContractTestRunner
{
    private int $assertions = 0;
    private int $failures = 0;

    public function assert(bool $condition, string $message): void
    {
        $this->assertions++;
        if (!$condition) {
            $this->failures++;
            fwrite(STDERR, "[FAIL] {$message}\n");
        } else {
            echo "[PASS] {$message}\n";
        }
    }

    public function assertThrows(callable $fn, string $expectedExceptionClass, string $message): void
    {
        $this->assertions++;
        try {
            $fn();
            $this->failures++;
            fwrite(STDERR, "[FAIL] Se esperaba la excepción {$expectedExceptionClass} pero no se lanzó. ({$message})\n");
        } catch (\Throwable $e) {
            if ($e instanceof $expectedExceptionClass) {
                echo "[PASS] Excepción capturada correctamente ({$expectedExceptionClass}): {$message}\n";
            } else {
                $this->failures++;
                $actual = get_class($e);
                fwrite(STDERR, "[FAIL] Se esperaba {$expectedExceptionClass} pero se recibió {$actual}. ({$message})\n");
            }
        }
    }

    public function summary(): int
    {
        echo "\nResumen: {$this->assertions} aserciones ejecutadas, {$this->failures} fallos.\n";
        return $this->failures === 0 ? 0 : 1;
    }
}

// 1. Simulación de sesión determinista en entorno CLI
if (!isset($_SESSION) || !is_array($_SESSION)) {
    $_SESSION = [];
}

// 2. Comprobación del archivo core/auth/SsoFlow.php
$ssoFlowPath = dirname(__DIR__) . '/core/auth/SsoFlow.php';

if (!file_exists($ssoFlowPath)) {
    fwrite(STDERR, "[RED EXPECTED] core/auth/SsoFlow.php no existe en: {$ssoFlowPath}\n");
    fwrite(STDERR, "El contrato exige la existencia e implementación de core/auth/SsoFlow.php.\n");
    exit(1);
}

require_once $ssoFlowPath;

// 3. Resolución de la clase SsoFlow (con namespace o en espacio global)
$className = null;
if (class_exists('\\Electroplan\\Auth\\SsoFlow')) {
    $className = '\\Electroplan\\Auth\\SsoFlow';
} elseif (class_exists('SsoFlow')) {
    $className = 'SsoFlow';
}

if ($className === null) {
    fwrite(STDERR, "[RED EXPECTED] La clase SsoFlow no está definida en core/auth/SsoFlow.php.\n");
    exit(1);
}

// 4. Verificación de métodos del contrato mínimo
$requiredMethods = [
    'generateState',
    'validateState',
    'generatePkce',
    'computeCodeChallenge',
    'consumeCodeVerifier',
];

foreach ($requiredMethods as $method) {
    if (!method_exists($className, $method)) {
        fwrite(STDERR, "[RED EXPECTED] El método requerido '{$method}' no está implementado en {$className}.\n");
        exit(1);
    }
}

$runner = new SsoContractTestRunner();
echo "--- Iniciando validación de contrato SsoFlow (State + PKCE S256) ---\n\n";

// =========================================================================
// SUITE 1: CONTRATO DE STATE (Aleatorio, Expiración corta, Comparación segura,
//                            Consumo único, Eliminación tras callback)
// =========================================================================
echo "=== Suite 1: State ===\n";
$_SESSION = [];
$flow = new $className();

// 1.1 State aleatorio y alta entropía
$state1 = $flow->generateState(300);
$state2 = $flow->generateState(300);

$runner->assert(
    is_string($state1) && strlen($state1) >= 32,
    "El state debe ser una cadena con al menos 32 caracteres de entropía (longitud: " . (is_string($state1) ? strlen($state1) : 0) . ")"
);

$runner->assert(
    $state1 !== $state2,
    "Llamadas consecutivas a generateState deben generar valores aleatorios distintos"
);

$runner->assert(
    is_string($state1) && preg_match('/^[A-Za-z0-9\-_.~]+$/', $state1) === 1,
    "El state debe contener únicamente caracteres seguros para URL"
);

// 1.2 Comparación segura: rechazo de state manipulado o vacío
$runner->assert(
    $flow->validateState($state1 . '_tampered') === false,
    "validateState debe rechazar un state manipulado/inválido"
);

$runner->assert(
    $flow->validateState('') === false,
    "validateState debe rechazar un state vacío"
);

// 1.3 Consumo único y eliminación tras callback (Anti-replay)
$validFirstTime = $flow->validateState($state1);
$runner->assert(
    $validFirstTime === true,
    "El state legítimo debe ser aceptado en su primera verificación"
);

$validSecondTime = $flow->validateState($state1);
$runner->assert(
    $validSecondTime === false,
    "El state debe ser de consumo único: un segundo intento con el mismo state debe fallar (prevención de replay)"
);

// Verificar eliminación en sesión tras callback
$state1StillInSession = false;
foreach ($_SESSION as $key => $val) {
    if ($val === $state1) {
        $state1StillInSession = true;
    }
    if (is_array($val) && in_array($state1, $val, true)) {
        $state1StillInSession = true;
    }
}
$runner->assert(
    !$state1StillInSession,
    "El state debe ser eliminado de \$_SESSION inmediatamente tras ser validado"
);

// 1.4 Expiración corta
$now = time();
$expiringFlow = new $className();
$shortLivedState = $expiringFlow->generateState(300, $now);

// Válido dentro de la ventana de expiración
$runner->assert(
    $expiringFlow->validateState($shortLivedState, $now + 150) === true,
    "El state debe ser válido dentro de su ventana de vida (ej. t + 150s con TTL de 300s)"
);

// Generar otro state para validar vencimiento
$expiredState = $expiringFlow->generateState(300, $now);

// Inválido tras superar TTL
$runner->assert(
    $expiringFlow->validateState($expiredState, $now + 301) === false,
    "El state debe ser rechazado si ha expirado (t + 301s con TTL de 300s)"
);


// =========================================================================
// SUITE 2: CONTRATO DE PKCE (Verifier de alta entropía en sesión,
//                            Challenge BASE64URL(SHA256(verifier)),
//                            Método S256, Rechazo estricto de plain)
// =========================================================================
echo "\n=== Suite 2: PKCE S256 ===\n";
$_SESSION = [];
$pkceFlow = new $className();

// 2.1 Generación de PKCE S256 y parámetros públicos
$pkceParams = $pkceFlow->generatePkce('S256');

$runner->assert(
    is_array($pkceParams),
    "generatePkce debe retornar un array con los parámetros públicos para autorización"
);

$runner->assert(
    isset($pkceParams['code_challenge']) && is_string($pkceParams['code_challenge']),
    "Los parámetros públicos deben contener 'code_challenge'"
);

$runner->assert(
    isset($pkceParams['code_challenge_method']) && $pkceParams['code_challenge_method'] === 'S256',
    "El parámetro 'code_challenge_method' debe ser estrictamente 'S256'"
);

$runner->assert(
    !isset($pkceParams['code_verifier']),
    "code_verifier NO debe ser expuesto en los parámetros públicos; debe guardarse sólo en sesión"
);

// 2.2 Verifier de alta entropía guardado en sesión
$storedVerifier = $pkceFlow->consumeCodeVerifier();

$runner->assert(
    is_string($storedVerifier) && strlen($storedVerifier) >= 43 && strlen($storedVerifier) <= 128,
    "RFC 7636: el verifier guardado debe tener entre 43 y 128 caracteres (longitud: " . (is_string($storedVerifier) ? strlen($storedVerifier) : 0) . ")"
);

$runner->assert(
    is_string($storedVerifier) && preg_match('/^[A-Za-z0-9\-_.~]+$/', $storedVerifier) === 1,
    "RFC 7636: el verifier debe contener exclusivamente caracteres no reservados [A-Za-z0-9-._~]"
);

// 2.3 Challenge BASE64URL(SHA256(verifier))
$expectedChallenge = rtrim(strtr(base64_encode(hash('sha256', (string)$storedVerifier, true)), '+/', '-_'), '=');

$runner->assert(
    isset($pkceParams['code_challenge']) && $pkceParams['code_challenge'] === $expectedChallenge,
    "code_challenge debe coincidir exactamente con BASE64URL(SHA256(verifier))"
);

$runner->assert(
    isset($pkceParams['code_challenge']) && strpos($pkceParams['code_challenge'], '=') === false,
    "code_challenge no debe contener relleno '=' (padding base64url omitido)"
);

$runner->assert(
    isset($pkceParams['code_challenge']) && strpos($pkceParams['code_challenge'], '+') === false && strpos($pkceParams['code_challenge'], '/') === false,
    "code_challenge debe ser URL-safe (sin '+' ni '/')"
);

// 2.4 Verificación del helper computeCodeChallenge
$computedHelper = $pkceFlow->computeCodeChallenge((string)$storedVerifier, 'S256');
$runner->assert(
    $computedHelper === $expectedChallenge,
    "computeCodeChallenge('{$storedVerifier}', 'S256') debe generar el challenge esperado"
);

// 2.5 Consumo único del verifier en sesión
$secondVerifierRetrieval = $pkceFlow->consumeCodeVerifier();
$runner->assert(
    $secondVerifierRetrieval === null,
    "consumeCodeVerifier debe retornar null en un segundo intento (consumo único para intercambio de token)"
);

// 2.6 Rechazo estricto de método 'plain' y métodos inválidos
$runner->assertThrows(
    function () use ($pkceFlow) {
        $pkceFlow->generatePkce('plain');
    },
    \InvalidArgumentException::class,
    "generatePkce('plain') debe ser rechazado con InvalidArgumentException"
);

$runner->assertThrows(
    function () use ($pkceFlow, $storedVerifier) {
        $pkceFlow->computeCodeChallenge((string)$storedVerifier, 'plain');
    },
    \InvalidArgumentException::class,
    "computeCodeChallenge con 'plain' debe ser rechazado con InvalidArgumentException"
);

$runner->assertThrows(
    function () use ($pkceFlow) {
        $pkceFlow->generatePkce('none');
    },
    \InvalidArgumentException::class,
    "generatePkce('none') debe ser rechazado con InvalidArgumentException"
);

// Resumen y salida
exit($runner->summary());
