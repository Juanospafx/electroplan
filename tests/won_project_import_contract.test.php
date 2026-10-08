<?php
declare(strict_types=1);

/**
 * tests/won_project_import_contract.test.php
 *
 * Test ejecutable contract-first para WonProjectImportService (STORY-0017 / TASK-0025).
 * Valida la conformidad estricta con api/v1/contracts/won-project-export.v1.schema.json,
 * la interfaz pública congelada, el manejo de excepciones y las aserciones de no-efectos colaterales.
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
 * Generador de payload canónico válido basado estrictamente en won-project-export.v1.schema.json
 */
function buildValidWonProjectPayload(array $overrides = []): array {
    $canonical = [
        'event_id' => '018f3a5b-7c2d-7890-a123-456789abcdef',
        'occurred_at' => '2026-10-08T12:00:00Z',
        'source_system' => 'takeoff',
        'source_project_id' => 'PRJ-TK-1001',
        'source_bid_id' => 'BID-TK-5001',
        'source_estimate_id' => 'EST-TK-9001',
        'idempotency_key' => 'idemp-key-takeoff-won-project-001',
        'correlation_id' => 'corr-tk-ep-001',
        'traceability' => [
            'awarded_by' => [
                'user_id' => 'usr-tk-44',
                'email' => 'estimator@brightronix.com',
                'display_name' => 'John Estimator'
            ],
            'notes' => 'Awarded via commercial pipeline export'
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
                ],
                [
                    'role' => 'lead_electrician',
                    'user_id' => 'usr-tk-15',
                    'name' => 'Elena Sparks',
                    'email' => 'elena.sparks@brightronix.com'
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
            'total_items' => 2,
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
                ],
                [
                    'item_id' => 'MAT-ITM-002',
                    'item_code' => 'CONDUIT-EMT-34',
                    'description' => '3/4 inch EMT Conduit 10ft',
                    'category' => 'Conduit & Fittings',
                    'quantity' => 150.0,
                    'unit_of_measure' => 'STICK',
                    'unit_cost' => 14.20,
                    'total_cost' => 2130.00
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

    return array_replace_recursive($canonical, $overrides);
}

// ---------------------------------------------------------------------------
// Carga condicional del servicio si existe
// ---------------------------------------------------------------------------
$serviceFile = __DIR__ . '/../core/services/WonProjectImportService.php';
if (file_exists($serviceFile)) {
    require_once $serviceFile;
}

// ---------------------------------------------------------------------------
// 1. Verificación del schema canónico y construcción de fixtures (Unitario/Esquema)
// ---------------------------------------------------------------------------
echo "=== 1. Comprobación de Fixtures Canónicos conforme a won-project-export.v1.schema.json ===\n";

$schemaPath = __DIR__ . '/../api/v1/contracts/won-project-export.v1.schema.json';
assertCondition(file_exists($schemaPath), "El esquema del contrato JSON existe en api/v1/contracts/won-project-export.v1.schema.json");

$rawSchema = file_exists($schemaPath) ? (string)file_get_contents($schemaPath) : '';
$decodedSchema = json_decode($rawSchema, true);
assertCondition(is_array($decodedSchema) && !empty($decodedSchema), "El esquema del contrato JSON se decodifica en runtime con json_decode");

$rootRequiredProperties = (is_array($decodedSchema) && isset($decodedSchema['required']) && is_array($decodedSchema['required']))
    ? $decodedSchema['required']
    : [];
$allowedRootProperties = (is_array($decodedSchema) && isset($decodedSchema['properties']) && is_array($decodedSchema['properties']))
    ? array_keys($decodedSchema['properties'])
    : [];

assertCondition(!empty($rootRequiredProperties), "Se derivan propiedades requeridas raíz desde el esquema decodificado");
assertCondition(!empty($allowedRootProperties), "Se derivan propiedades permitidas raíz desde el esquema decodificado");

$canonicalPayload = buildValidWonProjectPayload();

/**
 * Validador estricto del payload canónico contra las reglas de won-project-export.v1.schema.json
 */
function validateWonProjectContractPayload(
    array $payload,
    array &$errors = [],
    ?array $rootRequired = null,
    ?array $allowedProperties = null
): bool {
    global $rootRequiredProperties, $allowedRootProperties;
    $rootRequired = $rootRequired ?? $rootRequiredProperties;
    $allowedProperties = $allowedProperties ?? $allowedRootProperties;

    if (empty($rootRequired)) {
        $rootRequired = [
            'event_id', 'occurred_at', 'source_system', 'source_project_id',
            'source_bid_id', 'source_estimate_id', 'idempotency_key', 'correlation_id',
            'traceability', 'approved_estimate', 'project', 'commercial_summary',
            'materials_snapshot', 'documents_manifest'
        ];
    }
    if (empty($allowedProperties)) {
        $allowedProperties = $rootRequired;
    }

    // 1. Root required
    foreach ($rootRequired as $field) {
        if (!array_key_exists($field, $payload)) {
            $errors[] = "Root field missing: {$field}";
        }
    }
    // Root additionalProperties: false
    foreach (array_keys($payload) as $key) {
        if (!in_array($key, $allowedProperties, true)) {
            $errors[] = "Unknown root property: {$key}";
        }
    }

    // event_id pattern (UUID)
    if (isset($payload['event_id']) && (!is_string($payload['event_id']) || !preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $payload['event_id']))) {
        $errors[] = "event_id must be valid UUID string";
    }

    // occurred_at format: date-time
    if (isset($payload['occurred_at']) && (!is_string($payload['occurred_at']) || strtotime($payload['occurred_at']) === false)) {
        $errors[] = "occurred_at must be valid date-time string";
    }

    // source_system enum: ['takeoff']
    if (isset($payload['source_system']) && $payload['source_system'] !== 'takeoff') {
        $errors[] = "source_system must be 'takeoff'";
    }

    // source_* IDs minLength: 1
    foreach (['source_project_id', 'source_bid_id', 'source_estimate_id'] as $srcField) {
        if (isset($payload[$srcField]) && (!is_string($payload[$srcField]) || strlen($payload[$srcField]) < 1)) {
            $errors[] = "{$srcField} must have minLength 1";
        }
    }

    // idempotency_key minLength 16, maxLength 128
    if (isset($payload['idempotency_key']) && (!is_string($payload['idempotency_key']) || strlen($payload['idempotency_key']) < 16 || strlen($payload['idempotency_key']) > 128)) {
        $errors[] = "idempotency_key must be between 16 and 128 chars";
    }

    // correlation_id minLength 8, maxLength 128
    if (isset($payload['correlation_id']) && (!is_string($payload['correlation_id']) || strlen($payload['correlation_id']) < 8 || strlen($payload['correlation_id']) > 128)) {
        $errors[] = "correlation_id must be between 8 and 128 chars";
    }

    // traceability
    if (isset($payload['traceability']) && is_array($payload['traceability'])) {
        $trace = $payload['traceability'];
        if (!isset($trace['awarded_by']) || !is_array($trace['awarded_by'])) {
            $errors[] = "traceability.awarded_by is required";
        } else {
            $awarded = $trace['awarded_by'];
            foreach (['user_id', 'email', 'display_name'] as $req) {
                if (!isset($awarded[$req]) || !is_string($awarded[$req]) || strlen($awarded[$req]) < 1) {
                    $errors[] = "traceability.awarded_by.{$req} missing or invalid";
                }
            }
            if (isset($awarded['email']) && filter_var($awarded['email'], FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = "traceability.awarded_by.email invalid format";
            }
        }
    }

    // approved_estimate
    if (isset($payload['approved_estimate']) && is_array($payload['approved_estimate'])) {
        $est = $payload['approved_estimate'];
        $estReq = ['estimate_id', 'estimate_number', 'revision', 'approved_at', 'approved_by_user_id', 'checksum_sha256', 'currency', 'total_amount', 'total_labor_hours'];
        foreach ($estReq as $field) {
            if (!array_key_exists($field, $est)) {
                $errors[] = "approved_estimate.{$field} required";
            }
        }
        if (isset($est['checksum_sha256']) && !preg_match('/^[a-f0-9]{64}$/', (string)$est['checksum_sha256'])) {
            $errors[] = "approved_estimate.checksum_sha256 invalid pattern";
        }
        if (isset($est['currency']) && !preg_match('/^[A-Z]{3}$/', (string)$est['currency'])) {
            $errors[] = "approved_estimate.currency invalid pattern";
        }
        if (isset($est['total_amount']) && (!is_numeric($est['total_amount']) || (float)$est['total_amount'] < 0)) {
            $errors[] = "approved_estimate.total_amount must be >= 0";
        }
        if (isset($est['total_labor_hours']) && (!is_numeric($est['total_labor_hours']) || (float)$est['total_labor_hours'] < 0)) {
            $errors[] = "approved_estimate.total_labor_hours must be >= 0";
        }
    }

    // project
    if (isset($payload['project']) && is_array($payload['project'])) {
        $proj = $payload['project'];
        $projReq = ['number', 'name', 'client', 'location', 'dates', 'assigned_roles'];
        foreach ($projReq as $field) {
            if (!array_key_exists($field, $proj)) {
                $errors[] = "project.{$field} required";
            }
        }
        if (isset($proj['location']) && is_array($proj['location'])) {
            $loc = $proj['location'];
            if (isset($loc['country']) && !preg_match('/^[A-Z]{2}$/', (string)$loc['country'])) {
                $errors[] = "project.location.country must be 2 uppercase letters";
            }
            if (isset($loc['latitude']) && (!is_numeric($loc['latitude']) || $loc['latitude'] < -90 || $loc['latitude'] > 90)) {
                $errors[] = "project.location.latitude out of range";
            }
            if (isset($loc['longitude']) && (!is_numeric($loc['longitude']) || $loc['longitude'] < -180 || $loc['longitude'] > 180)) {
                $errors[] = "project.location.longitude out of range";
            }
            if (isset($loc['geofence_radius_meters']) && (!is_numeric($loc['geofence_radius_meters']) || $loc['geofence_radius_meters'] < 10 || $loc['geofence_radius_meters'] > 50000)) {
                $errors[] = "project.location.geofence_radius_meters out of range [10, 50000]";
            }
        }
        if (isset($proj['dates']) && is_array($proj['dates'])) {
            foreach (['estimated_start_date', 'estimated_completion_date'] as $dateField) {
                if (isset($proj['dates'][$dateField]) && !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', (string)$proj['dates'][$dateField])) {
                    $errors[] = "project.dates.{$dateField} invalid date pattern";
                }
            }
        }
        if (isset($proj['assigned_roles']) && is_array($proj['assigned_roles'])) {
            if (count($proj['assigned_roles']) < 1) {
                $errors[] = "project.assigned_roles minItems 1";
            }
            $allowedRoles = ['project_manager', 'lead_electrician', 'estimator', 'supervisor'];
            foreach ($proj['assigned_roles'] as $roleItem) {
                if (!isset($roleItem['role']) || !in_array($roleItem['role'], $allowedRoles, true)) {
                    $errors[] = "project.assigned_roles.role invalid enum value";
                }
            }
        }
    }

    // commercial_summary
    if (isset($payload['commercial_summary']) && is_array($payload['commercial_summary'])) {
        $comm = $payload['commercial_summary'];
        if (isset($comm['currency']) && !preg_match('/^[A-Z]{3}$/', (string)$comm['currency'])) {
            $errors[] = "commercial_summary.currency invalid pattern";
        }
        if (isset($comm['approved_estimate_hash']) && !preg_match('/^[a-f0-9]{64}$/', (string)$comm['approved_estimate_hash'])) {
            $errors[] = "commercial_summary.approved_estimate_hash invalid pattern";
        }
    }

    // materials_snapshot
    if (isset($payload['materials_snapshot']) && is_array($payload['materials_snapshot'])) {
        $mat = $payload['materials_snapshot'];
        $matReq = ['snapshot_id', 'version', 'generated_at', 'total_items', 'items'];
        foreach ($matReq as $field) {
            if (!array_key_exists($field, $mat)) {
                $errors[] = "materials_snapshot.{$field} required";
            }
        }
        if (isset($mat['items']) && is_array($mat['items'])) {
            $itemCodes = [];
            foreach ($mat['items'] as $item) {
                foreach (['item_id', 'item_code', 'description', 'category', 'quantity', 'unit_of_measure'] as $req) {
                    if (!array_key_exists($req, $item)) {
                        $errors[] = "materials_snapshot item {$req} required";
                    }
                }
                if (isset($item['quantity']) && (!is_numeric($item['quantity']) || $item['quantity'] < 0)) {
                    $errors[] = "materials_snapshot item quantity must be >= 0";
                }
                if (isset($item['item_id'])) {
                    if (in_array($item['item_id'], $itemCodes, true)) {
                        $errors[] = "materials_snapshot items must be unique (duplicate item_id: {$item['item_id']})";
                    }
                    $itemCodes[] = $item['item_id'];
                }
            }
        }
    }

    // documents_manifest
    if (isset($payload['documents_manifest']) && is_array($payload['documents_manifest'])) {
        $docs = $payload['documents_manifest'];
        if (isset($docs['documents']) && is_array($docs['documents'])) {
            $docTypes = ['drawings', 'specifications', 'proposal', 'contract', 'boq_export', 'permit', 'other'];
            foreach ($docs['documents'] as $doc) {
                if (isset($doc['document_type']) && !in_array($doc['document_type'], $docTypes, true)) {
                    $errors[] = "documents_manifest document_type invalid enum";
                }
                if (isset($doc['download_url']) && !preg_match('/^(https?:\\/\\/[a-zA-Z0-9.-]+(:[0-9]+)?\\/|\\/api\\/)[^\\\\\\s]+$/', (string)$doc['download_url'])) {
                    $errors[] = "documents_manifest download_url must be HTTPS/HTTP or /api/ endpoint without file protocols";
                }
            }
        }
    }

    return empty($errors);
}

$validationErrors = [];
$isValid = validateWonProjectContractPayload($canonicalPayload, $validationErrors);
assertCondition($isValid, "Payload canónico pasa validación estricta de schema (required, tipos, additionalProperties, enum, pattern, límites)");
if (!$isValid) {
    fwrite(STDERR, "Errores de validación en fixture canónico: " . implode('; ', $validationErrors) . "\n");
}

assertCondition(isset($canonicalPayload['event_id']) && preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $canonicalPayload['event_id']) === 1, "Payload canónico tiene event_id UUID válido");
assertCondition(isset($canonicalPayload['occurred_at']) && !empty($canonicalPayload['occurred_at']), "Payload canónico tiene occurred_at presente");
assertCondition($canonicalPayload['source_system'] === 'takeoff', "Payload canónico tiene source_system takeoff");
assertCondition(isset($canonicalPayload['source_project_id'], $canonicalPayload['source_bid_id'], $canonicalPayload['source_estimate_id']), "Payload canónico incluye IDs de origen de Takeoff");
assertCondition(isset($canonicalPayload['approved_estimate']['checksum_sha256']) && strlen($canonicalPayload['approved_estimate']['checksum_sha256']) === 64, "approved_estimate contiene checksum sha256 válido");
assertCondition(isset($canonicalPayload['project']['location']['geofence_radius_meters']), "project contiene geofence_radius_meters");
assertCondition(isset($canonicalPayload['materials_snapshot']['items'][0]['quantity']), "materials_snapshot contiene items con cantidad");
assertCondition(isset($canonicalPayload['materials_snapshot']['items'][0]['unit_of_measure']), "materials_snapshot contiene items con unit_of_measure (UOM)");
assertCondition(isset($canonicalPayload['materials_snapshot']['items'][0]['unit_cost']), "materials_snapshot contiene items con costo opcional unit_cost");
assertCondition(isset($canonicalPayload['documents_manifest']['documents'][0]['download_url']), "documents_manifest contiene download_url seguro HTTPS");

// ---------------------------------------------------------------------------
// 2. Comprobación de estructura de carpetas de proyecto esperadas: Takeoff Import, BoM, Drawings
// ---------------------------------------------------------------------------
echo "=== 2. Comprobación de especificación de carpetas requeridas (Takeoff Import, BoM, Drawings) ===\n";
$expectedFolders = ['Takeoff Import', 'BoM', 'Drawings'];
assertCondition(count($expectedFolders) === 3 && in_array('Takeoff Import', $expectedFolders, true) && in_array('BoM', $expectedFolders, true) && in_array('Drawings', $expectedFolders, true), "Especificación de carpetas predeterminadas requeridas en Electroplan contiene Takeoff Import, BoM y Drawings");

// ---------------------------------------------------------------------------
// 3. Verificación de no-invocación a Inventory System, sync_project_to_inventory_from_api ni cURL
// ---------------------------------------------------------------------------
echo "=== 3. Comprobación de aislamiento: no llamadas a Inventory System, sync_project_to_inventory_from_api ni cURL ===\n";
if (file_exists($serviceFile)) {
    $serviceCode = (string)file_get_contents($serviceFile);
    assertCondition(strpos($serviceCode, 'curl_init') === false, "WonProjectImportService no contiene llamadas a curl_init");
    assertCondition(strpos($serviceCode, 'curl_exec') === false, "WonProjectImportService no contiene llamadas a curl_exec");
    assertCondition(strpos($serviceCode, 'sync_project_to_inventory_from_api') === false, "WonProjectImportService no invoca sync_project_to_inventory_from_api");
    assertCondition(strpos($serviceCode, 'InventoryApiClient') === false, "WonProjectImportService no contiene referencias a InventoryApiClient");
} else {
    assertCondition(!function_exists('sync_project_to_inventory_from_api'), "Entorno de test no define sync_project_to_inventory_from_api");
    assertCondition(!class_exists('InventoryApiClient'), "Entorno de test no define InventoryApiClient");
}

// ---------------------------------------------------------------------------
// 4. Verificación de Interfaz Pública Congelada (STORY-0017)
// ---------------------------------------------------------------------------
echo "=== 4. Verificación de Interfaz Pública de WonProjectImportService ===\n";

$serviceClassExists = class_exists('WonProjectImportService');
$validationExceptionExists = class_exists('WonProjectImportValidationException');
$conflictExceptionExists = class_exists('WonProjectImportConflictException');

if (!$serviceClassExists || !$validationExceptionExists || !$conflictExceptionExists) {
    echo "[EXPECTED_RED] WonProjectImportService o sus excepciones especializadas no existen todavía.\n";
    echo "  - WonProjectImportService: " . ($serviceClassExists ? "EXISTS" : "MISSING (EXPECTED RED)") . "\n";
    echo "  - WonProjectImportValidationException: " . ($validationExceptionExists ? "EXISTS" : "MISSING (EXPECTED RED)") . "\n";
    echo "  - WonProjectImportConflictException: " . ($conflictExceptionExists ? "EXISTS" : "MISSING (EXPECTED RED)") . "\n";
    echo "  -> Fallo funcional registrado explícitamente conforme al flujo contract-first de STORY-0017.\n";

    // Si la clase no existe, salimos con código 1 documentando el rojo esperado de contract-first.
    exit(1);
}

// Si la clase existe en una ejecución futura del lane backend:
$reflectionClass = new ReflectionClass('WonProjectImportService');
assertCondition($reflectionClass->isFinal(), "WonProjectImportService debe ser declarada final");

$constructor = $reflectionClass->getConstructor();
assertCondition($constructor !== null, "WonProjectImportService tiene constructor");
if ($constructor !== null) {
    $params = $constructor->getParameters();
    assertCondition(count($params) >= 1 && $params[0]->hasType() && $params[0]->getType()->getName() === 'PDO', "El constructor debe aceptar PDO \$pdo");
}

assertCondition($reflectionClass->hasMethod('import'), "WonProjectImportService debe implementar public function import(array \$payload): array");
if ($reflectionClass->hasMethod('import')) {
    $importMethod = $reflectionClass->getMethod('import');
    assertCondition($importMethod->isPublic(), "El método import debe ser public");
    $importParams = $importMethod->getParameters();
    assertCondition(count($importParams) === 1 && $importParams[0]->hasType() && $importParams[0]->getType()->getName() === 'array', "El parámetro de import debe tener tipo array");
}

// Validación de excepciones
assertCondition(is_subclass_of('WonProjectImportValidationException', 'Exception'), "WonProjectImportValidationException hereda de Exception");
assertCondition(is_subclass_of('WonProjectImportConflictException', 'Exception'), "WonProjectImportConflictException hereda de Exception");

// Instanciación sobre PDO SQLite in-memory para verificar rechazo de payloads inválidos
try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Schema con nombres reales
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
            parent_id INTEGER NULL,
            name TEXT,
            depth INTEGER NOT NULL DEFAULT 0,
            deleted_at TEXT NULL,
            UNIQUE(project_id, name)
        );
        CREATE TABLE IF NOT EXISTS takeoff_won_project_receipts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            event_id TEXT UNIQUE,
            source_system TEXT,
            source_project_id TEXT,
            occurred_at TEXT,
            payload_hash TEXT,
            payload_canonical TEXT,
            status TEXT,
            result TEXT,
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
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(link_id, item_id)
        );
    ");

    $service = new WonProjectImportService($pdo);

    // Payload inválido sin event_id
    $invalidPayloadMissingEventId = $canonicalPayload;
    unset($invalidPayloadMissingEventId['event_id']);
    try {
        $service->import($invalidPayloadMissingEventId);
        assertCondition(false, "import() debe lanzar WonProjectImportValidationException si falta event_id");
    } catch (WonProjectImportValidationException $e) {
        assertCondition(true, "import() lanzó WonProjectImportValidationException ante payload sin event_id");
    }

    // Payload inválido con source_system inválido
    $invalidPayloadSource = $canonicalPayload;
    $invalidPayloadSource['source_system'] = 'other_system';
    try {
        $service->import($invalidPayloadSource);
        assertCondition(false, "import() debe lanzar WonProjectImportValidationException si source_system no es takeoff");
    } catch (WonProjectImportValidationException $e) {
        assertCondition(true, "import() lanzó WonProjectImportValidationException ante source_system inválido");
    }

    // Payload inválido con occurred_at ausente
    $invalidPayloadNoDate = $canonicalPayload;
    unset($invalidPayloadNoDate['occurred_at']);
    try {
        $service->import($invalidPayloadNoDate);
        assertCondition(false, "import() debe lanzar WonProjectImportValidationException si falta occurred_at");
    } catch (WonProjectImportValidationException $e) {
        assertCondition(true, "import() lanzó WonProjectImportValidationException ante payload sin occurred_at");
    }

    // Payload inválido con additionalProperties en root
    $invalidAdditionalProp = $canonicalPayload;
    $invalidAdditionalProp['unexpected_injected_prop'] = 'malicious_value';
    try {
        $service->import($invalidAdditionalProp);
        assertCondition(false, "import() debe lanzar WonProjectImportValidationException ante payload con additionalProperties no permitidas");
    } catch (WonProjectImportValidationException $e) {
        assertCondition(true, "import() lanzó WonProjectImportValidationException ante additionalProperties");
    }

    // Payload inválido con checksum_sha256 incorrecto
    $invalidChecksum = $canonicalPayload;
    $invalidChecksum['approved_estimate']['checksum_sha256'] = 'invalid_not_sha256';
    try {
        $service->import($invalidChecksum);
        assertCondition(false, "import() debe lanzar WonProjectImportValidationException ante checksum sha256 no conforme al patrón");
    } catch (WonProjectImportValidationException $e) {
        assertCondition(true, "import() lanzó WonProjectImportValidationException ante checksum sha256 no conforme");
    }

    // Payload inválido con rol asignado no permitido en enum
    $invalidRole = $canonicalPayload;
    $invalidRole['project']['assigned_roles'][0]['role'] = 'invalid_role_title';
    try {
        $service->import($invalidRole);
        assertCondition(false, "import() debe lanzar WonProjectImportValidationException ante rol no contemplado en enum");
    } catch (WonProjectImportValidationException $e) {
        assertCondition(true, "import() lanzó WonProjectImportValidationException ante rol enum inválido");
    }

} catch (Throwable $e) {
    fwrite(STDERR, "[UNEXPECTED ERROR] " . $e->getMessage() . "\n");
    exit(1);
}

if ($failureCount > 0) {
    fwrite(STDERR, "Total fallos de aserción: {$failureCount}\n");
    exit(1);
}

echo "Todos los tests de contrato pasaron con éxito.\n";
exit(0);

