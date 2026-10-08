<?php

declare(strict_types=1);

/**
 * WonProjectImportValidationException
 *
 * Excepcion arrojada cuando un payload no cumple con las reglas estrictas
 * del contrato WonProjectExport.v1.
 */
final class WonProjectImportValidationException extends \RuntimeException
{
    private array $errors;

    public function __construct(string $message, array $errors = [], int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}

/**
 * WonProjectImportConflictException
 *
 * Excepcion arrojada cuando un evento entrante tiene el mismo event_id
 * que un evento previamente registrado, pero con un hash canonico diferente.
 */
final class WonProjectImportConflictException extends \RuntimeException
{
    private string $eventId;
    private string $existingHash;
    private string $incomingHash;

    public function __construct(string $eventId, string $existingHash, string $incomingHash)
    {
        parent::__construct(sprintf(
            "Conflict: event_id '%s' already exists with payload hash '%s', but received hash '%s'",
            $eventId,
            $existingHash,
            $incomingHash
        ));
        $this->eventId = $eventId;
        $this->existingHash = $existingHash;
        $this->incomingHash = $incomingHash;
    }

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function getExistingHash(): string
    {
        return $this->existingHash;
    }

    public function getIncomingHash(): string
    {
        return $this->incomingHash;
    }
}

/**
 * WonProjectImportService
 *
 * Servicio transaccional e idempotente para recepcion, canonicalizacion,
 * persistencia y resolucion determinista de eventos WonProjectExport.v1.
 */
final class WonProjectImportService
{
    private \PDO $pdo;
    private array $tableColumnsCache = [];

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Procesa un payload WonProjectExport.v1 decodificado.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     * @throws WonProjectImportValidationException Si el payload no cumple el esquema estricto.
     * @throws WonProjectImportConflictException Si event_id existe con hash canonico diferente.
     * @throws \Throwable Ante cualquier fallo dentro de la transaccion.
     */
    public function import(array $payload): array
    {
        $this->validatePayload($payload);

        $eventId = (string)$payload['event_id'];
        $occurredAt = (string)$payload['occurred_at'];
        $sourceSystem = (string)$payload['source_system'];
        $sourceProjectId = (string)$payload['source_project_id'];

        $canonicalJson = $this->computeCanonicalJson($payload);
        $canonicalHash = hash('sha256', $canonicalJson);

        // 1. Verificacion rapida de receipt existente fuera de transaccion
        $existingReceipt = $this->getReceipt($eventId);
        if ($existingReceipt !== null) {
            return $this->resolveExistingReceipt(
                $existingReceipt,
                $canonicalHash,
                $eventId,
                $sourceSystem,
                $sourceProjectId
            );
        }

        $dtOccurred = (new \DateTimeImmutable($occurredAt))->setTimezone(new \DateTimeZone('UTC'));
        $occurredAtDb = $dtOccurred->format('Y-m-d H:i:s.u');

        // 2. Control acotado de reintentos para carreras de concurrencia
        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $this->pdo->beginTransaction();

            try {
                // Confirmar decision replay/conflict dentro de la transaccion
                $existingReceiptTx = $this->getReceipt($eventId, true);
                if ($existingReceiptTx !== null) {
                    if ($this->pdo->inTransaction()) {
                        try {
                            $this->pdo->rollBack();
                        } catch (\PDOException $rbEx) {
                            // Ignorar si el motor ya cerro la transaccion
                        }
                    }
                    return $this->resolveExistingReceipt(
                        $existingReceiptTx,
                        $canonicalHash,
                        $eventId,
                        $sourceSystem,
                        $sourceProjectId
                    );
                }

                // Serializar sobre el vinculo de proyecto
                $existingLink = $this->getLink($sourceSystem, $sourceProjectId, true);

                // Volver a comprobar receipt despues de serializar el vinculo,
                // antes de cualquier mutacion o insercion
                $existingReceiptAfterLock = $this->getReceipt($eventId, true);
                if ($existingReceiptAfterLock !== null) {
                    if ($this->pdo->inTransaction()) {
                        try {
                            $this->pdo->rollBack();
                        } catch (\PDOException $rbEx) {
                            // Ignorar si el motor ya cerro la transaccion
                        }
                    }
                    return $this->resolveExistingReceipt(
                        $existingReceiptAfterLock,
                        $canonicalHash,
                        $eventId,
                        $sourceSystem,
                        $sourceProjectId
                    );
                }

                if ($existingLink !== null) {
                    $lastOccurredAtDb = (string)$existingLink['last_occurred_at'];
                    $lastDt = (new \DateTimeImmutable($lastOccurredAtDb))->setTimezone(new \DateTimeZone('UTC'));
                    $lastOccurredAtNorm = $lastDt->format('Y-m-d H:i:s.u');

                    $timeCmp = strcmp($occurredAtDb, $lastOccurredAtNorm);
                    $isStale = false;

                    if ($timeCmp < 0) {
                        $isStale = true;
                    } elseif ($timeCmp === 0) {
                        if (strcmp($eventId, (string)$existingLink['last_event_id']) <= 0) {
                            $isStale = true;
                        }
                    }

                    if ($isStale) {
                        $result = $this->recordObsoleteEvent(
                            $payload,
                            $existingLink,
                            $canonicalHash,
                            $canonicalJson,
                            $occurredAtDb
                        );
                        $this->pdo->commit();
                        return $result;
                    }

                    $result = $this->processSubsequentImport(
                        $payload,
                        $existingLink,
                        $canonicalHash,
                        $canonicalJson,
                        $occurredAtDb
                    );
                    $this->pdo->commit();
                    return $result;
                }

                $result = $this->processFirstImport(
                    $payload,
                    $canonicalHash,
                    $canonicalJson,
                    $occurredAtDb
                );
                $this->pdo->commit();
                return $result;
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    try {
                        $this->pdo->rollBack();
                    } catch (\PDOException $rbEx) {
                        // Ignorar si el motor ya cerro la transaccion
                    }
                }

                if ($attempt < $maxAttempts && $this->isConcurrencyException($e)) {
                    usleep(random_int(10000, 30000));
                    continue;
                }

                throw $e;
            }
        }

        throw new \RuntimeException("Exceeded maximum concurrency retry attempts for event {$eventId}");
    }

    /**
     * Resuelve un receipt existente como replayed si el hash coincide,
     * o arroja WonProjectImportConflictException si el hash difiere.
     */
    private function resolveExistingReceipt(
        array $existingReceipt,
        string $canonicalHash,
        string $eventId,
        string $sourceSystem,
        string $sourceProjectId
    ): array {
        if ($existingReceipt['payload_hash'] === $canonicalHash) {
            $storedResult = json_decode((string)$existingReceipt['result'], true);
            if (!is_array($storedResult)) {
                $storedResult = [];
            }
            $projectId = isset($storedResult['project_id']) ? (int)$storedResult['project_id'] : 0;
            if ($projectId === 0) {
                $link = $this->getLink($sourceSystem, $sourceProjectId);
                $projectId = $link ? (int)$link['project_id'] : 0;
            }

            return [
                'status' => 'replayed',
                'event_id' => $eventId,
                'source_system' => $sourceSystem,
                'source_project_id' => $sourceProjectId,
                'project_id' => $projectId,
                'replayed' => true,
                'stale' => false,
            ];
        }

        throw new WonProjectImportConflictException(
            $eventId,
            (string)$existingReceipt['payload_hash'],
            $canonicalHash
        );
    }

    /**
     * Determina si una excepcion corresponde a un error de concurrencia conocido
     * (deadlock, lock timeout, o violacion unica por carrera de insercion).
     */
    private function isConcurrencyException(\Throwable $e): bool
    {
        if ($e instanceof WonProjectImportValidationException || $e instanceof WonProjectImportConflictException) {
            return false;
        }

        $sqlState = null;
        $driverCode = null;
        $message = $e->getMessage();

        if ($e instanceof \PDOException) {
            $code = $e->getCode();
            if (is_string($code)) {
                $sqlState = $code;
            }
            if (isset($e->errorInfo[0]) && is_string($e->errorInfo[0])) {
                $sqlState = $e->errorInfo[0];
            }
            if (isset($e->errorInfo[1])) {
                $driverCode = (int)$e->errorInfo[1];
            }
        }

        // 1. Deadlock / serialization failure
        if ($sqlState === '40001' || $sqlState === '40P01' || $driverCode === 1213 || stripos($message, 'deadlock') !== false) {
            return true;
        }

        // 2. Lock wait timeout / busy
        if ($sqlState === '55P03'
            || $driverCode === 1205
            || stripos($message, 'lock wait timeout') !== false
            || stripos($message, 'database is locked') !== false
            || stripos($message, 'database table is locked') !== false
            || ($driverCode === 5 || $driverCode === 6) // SQLITE_BUSY, SQLITE_LOCKED
        ) {
            return true;
        }

        // 3. Violacion unica de la carrera (MySQL 1062, SQLite 19 con UNIQUE, Postgres 23505)
        if ($driverCode === 1062
            || $sqlState === '23505'
            || stripos($message, 'Duplicate entry') !== false
            || stripos($message, 'UNIQUE constraint failed') !== false
        ) {
            return true;
        }

        return false;
    }

    /**
     * Procesa la primera importacion para un source_project_id.
     */
    private function processFirstImport(
        array $payload,
        string $canonicalHash,
        string $canonicalJson,
        string $occurredAtDb
    ): array {
        $eventId = (string)$payload['event_id'];
        $sourceSystem = (string)$payload['source_system'];
        $sourceProjectId = (string)$payload['source_project_id'];
        $sourceBidId = isset($payload['source_bid_id']) ? (string)$payload['source_bid_id'] : null;
        $sourceEstimateId = isset($payload['source_estimate_id']) ? (string)$payload['source_estimate_id'] : null;

        // 1. Crear proyecto
        $projectId = $this->createProject($payload);

        // 2. Crear vinculo
        $stmtLink = $this->pdo->prepare("
            INSERT INTO takeoff_project_links (
                project_id, source_system, source_project_id,
                source_bid_id, source_estimate_id,
                last_event_id, last_occurred_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtLink->execute([
            $projectId,
            $sourceSystem,
            $sourceProjectId,
            $sourceBidId,
            $sourceEstimateId,
            $eventId,
            $occurredAtDb
        ]);
        $linkId = (int)$this->pdo->lastInsertId();

        // 3. Crear carpetas raiz
        $this->ensureRootFolders($projectId);

        // 4. Sincronizar snapshot de materiales
        $materialsItems = $payload['materials_snapshot']['items'] ?? [];
        $this->syncMaterials($linkId, $materialsItems);

        // 5. Registrar receipt
        $resultData = [
            'status' => 'imported',
            'event_id' => $eventId,
            'source_system' => $sourceSystem,
            'source_project_id' => $sourceProjectId,
            'project_id' => $projectId,
            'replayed' => false,
            'stale' => false,
        ];

        $this->insertReceipt(
            $eventId,
            $sourceSystem,
            $sourceProjectId,
            $occurredAtDb,
            $canonicalHash,
            $canonicalJson,
            'imported',
            $resultData,
            $sourceBidId,
            $sourceEstimateId
        );

        return $resultData;
    }

    /**
     * Procesa una importacion posterior para un vinculo existente.
     */
    private function processSubsequentImport(
        array $payload,
        array $link,
        string $canonicalHash,
        string $canonicalJson,
        string $occurredAtDb
    ): array {
        $eventId = (string)$payload['event_id'];
        $sourceSystem = (string)$link['source_system'];
        $sourceProjectId = (string)$link['source_project_id'];
        $linkId = (int)$link['id'];
        $projectId = (int)$link['project_id'];
        $sourceBidId = isset($payload['source_bid_id']) ? (string)$payload['source_bid_id'] : (isset($link['source_bid_id']) ? (string)$link['source_bid_id'] : null);
        $sourceEstimateId = isset($payload['source_estimate_id']) ? (string)$payload['source_estimate_id'] : (isset($link['source_estimate_id']) ? (string)$link['source_estimate_id'] : null);

        // 1. Actualizar proyecto existente
        $this->updateProject($projectId, $payload);

        // 2. Actualizar vinculo
        $linkCols = $this->getTableColumns('takeoff_project_links');
        $hasUpdatedAt = in_array('updated_at', $linkCols, true);
        $updateSql = "
            UPDATE takeoff_project_links
            SET last_event_id = ?, last_occurred_at = ?" .
            ($hasUpdatedAt ? ", updated_at = CURRENT_TIMESTAMP" : "") . "
            WHERE id = ?
        ";
        $stmtUpdateLink = $this->pdo->prepare($updateSql);
        $stmtUpdateLink->execute([$eventId, $occurredAtDb, $linkId]);

        // 3. Asegurar carpetas raiz sin duplicar
        $this->ensureRootFolders($projectId);

        // 4. Sincronizar snapshot de materiales
        $materialsItems = $payload['materials_snapshot']['items'] ?? [];
        $this->syncMaterials($linkId, $materialsItems);

        // 5. Registrar receipt
        $resultData = [
            'status' => 'imported',
            'event_id' => $eventId,
            'source_system' => $sourceSystem,
            'source_project_id' => $sourceProjectId,
            'project_id' => $projectId,
            'replayed' => false,
            'stale' => false,
        ];

        $this->insertReceipt(
            $eventId,
            $sourceSystem,
            $sourceProjectId,
            $occurredAtDb,
            $canonicalHash,
            $canonicalJson,
            'imported',
            $resultData,
            $sourceBidId,
            $sourceEstimateId
        );

        return $resultData;
    }

    /**
     * Registra un evento obsoleto rechazado sin mutar proyecto, carpetas, vinculo ni materiales.
     */
    private function recordObsoleteEvent(
        array $payload,
        array $link,
        string $canonicalHash,
        string $canonicalJson,
        string $occurredAtDb
    ): array {
        $eventId = (string)$payload['event_id'];
        $sourceSystem = (string)$link['source_system'];
        $sourceProjectId = (string)$link['source_project_id'];
        $projectId = (int)$link['project_id'];
        $sourceBidId = isset($payload['source_bid_id']) ? (string)$payload['source_bid_id'] : (isset($link['source_bid_id']) ? (string)$link['source_bid_id'] : null);
        $sourceEstimateId = isset($payload['source_estimate_id']) ? (string)$payload['source_estimate_id'] : (isset($link['source_estimate_id']) ? (string)$link['source_estimate_id'] : null);

        $resultData = [
            'status' => 'stale',
            'event_id' => $eventId,
            'source_system' => $sourceSystem,
            'source_project_id' => $sourceProjectId,
            'project_id' => $projectId,
            'replayed' => false,
            'stale' => true,
        ];

        $this->insertReceipt(
            $eventId,
            $sourceSystem,
            $sourceProjectId,
            $occurredAtDb,
            $canonicalHash,
            $canonicalJson,
            'stale',
            $resultData,
            $sourceBidId,
            $sourceEstimateId
        );

        return $resultData;
    }

    /**
     * Inserta un registro en takeoff_won_project_receipts con prepared statements.
     */
    private function insertReceipt(
        string $eventId,
        string $sourceSystem,
        string $sourceProjectId,
        string $occurredAtDb,
        string $canonicalHash,
        string $canonicalJson,
        string $status,
        array $resultData,
        ?string $sourceBidId = null,
        ?string $sourceEstimateId = null
    ): void {
        $cols = $this->getTableColumns('takeoff_won_project_receipts');

        $candidateMap = [
            'event_id' => $eventId,
            'source_system' => $sourceSystem,
            'source_project_id' => $sourceProjectId,
            'occurred_at' => $occurredAtDb,
            'payload_hash' => $canonicalHash,
            'payload_canonical' => $canonicalJson,
            'status' => $status,
            'result' => json_encode($resultData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'source_bid_id' => $sourceBidId,
            'source_estimate_id' => $sourceEstimateId,
        ];

        $insertCols = [];
        $placeholders = [];
        $params = [];

        foreach ($candidateMap as $col => $val) {
            if (in_array($col, $cols, true)) {
                $insertCols[] = $col;
                $placeholders[] = '?';
                $params[] = $val;
            }
        }

        $sql = "INSERT INTO takeoff_won_project_receipts (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Crea un proyecto en la tabla projects utilizando columnas disponibles.
     */
    private function createProject(array $payload): int
    {
        $cols = $this->getTableColumns('projects');
        $fieldMap = $this->mapProjectFields($payload);

        $insertCols = [];
        $placeholders = [];
        $params = [];

        foreach ($fieldMap as $col => $val) {
            if (in_array($col, $cols, true)) {
                $insertCols[] = $col;
                $placeholders[] = '?';
                $params[] = $val;
            }
        }

        if (in_array('created_at', $cols, true)) {
            $insertCols[] = 'created_at';
            $placeholders[] = 'CURRENT_TIMESTAMP';
        }

        $sql = "INSERT INTO projects (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Actualiza un proyecto en la tabla projects utilizando columnas disponibles.
     */
    private function updateProject(int $projectId, array $payload): void
    {
        $cols = $this->getTableColumns('projects');
        $fieldMap = $this->mapProjectFields($payload);

        $updateClauses = [];
        $params = [];

        foreach ($fieldMap as $col => $val) {
            if (in_array($col, ['created_by', 'assigned_user_id'], true)) {
                continue;
            }
            if (in_array($col, $cols, true)) {
                $updateClauses[] = "{$col} = ?";
                $params[] = $val;
            }
        }

        if (in_array('updated_at', $cols, true)) {
            $updateClauses[] = "updated_at = CURRENT_TIMESTAMP";
        }

        if (!empty($updateClauses)) {
            $params[] = $projectId;
            $sql = "UPDATE projects SET " . implode(', ', $updateClauses) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        }
    }

    /**
     * Mapea campos del payload a los campos soportados de la tabla projects.
     */
    private function mapProjectFields(array $payload): array
    {
        $project = $payload['project'] ?? [];
        $projectName = trim((string)($project['name'] ?? ''));
        if ($projectName === '') {
            throw new WonProjectImportValidationException("Project name is required in payload");
        }

        $projectNumber = isset($project['number']) ? (string)$project['number'] : null;
        $client = $project['client'] ?? [];
        $location = $project['location'] ?? [];
        $dates = $project['dates'] ?? [];
        $traceability = $payload['traceability'] ?? [];
        $approvedEstimate = $payload['approved_estimate'] ?? [];

        $addressParts = [];
        if (!empty($location['address_line1'])) $addressParts[] = (string)$location['address_line1'];
        if (!empty($location['address_line2'])) $addressParts[] = (string)$location['address_line2'];
        if (!empty($location['city'])) $addressParts[] = (string)$location['city'];
        if (!empty($location['state'])) $addressParts[] = (string)$location['state'];
        if (!empty($location['postal_code'])) $addressParts[] = (string)$location['postal_code'];
        if (!empty($location['country'])) $addressParts[] = (string)$location['country'];
        $address = implode(', ', $addressParts);

        $description = isset($traceability['notes']) ? (string)$traceability['notes'] : '';
        $contactName = isset($client['contact_name']) ? (string)$client['contact_name'] : null;
        $contactPhone = isset($client['contact_phone']) ? (string)$client['contact_phone'] : null;
        $companyName = isset($client['name']) ? (string)$client['name'] : null;

        $dateStarted = isset($dates['estimated_start_date']) ? (string)$dates['estimated_start_date'] : null;
        $dateFinished = isset($dates['estimated_completion_date']) ? (string)$dates['estimated_completion_date'] : null;
        $dateBidAwarded = !empty($approvedEstimate['approved_at']) ? substr((string)$approvedEstimate['approved_at'], 0, 10) : null;

        return [
            'name' => $projectName,
            'project_number' => $projectNumber,
            'number' => $projectNumber,
            'client_name' => $companyName,
            'description' => $description,
            'address' => $address,
            'notes' => $description,
            'contact_name' => $contactName,
            'contact_phone' => $contactPhone,
            'company_name' => $companyName,
            'company_phone' => $contactPhone,
            'company_address' => $address !== '' ? $address : null,
            'date_bid_sent' => null,
            'date_bid_awarded' => $dateBidAwarded,
            'date_started' => $dateStarted,
            'date_finished' => $dateFinished,
            'date_warranty_end' => null,
            'created_by' => null,
            'assigned_user_id' => null,
            'status' => 'active'
        ];
    }

    /**
     * Asegura la existencia de las 3 carpetas raiz sin duplicarlas.
     * Carpetas raiz: Takeoff Import, BoM, Drawings (parent_id NULL, depth 0).
     */
    private function ensureRootFolders(int $projectId): array
    {
        $folderNames = ['Takeoff Import', 'BoM', 'Drawings'];
        $folderCols = $this->getTableColumns('folders');

        $hasParentId = in_array('parent_id', $folderCols, true);
        $hasDepth = in_array('depth', $folderCols, true);
        $hasDeletedAt = in_array('deleted_at', $folderCols, true);
        $hasCreatedAt = in_array('created_at', $folderCols, true);

        $existing = [];

        foreach ($folderNames as $name) {
            $where = ["project_id = ?", "name = ?"];
            $params = [$projectId, $name];

            if ($hasParentId) {
                $where[] = "parent_id IS NULL";
            }
            if ($hasDeletedAt) {
                $where[] = "deleted_at IS NULL";
            }

            $selectSql = "SELECT id FROM folders WHERE " . implode(' AND ', $where) . " LIMIT 1";
            $stmtSelect = $this->pdo->prepare($selectSql);
            $stmtSelect->execute($params);
            $row = $stmtSelect->fetch(\PDO::FETCH_ASSOC);

            if ($row) {
                $existing[$name] = (int)$row['id'];
                continue;
            }

            $insertCols = ['project_id', 'name'];
            $placeholders = ['?', '?'];
            $insertParams = [$projectId, $name];

            if ($hasParentId) {
                $insertCols[] = 'parent_id';
                $placeholders[] = 'NULL';
            }
            if ($hasDepth) {
                $insertCols[] = 'depth';
                $placeholders[] = '?';
                $insertParams[] = 0;
            }
            if ($hasCreatedAt) {
                $insertCols[] = 'created_at';
                $placeholders[] = 'CURRENT_TIMESTAMP';
            }

            $insertSql = "INSERT INTO folders (" . implode(', ', $insertCols) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmtInsert = $this->pdo->prepare($insertSql);
            $stmtInsert->execute($insertParams);
            $existing[$name] = (int)$this->pdo->lastInsertId();
        }

        return $existing;
    }

    /**
     * Sincroniza materiales vigentes: actualiza existentes, inserta nuevos y retira obsoletos.
     */
    private function syncMaterials(int $linkId, array $items): void
    {
        $materialCols = $this->getTableColumns('takeoff_imported_materials');
        $hasUpdatedAt = in_array('updated_at', $materialCols, true);

        $snapshotItemIds = [];

        $stmtFind = $this->pdo->prepare("
            SELECT id FROM takeoff_imported_materials
            WHERE link_id = ? AND item_id = ?
        ");

        $updateSql = "
            UPDATE takeoff_imported_materials
            SET item_code = ?, description = ?, category = ?, quantity = ?,
                unit_of_measure = ?, unit_cost = ?, total_cost = ?, is_active = 1" .
            ($hasUpdatedAt ? ", updated_at = CURRENT_TIMESTAMP" : "") . "
            WHERE link_id = ? AND item_id = ?
        ";
        $stmtUpdate = $this->pdo->prepare($updateSql);

        $stmtInsert = $this->pdo->prepare("
            INSERT INTO takeoff_imported_materials (
                link_id, item_id, item_code, description, category,
                quantity, unit_of_measure, unit_cost, total_cost, is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");

        foreach ($items as $item) {
            $itemId = (string)$item['item_id'];
            $snapshotItemIds[] = $itemId;

            $itemCode = (string)($item['item_code'] ?? '');
            $description = (string)($item['description'] ?? '');
            $category = (string)($item['category'] ?? '');
            $quantity = (float)($item['quantity'] ?? 0);
            $unitOfMeasure = (string)($item['unit_of_measure'] ?? '');
            $unitCost = (isset($item['unit_cost']) && $item['unit_cost'] !== null) ? (float)$item['unit_cost'] : null;
            $totalCost = (isset($item['total_cost']) && $item['total_cost'] !== null) ? (float)$item['total_cost'] : null;

            $stmtFind->execute([$linkId, $itemId]);
            $existing = $stmtFind->fetch(\PDO::FETCH_ASSOC);

            if ($existing) {
                $stmtUpdate->execute([
                    $itemCode,
                    $description,
                    $category,
                    $quantity,
                    $unitOfMeasure,
                    $unitCost,
                    $totalCost,
                    $linkId,
                    $itemId
                ]);
            } else {
                $stmtInsert->execute([
                    $linkId,
                    $itemId,
                    $itemCode,
                    $description,
                    $category,
                    $quantity,
                    $unitOfMeasure,
                    $unitCost,
                    $totalCost
                ]);
            }
        }

        // Retirar materiales que ya no estan en el snapshot
        if (empty($snapshotItemIds)) {
            $retireAllSql = "
                UPDATE takeoff_imported_materials
                SET is_active = 0" . ($hasUpdatedAt ? ", updated_at = CURRENT_TIMESTAMP" : "") . "
                WHERE link_id = ? AND is_active = 1
            ";
            $stmtRetireAll = $this->pdo->prepare($retireAllSql);
            $stmtRetireAll->execute([$linkId]);
        } else {
            $inPlaceholders = implode(',', array_fill(0, count($snapshotItemIds), '?'));
            $retireSql = "
                UPDATE takeoff_imported_materials
                SET is_active = 0" . ($hasUpdatedAt ? ", updated_at = CURRENT_TIMESTAMP" : "") . "
                WHERE link_id = ? AND item_id NOT IN ({$inPlaceholders}) AND is_active = 1
            ";
            $stmtRetire = $this->pdo->prepare($retireSql);
            $params = array_merge([$linkId], $snapshotItemIds);
            $stmtRetire->execute($params);
        }
    }

    /**
     * Canonicaliza recursivamente un arreglo de datos:
     * - Ordena recursivamente las claves de objetos asociativos (alfabeticamente).
     * - Conserva el orden de arrays secuenciales / listas.
     */
    public function canonicalizePayload(array $payload): array
    {
        return $this->canonicalizeValue($payload);
    }

    /**
     * Genera la representacion JSON canonica determinista.
     */
    public function computeCanonicalJson(array $payload): string
    {
        $canonical = $this->canonicalizePayload($payload);
        $json = json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException("Failed to encode canonical payload to JSON");
        }
        return $json;
    }

    /**
     * Calcula el hash SHA-256 del payload canonico.
     */
    public function computeCanonicalHash(array $payload): string
    {
        return hash('sha256', $this->computeCanonicalJson($payload));
    }

    /**
     * Obtiene un receipt por event_id.
     */
    public function getReceipt(string $eventId, bool $forUpdate = false): ?array
    {
        $lockClause = '';
        if ($forUpdate && $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            $lockClause = ' FOR UPDATE';
        }

        $stmt = $this->pdo->prepare("
            SELECT event_id, source_system, source_project_id, occurred_at,
                   payload_hash, payload_canonical, status, result, created_at
            FROM takeoff_won_project_receipts
            WHERE event_id = ?{$lockClause}
        ");
        $stmt->execute([$eventId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Obtiene el vinculo externo por source_system y source_project_id.
     * En motores compatibles como MariaDB/MySQL aplica bloqueo FOR UPDATE dentro de transacciones.
     */
    public function getLink(string $sourceSystem, string $sourceProjectId, bool $forUpdate = false): ?array
    {
        $lockClause = '';
        if ($forUpdate && $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            $lockClause = ' FOR UPDATE';
        }

        $stmt = $this->pdo->prepare("
            SELECT id, project_id, source_system, source_project_id,
                   source_bid_id, source_estimate_id, last_event_id,
                   last_occurred_at, created_at, updated_at
            FROM takeoff_project_links
            WHERE source_system = ? AND source_project_id = ?{$lockClause}
        ");
        $stmt->execute([$sourceSystem, $sourceProjectId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Obtiene todos los materiales vigentes (activos) para un vinculo.
     */
    public function getActiveMaterials(int $linkId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT item_id, item_code, description, category, quantity,
                   unit_of_measure, unit_cost, total_cost
            FROM takeoff_imported_materials
            WHERE link_id = ? AND is_active = 1
            ORDER BY item_id ASC
        ");
        $stmt->execute([$linkId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Helper recursivo de canonicalizacion.
     */
    private function canonicalizeValue($value)
    {
        if (is_object($value)) {
            $value = (array)$value;
        }

        if (is_array($value)) {
            if ($this->isAssoc($value)) {
                ksort($value, SORT_STRING);
                $result = [];
                foreach ($value as $k => $v) {
                    $result[$k] = $this->canonicalizeValue($v);
                }
                return $result;
            }

            $result = [];
            foreach ($value as $v) {
                $result[] = $this->canonicalizeValue($v);
            }
            return $result;
        }

        return $value;
    }

    /**
     * Determina si un array es asociativo.
     */
    private function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Obtiene columnas de una tabla independientemente del motor PDO (MySQL o SQLite).
     */
    private function getTableColumns(string $table): array
    {
        if (isset($this->tableColumnsCache[$table])) {
            return $this->tableColumnsCache[$table];
        }

        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->query("PRAGMA table_info({$table})");
            $cols = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $cols[] = $row['name'];
            }
            return $this->tableColumnsCache[$table] = $cols;
        }

        return $this->tableColumnsCache[$table] = $this->pdo->query("DESCRIBE `{$table}`")->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Validador estricto del payload conforme a won-project-export.v1.schema.json.
     */
    private function validatePayload(array $payload): void
    {
        $errors = [];

        $rootRequired = [
            'event_id', 'occurred_at', 'source_system', 'source_project_id',
            'source_bid_id', 'source_estimate_id', 'idempotency_key', 'correlation_id',
            'traceability', 'approved_estimate', 'project', 'commercial_summary',
            'materials_snapshot', 'documents_manifest'
        ];

        // 1. Root required
        foreach ($rootRequired as $field) {
            if (!array_key_exists($field, $payload)) {
                $errors[] = "Root field missing: {$field}";
            }
        }

        // Root additionalProperties: false
        foreach (array_keys($payload) as $key) {
            if (!in_array($key, $rootRequired, true)) {
                $errors[] = "Unknown root property: {$key}";
            }
        }

        // event_id pattern (UUID)
        if (isset($payload['event_id'])) {
            if (!is_string($payload['event_id']) || !preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $payload['event_id'])) {
                $errors[] = "event_id must be valid UUID string";
            }
        }

        // occurred_at format: date-time
        if (isset($payload['occurred_at'])) {
            if (!is_string($payload['occurred_at']) || strtotime($payload['occurred_at']) === false) {
                $errors[] = "occurred_at must be valid date-time string";
            }
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
        if (isset($payload['traceability'])) {
            if (!is_array($payload['traceability'])) {
                $errors[] = "traceability must be object";
            } else {
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
                    if (isset($awarded['email']) && (!is_string($awarded['email']) || filter_var($awarded['email'], FILTER_VALIDATE_EMAIL) === false)) {
                        $errors[] = "traceability.awarded_by.email invalid format";
                    }
                }
            }
        }

        // approved_estimate
        if (isset($payload['approved_estimate'])) {
            if (!is_array($payload['approved_estimate'])) {
                $errors[] = "approved_estimate must be object";
            } else {
                $est = $payload['approved_estimate'];
                $estReq = ['estimate_id', 'estimate_number', 'revision', 'approved_at', 'approved_by_user_id', 'checksum_sha256', 'currency', 'total_amount', 'total_labor_hours'];
                foreach ($estReq as $field) {
                    if (!array_key_exists($field, $est)) {
                        $errors[] = "approved_estimate.{$field} required";
                    }
                }
                if (isset($est['checksum_sha256']) && (!is_string($est['checksum_sha256']) || !preg_match('/^[a-f0-9]{64}$/', $est['checksum_sha256']))) {
                    $errors[] = "approved_estimate.checksum_sha256 invalid pattern";
                }
                if (isset($est['currency']) && (!is_string($est['currency']) || !preg_match('/^[A-Z]{3}$/', $est['currency']))) {
                    $errors[] = "approved_estimate.currency invalid pattern";
                }
                if (isset($est['total_amount']) && (!is_numeric($est['total_amount']) || (float)$est['total_amount'] < 0)) {
                    $errors[] = "approved_estimate.total_amount must be >= 0";
                }
                if (isset($est['total_labor_hours']) && (!is_numeric($est['total_labor_hours']) || (float)$est['total_labor_hours'] < 0)) {
                    $errors[] = "approved_estimate.total_labor_hours must be >= 0";
                }
            }
        }

        // project
        if (isset($payload['project'])) {
            if (!is_array($payload['project'])) {
                $errors[] = "project must be object";
            } else {
                $proj = $payload['project'];
                $projReq = ['number', 'name', 'client', 'location', 'dates', 'assigned_roles'];
                foreach ($projReq as $field) {
                    if (!array_key_exists($field, $proj)) {
                        $errors[] = "project.{$field} required";
                    }
                }
                if (isset($proj['name']) && (!is_string($proj['name']) || trim($proj['name']) === '')) {
                    $errors[] = "project.name cannot be empty";
                }
                if (isset($proj['location']) && is_array($proj['location'])) {
                    $loc = $proj['location'];
                    if (isset($loc['country']) && (!is_string($loc['country']) || !preg_match('/^[A-Z]{2}$/', $loc['country']))) {
                        $errors[] = "project.location.country must be 2 uppercase letters";
                    }
                    if (isset($loc['latitude']) && (!is_numeric($loc['latitude']) || (float)$loc['latitude'] < -90 || (float)$loc['latitude'] > 90)) {
                        $errors[] = "project.location.latitude out of range";
                    }
                    if (isset($loc['longitude']) && (!is_numeric($loc['longitude']) || (float)$loc['longitude'] < -180 || (float)$loc['longitude'] > 180)) {
                        $errors[] = "project.location.longitude out of range";
                    }
                    if (isset($loc['geofence_radius_meters']) && (!is_numeric($loc['geofence_radius_meters']) || (float)$loc['geofence_radius_meters'] < 10 || (float)$loc['geofence_radius_meters'] > 50000)) {
                        $errors[] = "project.location.geofence_radius_meters out of range [10, 50000]";
                    }
                }
                if (isset($proj['dates']) && is_array($proj['dates'])) {
                    foreach (['estimated_start_date', 'estimated_completion_date'] as $dateField) {
                        if (isset($proj['dates'][$dateField]) && (!is_string($proj['dates'][$dateField]) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $proj['dates'][$dateField]))) {
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
        }

        // commercial_summary
        if (isset($payload['commercial_summary'])) {
            if (!is_array($payload['commercial_summary'])) {
                $errors[] = "commercial_summary must be object";
            } else {
                $comm = $payload['commercial_summary'];
                if (isset($comm['currency']) && (!is_string($comm['currency']) || !preg_match('/^[A-Z]{3}$/', $comm['currency']))) {
                    $errors[] = "commercial_summary.currency invalid pattern";
                }
                if (isset($comm['approved_estimate_hash']) && (!is_string($comm['approved_estimate_hash']) || !preg_match('/^[a-f0-9]{64}$/', $comm['approved_estimate_hash']))) {
                    $errors[] = "commercial_summary.approved_estimate_hash invalid pattern";
                }
            }
        }

        // materials_snapshot
        if (isset($payload['materials_snapshot'])) {
            if (!is_array($payload['materials_snapshot'])) {
                $errors[] = "materials_snapshot must be object";
            } else {
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
                        if (isset($item['quantity']) && (!is_numeric($item['quantity']) || (float)$item['quantity'] < 0)) {
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
        }

        // documents_manifest
        if (isset($payload['documents_manifest'])) {
            if (!is_array($payload['documents_manifest'])) {
                $errors[] = "documents_manifest must be object";
            } else {
                $docs = $payload['documents_manifest'];
                if (isset($docs['documents']) && is_array($docs['documents'])) {
                    $docTypes = ['drawings', 'specifications', 'proposal', 'contract', 'boq_export', 'permit', 'other'];
                    foreach ($docs['documents'] as $doc) {
                        if (isset($doc['document_type']) && !in_array($doc['document_type'], $docTypes, true)) {
                            $errors[] = "documents_manifest document_type invalid enum";
                        }
                        if (isset($doc['download_url']) && (!is_string($doc['download_url']) || !preg_match('/^(https?:\\/\\/[a-zA-Z0-9.-]+(:[0-9]+)?\\/|\\/api\\/)[^\\\\\\s]+$/', $doc['download_url']))) {
                            $errors[] = "documents_manifest download_url must be HTTPS/HTTP or /api/ endpoint without file protocols";
                        }
                    }
                }
            }
        }

        if (!empty($errors)) {
            throw new WonProjectImportValidationException("Validation failed: " . implode('; ', $errors), $errors);
        }
    }
}
