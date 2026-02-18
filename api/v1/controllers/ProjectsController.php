<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

class ProjectsController
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function health(): void
    {
        ok_response(['status' => 'up']);
    }

    public function index(): void
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT id, name, description, status, assigned_user_id, created_at, updated_at\n                 FROM projects\n                 WHERE deleted_at IS NULL\n                 ORDER BY created_at DESC"
            );
            $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ok_response($projects);
        } catch (Exception $e) {
            error_response('INTERNAL_ERROR', 'Unexpected error', null, 500);
        }
    }

    private function map_project_export_payload(array $project): array
    {
        $projectId = (int)$project['id'];
        $updatedAt = $project['updated_at'] ?? $project['created_at'] ?? null;

        return [
            'project_id' => (string)$projectId,
            'name' => (string)($project['name'] ?? ''),
            'status' => (string)($project['status'] ?? 'unknown'),
            'updated_at' => $updatedAt,
            'metadata' => [
                'source' => 'electroplan',
                'electroplan_project_id' => $projectId,
            ],
        ];
    }

    public function show(array $params): void
    {
        $id = require_int($params['id'] ?? null);
        if (!$id) {
            error_response('VALIDATION_ERROR', 'Invalid id', ['field' => 'id'], 400);
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, name, description, status, assigned_user_id, created_at, updated_at
                 FROM projects
                 WHERE id = ? AND deleted_at IS NULL
                 LIMIT 1"
            );
            $stmt->execute([$id]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$project) {
                error_response('NOT_FOUND', 'Project not found', ['id' => $id], 404);
            }

            ok_response($project);
        } catch (Exception $e) {
            error_response('INTERNAL_ERROR', 'Unexpected error', null, 500);
        }
    }

    public function export(array $params): void
    {
        $id = require_int($params['id'] ?? null);
        if (!$id) {
            error_response('VALIDATION_ERROR', 'Invalid id', ['field' => 'id'], 400);
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, name, description, status, assigned_user_id, created_at, updated_at
                 FROM projects
                 WHERE id = ? AND deleted_at IS NULL
                 LIMIT 1"
            );
            $stmt->execute([$id]);
            $project = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$project) {
                error_response('NOT_FOUND', 'Project not found', ['id' => $id], 404);
            }

            $payload = $this->map_project_export_payload($project);

            ok_response([
                'project' => $project,
                'export' => $payload,
            ]);
        } catch (Exception $e) {
            error_response('INTERNAL_ERROR', 'Unexpected error', null, 500);
        }
    }

    private function get_default_admin_user_id(): ?int
    {
        $stmt = $this->pdo->prepare(
            "SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1"
        );
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    }

    public function store(): void
    {
        $data = read_json_body();
        $name = require_string($data['name'] ?? null);
        $desc = $data['description'] ?? '';
        $status = $data['status'] ?? 'Active';

        if (!$name) {
            error_response('VALIDATION_ERROR', 'Name is required', ['field' => 'name'], 422);
        }

        $assignedUserId = require_int($data['assigned_user_id'] ?? null);
        if ($assignedUserId !== null) {
            require_client_role('admin');
        } else {
            $assignedUserId = $this->get_default_admin_user_id();
        }

        if (!$assignedUserId) {
            error_response('VALIDATION_ERROR', 'No admin user available for assignment', null, 422);
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO projects (name, description, status, created_by, assigned_user_id)\n                 VALUES (?, ?, ?, ?, ?)"
            );
            // No session in v1. created_by nullable.
            $createdBy = null;
            $stmt->execute([$name, $desc, $status, $createdBy, $assignedUserId]);

            $projectId = (int)$this->pdo->lastInsertId();

            // Also register in directory
            $dir = $this->pdo->prepare(
                "INSERT IGNORE INTO directory (project_id, user_id) VALUES (?, ?)"
            );
            $dir->execute([$projectId, $assignedUserId]);

            ok_response(['id' => $projectId], null, 201);
        } catch (Exception $e) {
            error_response('INTERNAL_ERROR', 'Unexpected error', null, 500);
        }
    }

    public function update(array $params): void
    {
        $id = require_int($params['id'] ?? null);
        if (!$id) {
            error_response('VALIDATION_ERROR', 'Invalid id', ['field' => 'id'], 422);
        }

        $data = read_json_body();
        $name = $data['name'] ?? null;
        $desc = $data['description'] ?? null;
        $status = $data['status'] ?? null;

        if ($name !== null && !require_string($name)) {
            error_response('VALIDATION_ERROR', 'Invalid name', ['field' => 'name'], 422);
        }

        $fields = [];
        $values = [];

        if ($name !== null) { $fields[] = "name = ?"; $values[] = $name; }
        if ($desc !== null) { $fields[] = "description = ?"; $values[] = $desc; }
        if ($status !== null) { $fields[] = "status = ?"; $values[] = $status; }

        if (count($fields) === 0) {
            error_response('VALIDATION_ERROR', 'No fields to update', null, 422);
        }

        try {
            $sql = "UPDATE projects SET " . implode(', ', $fields) . " WHERE id = ?";
            $values[] = $id;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($values);

            ok_response(['updated' => true]);
        } catch (Exception $e) {
            error_response('INTERNAL_ERROR', 'Unexpected error', null, 500);
        }
    }
}
