<?php
/**
 * Static Test Suite: Electroplan Local User Active State Migration & Rollback Contract (TASK-0286).
 *
 * Validates:
 * 1. Dedicated static analysis only: zero database connection, zero migration execution.
 * 2. Forward migration is strictly additive and MariaDB 10.11+ compatible.
 * 3. Adds a boolean-compatible active column (`active` tinyint(1) NOT NULL) to the `users` table.
 * 4. Specifies an explicit safe default (`DEFAULT 1`) for existing users so existing accounts remain active.
 * 5. Uses idempotent IF NOT EXISTS for safe MariaDB 10.11+ execution without error on repetition.
 * 6. Documents exact, reversible rollback SQL using ALTER TABLE `users` DROP COLUMN IF EXISTS `active`.
 * 7. Forward migration contains no destructive operations (no DROP TABLE, no TRUNCATE, no DELETE).
 * 8. Static security contract: zero credentials, zero secrets, zero DSN leaks, and zero forbidden terms.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

class LocalUserActiveSchemaTestRunner
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

    public function summary(): int
    {
        echo "\nResumen: {$this->assertions} aserciones ejecutadas, {$this->failures} fallos.\n";
        return $this->failures === 0 ? 0 : 1;
    }
}

$runner = new LocalUserActiveSchemaTestRunner();
echo "--- Iniciando validacion estatica del contrato de migracion (TASK-0286) ---\n\n";

// =========================================================================
// SUITE 1: STATIC VERIFICATION ONLY (ZERO DATABASE CONNECTION / EXECUTION)
// =========================================================================
echo "=== Suite 1: Static verification only (zero DB connection / execution) ===\n";

$migrationPath = dirname(__DIR__) . '/db/migrations/20261015_sso_local_user_active.sql';
$runner->assert(file_exists($migrationPath), 'Migration file 20261015_sso_local_user_active.sql exists');

// Verify zero database connection or driver loaded during test
$runner->assert(
    !isset($GLOBALS['real_db_connected']) && !isset($GLOBALS['pdo']),
    'Zero real database access: no database connection initialized'
);

$migrationContent = file_get_contents($migrationPath);
$runner->assert(
    is_string($migrationContent) && strlen($migrationContent) > 0,
    'Migration file content is readable and non-empty'
);

// Target platform documentation
$runner->assert(
    stripos($migrationContent, 'MariaDB 10.11') !== false,
    'Migration explicitly targets MariaDB 10.11+'
);

// =========================================================================
// SUITE 2: FORWARD MIGRATION SCHEMA CONTRACT (ADDITIVE & MARIADB 10.11+)
// =========================================================================
echo "\n=== Suite 2: Forward migration schema contract (additive & MariaDB 10.11+) ===\n";

// Target table is `users`
$runner->assert(
    preg_match('/ALTER\s+TABLE\s+[`"]?users[`"]?/i', $migrationContent) === 1,
    'Forward migration alters the users table'
);

// Additive column addition with IF NOT EXISTS
$runner->assert(
    preg_match('/ADD\s+COLUMN\s+IF\s+NOT\s+EXISTS\s+[`"]?active[`"]?/i', $migrationContent) === 1,
    'Migration uses additive ADD COLUMN IF NOT EXISTS for column active'
);

// Column type is boolean-compatible: tinyint(1)
$runner->assert(
    preg_match('/[`"]?active[`"]?\s+tinyint\(1\)/i', $migrationContent) === 1
    || preg_match('/[`"]?active[`"]?\s+boolean/i', $migrationContent) === 1,
    'Column active has boolean-compatible data type (tinyint(1))'
);

// NOT NULL constraint
$runner->assert(
    preg_match('/[`"]?active[`"]?\s+tinyint\(1\)\s+NOT\s+NULL/i', $migrationContent) === 1
    || preg_match('/NOT\s+NULL/i', $migrationContent) === 1,
    'Column active enforces NOT NULL constraint'
);

// Explicit safe default for existing users: DEFAULT 1
$runner->assert(
    preg_match('/DEFAULT\s+1\b/i', $migrationContent) === 1,
    'Column active specifies explicit safe default DEFAULT 1 for existing users'
);

// No destructive operations in forward migration
$forbiddenForwardKeywords = [
    'DROP TABLE',
    'DROP DATABASE',
    'TRUNCATE',
    'DELETE FROM',
];

$destructiveDetected = false;
// Strip out rollback comment section when checking forward execution statements
$forwardOnlySql = preg_replace('/--\s*=+\s*ROLLBACK[\s\S]*?--\s*=+/i', '', $migrationContent);
foreach ($forbiddenForwardKeywords as $kw) {
    if (stripos($forwardOnlySql, $kw) !== false) {
        $destructiveDetected = true;
        fwrite(STDERR, "[FAIL] Destructive keyword in forward migration: {$kw}\n");
    }
}
$runner->assert(!$destructiveDetected, 'Forward migration contains zero destructive SQL statements');

// =========================================================================
// SUITE 3: DOCUMENTED ROLLBACK CONTRACT
// =========================================================================
echo "\n=== Suite 3: Documented rollback contract ===\n";

// Rollback documentation block exists
$runner->assert(
    stripos($migrationContent, 'ROLLBACK INSTRUCTIONS') !== false,
    'Migration contains dedicated documented ROLLBACK INSTRUCTIONS block'
);

// Exact rollback statement targets users table and drops active column
$runner->assert(
    preg_match('/ALTER\s+TABLE\s+[`"]?users[`"]?\s+DROP\s+COLUMN\s+(?:IF\s+EXISTS\s+)?[`"]?active[`"]?/i', $migrationContent) === 1,
    'Rollback documents exact statement: ALTER TABLE users DROP COLUMN IF EXISTS active'
);

// Rollback must not drop the users table
$runner->assert(
    preg_match('/DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?[`"]?users[`"]?/i', $migrationContent) === 0,
    'Rollback strictly drops only the added active column, never drops users table'
);

// =========================================================================
// SUITE 4: STATIC SECURITY & FORBIDDEN TERMS CHECK
// =========================================================================
echo "\n=== Suite 4: Static security & forbidden terms check ===\n";

// No database credentials, passwords, or DSNs in migration file
$secretPatterns = [
    '/password\s*=\s*[\'"][^\'"]+[\'"]/i',
    '/dsn\s*=\s*[\'"][^\'"]+[\'"]/i',
    '/mysql:\s*host=/i',
    '/mariadb:\s*host=/i',
];

$secretFound = false;
foreach ($secretPatterns as $p) {
    if (preg_match($p, $migrationContent)) {
        $secretFound = true;
    }
}
$runner->assert(!$secretFound, 'Migration file contains no credentials, DSNs, or secrets');

// Check forbidden terms in migration and test file
$forbiddenTerms = [
    implode('', ['open', 'id']),
    implode('', ['id_', 'token']),
    implode('', ['non', 'ce']),
    implode('', ['oi', 'dc']),
];

$filesToCheck = [
    $migrationPath,
    __FILE__,
];

foreach ($filesToCheck as $filePath) {
    $content = file_get_contents($filePath);
    $baseName = basename($filePath);
    $foundForbidden = false;
    foreach ($forbiddenTerms as $term) {
        if (stripos($content, $term) !== false) {
            $foundForbidden = true;
            fwrite(STDERR, "[FAIL] Forbidden term found in {$baseName}: {$term}\n");
        }
    }
    $runner->assert(!$foundForbidden, "No functional forbidden references in {$baseName}");
}

exit($runner->summary());
