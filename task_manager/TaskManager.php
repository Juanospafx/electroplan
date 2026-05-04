<?php
/**
 * Class TaskManager
 * 
 * Maneja la lógica de negocio y acceso a datos para el módulo Smart PM (Task Manager).
 * Incluye validaciones de seguridad de directorio de proyectos.
 */
class TaskManager {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * ========================================================================
     * REGLA DE SEGURIDAD (Filtro de Directorio)
     * Valida que el usuario asignado exista en el directorio del proyecto.
     * ========================================================================
     */
    public function validateUserInDirectory(int $projectId, ?int $userId): void {
        // Si no hay usuario asignado (NULL), es válido (tarea sin asignar)
        if ($userId === null) {
            return;
        }

        $stmt = $this->pdo->prepare("SELECT 1 FROM directory WHERE project_id = ? AND user_id = ?");
        $stmt->execute([$projectId, $userId]);
        
        if (!$stmt->fetchColumn()) {
            throw new Exception("Security Violation: User ID {$userId} is not assigned to Project ID {$projectId} in the directory.");
        }
    }

    /**
     * ========================================================================
     * PROJECT TAILORING (Edición de Tareas y Directorio)
     * ========================================================================
     */
    public function getProjectDirectoryUsers(int $projectId): array {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.username, u.role 
            FROM directory d 
            JOIN users u ON d.user_id = u.id 
            WHERE d.project_id = ?
            ORDER BY u.username ASC
        ");
        $stmt->execute([$projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRfiTemplates(): array {
        $stmt = $this->pdo->query("SELECT id, name FROM task_templates WHERE name LIKE '%RFI%' ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTemplate(int $templateId): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM task_templates WHERE id = ?");
        $stmt->execute([$templateId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function updateTaskDetails(
        int $taskId, 
        int $projectId, 
        string $name, 
        float $estimatedHours, 
        ?int $assignedUserId
    ): bool {
        $this->validateUserInDirectory($projectId, $assignedUserId);
        
        $stmt = $this->pdo->prepare("
            UPDATE project_tasks 
            SET name = ?, estimated_hours = ?, assigned_user_id = ? 
            WHERE id = ? AND project_id = ?
        ");
        return $stmt->execute([$name, $estimatedHours, $assignedUserId, $taskId, $projectId]);
    }

    /**
     * ========================================================================
     * 1. SISTEMA DE PLANTILLAS (Task Templates)
     * ========================================================================
     */
    public function getAllTemplates(): array {
        $stmt = $this->pdo->query("SELECT id, name, description FROM task_templates ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createTemplate(string $name, ?string $description, ?int $createdBy): int {
        $stmt = $this->pdo->prepare("INSERT INTO task_templates (name, description, created_by) VALUES (?, ?, ?)");
        $stmt->execute([$name, $description, $createdBy]);
        return (int) $this->pdo->lastInsertId();
    }

    public function addTemplateItem(int $templateId, string $stageName, string $name, int $itemOrder, float $estimatedHours = 24.00, ?int $parentItemId = null): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO task_template_items 
            (template_id, stage_name, parent_item_id, item_order, name, estimated_hours) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$templateId, $stageName, $parentItemId, $itemOrder, $name, $estimatedHours]);
        return (int) $this->pdo->lastInsertId();
    }

    public function getTemplateItems(int $templateId): array {
        $stmt = $this->pdo->prepare("SELECT * FROM task_template_items WHERE template_id = ? ORDER BY item_order ASC");
        $stmt->execute([$templateId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTemplateDetailsForAssignment(int $templateId, int $projectId): array {
        $templateItems = $this->getTemplateItems($templateId);
        $projectUsers = $this->getProjectDirectoryUsers($projectId);

        $structuredTemplate = [];
        foreach ($templateItems as $item) {
            if (!isset($structuredTemplate[$item['stage_name']])) {
                $structuredTemplate[$item['stage_name']] = [
                    'name' => $item['stage_name'],
                    'tasks' => []
                ];
            }
            $structuredTemplate[$item['stage_name']]['tasks'][] = [
                'template_item_id' => (int)$item['id'],
                'name' => $item['name'],
                'estimated_hours' => (float)$item['estimated_hours'],
                'item_order' => (int)$item['item_order']
            ];
        }

        return ['template_items_structured' => array_values($structuredTemplate), 'project_users' => $projectUsers];
    }

    public function applyTemplateToProject(int $templateId, int $projectId, array $assignments = [], array $stageAssignments = []): void {
        $templateItems = $this->getTemplateItems($templateId);
        if (empty($templateItems)) {
            return; // No items to apply
        }

        $this->pdo->beginTransaction();
        try {
            // Group items by stage
            $stagesData = [];
            foreach ($templateItems as $item) {
                $stagesData[$item['stage_name']][] = $item;
            }

            $stmtStage = $this->pdo->prepare("SELECT MAX(stage_order) FROM project_stages WHERE project_id = ?");
            $stmtStage->execute([$projectId]);
            $stageOrder = (int)$stmtStage->fetchColumn() + 1;

            $stmtMax = $this->pdo->prepare("SELECT MAX(task_order) FROM project_tasks WHERE project_id = ?");
            $stmtMax->execute([$projectId]);
            $currentMaxOrder = (int)$stmtMax->fetchColumn();
            $mainTaskOrderCounter = max(1, floor($currentMaxOrder / 10000) + 1);

            foreach ($stagesData as $stageName => $items) {
                // Recibir el ID del usuario asignado para la etapa completa
                $stageAssignedUserId = $stageAssignments[$stageName] ?? null;
                // Create Stage
                $stageId = $this->createProjectStage($projectId, $stageName, $stageOrder++, $stageAssignedUserId);

                // Separate parents and children from template
                $parentItems = array_filter($items, fn($i) => $i['parent_item_id'] === null);
                $childItems = array_filter($items, fn($i) => $i['parent_item_id'] !== null);
                $childrenByParentTplId = [];
                foreach ($childItems as $child) {
                    $childrenByParentTplId[$child['parent_item_id']][] = $child;
                }

                // Create Parent Tasks and then their children
                foreach ($parentItems as $parentItem) {
                    $taskOrder = $mainTaskOrderCounter * 10000; // Espaciado Dinámico Escalable
                    $mainTaskOrderCounter++;

                    // Obtener el usuario asignado para este item de plantilla
                    $assignedUserId = $assignments[$parentItem['id']] ?? null;

                    // Create the parent task in the project
                    $projectParentTaskId = $this->createProjectTask($projectId, $stageId, $parentItem['name'], $taskOrder, (float)$parentItem['estimated_hours'], null, null, $assignedUserId);

                    // If this template parent has children, create them as project sub-tasks
                    if (isset($childrenByParentTplId[$parentItem['id']])) {
                        $subTaskOrderCounter = 1;
                        foreach ($childrenByParentTplId[$parentItem['id']] as $childItem) {
                            $subTaskOrder = $taskOrder + ($subTaskOrderCounter * 100); // Espacio para 99 subtareas
                            $subTaskOrderCounter++;

                            // Obtener el usuario asignado para este sub-item de plantilla, o heredar del padre
                            $subAssignedUserId = $assignments[$childItem['id']] ?? $assignedUserId;

                            $this->createProjectTask($projectId, $stageId, $childItem['name'], $subTaskOrder, (float)$childItem['estimated_hours'], $projectParentTaskId, null, $subAssignedUserId);
                        }
                    }
                }
            }

            $this->updateProjectTaskCounts($projectId);
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e; // Re-throw the exception
        }
    }


    /**
     * ========================================================================
     * 2. SISTEMA VIVO (Project Stages & Tasks)
     * ========================================================================
     */
    
    public function createProjectStage(int $projectId, string $name, int $stageOrder, ?int $assignedUserId = null): int {
        $this->validateUserInDirectory($projectId, $assignedUserId);

        $stmt = $this->pdo->prepare("
            INSERT INTO project_stages (project_id, name, stage_order, assigned_user_id) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$projectId, $name, $stageOrder, $assignedUserId]);
        return (int) $this->pdo->lastInsertId();
    }

    public function createProjectTask(
        int $projectId, 
        int $stageId, 
        string $name, 
        int $taskOrder, 
        float $estimatedHours = 24.00, 
        ?int $parentTaskId = null, 
        ?int $folderId = null, 
        ?int $assignedUserId = null
    ): int {
        $this->validateUserInDirectory($projectId, $assignedUserId);

        $stmt = $this->pdo->prepare("
            INSERT INTO project_tasks 
            (project_id, stage_id, parent_task_id, folder_id, task_order, name, estimated_hours, assigned_user_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $projectId, $stageId, $parentTaskId, $folderId, 
            $taskOrder, $name, $estimatedHours, $assignedUserId
        ]);
        
        $taskId = (int) $this->pdo->lastInsertId();
        $this->updateProjectTaskCounts($projectId);
        
        return $taskId;
    }

    /**
     * ========================================================================
     * RFI / TAREAS INTERMEDIAS (Sub-tareas dinámicas)
     * ========================================================================
     */
    public function createSubTask(int $projectId, int $parentTaskId, string $name, float $estimatedHours, ?int $assignedUserId = null): int {
        $parent = $this->getTask($parentTaskId);
        if (!$parent) {
            throw new Exception("Parent task not found.");
        }
        
        // FASE 24: Herencia Obligatoria de folder_id
        $inheritedFolderId = isset($parent['folder_id']) ? (int)$parent['folder_id'] : null;

        // Encontrar el último número intermedio disponible de 100 en 100.
        $stmt = $this->pdo->prepare("SELECT MAX(task_order) FROM project_tasks WHERE parent_task_id = ?");
        $stmt->execute([$parentTaskId]);
        $maxOrder = $stmt->fetchColumn();

        $newOrder = $maxOrder ? $maxOrder + 100 : $parent['task_order'] + 100;

        // Reutilizamos el método base para heredar validaciones, stage_id y folder_id.
        // Por defecto, se crea con el status "Pending".
        return $this->createProjectTask($projectId, (int)$parent['stage_id'], $name, $newOrder, $estimatedHours, $parentTaskId, $inheritedFolderId, $assignedUserId);
    }

    public function applyRfiTemplateToTask(int $projectId, int $parentTaskId, int $rfiTemplateId): bool {
        $parent = $this->getTask($parentTaskId);
        if (!$parent) {
            throw new Exception("Parent task not found.");
        }

        $rfiTemplateItems = $this->getTemplateItems($rfiTemplateId);
        if (empty($rfiTemplateItems)) {
            throw new Exception("RFI Template has no items.");
        }
        
        // FASE 24: Herencia Obligatoria de folder_id y asignación
        $inheritedFolderId = isset($parent['folder_id']) ? (int)$parent['folder_id'] : null;
        $inheritedUserId = isset($parent['assigned_user_id']) ? (int)$parent['assigned_user_id'] : null;

        // Find the starting order for the RFI block
        $stmt = $this->pdo->prepare("SELECT MAX(task_order) FROM project_tasks WHERE parent_task_id = ?");
        $stmt->execute([$parentTaskId]);
        $maxOrder = $stmt->fetchColumn();
        $startOrder = $maxOrder ? $maxOrder + 100 : (int)$parent['task_order'] + 100;

        $this->pdo->beginTransaction();
        try {
            $itemCounter = 0;
            foreach ($rfiTemplateItems as $item) {
                // Calculate task order for each step of the RFI
                $taskOrder = $startOrder + ($itemCounter * 100);
                
                $this->createProjectTask(
                    $projectId,
                    (int)$parent['stage_id'],
                    $item['name'],
                    $taskOrder,
                    (float)$item['estimated_hours'],
                    $parentTaskId,
                    $inheritedFolderId, // FASE 24: Fuerza la herencia del directorio padre
                    $inheritedUserId    // HERENCIA: Asignar el mismo usuario que la tarea padre
                );
                $itemCounter++;
            }
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        return true;
    }

    public function assignUserToTask(int $taskId, int $projectId, ?int $assignedUserId): bool {
        $this->validateUserInDirectory($projectId, $assignedUserId);

        $stmt = $this->pdo->prepare("UPDATE project_tasks SET assigned_user_id = ? WHERE id = ? AND project_id = ?");
        return $stmt->execute([$assignedUserId, $taskId, $projectId]);
    }

    public function updateTaskStatus(
        int $taskId, 
        int $projectId, 
        string $status, 
        ?string $expectedEndTime = null
    ): bool {
        $allowedStatuses = ['Pending', 'Active', 'On_Hold', 'System_Pause', 'Overdue', 'Bypassed', 'Completed'];
        if (!in_array($status, $allowedStatuses, true)) {
            throw new InvalidArgumentException("Invalid status provided.");
        }

        $query = "UPDATE project_tasks SET status = ?";
        $params = [$status];

        // Lógica automática de fechas según el estado
        if ($status === 'Active') {
            $query .= ", actual_start_time = COALESCE(actual_start_time, NOW())";
            if ($expectedEndTime !== null) {
                $query .= ", expected_end_time = ?";
                $params[] = $expectedEndTime;
            }
        } elseif ($status === 'Completed' || $status === 'Bypassed') {
            $query .= ", actual_end_time = NOW()";
        } elseif ($status === 'On_Hold') {
            $query .= ", expected_end_time = NULL";
        }

        $query .= " WHERE id = ? AND project_id = ?";
        $params[] = $taskId;
        $params[] = $projectId;

        $stmt = $this->pdo->prepare($query);
        $result = $stmt->execute($params);

        if ($result) {
            $this->updateProjectTaskCounts($projectId);
        }

        return $result;
    }

    public function getTask(int $taskId): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM project_tasks WHERE id = ?");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        return $task ?: null;
    }

    public function getNextTask(int $projectId): ?array {
        $query = "SELECT * FROM project_tasks 
                  WHERE project_id = ? AND status NOT IN ('Completed', 'Bypassed') 
                  ORDER BY task_order ASC LIMIT 1";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$projectId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        return $task ?: null;
    }

    public function appendProjectNote(int $projectId, string $note): void {
        $stmt = $this->pdo->prepare("UPDATE projects SET notes = CONCAT(COALESCE(notes, ''), ?) WHERE id = ?");
        $stmt->execute([$note, $projectId]);
    }

    public function resetProjectTasks(int $projectId): void {
        $this->pdo->beginTransaction();
        try {
            // Borrar tareas (logs y sub-tareas se eliminarán por CASCADE si la BD está configurada así)
            $stmtTasks = $this->pdo->prepare("DELETE FROM project_tasks WHERE project_id = ?");
            $stmtTasks->execute([$projectId]);
            
            // Borrar etapas para dejar un lienzo completamente en blanco
            $stmtStages = $this->pdo->prepare("DELETE FROM project_stages WHERE project_id = ?");
            $stmtStages->execute([$projectId]);
            
            $this->updateProjectTaskCounts($projectId);
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function checkParentTaskCompletion(int $parentTaskId): void {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM project_tasks WHERE parent_task_id = ? AND status NOT IN ('Completed', 'Bypassed')"
        );
        $stmt->execute([$parentTaskId]);
        $pendingChildren = (int)$stmt->fetchColumn();

        if ($pendingChildren === 0) {
            $parentTask = $this->getTask($parentTaskId);
            if ($parentTask) $this->updateTaskStatus($parentTaskId, (int)$parentTask['project_id'], 'Completed');
        }
    }

    public function getProjectStagesAndTasks(int $projectId): array {
        // Obtener Stages
        $stmtStages = $this->pdo->prepare("SELECT * FROM project_stages WHERE project_id = ? ORDER BY stage_order ASC");
        $stmtStages->execute([$projectId]);
        $stages = $stmtStages->fetchAll(PDO::FETCH_ASSOC);

        // Obtener Tasks
        $stmtTasks = $this->pdo->prepare("
            SELECT pt.*, u.username as assigned_user_name 
            FROM project_tasks pt 
            LEFT JOIN users u ON pt.assigned_user_id = u.id 
            WHERE pt.project_id = ? 
            ORDER BY pt.task_order ASC
        ");
        $stmtTasks->execute([$projectId]);
        $tasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);

        // Anidar Tasks en Stages (soportando 1 nivel de sub-tareas por ahora)
        $structuredData = [];
        foreach ($stages as $stage) {
            $stage['tasks'] = [];
            $structuredData[$stage['id']] = $stage;
        }

        $subTasks = [];
        foreach ($tasks as $task) {
            if ($task['parent_task_id'] !== null) {
                $subTasks[$task['parent_task_id']][] = $task;
            } else {
                if (isset($structuredData[$task['stage_id']])) {
                    $task['sub_tasks'] = [];
                    $structuredData[$task['stage_id']]['tasks'][$task['id']] = $task;
                }
            }
        }

        // Asignar subtareas a sus padres
        foreach ($subTasks as $parentId => $children) {
            foreach ($structuredData as &$stage) {
                if (isset($stage['tasks'][$parentId])) {
                    $stage['tasks'][$parentId]['sub_tasks'] = $children;
                }
            }
        }

        // Convertir el array asociativo de tareas a un array indexado regular.
        // Esto es CRÍTICO para Javascript: si se envían las claves numéricas (IDs), 
        // JS auto-ordenará el objeto por ID, destruyendo el `ORDER BY task_order ASC`.
        foreach ($structuredData as &$stage) {
            $stage['tasks'] = array_values($stage['tasks']);
        }

        return array_values($structuredData);
    }

    /**
     * ========================================================================
     * 3. HISTORIAL Y JUSTIFICACIONES (Task Time Logs)
     * ========================================================================
     */
    public function logTaskAction(int $taskId, ?int $userId, string $actionType, ?string $justificationNote = null): int {
        $allowedActions = ['Started', 'Paused', 'Resumed', 'Bypassed', 'Completed', 'Extended'];
        if (!in_array($actionType, $allowedActions, true)) {
            throw new InvalidArgumentException("Invalid action type provided.");
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO task_time_logs (task_id, user_id, action_type, justification_note) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$taskId, $userId, $actionType, $justificationNote]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * ========================================================================
     * 4. REPORTING & PERFORMANCE
     * ========================================================================
     */

    /**
     * Calculates the actual hours spent on a task by summing up intervals
     * between 'Started'/'Resumed' and 'Paused'/'Completed' events.
     */
    public function calculateActualHoursTask(int $taskId): float {
        $stmt = $this->pdo->prepare("
            SELECT action_type, logged_at 
            FROM task_time_logs 
            WHERE task_id = ? 
            ORDER BY logged_at ASC
        ");
        $stmt->execute([$taskId]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalSeconds = 0;
        $lastStartTime = null;

        foreach ($logs as $log) {
            $actionTime = new DateTime($log['logged_at']);
            
            if (in_array($log['action_type'], ['Started', 'Resumed'])) {
                $lastStartTime = $actionTime;
            } elseif (in_array($log['action_type'], ['Paused', 'Completed', 'Bypassed']) && $lastStartTime !== null) {
                $totalSeconds += $actionTime->getTimestamp() - $lastStartTime->getTimestamp();
                $lastStartTime = null; // Reset after calculating an interval
            }
        }

        return round($totalSeconds / 3600, 2);
    }

    public function getUserPerformanceReport(int $projectId, int $userId): array {
        $stmt = $this->pdo->prepare("
            SELECT id, name, estimated_hours 
            FROM project_tasks
            WHERE project_id = ? AND assigned_user_id = ? AND status IN ('Completed', 'Bypassed')
        ");
        $stmt->execute([$projectId, $userId]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalEstimatedHours = 0.0;
        $totalActualHours = 0.0;
        $detailedTasks = [];

        foreach ($tasks as $task) {
            $estimated = (float)$task['estimated_hours'];
            $actual = $this->calculateActualHoursTask((int)$task['id']);

            $totalEstimatedHours += $estimated;
            $totalActualHours += $actual;

            $detailedTasks[] = ['id' => (int)$task['id'], 'name' => $task['name'], 'estimated_hours' => $estimated, 'actual_hours' => $actual, 'variance' => round($estimated - $actual, 2)];
        }

        return ['total_estimated_hours' => round($totalEstimatedHours, 2), 'total_actual_hours' => round($totalActualHours, 2), 'performance_ratio' => $totalEstimatedHours > 0 ? round($totalActualHours / $totalEstimatedHours, 2) : 0, 'completed_tasks_count' => count($tasks), 'tasks' => $detailedTasks];
    }

    /**
     * ========================================================================
     * MÉTODOS INTERNOS (Helpers)
     * ========================================================================
     */
    private function updateProjectTaskCounts(int $projectId): void {
        $stmt = $this->pdo->prepare("
            UPDATE projects 
            SET total_tasks = (SELECT COUNT(*) FROM project_tasks WHERE project_id = ?),
                completed_tasks = (SELECT COUNT(*) FROM project_tasks WHERE project_id = ? AND status IN ('Completed', 'Bypassed'))
            WHERE id = ?
        ");
        $stmt->execute([$projectId, $projectId, $projectId]);
    }

    public function attachFileToTask(int $taskId, int $projectId, int $folderId, array $file, int $userId): array {
        // 1. Validations
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['status' => 'error', 'message' => 'File upload error code: ' . $file['error']];
        }
        $this->validateUserInDirectory($projectId, $userId);
        
        $task = $this->getTask($taskId);
        if (!$task || (int)$task['project_id'] !== $projectId || (int)$task['folder_id'] !== $folderId) {
            return ['status' => 'error', 'message' => 'Task/Folder mismatch or not found.'];
        }

        // 2. Path and Filename Sanitization
        $filename = basename($file['name']);
        $filename = preg_replace('/[^A-Za-z0-9._\-]/', '_', $filename);

        // 3. Directory Structure
        $baseUploadDir = __DIR__ . '/../uploads';
        $projectDir = $baseUploadDir . '/' . $projectId;
        $folderDir = $projectDir . '/' . $folderId;
        
        if (!is_dir($folderDir)) {
            if (!mkdir($folderDir, 0775, true) && !is_dir($folderDir)) {
                return ['status' => 'error', 'message' => 'Failed to create attachment directory.'];
            }
        }
        
        // Avoid overwriting files by appending a counter
        $originalName = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $counter = 1;
        $targetPath = $folderDir . '/' . $filename;
        while (file_exists($targetPath)) {
            $filename = $originalName . '_' . $counter . '.' . $extension;
            $targetPath = $folderDir . '/' . $filename;
            $counter++;
        }

        // 4. Move the file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['status' => 'error', 'message' => 'Failed to save the uploaded file.'];
        }
        
        // 5. Database record
        $relativePath = 'uploads/' . $projectId . '/' . $folderId . '/' . $filename;
        $stmt = $this->pdo->prepare(
            "INSERT INTO files (project_id, folder_id, filename, filepath, uploaded_by, file_type, file_size, uploaded_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$projectId, $folderId, $filename, $relativePath, $userId, $file['type'], $file['size']]);

        return ['status' => 'success', 'message' => 'File attached!'];
    }
}