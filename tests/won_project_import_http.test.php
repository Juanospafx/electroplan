<?php
declare(strict_types=1);

/**
 * Deterministic HTTP test for WonProject endpoint.
 *
 * Exercises the real entrypoint (api/v1/index.php), routes.php,
 * middleware_auth.php, and WonProjectsController.php via a real
 * HTTP server process and php://input.
 */

// --- Test Assertions & Helpers ---

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new \RuntimeException("Assertion failed: $message");
    }
}

function assert_equals($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        $expStr = is_scalar($expected) ? (string)$expected : json_encode($expected);
        $actStr = is_scalar($actual) ? (string)$actual : json_encode($actual);
        throw new \RuntimeException("Assertion failed: $message. Expected: '$expStr', got: '$actStr'");
    }
}

function assert_valid_correlation_id(?string $cid, string $context): void
{
    assert_true(!empty($cid), "$context: correlation_id must not be empty");
    assert_true(
        (bool)preg_match('/^[a-zA-Z0-9._-]{8,128}\z/', (string)$cid),
        "$context: correlation_id format is invalid: '$cid'"
    );
}

function assert_no_leaks(string $rawResponse, array $forbiddenTerms, string $context): void
{
    foreach ($forbiddenTerms as $term) {
        if ($term !== '' && stripos($rawResponse, $term) !== false) {
            throw new \RuntimeException("$context: Leaked sensitive internal term '$term' in response");
        }
    }
}

function compute_hmac(string $method, string $path, string $timestamp, string $rawBody, string $secret): string
{
    $payload = $method . "\n" . $path . "\n" . $timestamp . "\n" . $rawBody;
    return hash_hmac('sha256', $payload, $secret);
}

function get_free_port(): int
{
    $sock = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if (!$sock) {
        return 8989;
    }
    $name = (string)stream_socket_get_name($sock, false);
    fclose($sock);
    $parts = explode(':', $name);
    return (int)end($parts);
}

function link_file_safe(string $source, string $destination): void
{
    $dir = dirname($destination);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    if (file_exists($destination)) {
        @unlink($destination);
    }
    $linked = @link($source, $destination);
    if (!$linked) {
        $linked = @symlink($source, $destination);
    }
    if (!$linked) {
        throw new \RuntimeException("Failed to link '$source' to '$destination'");
    }
}

function remove_dir_recursive(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path) && !is_link($path)) {
            remove_dir_recursive($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function get_valid_contract_payload(): array
{
    $estimateHash = hash('sha256', 'approved-estimate-content-test');
    return [
        'event_id' => 'a1b2c3d4-e5f6-4a1b-8c2d-3e4f5a6b7c8d',
        'occurred_at' => '2026-10-09T10:00:00Z',
        'source_system' => 'takeoff',
        'source_project_id' => 'proj-tk-001',
        'source_bid_id' => 'bid-tk-001',
        'source_estimate_id' => 'est-tk-001',
        'idempotency_key' => 'idemp-key-test-123456789012',
        'correlation_id' => 'corr-id-init-12345678',
        'traceability' => [
            'awarded_by' => [
                'user_id' => 'user-test-01',
                'email' => 'estimator@brightronix.com',
                'display_name' => 'Lead Estimator',
            ],
            'notes' => 'Contract approved via direct award',
        ],
        'approved_estimate' => [
            'estimate_id' => 'est-tk-001',
            'estimate_number' => 'EST-2026-001',
            'revision' => 'r1',
            'approved_at' => '2026-10-09T09:30:00Z',
            'approved_by_user_id' => 'user-test-01',
            'checksum_sha256' => $estimateHash,
            'currency' => 'USD',
            'total_amount' => 25000.50,
            'total_labor_hours' => 120.0,
        ],
        'project' => [
            'number' => 'PRJ-ELP-001',
            'name' => 'Commercial Building Project',
            'client' => [
                'client_id' => 'cli-101',
                'name' => 'Commercial Properties Inc',
                'contact_name' => 'Jane Client',
                'contact_email' => 'jane@commercial.com',
                'contact_phone' => '+1-555-0155',
            ],
            'location' => [
                'address_line1' => '100 Business Parkway',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78701',
                'country' => 'US',
                'latitude' => 30.2672,
                'longitude' => -97.7431,
                'geofence_radius_meters' => 150,
            ],
            'dates' => [
                'estimated_start_date' => '2026-11-01',
                'estimated_completion_date' => '2027-03-01',
            ],
            'assigned_roles' => [
                [
                    'role' => 'project_manager',
                    'user_id' => 'pm-usr-01',
                    'name' => 'Robert Manager',
                    'email' => 'robert.pm@brightronix.com',
                ],
            ],
        ],
        'commercial_summary' => [
            'currency' => 'USD',
            'total_amount' => 25000.50,
            'total_labor_hours' => 120.0,
            'estimate_revision' => 'r1',
            'approved_estimate_hash' => $estimateHash,
        ],
        'materials_snapshot' => [
            'snapshot_id' => 'snap-001',
            'version' => '1.0',
            'generated_at' => '2026-10-09T09:30:00Z',
            'total_items' => 1,
            'items' => [
                [
                    'item_id' => 'itm-001',
                    'item_code' => 'WIRE-12-THHN',
                    'description' => 'Copper Building Wire',
                    'category' => 'Conductors',
                    'quantity' => 1000,
                    'unit_of_measure' => 'ft',
                ],
            ],
        ],
        'documents_manifest' => [
            'manifest_version' => '1.0',
            'total_files' => 1,
            'documents' => [
                [
                    'document_id' => 'doc-001',
                    'file_name' => 'drawings_rev1.pdf',
                    'document_type' => 'drawings',
                    'content_type' => 'application/pdf',
                    'size_bytes' => 1048576,
                    'checksum' => [
                        'algorithm' => 'sha256',
                        'value' => hash('sha256', 'drawings_content'),
                    ],
                    'download_url' => 'https://storage.brightronix.com/docs/drawings_rev1.pdf',
                ],
            ],
        ],
    ];
}

// --- HTTP Client ---

function http_request(string $method, string $path, array $headers, string $body, int $port): array
{
    $fp = @stream_socket_client("tcp://127.0.0.1:$port", $errno, $errstr, 5.0);
    if (!$fp) {
        throw new \RuntimeException("Unable to connect to HTTP test server on port $port: $errstr ($errno)");
    }

    $req = "$method $path HTTP/1.1\r\n";
    $req .= "Host: 127.0.0.1:$port\r\n";
    $req .= "Connection: close\r\n";
    if (!isset($headers['Content-Length']) && !isset($headers['content-length'])) {
        $headers['Content-Length'] = (string)strlen($body);
    }
    foreach ($headers as $k => $v) {
        $req .= "$k: $v\r\n";
    }
    $req .= "\r\n";
    $req .= $body;

    fwrite($fp, $req);

    $raw = '';
    while (!feof($fp)) {
        $chunk = fread($fp, 8192);
        if ($chunk === false || $chunk === '') {
            break;
        }
        $raw .= $chunk;
    }
    fclose($fp);

    $parts = explode("\r\n\r\n", $raw, 2);
    $headerBlock = $parts[0] ?? '';
    $responseBody = $parts[1] ?? '';

    $lines = explode("\r\n", $headerBlock);
    $statusLine = array_shift($lines) ?: '';
    preg_match('#^HTTP/\S+\s+(\d{3})#', $statusLine, $m);
    $statusCode = isset($m[1]) ? (int)$m[1] : 0;

    $parsedHeaders = [];
    foreach ($lines as $line) {
        $colon = strpos($line, ':');
        if ($colon !== false) {
            $hName = strtolower(trim(substr($line, 0, $colon)));
            $hVal = trim(substr($line, $colon + 1));
            $parsedHeaders[$hName] = $hVal;
        }
    }

    if (isset($parsedHeaders['transfer-encoding']) && strtolower($parsedHeaders['transfer-encoding']) === 'chunked') {
        $decoded = '';
        $buf = $responseBody;
        while ($buf !== '') {
            $p = strpos($buf, "\r\n");
            if ($p === false) {
                break;
            }
            $lenHex = substr($buf, 0, $p);
            $chunkLen = hexdec(trim($lenHex));
            if ($chunkLen === 0) {
                break;
            }
            $decoded .= substr($buf, $p + 2, $chunkLen);
            $buf = substr($buf, $p + 2 + $chunkLen + 2);
        }
        $responseBody = $decoded;
    }

    $json = json_decode($responseBody, true);

    return [
        'status' => $statusCode,
        'headers' => $parsedHeaders,
        'raw_body' => $responseBody,
        'json' => is_array($json) ? $json : null,
    ];
}

// --- Environment Setup ---

$repoRoot = realpath(__DIR__ . '/..');
if (!$repoRoot || !is_dir($repoRoot . '/api/v1')) {
    throw new \RuntimeException('Repository root not located correctly from tests directory.');
}

$rawTempBase = sys_get_temp_dir();
$realTempBase = realpath($rawTempBase) ?: $rawTempBase;
$tempDir = $realTempBase . DIRECTORY_SEPARATOR . 'won_proj_http_test_' . bin2hex(random_bytes(8));
mkdir($tempDir, 0777, true);
$tempDir = realpath($tempDir) ?: $tempDir;

$serverProcess = null;
$serverPipes = null;

$cleanup = function () use (&$serverProcess, &$serverPipes, &$tempDir): void {
    if (is_array($serverPipes)) {
        foreach ($serverPipes as $pipe) {
            if (is_resource($pipe)) {
                @fclose($pipe);
            }
        }
        $serverPipes = null;
    }
    if (is_resource($serverProcess)) {
        @proc_terminate($serverProcess);
        usleep(50000);
        @proc_close($serverProcess);
        $serverProcess = null;
    }
    if (is_string($tempDir) && is_dir($tempDir)) {
        remove_dir_recursive($tempDir);
    }
};

register_shutdown_function($cleanup);

try {
    // 1. Mechanically link real code files into temp tree preserving structure
    link_file_safe($repoRoot . '/api/v1/index.php', $tempDir . '/api/v1/index.php');
    link_file_safe($repoRoot . '/api/v1/routes.php', $tempDir . '/api/v1/routes.php');
    link_file_safe($repoRoot . '/api/v1/middleware_auth.php', $tempDir . '/api/v1/middleware_auth.php');
    link_file_safe($repoRoot . '/api/v1/helpers.php', $tempDir . '/api/v1/helpers.php');
    link_file_safe($repoRoot . '/api/v1/controllers/WonProjectsController.php', $tempDir . '/api/v1/controllers/WonProjectsController.php');
    link_file_safe($repoRoot . '/core/services/WonProjectImportService.php', $tempDir . '/core/services/WonProjectImportService.php');

    // 2. Generate runtime random secrets and write isolated test config.php
    $serviceSecret = bin2hex(random_bytes(24));
    $adminSecret   = bin2hex(random_bytes(24));
    $viewerSecret  = bin2hex(random_bytes(24));

    $testConfigContent = "<?php\ndeclare(strict_types=1);\n\nreturn [\n"
        . "    'CLIENTS' => [\n"
        . "        'service-client' => " . var_export($serviceSecret, true) . ",\n"
        . "        'admin-client'   => " . var_export($adminSecret, true) . ",\n"
        . "        'viewer-client'  => " . var_export($viewerSecret, true) . ",\n"
        . "    ],\n"
        . "    'CLIENT_ROLES' => [\n"
        . "        'service-client' => 'service',\n"
        . "        'admin-client'   => 'admin',\n"
        . "        'viewer-client'  => 'viewer',\n"
        . "    ],\n"
        . "    'MAX_TIMESTAMP_SKEW' => 300,\n"
        . "];\n";
    file_put_contents($tempDir . '/api/v1/config.php', $testConfigContent);

    // Initial scenario file before server start
    file_put_contents($tempDir . '/test_scenario.json', json_encode(['mode' => 'imported']));

    // 3. Create server bootstrap for PHP built-in web server
    $routerContent = <<<'PHP'
<?php
declare(strict_types=1);

unset($GLOBALS['RAW_REQUEST_BODY']);
unset($GLOBALS['API_CORRELATION_ID']);
unset($GLOBALS['API_CLIENT_ID']);
unset($GLOBALS['API_CLIENT_ROLE']);

require_once __DIR__ . '/api/v1/controllers/WonProjectsController.php';

$scenarioFile = __DIR__ . '/test_scenario.json';
clearstatcache(true, $scenarioFile);
$scenario = [];
if (file_exists($scenarioFile)) {
    $rawScenario = (string)file_get_contents($scenarioFile);
    $scenario = json_decode($rawScenario, true) ?: [];
}

$mode = $scenario['mode'] ?? 'imported';

if ($mode === 'imported') {
    WonProjectsController::setImportService(new class {
        public function import(array $p): array {
            return ['status' => 'imported', 'project_id' => 'proj-test-123'];
        }
    });
} elseif ($mode === 'replayed') {
    WonProjectsController::setImportService(new class {
        public function import(array $p): array {
            return ['status' => 'replayed', 'project_id' => 'proj-test-123'];
        }
    });
} elseif ($mode === 'stale') {
    WonProjectsController::setImportService(new class {
        public function import(array $p): array {
            return ['status' => 'stale'];
        }
    });
} elseif ($mode === 'conflict') {
    $evtMarker = $scenario['conflict_event_id'] ?? 'marker_conflict_event_id_default';
    $existMarker = $scenario['conflict_existing_hash'] ?? 'marker_conflict_existing_hash_default';
    $incMarker = $scenario['conflict_incoming_hash'] ?? 'marker_conflict_incoming_hash_default';
    WonProjectsController::setImportService(new class($evtMarker, $existMarker, $incMarker) {
        private $evt;
        private $exist;
        private $inc;
        public function __construct(string $e, string $ex, string $in) {
            $this->evt = $e;
            $this->exist = $ex;
            $this->inc = $in;
        }
        public function import(array $p): array {
            throw new WonProjectImportConflictException($this->evt, $this->exist, $this->inc);
        }
    });
} elseif ($mode === 'failing') {
    $fMarker = $scenario['failing_marker'] ?? 'query_details_internal_secret_marker';
    WonProjectsController::setImportService(new class($fMarker) {
        private $fm;
        public function __construct(string $fm) { $this->fm = $fm; }
        public function import(array $p): array {
            throw new \RuntimeException('Internal database connection failed with query details marker: ' . $this->fm);
        }
    });
} elseif ($mode === 'spy') {
    $spyFile = $scenario['spy_file'] ?? (__DIR__ . '/spy.json');
    WonProjectsController::setImportService(new class($spyFile) {
        private $sf;
        public function __construct(string $sf) { $this->sf = $sf; }
        public function import(array $p): array {
            file_put_contents($this->sf, json_encode(['called' => true, 'payload' => $p]));
            return ['status' => 'imported', 'project_id' => 'proj-test-123'];
        }
    });
} else {
    // Mode 'real': use production WonProjectImportService (constructed inside controller)
    WonProjectsController::setImportService(null);
}

// Predefine $pdo for SQLite memory
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Delegate to real entrypoint
require __DIR__ . '/api/v1/index.php';
PHP;
    file_put_contents($tempDir . '/server_router.php', $routerContent);

    // 4. Start PHP built-in web server with propagated extensions
    $serverPort = 0;
    $maxAttempts = 3;
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $candidatePort = get_free_port();

        $cmd = [PHP_BINARY];
        $extDir = (string)ini_get('extension_dir');
        if ($extDir !== '') {
            $cmd[] = '-d';
            $cmd[] = "extension_dir=$extDir";
        }
        if (extension_loaded('pdo_sqlite')) {
            $cmd[] = '-d';
            $cmd[] = PHP_OS_FAMILY === 'Windows' ? 'extension=php_pdo_sqlite.dll' : 'extension=pdo_sqlite';
        }
        if (extension_loaded('sqlite3')) {
            $cmd[] = '-d';
            $cmd[] = PHP_OS_FAMILY === 'Windows' ? 'extension=php_sqlite3.dll' : 'extension=sqlite3';
        }
        $cmd[] = '-S';
        $cmd[] = "127.0.0.1:$candidatePort";
        $cmd[] = '-t';
        $cmd[] = $tempDir;
        $cmd[] = $tempDir . '/server_router.php';

        $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $nullDevice, 'w'],
            2 => ['file', $nullDevice, 'w'],
        ];
        $proc = proc_open($cmd, $descriptors, $pipes, $tempDir);
        if (!is_resource($proc)) {
            continue;
        }

        $ready = false;
        $deadline = microtime(true) + 3.0;
        while (microtime(true) < $deadline) {
            $status = proc_get_status($proc);
            if (!$status['running']) {
                break;
            }
            $conn = @stream_socket_client("tcp://127.0.0.1:$candidatePort", $errno, $errstr, 0.1);
            if ($conn) {
                fclose($conn);
                $ready = true;
                break;
            }
            usleep(20000);
        }

        if ($ready) {
            $serverProcess = $proc;
            $serverPipes = $pipes;
            $serverPort = $candidatePort;
            break;
        }

        if (is_array($pipes)) {
            foreach ($pipes as $p) {
                if (is_resource($p)) {
                    @fclose($p);
                }
            }
        }
        @proc_terminate($proc);
        @proc_close($proc);
    }

    if ($serverPort === 0 || !is_resource($serverProcess)) {
        throw new \RuntimeException('Failed to start PHP built-in HTTP server.');
    }

    // Helper for executing requests
    $endpoint = '/api/v1/integrations/takeoff/won-projects';

    $setScenario = function (array $cfg) use ($tempDir): void {
        file_put_contents($tempDir . '/test_scenario.json', json_encode($cfg));
        clearstatcache(true, $tempDir . '/test_scenario.json');
    };

    $sendSigned = function (
        string $rawBody,
        string $clientId = 'service-client',
        ?string $secret = null,
        ?int $timestamp = null,
        ?string $customSignature = null,
        array $extraHeaders = []
    ) use ($endpoint, $serverPort, $serviceSecret): array {
        $actualSecret = $secret ?? $serviceSecret;
        $ts = $timestamp ?? time();
        $sig = $customSignature ?? compute_hmac('POST', $endpoint, (string)$ts, $rawBody, $actualSecret);

        $headers = array_merge([
            'Content-Type' => 'application/json',
            'X-Client-Id' => $clientId,
            'X-Timestamp' => (string)$ts,
            'X-Signature' => $sig,
        ], $extraHeaders);

        return http_request('POST', $endpoint, $headers, $rawBody, $serverPort);
    };

    $validPayloadJson = json_encode(get_valid_contract_payload());
    $sensitiveLeakTerms = [
        'runtimeexception',
        'stack trace',
        'trace',
        'query details',
        'database connection',
        'server_router.php',
        'wonprojectimportconflictexception',
        $serviceSecret,
        $adminSecret,
        $viewerSecret,
        'internal secret',
    ];

    // ==========================================
    // TEST 1: 201 Created (imported)
    // ==========================================
    $setScenario(['mode' => 'imported']);
    $res = $sendSigned($validPayloadJson);
    assert_equals(201, $res['status'], 'Test 1: Status must be 201');
    assert_true($res['json']['ok'] ?? false, 'Test 1: ok must be true');
    assert_equals('imported', $res['json']['data']['status'] ?? null, 'Test 1: data.status must be imported');
    assert_valid_correlation_id($res['json']['correlation_id'] ?? null, 'Test 1 JSON');
    assert_valid_correlation_id($res['headers']['x-correlation-id'] ?? null, 'Test 1 Header');
    assert_equals($res['headers']['x-correlation-id'], $res['json']['correlation_id'], 'Test 1 Correlation-Id match');

    // ==========================================
    // TEST 2: 200 OK (replayed)
    // ==========================================
    $setScenario(['mode' => 'replayed']);
    $res = $sendSigned($validPayloadJson);
    assert_equals(200, $res['status'], 'Test 2: Status must be 200');
    assert_true($res['json']['ok'] ?? false, 'Test 2: ok must be true');
    assert_equals('replayed', $res['json']['data']['status'] ?? null, 'Test 2: data.status must be replayed');
    assert_valid_correlation_id($res['json']['correlation_id'] ?? null, 'Test 2 JSON');
    assert_valid_correlation_id($res['headers']['x-correlation-id'] ?? null, 'Test 2 Header');
    assert_equals($res['headers']['x-correlation-id'], $res['json']['correlation_id'], 'Test 2 Correlation-Id match');

    // ==========================================
    // TEST 3: 400 Bad Request (empty body and whitespace)
    // ==========================================
    $setScenario(['mode' => 'imported']);
    $resEmpty = $sendSigned('');
    assert_equals(400, $resEmpty['status'], 'Test 3a: Empty body status must be 400');
    assert_equals(false, $resEmpty['json']['ok'] ?? null, 'Test 3a: ok must be false');
    assert_equals('VALIDATION_ERROR', $resEmpty['json']['error']['code'] ?? null, 'Test 3a: code VALIDATION_ERROR');
    assert_valid_correlation_id($resEmpty['json']['correlation_id'] ?? null, 'Test 3a JSON');
    assert_valid_correlation_id($resEmpty['headers']['x-correlation-id'] ?? null, 'Test 3a Header');

    $resSpaces = $sendSigned("   \n\t  ");
    assert_equals(400, $resSpaces['status'], 'Test 3b: Whitespace body status must be 400');
    assert_equals(false, $resSpaces['json']['ok'] ?? null, 'Test 3b: ok must be false');
    assert_equals('VALIDATION_ERROR', $resSpaces['json']['error']['code'] ?? null, 'Test 3b: code VALIDATION_ERROR');
    assert_valid_correlation_id($resSpaces['json']['correlation_id'] ?? null, 'Test 3b JSON');

    // ==========================================
    // TEST 4: 400 Bad Request (malformed JSON)
    // ==========================================
    $resMalformed = $sendSigned('{"incomplete": true, "broken": ');
    assert_equals(400, $resMalformed['status'], 'Test 4: Malformed JSON status must be 400');
    assert_equals(false, $resMalformed['json']['ok'] ?? null, 'Test 4: ok must be false');
    assert_equals('VALIDATION_ERROR', $resMalformed['json']['error']['code'] ?? null, 'Test 4: code VALIDATION_ERROR');
    assert_valid_correlation_id($resMalformed['json']['correlation_id'] ?? null, 'Test 4 JSON');
    assert_valid_correlation_id($resMalformed['headers']['x-correlation-id'] ?? null, 'Test 4 Header');

    // ==========================================
    // TEST 5: 401 Unauthorized (missing auth headers)
    // ==========================================
    $ts = time();
    $rawH = [
        'Content-Type' => 'application/json',
        'X-Client-Id' => 'service-client',
        'X-Timestamp' => (string)$ts,
    ];
    $resNoSig = http_request('POST', $endpoint, $rawH, $validPayloadJson, $serverPort);
    assert_equals(401, $resNoSig['status'], 'Test 5a: Missing signature status must be 401');
    assert_equals('UNAUTHORIZED', $resNoSig['json']['error']['code'] ?? null, 'Test 5a: code UNAUTHORIZED');
    assert_valid_correlation_id($resNoSig['json']['correlation_id'] ?? null, 'Test 5a JSON');

    $rawNoClient = [
        'Content-Type' => 'application/json',
        'X-Timestamp' => (string)$ts,
        'X-Signature' => 'dummy',
    ];
    $resNoClient = http_request('POST', $endpoint, $rawNoClient, $validPayloadJson, $serverPort);
    assert_equals(401, $resNoClient['status'], 'Test 5b: Missing client status must be 401');
    assert_equals('UNAUTHORIZED', $resNoClient['json']['error']['code'] ?? null, 'Test 5b: code UNAUTHORIZED');

    // ==========================================
    // TEST 6: 401 Unauthorized (invalid signature)
    // ==========================================
    $badSig = hash('sha256', 'completely-invalid-signature-value');
    $resBadSig = $sendSigned($validPayloadJson, 'service-client', $serviceSecret, $ts, $badSig);
    assert_equals(401, $resBadSig['status'], 'Test 6: Invalid signature status must be 401');
    assert_equals('UNAUTHORIZED', $resBadSig['json']['error']['code'] ?? null, 'Test 6: code UNAUTHORIZED');
    assert_valid_correlation_id($resBadSig['json']['correlation_id'] ?? null, 'Test 6 JSON');

    // ==========================================
    // TEST 7: 401 Unauthorized (expired / out-of-range timestamp)
    // ==========================================
    $expiredTs = time() - 360;
    $resPast = $sendSigned($validPayloadJson, 'service-client', $serviceSecret, $expiredTs);
    assert_equals(401, $resPast['status'], 'Test 7a: Past expired timestamp status must be 401');
    assert_equals('UNAUTHORIZED', $resPast['json']['error']['code'] ?? null, 'Test 7a: code UNAUTHORIZED');

    $futureTs = time() + 360;
    $resFuture = $sendSigned($validPayloadJson, 'service-client', $serviceSecret, $futureTs);
    assert_equals(401, $resFuture['status'], 'Test 7b: Future timestamp status must be 401');
    assert_equals('UNAUTHORIZED', $resFuture['json']['error']['code'] ?? null, 'Test 7b: code UNAUTHORIZED');

    // ==========================================
    // TEST 8: 403 Forbidden (authenticated role unauthorized)
    // ==========================================
    $resForbidden = $sendSigned($validPayloadJson, 'viewer-client', $viewerSecret);
    assert_equals(403, $resForbidden['status'], 'Test 8: Forbidden role status must be 403');
    assert_equals(false, $resForbidden['json']['ok'] ?? null, 'Test 8: ok must be false');
    assert_equals('FORBIDDEN', $resForbidden['json']['error']['code'] ?? null, 'Test 8: code FORBIDDEN');
    assert_valid_correlation_id($resForbidden['json']['correlation_id'] ?? null, 'Test 8 JSON');
    assert_valid_correlation_id($resForbidden['headers']['x-correlation-id'] ?? null, 'Test 8 Header');

    // ==========================================
    // TEST 9: 409 Conflict (IDEMPOTENCY_CONFLICT)
    // ==========================================
    $conflictEventMarker = 'marker_conflict_event_id_888';
    $conflictExistingMarker = 'marker_conflict_existing_hash_777';
    $conflictIncomingMarker = 'marker_conflict_incoming_hash_666';

    $setScenario([
        'mode' => 'conflict',
        'conflict_event_id' => $conflictEventMarker,
        'conflict_existing_hash' => $conflictExistingMarker,
        'conflict_incoming_hash' => $conflictIncomingMarker,
    ]);
    $resConflict = $sendSigned($validPayloadJson);
    assert_equals(409, $resConflict['status'], 'Test 9: Idempotency conflict status must be 409');
    assert_equals(false, $resConflict['json']['ok'] ?? null, 'Test 9: ok must be false');
    assert_equals('IDEMPOTENCY_CONFLICT', $resConflict['json']['error']['code'] ?? null, 'Test 9: code IDEMPOTENCY_CONFLICT');
    assert_valid_correlation_id($resConflict['json']['correlation_id'] ?? null, 'Test 9 JSON');
    assert_valid_correlation_id($resConflict['headers']['x-correlation-id'] ?? null, 'Test 9 Header');
    assert_no_leaks($resConflict['raw_body'], array_merge($sensitiveLeakTerms, [
        $conflictEventMarker,
        $conflictExistingMarker,
        $conflictIncomingMarker,
    ]), 'Test 9 409 Conflict body');

    // ==========================================
    // TEST 10: 409 Conflict (STALE_EVENT)
    // ==========================================
    $setScenario(['mode' => 'stale']);
    $resStale = $sendSigned($validPayloadJson);
    assert_equals(409, $resStale['status'], 'Test 10: Stale event status must be 409');
    assert_equals(false, $resStale['json']['ok'] ?? null, 'Test 10: ok must be false');
    assert_equals('STALE_EVENT', $resStale['json']['error']['code'] ?? null, 'Test 10: code STALE_EVENT');
    assert_valid_correlation_id($resStale['json']['correlation_id'] ?? null, 'Test 10 JSON');
    assert_valid_correlation_id($resStale['headers']['x-correlation-id'] ?? null, 'Test 10 Header');
    assert_no_leaks($resStale['raw_body'], $sensitiveLeakTerms, 'Test 10 409 Stale body');

    // ==========================================
    // TEST 11: 422 Unprocessable Entity (real WonProjectImportService schema validation)
    // ==========================================
    $setScenario(['mode' => 'real']);
    $invalidContractPayload = get_valid_contract_payload();
    unset($invalidContractPayload['event_id']);
    $invalidContractPayload['source_system'] = 'unsupported_source';

    $res422 = $sendSigned(json_encode($invalidContractPayload));
    assert_equals(422, $res422['status'], 'Test 11: Contract invalid payload status must be 422');
    assert_equals(false, $res422['json']['ok'] ?? null, 'Test 11: ok must be false');
    assert_equals('VALIDATION_ERROR', $res422['json']['error']['code'] ?? null, 'Test 11: code VALIDATION_ERROR');
    assert_true(!empty($res422['json']['error']['details']), 'Test 11: 422 must provide validation details');
    assert_valid_correlation_id($res422['json']['correlation_id'] ?? null, 'Test 11 JSON');
    assert_valid_correlation_id($res422['headers']['x-correlation-id'] ?? null, 'Test 11 Header');

    // ==========================================
    // TEST 12: 500 Internal Server Error (seam service throws unexpected exception)
    // ==========================================
    $failingMarker = 'marker_internal_db_failure_details_query_500';
    $setScenario(['mode' => 'failing', 'failing_marker' => $failingMarker]);
    $res500 = $sendSigned($validPayloadJson);
    assert_equals(500, $res500['status'], 'Test 12: Failing service status must be 500');
    assert_equals(false, $res500['json']['ok'] ?? null, 'Test 12: ok must be false');
    assert_equals('INTERNAL_ERROR', $res500['json']['error']['code'] ?? null, 'Test 12: code INTERNAL_ERROR');
    assert_equals('Unexpected error', $res500['json']['error']['message'] ?? null, 'Test 12: message Unexpected error');
    assert_valid_correlation_id($res500['json']['correlation_id'] ?? null, 'Test 12 JSON');
    assert_valid_correlation_id($res500['headers']['x-correlation-id'] ?? null, 'Test 12 Header');
    assert_no_leaks($res500['raw_body'], array_merge($sensitiveLeakTerms, [$failingMarker]), 'Test 12 500 body');

    // ==========================================
    // TEST 13: Exact preservation of incoming X-Correlation-Id
    // ==========================================
    $setScenario(['mode' => 'imported']);
    $customCid = 'test.custom-correlation_id-99887766';
    $resCustomCid = $sendSigned($validPayloadJson, 'service-client', $serviceSecret, null, null, [
        'X-Correlation-Id' => $customCid,
    ]);
    assert_equals(201, $resCustomCid['status'], 'Test 13: Status 201');
    assert_equals($customCid, $resCustomCid['headers']['x-correlation-id'] ?? null, 'Test 13: Header exact preservation');
    assert_equals($customCid, $resCustomCid['json']['correlation_id'] ?? null, 'Test 13: JSON exact preservation');

    // ==========================================
    // TEST 14: HMAC payload covers METHOD, PATH, TIMESTAMP and raw body
    // ==========================================
    $tamperedBody = $validPayloadJson . ' ';
    $ts14 = time();
    $sigOriginal = compute_hmac('POST', $endpoint, (string)$ts14, $validPayloadJson, $serviceSecret);
    $resTampered = $sendSigned($tamperedBody, 'service-client', $serviceSecret, $ts14, $sigOriginal);
    assert_equals(401, $resTampered['status'], 'Test 14: Tampered body must fail HMAC validation (401)');

    // ==========================================
    // TEST 15: Substituted service is invoked ONLY after valid auth, role, and JSON
    // ==========================================
    $spyFile = $tempDir . '/spy_test.json';
    $setScenario(['mode' => 'spy', 'spy_file' => $spyFile]);

    // 15a: Bad signature must NOT invoke service
    if (file_exists($spyFile)) {
        unlink($spyFile);
    }
    $sendSigned($validPayloadJson, 'service-client', $serviceSecret, time(), $badSig);
    assert_true(!file_exists($spyFile), 'Test 15a: Service must NOT be invoked on bad signature');

    // 15b: Forbidden role must NOT invoke service
    if (file_exists($spyFile)) {
        unlink($spyFile);
    }
    $sendSigned($validPayloadJson, 'viewer-client', $viewerSecret);
    assert_true(!file_exists($spyFile), 'Test 15b: Service must NOT be invoked on forbidden role');

    // 15c: Malformed JSON must NOT invoke service
    if (file_exists($spyFile)) {
        unlink($spyFile);
    }
    $sendSigned('{"incomplete": true, ');
    assert_true(!file_exists($spyFile), 'Test 15c: Service must NOT be invoked on malformed JSON');

    // 15d: Empty body must NOT invoke service
    if (file_exists($spyFile)) {
        unlink($spyFile);
    }
    $sendSigned('');
    assert_true(!file_exists($spyFile), 'Test 15d: Service must NOT be invoked on empty body');

    // 15e: Valid request MUST invoke service
    if (file_exists($spyFile)) {
        unlink($spyFile);
    }
    $resSpyValid = $sendSigned($validPayloadJson);
    assert_equals(201, $resSpyValid['status'], 'Test 15e: Valid request status must be 201');
    assert_true(file_exists($spyFile), 'Test 15e: Service MUST be invoked on valid request');
    $spyData = json_decode((string)file_get_contents($spyFile), true);
    assert_true($spyData['called'] ?? false, 'Test 15e: Spy called must be true');
    assert_equals(
        'proj-tk-001',
        $spyData['payload']['source_project_id'] ?? null,
        'Test 15e: Substituted service received full payload'
    );

    echo "All WonProject HTTP endpoint tests passed successfully.\n";
    exit(0);

} catch (\Throwable $e) {
    echo "TEST FAILURE: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
} finally {
    $cleanup();
}
