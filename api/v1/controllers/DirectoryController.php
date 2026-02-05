<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

class DirectoryController
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function index(): void
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT\n                    p.id AS project_id,\n                    p.name AS project_name,\n                    p.description AS project_description,\n                    p.status AS project_status,\n                    p.assigned_user_id AS primary_user_id,\n                    u.id AS user_id,\n                    u.username AS username,\n                    u.role AS user_role\n                 FROM projects p\n                 LEFT JOIN directory d ON d.project_id = p.id\n                 LEFT JOIN users u ON u.id = d.user_id\n                 WHERE p.deleted_at IS NULL\n                 ORDER BY p.created_at DESC, u.username ASC"
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ok_response($rows);
        } catch (Exception $e) {
            error_response('INTERNAL_ERROR', 'Unexpected error', null, 500);
        }
    }

    public function assign(array $params): void
    {
        require_client_role('admin');

        $projectId = require_int($params['id'] ?? null);
        if (!$projectId) {
            error_response('VALIDATION_ERROR', 'Invalid project id', ['field' => 'id'], 422);
        }

        $data = read_json_body();
        $userId = require_int($data['user_id'] ?? null);
        if (!$userId) {
            error_response('VALIDATION_ERROR', 'Invalid user_id', ['field' => 'user_id'], 422);
        }

        try {
            $stmt = $this->pdo->prepare("SELECT id FROM projects WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$projectId]);
            if (!$stmt->fetch()) {
                error_response('NOT_FOUND', 'Project not found', null, 404);
            }

            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            if (!$stmt->fetch()) {
                error_response('NOT_FOUND', 'User not found', null, 404);
            }

            $update = $this->pdo->prepare("UPDATE projects SET assigned_user_id = ? WHERE id = ?");
            $update->execute([$userId, $projectId]);

            $dir = $this->pdo->prepare(
                "INSERT IGNORE INTO directory (project_id, user_id) VALUES (?, ?)"
            );
            $dir->execute([$projectId, $userId]);

            ok_response(['assigned' => true]);
        } catch (Exception $e) {
            error_response('INTERNAL_ERROR', 'Unexpected error', null, 500);
        }
    }
}
