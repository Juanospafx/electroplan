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
        int $estimatedMinutes, 
        ?int $assignedUserId
    ): bool {
        $this->validateUserInDirectory($projectId, $assignedUserId);
        
        $stmt = $this->pdo->prepare("
            UPDATE project_tasks 
            SET name = ?, estimated_minutes = ?, assigned_user_id = ? 
            WHERE id = ? AND project_id = ?
        ");
        return $stmt->execute([$name, $estimatedMinutes, $assignedUserId, $taskId, $projectId]);
    }

    public function deleteProjectTask(int $taskId, int $projectId, bool $deleteSubtasks = false): void {
        $this->pdo->beginTransaction();
        try {
            if ($deleteSubtasks) {
                // Borrar subtareas primero (si es una tarea padre para prevenir conflictos en constraints)
                $stmtSub = $this->pdo->prepare("DELETE FROM project_tasks WHERE parent_task_id = ? AND project_id = ?");
                $stmtSub->execute([$taskId, $projectId]);
            } else {
                // Desvincular subtareas para que se conviertan en tareas principales en vez de borrarse
                $stmtSub = $this->pdo->prepare("UPDATE project_tasks SET parent_task_id = NULL WHERE parent_task_id = ? AND project_id = ?");
                $stmtSub->execute([$taskId, $projectId]);
            }

            // Borrar la tarea en sí
            $stmtTask = $this->pdo->prepare("DELETE FROM project_tasks WHERE id = ? AND project_id = ?");
            $stmtTask->execute([$taskId, $projectId]);

            // Actualizar contadores del proyecto
            $this->updateProjectTaskCounts($projectId);
            
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getPersonalTasks(int $userId): array {
        $stmt = $this->pdo->prepare("SELECT * FROM personal_tasks WHERE user_id = ? ORDER BY created_at ASC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProjectStagesList(int $projectId): array {
        $stmt = $this->pdo->prepare("SELECT id, name FROM project_stages WHERE project_id = ? ORDER BY stage_order ASC");
        $stmt->execute([$projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addPersonalTask(int $userId, string $name, int $estimatedMinutes): int {
        $stmt = $this->pdo->prepare("INSERT INTO personal_tasks (user_id, name, estimated_minutes, status, created_at) VALUES (?, ?, ?, 'Pending', NOW())");
        $stmt->execute([$userId, $name, $estimatedMinutes]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updatePersonalTaskStatus(int $taskId, int $userId, string $status): void {
        $stmt = $this->pdo->prepare("SELECT * FROM personal_tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$taskId, $userId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$task) return;

        $worked = (int)$task['worked_minutes'];
        $now = new DateTime();

        // Si pausamos o detenemos, sumar el tiempo transcurrido
        if ($task['status'] === 'Active' && in_array($status, ['On_Hold', 'Pending', 'Completed'])) {
            if ($task['actual_start_time']) {
                $start = new DateTime($task['actual_start_time']);
                $diffMins = (int)floor(($now->getTimestamp() - $start->getTimestamp()) / 60);
                if ($diffMins > 0) $worked += $diffMins;
            }
        }

        if ($status === 'Active') {
            $stmtUpd = $this->pdo->prepare("UPDATE personal_tasks SET status = 'Active', actual_start_time = NOW(), worked_minutes = ? WHERE id = ?");
            $stmtUpd->execute([$worked, $taskId]);
        } else {
            $stmtUpd = $this->pdo->prepare("UPDATE personal_tasks SET status = ?, worked_minutes = ? WHERE id = ?");
            $stmtUpd->execute([$status, $worked, $taskId]);
        }
    }

    public function completePersonalTask(int $taskId, int $userId): void {
        $stmt = $this->pdo->prepare("UPDATE personal_tasks SET status = 'Completed' WHERE id = ? AND user_id = ?");
        $stmt->execute([$taskId, $userId]);
    }

    public function deletePersonalTask(int $taskId, int $userId): void {
        $stmt = $this->pdo->prepare("DELETE FROM personal_tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$taskId, $userId]);
    }

    public function transferPersonalTaskToProject(int $taskId, int $projectId, ?int $stageId, int $userId, bool $markAsCompleted = true): int {
        $stmt = $this->pdo->prepare("SELECT * FROM personal_tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$taskId, $userId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) throw new Exception("Personal task not found.");

        if (!$stageId) {
            // Buscar o crear la etapa "Quick Tasks" si no se especificó una
            $stmtStage = $this->pdo->prepare("SELECT id FROM project_stages WHERE project_id = ? AND name = 'Quick Tasks' LIMIT 1");
            $stmtStage->execute([$projectId]);
            $stageId = $stmtStage->fetchColumn();
            if (!$stageId) {
                $stmtMax = $this->pdo->prepare("SELECT MAX(stage_order) FROM project_stages WHERE project_id = ?");
                $stmtMax->execute([$projectId]);
                $stageId = $this->createProjectStage($projectId, 'Quick Tasks', (int)$stmtMax->fetchColumn() + 1);
            }
        }

        $stmtMaxTask = $this->pdo->prepare("SELECT MAX(task_order) FROM project_tasks WHERE project_id = ?");
        $stmtMaxTask->execute([$projectId]);
        $currentMaxOrder = (int)$stmtMaxTask->fetchColumn();
        $taskOrder = max(10000, (floor($currentMaxOrder / 10000) + 1) * 10000);

        $newTaskId = $this->createProjectTask($projectId, (int)$stageId, $task['name'], $taskOrder, (int)$task['estimated_minutes']);
        
        $workedMins = (int)$task['worked_minutes'];
        $startTime = $task['actual_start_time'] ? $task['actual_start_time'] : date('Y-m-d H:i:s');
        
        if ($markAsCompleted) {
            $stmtUpdate = $this->pdo->prepare("UPDATE project_tasks SET status = 'Completed', worked_minutes = ?, actual_start_time = ?, actual_end_time = NOW() WHERE id = ?");
            $stmtUpdate->execute([$workedMins, $startTime, $newTaskId]);
            
            $this->logTaskAction($newTaskId, $userId, 'Completed', 'Task completed in Quick Tasks and deployed to project.');
            $this->addProjectActivityLog($projectId, $newTaskId, $userId, 'Completed', "Task deployed from Quick Tasks.");
        } else {
            $newStatus = ($workedMins > 0 || $task['status'] === 'Active' || $task['status'] === 'On_Hold') ? 'On_Hold' : 'Pending';
            $stmtUpdate = $this->pdo->prepare("UPDATE project_tasks SET status = ?, worked_minutes = ?, actual_start_time = ? WHERE id = ?");
            $stmtUpdate->execute([$newStatus, $workedMins, $startTime, $newTaskId]);
            
            $this->logTaskAction($newTaskId, $userId, 'Started', 'Task deployed from Quick Tasks in progress.');
            $this->addProjectActivityLog($projectId, $newTaskId, $userId, 'Started', "Task deployed from Quick Tasks in progress.");
        }

        $this->updateProjectTaskCounts($projectId);

        // Borrar del Scratchpad personal
        $this->deletePersonalTask($taskId, $userId);
        return $newTaskId;
    }

    public function getActiveProjects(int $userId = 0, string $role = 'admin'): array {
        if ($role !== 'admin') {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT p.id, p.name, p.status 
                FROM projects p
                JOIN project_tasks pt ON p.id = pt.project_id
                WHERE p.deleted_at IS NULL AND pt.assigned_user_id = ?
                ORDER BY p.name ASC
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $stmt = $this->pdo->query("SELECT id, name, status FROM projects WHERE deleted_at IS NULL ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * FASE 58: Endpoint Global de Tareas Activas (Live Radar)
     * Busca en toda la base de datos qué tareas están corriendo actualmente.
     */
    public function getGlobalActiveTasks(int $userId = 0, string $role = 'admin'): array {
        $sql = "
            (SELECT 
                pt.id AS task_id, 
                pt.name AS task_name, 
                pt.status,
                p.id AS project_id,
                p.name AS project_name, 
                u.username AS assigned_user_name, 
                u.work_start_time,
                u.work_end_time,
                pt.estimated_minutes, 
                pt.worked_minutes, 
                pt.actual_start_time AS start_time,
                pt.expected_end_time,
                'project_task' AS task_type
            FROM project_tasks pt
            JOIN projects p ON pt.project_id = p.id
            LEFT JOIN users u ON pt.assigned_user_id = u.id
            WHERE pt.status IN ('Active', 'On_Hold', 'System_Pause', 'Overdue')
        ";
        $params = [];
        if ($role !== 'admin') {
            $sql .= " AND pt.assigned_user_id = ?";
            $params[] = $userId;
        }
        $sql .= ")";

        // FASE 96: Integrar Personal Tasks (Scratchpad) al Radar en Vivo
        $sql .= " UNION ALL 
            (SELECT 
                pt.id AS task_id, 
                pt.name AS task_name, 
                pt.status,
                0 AS project_id,
                'Quick Tasks' AS project_name, 
                u.username AS assigned_user_name, 
                u.work_start_time,
                u.work_end_time,
                pt.estimated_minutes, 
                pt.worked_minutes, 
                pt.actual_start_time AS start_time,
                NULL AS expected_end_time,
                'personal_task' AS task_type
            FROM personal_tasks pt
            LEFT JOIN users u ON pt.user_id = u.id
            WHERE pt.status IN ('Active', 'On_Hold', 'System_Pause', 'Overdue') AND pt.user_id = ?)
        ";
        $params[] = ($userId === 0 && isset($_SESSION['user_id'])) ? (int)$_SESSION['user_id'] : $userId;

        $sql .= " ORDER BY status ASC, start_time DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ========================================================================
     * FASE 64: SISTEMA DE AUDITORÍA (Project Activity Logs)
     * ========================================================================
     */
    public function addProjectActivityLog(int $projectId, ?int $taskId, ?int $userId, string $actionType, string $description): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO project_activity_logs (project_id, task_id, user_id, action_type, description, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$projectId, $taskId, $userId, $actionType, $description]);
        return (int) $this->pdo->lastInsertId();
    }

    public function getProjectAlertLogs(int $projectId): array {
        $stmt = $this->pdo->prepare("
            SELECT pal.*, u.username, pt.name as task_name 
            FROM project_activity_logs pal
            LEFT JOIN users u ON pal.user_id = u.id
            LEFT JOIN project_tasks pt ON pal.task_id = pt.id
            WHERE pal.project_id = ? AND pal.action_type IN ('Hold', 'Overdue')
            ORDER BY pal.created_at DESC
        ");
        $stmt->execute([$projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProjectNotesLogs(int $projectId): array {
        $stmt = $this->pdo->prepare("
            SELECT pal.*, u.username, pt.name as task_name 
            FROM project_activity_logs pal
            LEFT JOIN users u ON pal.user_id = u.id
            LEFT JOIN project_tasks pt ON pal.task_id = pt.id
            WHERE pal.project_id = ?
            ORDER BY pal.created_at ASC
        ");
        $stmt->execute([$projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ========================================================================
     * 1. SISTEMA DE PLANTILLAS (Task Templates)
     * ========================================================================
     */
    public function getAllTemplates(): array {
        // AUDITORÍA 3: Payload ligero (No traer descripciones masivas si solo se usa en Dropdowns)
        $stmt = $this->pdo->query("SELECT id, name FROM task_templates ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createTemplate(string $name, ?string $description, ?int $createdBy): int {
        $stmt = $this->pdo->prepare("INSERT INTO task_templates (name, description, created_by) VALUES (?, ?, ?)");
        $stmt->execute([$name, $description, $createdBy]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateTemplate(int $templateId, string $name, ?string $description): void {
        $stmt = $this->pdo->prepare("UPDATE task_templates SET name = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $description, $templateId]);
    }

    public function clearTemplateItems(int $templateId): void {
        $stmt = $this->pdo->prepare("DELETE FROM task_template_items WHERE template_id = ?");
        $stmt->execute([$templateId]);
    }

    public function deleteTemplate(int $templateId): void {
        $this->clearTemplateItems($templateId);
        $stmt = $this->pdo->prepare("DELETE FROM task_templates WHERE id = ?");
        $stmt->execute([$templateId]);
    }

    public function addTemplateItem(int $templateId, string $stageName, string $name, int $itemOrder, int $estimatedMinutes = 0, ?int $parentItemId = null): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO task_template_items 
            (template_id, stage_name, parent_item_id, item_order, name, estimated_minutes) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$templateId, $stageName, $parentItemId, $itemOrder, $name, $estimatedMinutes]);
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
                'estimated_minutes' => isset($item['estimated_minutes']) ? (int)$item['estimated_minutes'] : 0,
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
                    $pMinutes = isset($parentItem['estimated_minutes']) ? (int)$parentItem['estimated_minutes'] : 0;
                    $projectParentTaskId = $this->createProjectTask($projectId, $stageId, $parentItem['name'], $taskOrder, $pMinutes, null, null, $assignedUserId);

                    // If this template parent has children, create them as project sub-tasks
                    if (isset($childrenByParentTplId[$parentItem['id']])) {
                        $subTaskOrderCounter = 1;
                        foreach ($childrenByParentTplId[$parentItem['id']] as $childItem) {
                            $subTaskOrder = $taskOrder + ($subTaskOrderCounter * 100); // Espacio para 99 subtareas
                            $subTaskOrderCounter++;

                            // Obtener el usuario asignado para este sub-item de plantilla, o heredar del padre
                            $subAssignedUserId = $assignments[$childItem['id']] ?? $assignedUserId;

                            $cMinutes = isset($childItem['estimated_minutes']) ? (int)$childItem['estimated_minutes'] : 0;
                            $this->createProjectTask($projectId, $stageId, $childItem['name'], $subTaskOrder, $cMinutes, $projectParentTaskId, null, $subAssignedUserId);
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
        int $estimatedMinutes = 0, 
        ?int $parentTaskId = null, 
        ?int $folderId = null, 
        ?int $assignedUserId = null
    ): int {
        $this->validateUserInDirectory($projectId, $assignedUserId);

        $stmt = $this->pdo->prepare("
            INSERT INTO project_tasks 
            (project_id, stage_id, parent_task_id, folder_id, task_order, name, estimated_minutes, assigned_user_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $projectId, $stageId, $parentTaskId, $folderId, 
            $taskOrder, $name, $estimatedMinutes, $assignedUserId
        ]);
        
        $taskId = (int) $this->pdo->lastInsertId();
        $this->updateProjectTaskCounts($projectId);
        
        return $taskId;
    }

    /**
     * FASE 84: Creación rápida de tareas sueltas (Quick Task) al final de un proyecto.
     */
    public function createQuickTask(int $projectId, string $name, int $estimatedMinutes): int {
        // 1. Encontrar la última etapa del proyecto
        $stmtStage = $this->pdo->prepare("SELECT id FROM project_stages WHERE project_id = ? ORDER BY stage_order DESC LIMIT 1");
        $stmtStage->execute([$projectId]);
        $stageId = $stmtStage->fetchColumn();

        // Si el proyecto está vacío y no tiene etapas, crear una por defecto llamada "Quick Tasks"
        if (!$stageId) {
            $stageId = $this->createProjectStage($projectId, 'Quick Tasks', 1);
        }

        // 2. Calcular el order al final de la cascada
        $stmtMax = $this->pdo->prepare("SELECT MAX(task_order) FROM project_tasks WHERE project_id = ?");
        $stmtMax->execute([$projectId]);
        $currentMaxOrder = (int)$stmtMax->fetchColumn();
        $taskOrder = max(10000, (floor($currentMaxOrder / 10000) + 1) * 10000);

        // 3. Insertar como tarea principal
        return $this->createProjectTask($projectId, (int)$stageId, $name, $taskOrder, $estimatedMinutes, null, null, null);
    }

    /**
     * ========================================================================
     * RFI / TAREAS INTERMEDIAS (Sub-tareas dinámicas)
     * ========================================================================
     */
    public function createSubTask(int $projectId, int $parentTaskId, string $name, int $estimatedMinutes, ?int $assignedUserId = null): int {
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
        return $this->createProjectTask($projectId, (int)$parent['stage_id'], $name, $newOrder, $estimatedMinutes, $parentTaskId, $inheritedFolderId, $assignedUserId);
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
                
                // Asegurarnos de que el nombre contenga 'RFI' para que el frontend 
                // lo identifique correctamente y aplique las formas geométricas y badges.
                $itemName = $item['name'];
                if (stripos($itemName, 'RFI') === false) {
                    $itemName = 'RFI - ' . $itemName;
                }

                $this->createProjectTask(
                    $projectId,
                    (int)$parent['stage_id'],
                    $itemName,
                    $taskOrder,
                    isset($item['estimated_minutes']) ? (int)$item['estimated_minutes'] : 0,
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
        $allowedStatuses = ['Pending', 'Active', 'On_Hold', 'System_Pause', 'Overdue', 'Bypassed', 'Completed', 'Completed_Late'];
        if (!in_array($status, $allowedStatuses, true)) {
            throw new InvalidArgumentException("Invalid status provided.");
        }

        $query = "UPDATE project_tasks SET status = ?";
        $params = [$status];

        // Lógica automática de fechas según el estado
        if ($status === 'Active') {
            $query .= ", actual_start_time = NOW()";
            if ($expectedEndTime !== null) {
                $query .= ", expected_end_time = ?";
                $params[] = $expectedEndTime;
            }
        } elseif ($status === 'Completed' || $status === 'Completed_Late' || $status === 'Bypassed') {
            $query .= ", actual_end_time = NOW()";
        } elseif ($status === 'On_Hold' || $status === 'System_Pause') {
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
        $stmt = $this->pdo->prepare("
            SELECT pt.*, u.work_start_time, u.work_end_time 
            FROM project_tasks pt 
            LEFT JOIN users u ON pt.assigned_user_id = u.id 
            WHERE pt.id = ?
        ");
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        return $task ?: null;
    }

    /**
     * FASE 90: Cuenta las tareas activas de un usuario en un proyecto.
     */
    public function countUserActiveTasks(int $projectId, int $userId): int {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM project_tasks WHERE project_id = ? AND assigned_user_id = ? AND status = 'Active'");
        $stmt->execute([$projectId, $userId]);
        return (int)$stmt->fetchColumn();
    }

    public function getNextTask(int $projectId): ?array {
        $query = "SELECT * FROM project_tasks 
                  WHERE project_id = ? AND status NOT IN ('Completed', 'Completed_Late', 'Bypassed') 
                  ORDER BY task_order ASC LIMIT 1";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$projectId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        return $task ?: null;
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
            "SELECT COUNT(*) FROM project_tasks WHERE parent_task_id = ? AND status NOT IN ('Completed', 'Completed_Late', 'Bypassed')"
        );
        $stmt->execute([$parentTaskId]);
        $pendingChildren = (int)$stmt->fetchColumn();

        if ($pendingChildren === 0) {
            $parentTask = $this->getTask($parentTaskId);
            if ($parentTask) $this->updateTaskStatus($parentTaskId, (int)$parentTask['project_id'], 'Completed');
        }
    }

    public function getProjectStagesAndTasks(int $projectId, int $userId = 0, string $role = 'admin'): array {
        // Obtener Stages
        $stmtStages = $this->pdo->prepare("SELECT * FROM project_stages WHERE project_id = ? ORDER BY stage_order ASC");
        $stmtStages->execute([$projectId]);
        $stages = $stmtStages->fetchAll(PDO::FETCH_ASSOC);

        // Obtener Tasks
        $sql = "
            SELECT pt.*, u.username as assigned_user_name, u.work_start_time, u.work_end_time 
            FROM project_tasks pt 
            LEFT JOIN users u ON pt.assigned_user_id = u.id 
            WHERE pt.project_id = ? 
        ";
        $params = [$projectId];
        if ($role !== 'admin') {
            $sql .= " AND pt.assigned_user_id = ?";
            $params[] = $userId;
        }
        $sql .= " ORDER BY pt.task_order ASC";
        $stmtTasks = $this->pdo->prepare($sql);
        $stmtTasks->execute($params);
        $tasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);

        // Fetch all files for the project to attach to tasks
        $stmtFiles = $this->pdo->prepare("
            SELECT f.id, f.folder_id, f.filename, f.filepath, fo.name as folder_name 
            FROM files f 
            LEFT JOIN folders fo ON f.folder_id = fo.id 
            WHERE f.project_id = ?
        ");
        $stmtFiles->execute([$projectId]);
        $allFiles = $stmtFiles->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tasks as &$task) {
            $taskFolders = [];
            $prefix = $task['task_order'] . '_Task_';
            foreach ($allFiles as $f) {
                if (strpos($f['filename'], $prefix) === 0) {
                    $fid = $f['folder_id'];
                    if (!isset($taskFolders[$fid])) {
                        $taskFolders[$fid] = [
                            'folder_id' => $fid,
                            'folder_name' => $f['folder_name'] ?? 'Root Folder',
                            'files' => []
                        ];
                    }
                    $taskFolders[$fid]['files'][] = $f;
                }
            }
            $task['attached_folders'] = array_values($taskFolders);
        }
        unset($task);

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
        $allowedActions = ['Started', 'Paused', 'Resumed', 'Bypassed', 'Completed', 'Completed_Late', 'Extended'];
        if (!in_array($actionType, $allowedActions, true)) {
            throw new InvalidArgumentException("Invalid action type provided.");
        }

        // FASE 36.5: Acumulación de tiempo trabajado en la BD
        if (in_array($actionType, ['Paused', 'Completed', 'Completed_Late', 'Bypassed'])) {
            // Solo sumar tiempo si la ÚLTIMA acción registrada fue Started o Resumed (evita duplicar el tiempo por triggers paralelos)
            $stmtLast = $this->pdo->prepare("
                SELECT action_type, logged_at FROM task_time_logs 
                WHERE task_id = ? 
                ORDER BY id DESC LIMIT 1
            ");
            $stmtLast->execute([$taskId]);
            $lastLog = $stmtLast->fetch(PDO::FETCH_ASSOC);

            if ($lastLog && in_array($lastLog['action_type'], ['Started', 'Resumed'])) {
                $start = new DateTime($lastLog['logged_at']);
                $end = new DateTime();
                
                // FASE 92 (OVERTIME FIX): Se revierte al cálculo absoluto puro.
                // Si la tarea corre a las 3 AM (Overtime Exception), todos sus segundos deben contar.
                $diffSeconds = $end->getTimestamp() - $start->getTimestamp();
                $diffMinutes = (int) floor($diffSeconds / 60);

                if ($diffMinutes > 0) {
                    $stmtUpdate = $this->pdo->prepare("UPDATE project_tasks SET worked_minutes = COALESCE(worked_minutes, 0) + ? WHERE id = ?");
                    $stmtUpdate->execute([$diffMinutes, $taskId]);
                }
            }
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
            } elseif (in_array($log['action_type'], ['Paused', 'Completed', 'Completed_Late', 'Bypassed']) && $lastStartTime !== null) {
                $totalSeconds += $actionTime->getTimestamp() - $lastStartTime->getTimestamp();
                $lastStartTime = null; // Reset after calculating an interval
            }
        }

        return round($totalSeconds / 3600, 2);
    }

    /**
     * AUDITORÍA 3 (Anti N+1): Bulk Fetch para obtener las horas reales de TODAS las tareas
     * de un proyecto en una sola consulta.
     */
    public function getProjectActualHours(int $projectId, int $userId = 0, string $role = 'admin'): array {
        $sql = "
            SELECT t.id, l.action_type, l.logged_at 
            FROM project_tasks t
            JOIN task_time_logs l ON t.id = l.task_id
            WHERE t.project_id = ?
        ";
        $params = [$projectId];
        if ($role !== 'admin') {
            $sql .= " AND t.assigned_user_id = ?";
            $params[] = $userId;
        }
        $sql .= " ORDER BY l.task_id, l.logged_at ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalSeconds = [];
        $lastStartTime = [];

        foreach ($logs as $log) {
            $tid = (int)$log['id'];
            if (!isset($totalSeconds[$tid])) { $totalSeconds[$tid] = 0; $lastStartTime[$tid] = null; }
            
            $actionTime = new DateTime($log['logged_at']);
            if (in_array($log['action_type'], ['Started', 'Resumed'])) {
                $lastStartTime[$tid] = $actionTime;
            } elseif (in_array($log['action_type'], ['Paused', 'Completed', 'Completed_Late', 'Bypassed']) && $lastStartTime[$tid] !== null) {
                $totalSeconds[$tid] += $actionTime->getTimestamp() - $lastStartTime[$tid]->getTimestamp();
                $lastStartTime[$tid] = null;
            }
        }
        return array_map(fn($secs) => round($secs / 3600, 2), $totalSeconds);
    }

    public function getUserPerformanceReport(int $projectId, int $userId): array {
        // Verificar existencia en directorio y traer datos del usuario
        $stmtUser = $this->pdo->prepare("SELECT u.username, u.role FROM directory d JOIN users u ON d.user_id = u.id WHERE d.project_id = ? AND d.user_id = ?");
        $stmtUser->execute([$projectId, $userId]);
        $userInfo = $stmtUser->fetch(PDO::FETCH_ASSOC);
        
        if (!$userInfo) {
            throw new Exception("User is not in the project directory.");
        }

        $stmt = $this->pdo->prepare("
            SELECT id, name, estimated_minutes, status, task_order 
            FROM project_tasks
            WHERE project_id = ? AND assigned_user_id = ?
            ORDER BY task_order ASC
        ");
        $stmt->execute([$projectId, $userId]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($tasks)) {
            return [
                'user' => $userInfo, 'total_estimated_hours' => 0, 'total_actual_hours' => 0, 
                'performance_ratio' => 0, 'completed_tasks_count' => 0, 'total_assigned_tasks' => 0, 'tasks' => []
            ];
        }

        // AUDITORÍA 3 (Anti N+1): Bulk Fetch para logs y justificaciones
        $taskIds = array_column($tasks, 'id');
        $inQuery = implode(',', array_fill(0, count($taskIds), '?'));
        
        $stmtLogs = $this->pdo->prepare("SELECT task_id, action_type, logged_at, justification_note FROM task_time_logs WHERE task_id IN ($inQuery) ORDER BY logged_at ASC");
        $stmtLogs->execute($taskIds);
        $allLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
        
        $logsByTask = [];
        $justificationsByTask = [];
        foreach ($allLogs as $log) {
            $tid = (int)$log['task_id'];
            $logsByTask[$tid][] = $log;
            if (!empty($log['justification_note'])) { $justificationsByTask[$tid] = $log['justification_note']; }
        }

        $totalEstimatedHours = 0.0;
        $totalActualHours = 0.0;
        $detailedTasks = [];
        $completedCount = 0;

        foreach ($tasks as $task) {
            $tid = (int)$task['id'];
            $minutos = isset($task['estimated_minutes']) ? (int)$task['estimated_minutes'] : 0;
            $estimated = round($minutos / 60, 2);
            
            // Cálculo en memoria (Ya no ejecuta BD)
            $actual = 0;
            if (isset($logsByTask[$tid])) {
                $tSecs = 0; $lStart = null;
                foreach ($logsByTask[$tid] as $log) {
                    $aTime = new DateTime($log['logged_at']);
                    if (in_array($log['action_type'], ['Started', 'Resumed'])) { $lStart = $aTime; } 
                    elseif (in_array($log['action_type'], ['Paused', 'Completed', 'Completed_Late', 'Bypassed']) && $lStart !== null) {
                        $tSecs += $aTime->getTimestamp() - $lStart->getTimestamp();
                        $lStart = null;
                    }
                }
                $actual = round($tSecs / 3600, 2);
            }

            $totalEstimatedHours += $estimated;
            $totalActualHours += $actual;

            if (in_array($task['status'], ['Completed', 'Completed_Late', 'Bypassed'])) {
                $completedCount++;
            }
            
            $justification = $justificationsByTask[$tid] ?? 'None';
            
            $remaining = max(0, $estimated - $actual);
            if (in_array($task['status'], ['Completed', 'Completed_Late', 'Bypassed'])) $remaining = 0;

            $detailedTasks[] = [
                'id' => $tid, 
                'task_order' => $task['task_order'],
                'name' => $task['name'], 
                'status' => str_replace('_', ' ', $task['status']),
                'estimated_hours' => $estimated, 
                'actual_hours' => $actual, 
                'remaining_hours' => $remaining,
                'variance' => round($estimated - $actual, 2),
                'justification' => $justification
            ];
        }

        return [
            'user' => $userInfo,
            'total_estimated_hours' => round($totalEstimatedHours, 2), 
            'total_actual_hours' => round($totalActualHours, 2), 
            'performance_ratio' => $totalEstimatedHours > 0 ? round($totalActualHours / $totalEstimatedHours, 2) : 0, 
            'completed_tasks_count' => $completedCount,
            'total_assigned_tasks' => count($tasks),
            'tasks' => $detailedTasks
        ];
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
                completed_tasks = (SELECT COUNT(*) FROM project_tasks WHERE project_id = ? AND status IN ('Completed', 'Completed_Late', 'Bypassed'))
            WHERE id = ?
        ");
        $stmt->execute([$projectId, $projectId, $projectId]);
    }

    public function attachFilesToTask(int $taskId, int $projectId, int $folderId, array $files, int $userId): array {
        // 1. Validations
        $this->validateUserInDirectory($projectId, $userId);
        
        $task = $this->getTask($taskId);
        // FASE 27: Se elimina la validación del folder_id de la tarea porque ahora el usuario lo elige.
        if (!$task || (int)$task['project_id'] !== $projectId) {
            return ['status' => 'error', 'message' => 'Task/Project mismatch or task not found.'];
        }

        // 2. Path and Filename Sanitization (Prefix Task Order)
        $taskOrder = isset($task['task_order']) ? $task['task_order'] : $taskId;

        // 3. Directory Structure
        $baseUploadDir = __DIR__ . '/../uploads';
        $projectDir = $baseUploadDir . '/' . $projectId;
        $folderDir = $projectDir . '/' . $folderId;
        
        if (!is_dir($folderDir)) {
            if (!mkdir($folderDir, 0775, true) && !is_dir($folderDir)) {
                return ['status' => 'error', 'message' => 'Failed to create attachment directory.'];
            }
        }
        
        $this->pdo->beginTransaction();
        try {
            $fileCount = isset($files['name']) && is_array($files['name']) ? count($files['name']) : 0;
            
            $stmtExisting = $this->pdo->prepare("SELECT COUNT(*) FROM files WHERE project_id = ? AND filename LIKE ?");
            $stmtExisting->execute([$projectId, $taskOrder . '_Task_%']);
            $existingCount = (int)$stmtExisting->fetchColumn();
            
            if (($existingCount + $fileCount) > 20) {
                throw new Exception("Maximum 20 files allowed per task. You currently have {$existingCount} attached.");
            }

            for ($i = 0; $i < $fileCount; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }

                $filename = basename($files['name'][$i]);
                $originalName = pathinfo($filename, PATHINFO_FILENAME);
                $extension = pathinfo($filename, PATHINFO_EXTENSION);
                
                $originalName = preg_replace('/[^A-Za-z0-9._\-]/', '_', $originalName);
                $filename = $taskOrder . '_Task_' . $originalName . '.' . $extension;

                $counter = 1;
                $targetPath = $folderDir . '/' . $filename;
                while (file_exists($targetPath)) {
                    $filename = $taskOrder . '_Task_' . $originalName . '_' . $counter . '.' . $extension;
                    $targetPath = $folderDir . '/' . $filename;
                    $counter++;
                }

                if (move_uploaded_file($files['tmp_name'][$i], $targetPath)) {
                    $relativePath = 'uploads/' . $projectId . '/' . $folderId . '/' . $filename;
                    $stmt = $this->pdo->prepare(
                        "INSERT INTO files (project_id, folder_id, filename, filepath, uploaded_by, file_type, uploaded_at) 
                         VALUES (?, ?, ?, ?, ?, ?, NOW())"
                    );
                    $stmt->execute([$projectId, $folderId, $filename, $relativePath, $userId, $files['type'][$i]]);
                }
            }

            $stmtTask = $this->pdo->prepare("UPDATE project_tasks SET folder_id = ? WHERE id = ? AND folder_id IS NULL");
            $stmtTask->execute([$folderId, $taskId]);

            $this->pdo->commit();
            return ['status' => 'success', 'message' => 'Files attached successfully!'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}