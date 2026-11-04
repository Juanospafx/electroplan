<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../../../core/services/WonProjectImportService.php';

class WonProjectsController
{
    private $pdo;
    private $importService;
    private static $seamImportService = null;

    public static function setImportService($importService): void
    {
        self::$seamImportService = $importService;
    }

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->importService = self::$seamImportService ?? new WonProjectImportService($pdo);
    }

    public function import(array $params = []): void
    {
        $this->store($params);
    }

    public function store(array $params = []): void
    {
        $role = get_client_role();
        if (!in_array($role, ['service', 'admin'], true)) {
            error_response('FORBIDDEN', 'Insufficient privileges', null, 403);
        }

        $rawBody = get_raw_body();
        if (trim($rawBody) === '') {
            error_response('VALIDATION_ERROR', 'Request body cannot be empty', null, 400);
        }

        $payload = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
            error_response('VALIDATION_ERROR', 'Malformed JSON payload: ' . json_last_error_msg(), null, 400);
        }

        try {
            $result = $this->importService->import($payload);
            $status = $result['status'] ?? null;

            if ($status === 'imported') {
                ok_response($result, null, 201);
            } elseif ($status === 'replayed') {
                ok_response($result, null, 200);
            } elseif ($status === 'stale' || !empty($result['stale'])) {
                error_response('STALE_EVENT', 'Stale or obsolete event rejected', null, 409);
            } else {
                ok_response($result, null, 200);
            }
        } catch (WonProjectImportValidationException $e) {
            $errors = $e->getErrors();
            error_response('VALIDATION_ERROR', $e->getMessage(), !empty($errors) ? $errors : null, 422);
        } catch (WonProjectImportConflictException $e) {
            error_response('IDEMPOTENCY_CONFLICT', 'Idempotency conflict detected', null, 409);
        } catch (\Throwable $e) {
            error_response('INTERNAL_ERROR', 'Unexpected error', null, 500);
        }
    }
}
