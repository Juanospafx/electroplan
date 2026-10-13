<?php
declare(strict_types=1);

/**
 * Contract verification test for Electroplan deployment manifest and checksums.
 *
 * Validates:
 * - Workflow triggers (push main only + workflow_dispatch; no PR/feature deploys)
 * - Concurrency control (fixed production group, cancel-in-progress false)
 * - Artifact exclusions (migrations, tests, docs, .env*, logs, temporales, harness)
 * - Manifest JSON generation inside deploy_artifact/ (format_version, repository, commit_sha, generated_at_utc, workflow_run_id)
 * - Strict absence of sensitive fields in manifest (branch, actor, environment, secrets, config, private content)
 * - Deterministic SHA256SUMS generation for runtime files avoiding recursive autochecksum
 * - FTP deploy target isolation without executing FTP
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

final class DeploymentManifestContractTest
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
        echo "Running DeploymentManifestContractTest...\n";

        $this->testDeployWorkflowExists();
        $this->testTriggersContract();
        $this->testConcurrencyContract();
        $this->testArtifactExclusionsContract();
        $this->testManifestGenerationInWorkflow();
        $this->testManifestStructureAndContentContract();
        $this->testManifestNoSensitiveFieldsContract();
        $this->testChecksumsGenerationContract();
        $this->testDeterministicChecksumSimulation();
        $this->testFtpDeployActionContract();
        $this->testLineEndingEquivalenceContract();

        echo "\nSummary: {$this->assertions} assertions executed.\n";

        if (!empty($this->failures)) {
            echo "FAILED (" . count($this->failures) . " errors):\n";
            foreach ($this->failures as $failure) {
                echo "  - {$failure}\n";
            }
            return 1;
        }

        echo "SUCCESS: All deployment manifest contract tests passed.\n";
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

    public static function normalizeLineEndings(string $content): string
    {
        return str_replace("\r\n", "\n", $content);
    }

    private function getDeployWorkflowContent(): string
    {
        $path = $this->rootDir . '/.github/workflows/deploy.yml';
        if (!file_exists($path)) {
            $this->assert(false, "Workflow file .github/workflows/deploy.yml exists");
            return '';
        }
        return self::normalizeLineEndings((string)file_get_contents($path));
    }

    private function getRawDeployWorkflowContent(): string
    {
        $path = $this->rootDir . '/.github/workflows/deploy.yml';
        if (!file_exists($path)) {
            $this->assert(false, "Workflow file .github/workflows/deploy.yml exists");
            return '';
        }
        return (string)file_get_contents($path);
    }

    private function testDeployWorkflowExists(): void
    {
        $path = $this->rootDir . '/.github/workflows/deploy.yml';
        $this->assert(file_exists($path), "Deploy workflow exists at .github/workflows/deploy.yml");
    }

    private function testTriggersContract(?string $content = null): void
    {
        $content = $content ?? $this->getDeployWorkflowContent();

        $this->assert(
            preg_match('/push\s*:\s*\r?\n\s*branches\s*:\s*(\[\s*"?main"?\s*\]|\r?\n\s*-\s*"?main"?)/', $content) === 1,
            "Workflow push trigger is restricted exclusively to 'main' branch"
        );

        $this->assert(
            preg_match('/branches\s*:.*?(feature|develop|\*)/i', $content) === 0,
            "Workflow push trigger does not deploy feature branches or wildcards"
        );

        $this->assert(
            preg_match('/workflow_dispatch\s*:/', $content) === 1,
            "Workflow maintains 'workflow_dispatch' trigger for manual execution"
        );

        $this->assert(
            preg_match('/pull_request\s*:/', $content) === 0,
            "Workflow strictly excludes 'pull_request' trigger"
        );
    }

    private function testConcurrencyContract(): void
    {
        $content = $this->getDeployWorkflowContent();

        $hasFixedGroup = (bool)preg_match('/group\s*:\s*["\']?([a-zA-Z0-9_-]+)["\']?/', $content, $matches);
        $groupName = $hasFixedGroup ? ($matches[1] ?? '') : '';
        $this->assert(
            $hasFixedGroup && $groupName === 'electroplan-production',
            "Workflow maintains fixed production concurrency group ('electroplan-production')"
        );

        $this->assert(
            preg_match('/cancel-in-progress\s*:\s*false/', $content) === 1,
            "Concurrency cancel-in-progress remains explicitly set to false"
        );
    }

    private function testArtifactExclusionsContract(): void
    {
        $content = $this->getDeployWorkflowContent();

        $requiredExclusions = [
            '.git',
            '.github',
            '.gitignore',
            '.env',
            'tests',
            'docs',
            'harness',
            'db',
            'db/migrations',
            'logs',
            'dumps',
            'temp',
            'tmp',
        ];

        foreach ($requiredExclusions as $exclusion) {
            $this->assert(
                str_contains($content, "--exclude='{$exclusion}") ||
                str_contains($content, "--exclude=\".*{$exclusion}") ||
                str_contains($content, "--exclude='.*{$exclusion}") ||
                str_contains($content, "--exclude=\"{$exclusion}"),
                "Artifact build step preserves exclusion of '{$exclusion}'"
            );
        }
    }

    private function testManifestGenerationInWorkflow(): void
    {
        $content = $this->getDeployWorkflowContent();

        $this->assert(
            str_contains($content, 'deploy_artifact/deployment_manifest.json'),
            "Workflow targets deploy_artifact/deployment_manifest.json for manifest generation"
        );

        $this->assert(
            str_contains($content, 'test -f deploy_artifact/deployment_manifest.json'),
            "Workflow explicitly verifies existence of deployment_manifest.json in deploy_artifact"
        );
    }

    private function testManifestStructureAndContentContract(?string $content = null): void
    {
        $content = $content ?? $this->getDeployWorkflowContent();

        // Required manifest fields per acceptance criteria
        $requiredFields = [
            'format_version',
            'repository',
            'commit_sha',
            'generated_at_utc',
            'workflow_run_id',
        ];

        foreach ($requiredFields as $field) {
            $this->assert(
                str_contains($content, "\"{$field}\""),
                "Manifest template includes required field '{$field}'"
            );
        }

        // Verify GitHub Actions context interpolations
        $this->assert(
            str_contains($content, '${{ github.repository }}'),
            "Manifest uses \${{ github.repository }} for repository field"
        );

        $this->assert(
            str_contains($content, '${{ github.sha }}'),
            "Manifest uses \${{ github.sha }} for commit_sha field"
        );

        $this->assert(
            str_contains($content, '${{ github.run_id }}'),
            "Manifest uses \${{ github.run_id }} for workflow_run_id field"
        );

        $this->assert(
            str_contains($content, 'date -u') || str_contains($content, 'NOW_UTC'),
            "Manifest uses UTC timestamp generation for generated_at_utc"
        );

        // Extract JSON snippet and simulate decoding
        $jsonPattern = '/cat\s*<<\s*EOF\s*>\s*deploy_artifact\/deployment_manifest\.json\s*\r?\n(.*?)\r?\n\s*EOF/s';
        $matched = preg_match($jsonPattern, $content, $matches);
        $this->assert($matched === 1, "Extracted manifest JSON block from deploy workflow");

        if ($matched === 1) {
            $rawJson = trim($matches[1]);
            // Simulate variable expansion as performed during GitHub Actions run
            $simulatedJson = str_replace(
                [
                    '${{ github.repository }}',
                    '${{ github.sha }}',
                    '${{ github.run_id }}',
                    '${NOW_UTC}',
                    '$(date -u +"%Y-%m-%dT%H:%M:%SZ")',
                ],
                [
                    'codex/electroplan',
                    '0123456789abcdef0123456789abcdef01234567',
                    '987654321',
                    '2026-10-13T10:30:00Z',
                    '2026-10-13T10:30:00Z',
                ],
                $rawJson
            );

            $decoded = json_decode($simulatedJson, true);
            $this->assert(
                json_last_error() === JSON_ERROR_NONE && is_array($decoded),
                "Simulated manifest parses as valid JSON"
            );

            if (is_array($decoded)) {
                $decodedKeys = array_keys($decoded);
                sort($decodedKeys);
                $expectedSorted = $requiredFields;
                sort($expectedSorted);

                $this->assert(
                    $decodedKeys === $expectedSorted,
                    "Manifest contains exactly the required fields without extra or missing fields"
                );

                $this->assert(
                    preg_match('/^\d+\.\d+(\.\d+)?$/', (string)$decoded['format_version']) === 1,
                    "format_version conforms to version pattern ('{$decoded['format_version']}')"
                );

                $this->assert(
                    preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', (string)$decoded['generated_at_utc']) === 1,
                    "generated_at_utc conforms to ISO-8601 UTC timestamp format"
                );

                $this->assert(
                    $decoded['repository'] === 'codex/electroplan',
                    "repository field populated correctly"
                );

                $this->assert(
                    $decoded['commit_sha'] === '0123456789abcdef0123456789abcdef01234567',
                    "commit_sha field populated correctly"
                );

                $this->assert(
                    (string)$decoded['workflow_run_id'] === '987654321',
                    "workflow_run_id field populated correctly"
                );
            }
        }
    }

    private function testManifestNoSensitiveFieldsContract(?string $content = null): void
    {
        $content = $content ?? $this->getDeployWorkflowContent();

        $forbiddenKeys = [
            'branch',
            'ref',
            'actor',
            'triggering_actor',
            'environment',
            'secrets',
            'token',
            'password',
            'config',
            'private',
        ];

        // Isolate the manifest section in the workflow
        $manifestSection = '';
        if (preg_match('/cat\s*<<\s*EOF\s*>\s*deploy_artifact\/deployment_manifest\.json.*?\r?\n\s*EOF/s', $content, $m)) {
            $manifestSection = $m[0];
        }

        $this->assert(!empty($manifestSection), "Located manifest definition section in workflow");

        foreach ($forbiddenKeys as $forbidden) {
            $this->assert(
                !preg_match("/\"{$forbidden}\"\s*:/i", $manifestSection),
                "Manifest strictly excludes sensitive field key '{$forbidden}'"
            );
            $this->assert(
                !preg_match("/github\.{$forbidden}/i", $manifestSection),
                "Manifest does not reference github.{$forbidden} context"
            );
        }

        // Verify no secrets or env vars are embedded in manifest
        $this->assert(
            !str_contains($manifestSection, 'secrets.'),
            "No secrets are referenced in manifest creation"
        );
        $this->assert(
            !str_contains($manifestSection, '.env'),
            "No .env configuration is referenced in manifest creation"
        );
    }

    private function testChecksumsGenerationContract(?string $content = null): void
    {
        $content = $content ?? $this->getDeployWorkflowContent();

        $this->assert(
            str_contains($content, 'SHA256SUMS'),
            "Workflow specifies generation of SHA256SUMS"
        );

        $this->assert(
            str_contains($content, 'test -f deploy_artifact/SHA256SUMS'),
            "Workflow explicitly verifies existence of deploy_artifact/SHA256SUMS"
        );

        $this->assert(
            str_contains($content, 'sha256sum'),
            "Workflow uses sha256sum utility for checksum generation"
        );

        $this->assert(
            str_contains($content, 'sort') || str_contains($content, 'LC_ALL=C sort'),
            "Checksum generation includes sorting for deterministic file ordering"
        );

        $this->assert(
            str_contains($content, 'find') && str_contains($content, '-print0'),
            "Checksum generation uses find with -print0 for null-delimited path handling"
        );

        $this->assert(
            str_contains($content, 'LC_ALL=C sort -z') || (str_contains($content, 'sort -z') && str_contains($content, 'LC_ALL=C')),
            "Checksum generation includes null-delimited sorting (LC_ALL=C sort -z) for deterministic file ordering"
        );

        $this->assert(
            str_contains($content, 'xargs -0'),
            "Checksum generation uses xargs -0 to consume null-delimited file stream"
        );

        // Check prevention of recursive autochecksum
        $this->assert(
            str_contains($content, "! -name 'SHA256SUMS'") ||
            str_contains($content, '! -name "SHA256SUMS"') ||
            str_contains($content, "! -name SHA256SUMS"),
            "Checksum generation explicitly excludes SHA256SUMS to avoid recursive autochecksum"
        );

        // Check relative paths (executed inside deploy_artifact)
        $this->assert(
            str_contains($content, 'cd deploy_artifact'),
            "Checksum generation executes relative to deploy_artifact directory"
        );
    }

    private function testDeterministicChecksumSimulation(): void
    {
        // Simulate runtime files in arbitrary order
        $runtimeFiles = [
            'index.php' => "<?php echo 'index'; ?>",
            'api/index.php' => "<?php echo 'api'; ?>",
            'core/Bootstrap.php' => "<?php echo 'bootstrap'; ?>",
            'assets/css/style.css' => "body { margin: 0; }",
            'deployment_manifest.json' => '{"format_version":"1.0.0"}',
        ];

        // Simulate 2 runs with different initial traversal order
        $keysRun1 = array_keys($runtimeFiles);
        $keysRun2 = array_reverse($keysRun1);

        // Deterministic sort simulation (matching LC_ALL=C sort)
        sort($keysRun1, SORT_STRING);
        sort($keysRun2, SORT_STRING);

        $this->assert(
            $keysRun1 === $keysRun2,
            "File path sorting produces deterministic order regardless of directory discovery order"
        );

        // Verify SHA256SUMS is excluded from hashed set
        $candidateFiles = array_merge($keysRun1, ['SHA256SUMS']);
        $filteredFiles = array_values(array_filter($candidateFiles, fn($f) => $f !== 'SHA256SUMS'));

        $this->assert(
            !in_array('SHA256SUMS', $filteredFiles, true),
            "Checksum simulation strictly excludes SHA256SUMS from hash input"
        );

        // Generate simulated checksum lines
        $checksumLines = [];
        foreach ($filteredFiles as $file) {
            $hash = hash('sha256', $runtimeFiles[$file] ?? '');
            $checksumLines[] = "{$hash}  ./{$file}";
        }

        $checksumOutput = implode("\n", $checksumLines) . "\n";
        $this->assert(
            count($checksumLines) === count($runtimeFiles),
            "Checksum simulation produces an entry for each runtime file"
        );

        $this->assert(
            !str_contains($checksumOutput, 'SHA256SUMS'),
            "Checksum output does not contain any entry for SHA256SUMS itself"
        );

        // Verify null-delimited path stream roundtrip simulation
        $nullDelimited = implode("\0", $filteredFiles) . "\0";
        $unpacked = array_values(array_filter(explode("\0", $nullDelimited), fn($p) => $p !== ''));
        $this->assert(
            $unpacked === $filteredFiles,
            "Null-delimited stream simulation preserves exact ordered file paths"
        );
    }

    private function testFtpDeployActionContract(): void
    {
        $content = $this->getDeployWorkflowContent();

        $this->assert(
            str_contains($content, 'SamKirkland/FTP-Deploy-Action@v4.3.5'),
            "Workflow uses SamKirkland/FTP-Deploy-Action@v4.3.5"
        );

        $this->assert(
            preg_match('/local-dir\s*:\s*(\.\/)?deploy_artifact\/?/', $content) === 1,
            "FTP action local-dir targets deploy_artifact exclusively"
        );

        $this->assert(
            preg_match('/dangerous-clean-slate\s*:\s*false/', $content) === 1,
            "dangerous-clean-slate remains set to false"
        );
    }

    private function testLineEndingEquivalenceContract(): void
    {
        $raw = $this->getRawDeployWorkflowContent();
        if ($raw === '') {
            return;
        }

        $lfContent = str_replace("\r\n", "\n", $raw);
        $crlfContent = str_replace("\n", "\r\n", $lfContent);

        // 1. Line ending normalization produces identical LF content
        $this->assert(
            self::normalizeLineEndings($lfContent) === self::normalizeLineEndings($crlfContent),
            "Workflow content normalizes identically across LF and CRLF line endings"
        );

        // 2. Push trigger contract validates equivalently under LF and CRLF
        $triggerRegex = '/push\s*:\s*\r?\n\s*branches\s*:\s*(\[\s*"?main"?\s*\]|\r?\n\s*-\s*"?main"?)/';
        $this->assert(
            preg_match($triggerRegex, $lfContent) === 1 && preg_match($triggerRegex, $crlfContent) === 1,
            "Push triggers contract matches equivalently on both LF and CRLF workflows"
        );

        // 3. Exercise testTriggersContract directly on both LF and CRLF representations
        $this->testTriggersContract($lfContent);
        $this->testTriggersContract($crlfContent);

        // 4. Manifest block extraction operates identically on both LF and CRLF
        $manifestRegex = '/cat\s*<<\s*EOF\s*>\s*deploy_artifact\/deployment_manifest\.json\s*\r?\n(.*?)\r?\n\s*EOF/s';
        $matchedLf = preg_match($manifestRegex, $lfContent, $matchesLf);
        $matchedCrlf = preg_match($manifestRegex, $crlfContent, $matchesCrlf);
        $this->assert(
            $matchedLf === 1 && $matchedCrlf === 1,
            "Manifest heredoc block extracts equivalently from both LF and CRLF workflows"
        );

        if ($matchedLf === 1 && $matchedCrlf === 1) {
            $jsonLf = trim($matchesLf[1]);
            $jsonCrlf = trim($matchesCrlf[1]);
            $this->assert(
                self::normalizeLineEndings($jsonLf) === self::normalizeLineEndings($jsonCrlf),
                "Extracted manifest template content matches identically between LF and CRLF workflows"
            );
        }

        // 5. Exercise manifest content and sensitive field contracts on both representations
        $this->testManifestStructureAndContentContract($lfContent);
        $this->testManifestStructureAndContentContract($crlfContent);
        $this->testManifestNoSensitiveFieldsContract($lfContent);
        $this->testManifestNoSensitiveFieldsContract($crlfContent);

        // 6. Null-delimited checksum command matches equivalently on both LF and CRLF
        $checksumRegex = '/find\s+\.\s+-type\s+f\s+!\s+-name\s+[\'"]?SHA256SUMS[\'"]?\s+-print0\s*\|\s*LC_ALL=C\s+sort\s+-z\s*\|\s*xargs\s+-0\s+sha256sum\s*>\s*SHA256SUMS/';
        $this->assert(
            preg_match($checksumRegex, $lfContent) === 1 && preg_match($checksumRegex, $crlfContent) === 1,
            "Null-delimited checksum command matches equivalently on both LF and CRLF workflows"
        );

        // 7. Exercise checksums generation assertions on both representations
        $this->testChecksumsGenerationContract($lfContent);
        $this->testChecksumsGenerationContract($crlfContent);
    }
}

$testRunner = new DeploymentManifestContractTest(dirname(__DIR__));
exit($testRunner->run());
