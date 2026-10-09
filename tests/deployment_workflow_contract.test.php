<?php
declare(strict_types=1);

/**
 * Contract verification test for Electroplan deployment workflow and gitignore.
 *
 * Validates:
 * - Workflow triggers (main push only + workflow_dispatch; no PR/feature deploys)
 * - Concurrency control (fixed production group, cancel-in-progress false)
 * - Deploy artifact construction and exclusions (api/, core/, assets preserved)
 * - FTP deploy target isolation (local-dir set exclusively to deploy_artifact)
 * - FTP port 21 documented retention
 * - Secret hygiene (no HMAC secrets, no shared/reused secrets)
 * - .gitignore protection (env, config, logs, dumps, tmp, caches, outputs, harness; runtime preserved)
 */

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

final class DeploymentWorkflowContractTest
{
    private string $rootDir;
    private int $assertions = 0;
    private array $failures = [];

    public function __construct(string $rootDir)
    {
        $this->rootDir = rtrim($rootDir, '/\\');
    }

    public function run(): int
    {
        echo "Running DeploymentWorkflowContractTest...\n";

        $this->testDeployWorkflowExists();
        $this->testTriggersContract();
        $this->testConcurrencyContract();
        $this->testArtifactBuildAndExclusions();
        $this->testFtpDeployActionContract();
        $this->testSecretHygieneContract();
        $this->testGitignoreProtection();
        $this->testRuntimeNotIgnored();

        echo "\nSummary: {$this->assertions} assertions executed.\n";

        if (!empty($this->failures)) {
            echo "FAILED (" . count($this->failures) . " errors):\n";
            foreach ($this->failures as $failure) {
                echo "  - {$failure}\n";
            }
            return 1;
        }

        echo "SUCCESS: All deployment workflow contract tests passed.\n";
        return 0;
    }

    private function assert(bool $condition, string $message): void
    {
        $this->assertions++;
        if ($condition) {
            echo "  [PASS] {$message}\n";
        } else {
            echo "  [FAIL] {$message}\n";
            $this->failures[] = $message;
        }
    }

    private function getDeployWorkflowContent(): string
    {
        $path = $this->rootDir . '/.github/workflows/deploy.yml';
        if (!file_exists($path)) {
            $this->assert(false, "Workflow file .github/workflows/deploy.yml exists");
            return '';
        }
        return (string)file_get_contents($path);
    }

    private function getGitignoreContent(): string
    {
        $path = $this->rootDir . '/.gitignore';
        if (!file_exists($path)) {
            $this->assert(false, "File .gitignore exists");
            return '';
        }
        return (string)file_get_contents($path);
    }

    private function testDeployWorkflowExists(): void
    {
        $path = $this->rootDir . '/.github/workflows/deploy.yml';
        $this->assert(file_exists($path), "Deploy workflow exists at .github/workflows/deploy.yml");
    }

    private function testTriggersContract(): void
    {
        $content = $this->getDeployWorkflowContent();

        $this->assert(
            preg_match('/on\s*:/', $content) === 1,
            "Workflow defines trigger events ('on:')"
        );

        $this->assert(
            preg_match('/push\s*:\s*\n\s*branches\s*:\s*(\[\s*"?main"?\s*\]|\n\s*-\s*"?main"?)/', $content) === 1,
            "Workflow push trigger is restricted exclusively to 'main' branch"
        );

        $this->assert(
            preg_match('/branches\s*:.*?(feature|develop|\*)/i', $content) === 0,
            "Workflow push trigger does not deploy feature branches or wildcards"
        );

        $this->assert(
            preg_match('/workflow_dispatch\s*:/', $content) === 1,
            "Workflow includes 'workflow_dispatch' trigger for manual execution"
        );

        $this->assert(
            preg_match('/pull_request\s*:/', $content) === 0,
            "Workflow strictly excludes 'pull_request' trigger (no deploy on PR)"
        );
    }

    private function testConcurrencyContract(): void
    {
        $content = $this->getDeployWorkflowContent();

        $this->assert(
            preg_match('/concurrency\s*:/', $content) === 1,
            "Workflow specifies a concurrency configuration"
        );

        $hasFixedGroup = (bool)preg_match('/group\s*:\s*["\']?([a-zA-Z0-9_-]+)["\']?/', $content, $matches);
        $groupName = $hasFixedGroup ? ($matches[1] ?? '') : '';
        $this->assert(
            $hasFixedGroup && !empty($groupName) && !str_contains($groupName, '$'),
            "Concurrency group is fixed and repo/production-scoped ('{$groupName}') without dynamic branch expansion"
        );

        $this->assert(
            preg_match('/cancel-in-progress\s*:\s*false/', $content) === 1,
            "Concurrency cancel-in-progress is explicitly set to false"
        );
    }

    private function testArtifactBuildAndExclusions(): void
    {
        $content = $this->getDeployWorkflowContent();

        $this->assert(
            str_contains($content, 'deploy_artifact'),
            "Workflow defines deploy_artifact directory"
        );

        $this->assert(
            str_contains($content, 'Build deploy artifact'),
            "Workflow contains step 'Build deploy artifact'"
        );

        // Required exclusions in build step
        $requiredExclusions = [
            '.git',
            '.github',
            '.gitignore',
            '.env',
            'tests',
            'docs',
            'harness',
        ];

        foreach ($requiredExclusions as $exclusion) {
            $this->assert(
                str_contains($content, "--exclude='{$exclusion}") ||
                str_contains($content, "--exclude=\".*{$exclusion}") ||
                str_contains($content, "--exclude='.*{$exclusion}") ||
                str_contains($content, "--exclude=\"{$exclusion}"),
                "Build artifact step excludes '{$exclusion}'"
            );
        }

        // Test exclusion filter simulation against candidate file sets
        $simulatedExcludedPaths = [
            '.git/config',
            '.github/workflows/deploy.yml',
            '.gitignore',
            '.gitattributes',
            '.env',
            '.env.local',
            '.env.production',
            'tests/deployment_workflow_contract.test.php',
            'docs/README.md',
            'harness/Invoke-AntigravityTask.ps1',
            'schemas/task.schema.json',
            'node_modules/dummy/index.js',
            'config.local.php',
        ];

        $simulatedRuntimePaths = [
            'api/index.php',
            'api/v1/plans.php',
            'core/Bootstrap.php',
            'core/Database.php',
            'assets/css/style.css',
            'assets/js/app.js',
            'index.php',
        ];

        // Parse exclusion rules from the workflow run block
        preg_match_all("/--exclude=['\"]?([^'\"]+)['\"]?/", $content, $excludeMatches);
        $rules = $excludeMatches[1] ?? [];

        $this->assert(!empty($rules), "Extracted exclusion rules from workflow artifact build step");

        foreach ($simulatedExcludedPaths as $path) {
            $excluded = $this->pathMatchesAnyExclusion($path, $rules);
            $this->assert($excluded, "Artifact build correctly excludes candidate: {$path}");
        }

        foreach ($simulatedRuntimePaths as $path) {
            $excluded = $this->pathMatchesAnyExclusion($path, $rules);
            $this->assert(!$excluded, "Artifact build preserves runtime candidate: {$path}");
        }
    }

    private function pathMatchesAnyExclusion(string $path, array $rules): bool
    {
        foreach ($rules as $rule) {
            $rulePattern = trim($rule);
            $isDir = str_ends_with($rulePattern, '/');
            $clean = rtrim($rulePattern, '/');

            // Glob conversion
            $cleanRegex = preg_quote($clean, '#');
            $cleanRegex = str_replace(['\*', '\?'], ['.*', '.'], $cleanRegex);

            $regex = '(^|/)' . $cleanRegex . ($isDir ? '(/.*)?$' : '($|/.*)');

            if (preg_match('#' . $regex . '#', $path) === 1) {
                return true;
            }
        }
        return false;
    }

    private function testFtpDeployActionContract(): void
    {
        $content = $this->getDeployWorkflowContent();

        $this->assert(
            str_contains($content, 'SamKirkland/FTP-Deploy-Action@v4.3.5'),
            "Uses SamKirkland/FTP-Deploy-Action@v4.3.5"
        );

        $this->assert(
            preg_match('/local-dir\s*:\s*(\.\/)?deploy_artifact\/?/', $content) === 1,
            "FTP action local-dir is explicitly restricted to deploy_artifact only"
        );

        $this->assert(
            preg_match('/port\s*:\s*21/', $content) === 1,
            "FTP action targets port 21"
        );

        $hasDocumentation = (bool)preg_match('/#.*(?:FTPS|puerto 21|falta de evidencia|conserva)/i', $content);
        $this->assert(
            $hasDocumentation,
            "FTP/21 is documented with rationale regarding FTPS evidence/support"
        );

        $this->assert(
            preg_match('/dangerous-clean-slate\s*:\s*false/', $content) === 1,
            "dangerous-clean-slate is set to false"
        );
    }

    private function testSecretHygieneContract(): void
    {
        $content = $this->getDeployWorkflowContent();

        $this->assert(
            stripos($content, 'HMAC') === false,
            "No HMAC secrets are referenced in deploy workflow"
        );

        $this->assert(
            str_contains($content, '${{ secrets.FTP_SERVER }}') &&
            str_contains($content, '${{ secrets.FTP_USERNAME }}') &&
            str_contains($content, '${{ secrets.FTP_PASSWORD }}'),
            "FTP deploy uses dedicated repository secrets (FTP_SERVER, FTP_USERNAME, FTP_PASSWORD)"
        );

        // Ensure no foreign or cross-service secret reuse
        $this->assert(
            !str_contains($content, 'PROD_FTP_') && !str_contains($content, 'SHARED_'),
            "No secret reuse from external environments or shared pools"
        );
    }

    private function testGitignoreProtection(): void
    {
        $content = $this->getGitignoreContent();
        $this->assert(!empty($content), ".gitignore has content");

        $patterns = array_values(array_filter(
            array_map('trim', explode("\n", $content)),
            fn($line) => $line !== '' && !str_starts_with($line, '#')
        ));

        $testCases = [
            'env' => ['.env', '.env.local', '.env.production'],
            'config privada' => ['config.local.php', 'db.local.php', 'local.config.php'],
            'logs' => ['error.log', 'logs/access.log', 'log/app.log'],
            'dumps' => ['dump.sql', 'backup.dump', 'dumps/db_2026.sql'],
            'temporales' => ['tmp/session.tmp', 'temp/data.temp', 'file.tmp'],
            'caches' => ['cache/routes.cache', '.cache/compiler'],
            'outputs' => ['deploy_artifact/index.php', 'dist/bundle.js', 'build/app', 'outputs/run.json'],
            'harness' => ['harness/New-AntigravityJob.ps1', 'harness/tasks/test.json'],
        ];

        foreach ($testCases as $category => $paths) {
            foreach ($paths as $path) {
                $ignored = $this->isPathGitignored($path, $patterns);
                $this->assert($ignored, ".gitignore protects {$category}: {$path}");
            }
        }
    }

    private function testRuntimeNotIgnored(): void
    {
        $content = $this->getGitignoreContent();
        $patterns = array_values(array_filter(
            array_map('trim', explode("\n", $content)),
            fn($line) => $line !== '' && !str_starts_with($line, '#')
        ));

        $runtimePaths = [
            'api/index.php',
            'api/v1/auth.php',
            'core/Bootstrap.php',
            'core/Database.php',
            'core/Router.php',
            'assets/css/style.css',
            'assets/js/app.js',
            'index.php',
        ];

        foreach ($runtimePaths as $path) {
            $ignored = $this->isPathGitignored($path, $patterns);
            $this->assert(!$ignored, "Runtime file is NOT ignored by .gitignore: {$path}");
        }
    }

    private function isPathGitignored(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $isDirOnly = str_ends_with($pattern, '/');
            $cleanPattern = rtrim($pattern, '/');

            $matchFromRoot = str_starts_with($cleanPattern, '/');
            if ($matchFromRoot) {
                $cleanPattern = substr($cleanPattern, 1);
            }

            $regex = preg_quote($cleanPattern, '#');
            $regex = str_replace(['\*', '\?'], ['.*', '.'], $regex);

            if ($matchFromRoot) {
                $patternRegex = '^' . $regex . ($isDirOnly ? '(/.*)?$' : '($|/.*)');
            } else {
                $patternRegex = '(^|/)' . $regex . ($isDirOnly ? '(/.*)?$' : '($|/.*)');
            }

            if (preg_match('#' . $patternRegex . '#', $path) === 1) {
                return true;
            }
        }
        return false;
    }
}

$testRunner = new DeploymentWorkflowContractTest(dirname(__DIR__));
exit($testRunner->run());
