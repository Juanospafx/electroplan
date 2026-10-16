<?php
declare(strict_types=1);

/**
 * Static contract test for core/auth/SsoFlow.php (Electroplan)
 * TASK-0239
 */

$targetRelativePath = 'core/auth/SsoFlow.php';
$targetFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'SsoFlow.php';

if (!file_exists($targetFile)) {
    if (file_exists($targetRelativePath)) {
        $targetFile = $targetRelativePath;
    }
}

if (!file_exists($targetFile)) {
    fwrite(STDERR, "FAIL: Target file does not exist: {$targetRelativePath}\n");
    exit(1);
}

$contents = file_get_contents($targetFile);
if ($contents === false) {
    fwrite(STDERR, "FAIL: Could not read target file: {$targetFile}\n");
    exit(1);
}

$errors = [];

// Helper for string containment
$contains = static function (string $haystack, string $needle): bool {
    return strpos($haystack, $needle) !== false;
};

// 1. References to hub_user_id, provider, active
if (!$contains($contents, 'hub_user_id')) {
    $errors[] = 'Missing reference to "hub_user_id".';
}

if (!$contains($contents, 'provider')) {
    $errors[] = 'Missing reference to "provider".';
}

if (!$contains($contents, 'active')) {
    $errors[] = 'Missing reference to "active".';
}

// 2. Reference to local user
if (!preg_match('/local_user|localUser|local\s+user/i', $contents)) {
    $errors[] = 'Missing reference to local user (e.g. local_user, localUser).';
}

// 3. Explicit roles
if (!preg_match('/\broles?\b/i', $contents)) {
    $errors[] = 'Missing explicit reference to role/roles.';
}

// 4. Rejections of central disabled and app unauthorized
if (!preg_match('/central[_\s\-]?disabled/i', $contents)) {
    $errors[] = 'Missing rejection reference for "central disabled" (e.g. central_disabled).';
}

if (!preg_match('/app[_\s\-]?unauthorized/i', $contents)) {
    $errors[] = 'Missing rejection reference for "app unauthorized" (e.g. app_unauthorized).';
}

// 5. No literal assignment of "admin" as fallback or default
$forbiddenFallbackPatterns = [
    '/\?\?\s*[\'"]admin[\'"]/i' => 'Null-coalesce fallback to literal "admin" (e.g. ?? "admin")',
    '/\?:\s*[\'"]admin[\'"]/i' => 'Ternary fallback to literal "admin" (e.g. ?: "admin")',
    '/\b(default|fallback)[_\w]*\s*(?:=>|:|=)\s*[\'"]admin[\'"]/i' => 'Default or fallback configuration with literal "admin"',
    '/\bdefault\s*:\s*(?:return\s+)?[\'"]admin[\'"]/i' => 'Switch default case returning literal "admin"',
    '/(?<![=!<>])=\s*[\'"]admin[\'"]/i' => 'Direct assignment of literal "admin"',
];

foreach ($forbiddenFallbackPatterns as $pattern => $description) {
    if (preg_match($pattern, $contents)) {
        $errors[] = "Forbidden fallback/default detected: {$description}. Literal \"admin\" fallback or default is prohibited.";
    }
}

if (!empty($errors)) {
    fwrite(STDERR, "FAIL: Static contract violations found in {$targetRelativePath}:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, " - {$error}\n");
    }
    exit(1);
}

echo "PASS: Static contract satisfied for {$targetRelativePath}.\n";
exit(0);
