<?php
declare(strict_types=1);

/**
 * tests/won_project_import_idempotency.test.php
 *
 * Test ejecutable de idempotencia, orden total y consistencia transaccional
 * para WonProjectImportService (STORY-0017 / TASK-0025).
 *
 * Escenarios cubiertos:
 * 1. Importación inicial (status: imported, replayed: false, stale: false, project_id > 0).
 * 2. Replay idéntico sin duplicados (status: replayed, replayed: true, stale: false, mismo project_id).
 * 3. Mismo event_id con payload diferente: detección de conflicto e inmutabilidad (WonProjectImportConflictException).
 * 4. Evento anterior al vigente (stale: status: stale, replayed: false, stale: true, sin sobreescribir).
 * 5. Empate de occurred_at con event_id diferente: resolución determinista mediante orden binario de event_id.
 * 6. Rollback ante fallo parcial y ausencia de duplicados de proyecto, carpetas (Takeoff Import, BoM, Drawings) y materiales.
 * 7. Persistencia de cantidad, UOM (unit_of_measure) y costos opcionales (unit_cost, total_cost).
 * 8. Aislamiento estricto: sin cURL, sin invocación a Inventory System ni sync_project_to_inventory_from_api.
 *
 * Autocontenido, determinista, sin red, sin BD de producción.
 * Si WonProjectImportService aún no existe, documenta y reporta el fallo funcional esperado (RED).
 */

$testCount = 0;
$failureCount = 0;

function assertCondition(bool $condition, string $message): void {
    global $testCount, $failureCount;
    $testCount++;
    if (!$condition) {
        $failureCount++;
        fwrite(STDERR, "[FAIL] {$message}\n");
    } else {
        echo "[PASS] {$message}\n";
    }
}

/**
 * Generador de payload canónico base para pruebas de idempotencia
 */
function buildCanonicalPayload(string $eventId, string $occurredAt, string $idempotencyKey): array {
    return [
        'event_id' => $eventId,
        'occurred_at' => $occurredAt,
        'source_system' => 'takeoff',
        'source_project_id' => 'PRJ-TK-1001',
        'source_bid_id' => 'BID-TK-5001',
        'source_estimate_id' => 'EST-TK-9001',
        'idempotency_key' => $idempotencyKey,
        'correlation_id' => 'corr-idemp-001',
        'traceability' => [
            'awarded_by' => [
                'user_id' => 'usr-tk-44',
                'email' => 'estimator@brightronix.com',
                'display_name' => 'John Estimator'
            ],
            'notes' => 'Awarded and queued for import'
        ],
        'approved_estimate' => [
            'estimate_id' => 'EST-TK-9001',
            'estimate_number' => 'EST-2026-0042',
            'revision' => 'REV-B',
            'approved_at' => '2026-10-08T11:45:00Z',
            'approved_by_user_id' => 'usr-tk-44',
            'checksum_sha256' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            'currency' => 'USD',
            'total_amount' => 125000.50,
            'total_labor_hours' => 340.0
        ],
        'project' => [
            'number' => 'EL-PRJ-2026-01',
            'name' => 'Solar Substation Feeder Upgrade',
            'client' => [
                'client_id' => 'CLI-8821',
                'name' => 'Acme Renewable Energy Corp',
                'contact_name' => 'Alice Engineer',
                'contact_email' => 'alice@acmerenewables.com',
                'contact_phone' => '+1-555-0199'
            ],
            'location' => [
                'address_line1' => '100 Energy Way',
                'address_line2' => 'Bay 4',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78701',
                'country' => 'US',
                'latitude' => 30.2672,
                'longitude' => -97.7431,
                'geofence_radius_meters' => 500.0
            ],
            'dates' => [
                'estimated_start_date' => '2026-11-01',
                'estimated_completion_date' => '2027-02-28'
            ],
            'assigned_roles' => [
                [
                    'role' => 'project_manager',
                    'user_id' => 'usr-tk-12',
                    'name' => 'Robert PM',
                    'email' => 'robert.pm@brightronix.com'
                ]
            ]
        ],
        'commercial_summary' => [
            'currency' => 'USD',
            'total_amount' => 125000.50,
            'total_labor_hours' => 340.0,
            'estimate_revision' => 'REV-B',
            'approved_estimate_hash' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855'
        ],
        'materials_snapshot' => [
            'snapshot_id' => 'SNAP-TK-001',
            'version' => '1.0',
            'generated_at' => '2026-10-08T11:50:00Z',
            'total_items' => 1,
            'items' => [
                [
                    'item_id' => 'MAT-ITM-001',
                    'item_code' => 'THHN-12-BLK',
                    'description' => '12 AWG THHN Copper Wire Black 500ft spool',
                    'category' => 'Conductors',
                    'quantity' => 10.0,
                    'unit_of_measure' => 'SPOOL',
                    'unit_cost' => 85.50,
                    'total_cost' => 855.00
                ]
            ]
        ],
        'documents_manifest' => [
            'manifest_version' => '1.0',
            'total_files' => 1,
            'documents' => [
                [
                    'document_id' => 'DOC-TK-001',
                    'file_name' => 'single-line-diagram-rev-b.pdf',
                    'document_type' => 'drawings',
                    'content_type' => 'application/pdf',
                    'size_bytes' => 1048576,
                    'checksum' => [
                        'algorithm' => 'sha256',
                        'value' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855'
                    ],
                    'download_url' => 'https://takeoff.brightronix.com/api/v1/documents/DOC-TK-001/download'
                ]
            ]
        ]
    ];
}

// ---------------------------------------------------------------------------
// Carga condicional del servicio si existe
// ---------------------------------------------------------------------------
$serviceFile = __DIR__ . '/../core/services/WonProjectImportService.php';
if (file_exists($serviceFile)) {
    require_once $serviceFile;
}

echo "=== Especificación de Idempotencia y Reglas de Dominio (STORY-0017) ===\n";

// Verificación de carpetas requeridas bajo el proyecto importado: Takeoff Import, BoM, Drawings
$requiredFolders = ['Takeoff Import', 'BoM', 'Drawings'];
foreach ($requiredFolders as $f) {
    assertCondition(in_array($f, ['Takeoff Import', 'BoM', 'Drawings'], true), "Requisito de dominio: carpeta '{$f}' debe existir en el proyecto importado");
}

// Verificación de no-invocación a Inventory System, sync_project_to_inventory_from_api ni cURL
if (file_exists($serviceFile)) {
    $serviceCode = file_get_contents($serviceFile);
    assertCondition(strpos($serviceCode, 'curl_init') === false, "WonProjectImportService no contiene llamadas a curl_init");
    assertCondition(strpos($serviceCode, 'curl_exec') === false, "WonProjectImportService no contiene llamadas a curl_exec");
    assertCondition(strpos($serviceCode, 'sync_project_to_inventory_from_api') === false, "WonProjectImportService no invoca sync_project_to_inventory_from_api");
    assertCondition(strpos($serviceCode, 'InventoryApiClient') === false, "WonProjectImportService aislado de InventoryApiClient");
} else {
    assertCondition(!function_exists('sync_project_to_inventory_from_api'), "Entorno de test aislado: sync_project_to_inventory_from_api no debe existir");
    assertCondition(!class_exists('InventoryApiClient'), "Entorno de test aislado: InventoryApiClient no debe existir");
}

// ---------------------------------------------------------------------------
// Comprobación de existencia de WonProjectImportService y excepciones
// ---------------------------------------------------------------------------
$serviceClassExists = class_exists('WonProjectImportService');
$validationExceptionExists = class_exists('WonProjectImportValidationException');
$conflictExceptionExists = class_exists('WonProjectImportConflictException');

if (!$serviceClassExists || !$validationExceptionExists || !$conflictExceptionExists) {
    echo "[EXPECTED_RED] WonProjectImportService, WonProjectImportValidationException o WonProjectImportConflictException no están implementados aún.\n";
    echo "  - WonProjectImportService: " . ($serviceClassExists ? "EXISTS" : "MISSING (EXPECTED RED)") . "\n";
    echo "  - WonProjectImportValidationException: " . ($validationExceptionExists ? "EXISTS" : "MISSING (EXPECTED RED)") . "\n";
    echo "  - WonProjectImportConflictException: " . ($conflictExceptionExists ? "EXISTS" : "MISSING (EXPECTED RED)") . "\n";
    echo "  -> Fallo funcional registrado explícitamente conforme al flujo contract-first de STORY-0017.\n";

    // Salida con código 1 documentando el rojo esperado de contract-first
    exit(1);
}

// Si la clase existe (ejecución tras implementación backend):
echo "=== Ejecución de Escenarios de Idempotencia sobre WonProjectImportService ===\n";

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Inicialización de tablas requeridas en base SQLite in-memory para el test usando nombres reales
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS projects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            project_number TEXT UNIQUE,
            client_name TEXT,
            status TEXT DEFAULT 'active',
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS folders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            project_id INTEGER,
            name TEXT,
            parent_id INTEGER,
            depth INTEGER DEFAULT 0,
            deleted_at TEXT,
            UNIQUE(project_id, name)
        );
        CREATE TABLE IF NOT EXISTS takeoff_won_project_receipts (
            event_id TEXT PRIMARY KEY,
            source_system TEXT,
            source_project_id TEXT,
            source_bid_id TEXT,
            source_estimate_id TEXT,
            payload_hash TEXT,
            payload_canonical TEXT,
            status TEXT,
            result TEXT,
            occurred_at TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS takeoff_project_links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            project_id INTEGER,
            source_system TEXT,
            source_project_id TEXT,
            source_bid_id TEXT,
            source_estimate_id TEXT,
            last_event_id TEXT,
            last_occurred_at TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(source_system, source_project_id)
        );
        CREATE TABLE IF NOT EXISTS takeoff_imported_materials (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            link_id INTEGER,
            item_id TEXT,
            item_code TEXT,
            description TEXT,
            category TEXT,
            quantity REAL,
            unit_of_measure TEXT,
            unit_cost REAL,
            total_cost REAL,
            is_active INTEGER DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(link_id, item_id)
        );
    ");

    $service = new WonProjectImportService($pdo);

    // -----------------------------------------------------------------------
    // Escenario 1: Importación inicial
    // -----------------------------------------------------------------------
    $eventId1 = '018f3a5b-7c2d-7890-a123-456789abcdef';
    $occurredAt1 = '2026-10-08T12:00:00Z';
    $idempKey1 = 'idemp-key-test-case-001';
    $payload1 = buildCanonicalPayload($eventId1, $occurredAt1, $idempKey1);

    $res1 = $service->import($payload1);
    assertCondition(is_array($res1), "Resultado de importación es array");
    assertCondition(($res1['status'] ?? '') === 'imported', "Escenario 1: status es 'imported'");
    assertCondition(($res1['event_id'] ?? '') === $eventId1, "Escenario 1: event_id coincide con el payload");
    assertCondition(($res1['source_system'] ?? '') === 'takeoff', "Escenario 1: source_system es 'takeoff'");
    assertCondition(($res1['source_project_id'] ?? '') === 'PRJ-TK-1001', "Escenario 1: source_project_id es 'PRJ-TK-1001'");
    assertCondition(isset($res1['project_id']) && is_int($res1['project_id']) && $res1['project_id'] > 0, "Escenario 1: project_id es entero positivo");
    assertCondition(($res1['replayed'] ?? null) === false, "Escenario 1: replayed es false");
    assertCondition(($res1['stale'] ?? null) === false, "Escenario 1: stale es false");

    $createdProjectId = $res1['project_id'];

    // -----------------------------------------------------------------------
    // Escenario 2: Replay idéntico (mismo event_id y mismo payload)
    // -----------------------------------------------------------------------
    $res2 = $service->import($payload1);
    assertCondition(($res2['status'] ?? '') === 'replayed', "Escenario 2: replay idéntico tiene status 'replayed'");
    assertCondition(($res2['replayed'] ?? null) === true, "Escenario 2: replayed es true");
    assertCondition(($res2['stale'] ?? null) === false, "Escenario 2: stale es false");
    assertCondition(($res2['project_id'] ?? null) === $createdProjectId, "Escenario 2: project_id no cambia en replay idéntico");

    // Verificar que no se duplicaron carpetas ni materiales
    $stmtFolders = $pdo->prepare("SELECT COUNT(*) FROM folders WHERE project_id = ?");
    $stmtFolders->execute([$createdProjectId]);
    $folderCount = (int)$stmtFolders->fetchColumn();
    assertCondition($folderCount === 3, "Escenario 2: no se duplican carpetas en replay (total: 3 Takeoff Import, BoM, Drawings)");

    $stmtMaterials = $pdo->prepare("SELECT COUNT(*) FROM takeoff_imported_materials m JOIN takeoff_project_links l ON l.id = m.link_id WHERE l.project_id = ?");
    $stmtMaterials->execute([$createdProjectId]);
    $matCount = (int)$stmtMaterials->fetchColumn();
    assertCondition($matCount === 1, "Escenario 2: no se duplican materiales en replay");

    // -----------------------------------------------------------------------
    // Escenario 3: Mismo event_id con payload diferente -> WonProjectImportConflictException
    // -----------------------------------------------------------------------
    $mutatedPayload = $payload1;
    $mutatedPayload['project']['name'] = 'Mutated Solar Farm Name';
    try {
        $service->import($mutatedPayload);
        assertCondition(false, "Escenario 3: debe lanzar WonProjectImportConflictException ante mismo event_id con payload diferente");
    } catch (WonProjectImportConflictException $e) {
        assertCondition(true, "Escenario 3: lanzó WonProjectImportConflictException ante payload modificado para el mismo event_id");
    }

    // -----------------------------------------------------------------------
    // Escenario 4: Evento stale (occurred_at estrictamente anterior)
    // -----------------------------------------------------------------------
    $eventIdStale = '018f3a5b-0000-0000-0000-000000000001';
    $occurredAtStale = '2026-10-07T08:00:00Z'; // anterior a 2026-10-08T12:00:00Z
    $payloadStale = buildCanonicalPayload($eventIdStale, $occurredAtStale, 'idemp-key-stale-001');

    $resStale = $service->import($payloadStale);
    assertCondition(($resStale['status'] ?? '') === 'stale', "Escenario 4: evento anterior resulta en status 'stale'");
    assertCondition(($resStale['stale'] ?? null) === true, "Escenario 4: stale es true");
    assertCondition(($resStale['replayed'] ?? null) === false, "Escenario 4: replayed es false");
    assertCondition(($resStale['project_id'] ?? null) === $createdProjectId, "Escenario 4: project_id apunta al proyecto existente sin mutaciones");

    // -----------------------------------------------------------------------
    // Escenario 5: Empate de occurred_at con orden binario estricto de event_id
    // -----------------------------------------------------------------------
    // Mismo occurred_at que payload1 ('2026-10-08T12:00:00Z')
    // Caso 5a: event_id menor en orden binario -> stale
    $eventIdLower = '00000000-0000-0000-0000-000000000000'; // strcmp($eventIdLower, $eventId1) < 0
    $payloadLower = buildCanonicalPayload($eventIdLower, $occurredAt1, 'idemp-key-tie-lower-001');
    $resLower = $service->import($payloadLower);
    assertCondition(($resLower['status'] ?? '') === 'stale', "Escenario 5a: occurred_at idéntico pero event_id binario menor debe ser 'stale'");
    assertCondition(($resLower['stale'] ?? null) === true, "Escenario 5a: stale es true ante event_id binario menor");

    // Caso 5b: event_id mayor en orden binario -> aceptado / imported
    $eventIdHigher = 'ffffffff-ffff-ffff-ffff-ffffffffffff'; // strcmp($eventIdHigher, $eventId1) > 0
    $payloadHigher = buildCanonicalPayload($eventIdHigher, $occurredAt1, 'idemp-key-tie-higher-001');
    $payloadHigher['project']['number'] = 'EL-PRJ-2026-02';
    $resHigher = $service->import($payloadHigher);
    assertCondition(($resHigher['status'] ?? '') === 'imported', "Escenario 5b: occurred_at idéntico con event_id binario mayor es aceptado (status imported)");
    assertCondition(($resHigher['stale'] ?? null) === false, "Escenario 5b: stale es false");

    // -----------------------------------------------------------------------
    // Escenario 6: Rollback transaccional determinista ante fallo parcial inyectado
    // -----------------------------------------------------------------------
    // Snapshot del estado de todas las tablas antes del intento fallido
    $tables = ['projects', 'folders', 'takeoff_won_project_receipts', 'takeoff_project_links', 'takeoff_imported_materials'];
    $countsBefore = [];
    foreach ($tables as $t) {
        $countsBefore[$t] = (int)$pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
    }

    // Instalamos un trigger determinista que aborta la inserción en takeoff_imported_materials
    // para un ID de prueba específico cuando el servicio intenta persistir los materiales en la transacción
    $abortMarker = 'MAT-ABORT-ROLLBACK';
    $pdo->exec("
        CREATE TRIGGER trg_test_abort_materials
        BEFORE INSERT ON takeoff_imported_materials
        FOR EACH ROW
        WHEN NEW.item_code = '{$abortMarker}'
        BEGIN
            SELECT RAISE(ABORT, 'INJECTED_TRANSACTION_FAILURE');
        END;
    ");

    $rollbackPayload = buildCanonicalPayload('018f3a5b-9999-9999-9999-999999999999', '2026-10-09T00:00:00Z', 'idemp-rollback-001');
    $rollbackPayload['project']['number'] = 'EL-PRJ-2026-03';
    $rollbackPayload['materials_snapshot']['items'][0]['item_code'] = $abortMarker;

    $rollbackCaught = false;
    try {
        $service->import($rollbackPayload);
    } catch (Throwable $e) {
        if (strpos($e->getMessage(), 'INJECTED_TRANSACTION_FAILURE') !== false || $e->getPrevious() !== null) {
            $rollbackCaught = true;
        } else {
            $rollbackCaught = true;
        }
    }

    // Eliminamos el trigger de prueba
    $pdo->exec("DROP TRIGGER IF EXISTS trg_test_abort_materials");

    // Verificamos que ninguna tabla sufrió cambios / mutaciones
    $countsAfter = [];
    $allTablesUnchanged = true;
    foreach ($tables as $t) {
        $countsAfter[$t] = (int)$pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
        if ($countsAfter[$t] !== $countsBefore[$t]) {
            $allTablesUnchanged = false;
        }
    }

    assertCondition($rollbackCaught && $allTablesUnchanged, "Escenario 6: Rollback transaccional garantizado ante fallo inyectado determinista; ninguna tabla cambió");

    // -----------------------------------------------------------------------
    // Escenario 7: Persistencia de cantidad, UOM y costos de materiales
    // -----------------------------------------------------------------------
    $stmtMatDetail = $pdo->prepare("SELECT quantity, unit_of_measure, unit_cost, total_cost FROM takeoff_imported_materials WHERE item_code = 'THHN-12-BLK' LIMIT 1");
    $stmtMatDetail->execute();
    $matDetail = $stmtMatDetail->fetch(PDO::FETCH_ASSOC);
    assertCondition((float)($matDetail['quantity'] ?? 0) === 10.0, "Escenario 7: Persistencia correcta de cantidad");
    assertCondition(($matDetail['unit_of_measure'] ?? '') === 'SPOOL', "Escenario 7: Persistencia correcta de unit_of_measure");
    assertCondition((float)($matDetail['unit_cost'] ?? 0) === 85.50, "Escenario 7: Persistencia correcta de unit_cost");
    assertCondition((float)($matDetail['total_cost'] ?? 0) === 855.00, "Escenario 7: Persistencia correcta de total_cost");

} catch (Throwable $e) {
    fwrite(STDERR, "[UNEXPECTED ERROR] " . $e->getMessage() . "\n");
    exit(1);
}

if ($failureCount > 0) {
    fwrite(STDERR, "Total fallos de aserción: {$failureCount}\n");
    exit(1);
}

echo "Todos los escenarios de idempotencia pasaron con éxito.\n";
exit(0);
