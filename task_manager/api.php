<?php
// task_manager/api.php

header('Content-Type: application/json');
require_once __DIR__ . '/../core/db/connection.php';
require_once __DIR__ . '/../core/auth/session.php';
require_once __DIR__ . '/TaskManager.php';
require_once __DIR__ . '/Core/TimeEngine.php';
require_once __DIR__ . '/Core/TaskManagerController.php';

// Validar Sesión
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized or session expired.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$projectId = (int)($_POST['project_id'] ?? $_GET['project_id'] ?? 0);

$taskManager = new TaskManager($pdo);
$timeEngine = new TimeEngine();
$controller = new TaskManagerController($taskManager, $timeEngine);

try {
    switch ($action) {
        case 'get_templates':
            $templates = $taskManager->getAllTemplates();
            echo json_encode(['status' => 'success', 'data' => $templates]);
            break;
            
        case 'get_template_details_for_assignment':
            $templateId = (int)($_POST['template_id'] ?? 0);
            if (!$projectId || !$templateId) throw new Exception("Missing project ID or template ID.");
            
            $details = $taskManager->getTemplateDetailsForAssignment($templateId, $projectId);
            echo json_encode(['status' => 'success', 'data' => $details]);
            break;

        case 'apply_template':
            $templateId = (int)($_POST['template_id'] ?? 0);
            $assignmentsJson = $_POST['assignments'] ?? '[]';
            $stageAssignmentsJson = $_POST['stage_assignments'] ?? '[]';
            
            $assignments = json_decode($assignmentsJson, true); 
            $stageAssignments = json_decode($stageAssignmentsJson, true);
            if (!$projectId || !$templateId) throw new Exception("Missing project or template ID.");
            if (!is_array($assignments)) throw new Exception("Invalid assignments data.");
            
            $taskManager->applyTemplateToProject($templateId, $projectId, $assignments, $stageAssignments);
            echo json_encode(['status' => 'success', 'message' => 'Template applied successfully.']);
            break;

        case 'get_tasks':
            if (!$projectId) throw new Exception("Missing project ID.");
            
            $data = $taskManager->getProjectStagesAndTasks($projectId);
            $isHoliday = $timeEngine->isTodayHoliday();
            echo json_encode(['status' => 'success', 'data' => $data, 'is_holiday' => $isHoliday]);
            break;

        case 'update_task_status':
            $taskId = (int)($_POST['task_id'] ?? 0);
            $newStatus = trim($_POST['status'] ?? '');
            $justification = $_POST['justification_note'] ?? null;
            if (empty($justification)) $justification = null;

            if (!$taskId || !$newStatus) throw new Exception("Missing task ID or status.");
            
            $result = $controller->updateTaskStatus($taskId, $newStatus, (int)$_SESSION['user_id'], $justification);
            if ($result['status'] === 'error') {
                throw new Exception($result['message']);
            }
            echo json_encode($result);
            break;

        case 'get_project_users':
            if (!$projectId) throw new Exception("Missing project ID.");
            
            $users = $taskManager->getProjectDirectoryUsers($projectId);
            echo json_encode(['status' => 'success', 'data' => $users]);
            break;
            
        case 'update_task_details':
            $taskId = (int)($_POST['task_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $estimatedHours = (float)($_POST['estimated_hours'] ?? 0);
            $assignedUserId = !empty($_POST['assigned_user_id']) ? (int)$_POST['assigned_user_id'] : null;
            
            if (!$taskId || !$projectId || empty($name) || $estimatedHours <= 0) {
                throw new Exception("Invalid task details provided.");
            }
            
            $taskManager->updateTaskDetails($taskId, $projectId, $name, $estimatedHours, $assignedUserId);
            echo json_encode(['status' => 'success', 'message' => 'Task updated successfully.']);
            break;
        
        case 'create_subtask': // ANTES: create_rfi
            $parentTaskId = (int)($_POST['parent_task_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $estimatedHours = (float)($_POST['estimated_hours'] ?? 8.0);
            $assignedUserId = !empty($_POST['assigned_user_id']) ? (int)$_POST['assigned_user_id'] : null;
            
            if (!$projectId || !$parentTaskId || empty($name)) {
                throw new Exception("Missing required fields for Sub-task.");
            }
            
            $result = $controller->createSingleSubtask($projectId, $parentTaskId, $name, $estimatedHours, $assignedUserId, (int)$_SESSION['user_id']);
            if ($result['status'] === 'error') {
                throw new Exception($result['message']);
            }
            echo json_encode($result);
            break;

        case 'get_rfi_templates':
            $templates = $taskManager->getRfiTemplates();
            echo json_encode(['status' => 'success', 'data' => $templates]);
            break;

        case 'apply_rfi_template':
            $parentTaskId = (int)($_POST['parent_task_id'] ?? 0);
            $rfiTemplateId = (int)($_POST['rfi_template_id'] ?? 0);

            if (!$projectId || !$parentTaskId || !$rfiTemplateId) {
                throw new Exception("Missing required fields for applying RFI template.");
            }

            $result = $controller->applyRfiTemplate($projectId, $parentTaskId, $rfiTemplateId, (int)$_SESSION['user_id']);
            if ($result['status'] === 'error') {
                throw new Exception($result['message']);
            }
            echo json_encode($result);
            break;

        case 'extend_task_time':
            $taskId = (int)($_POST['task_id'] ?? 0);
            $extendHours = (float)($_POST['extend_hours'] ?? 0);
            $justification = trim($_POST['justification_note'] ?? '');
            
            if (!$taskId || $extendHours <= 0 || empty($justification)) {
                throw new Exception("Invalid parameters for time extension.");
            }
            
            $task = $taskManager->getTask($taskId);
            if (!$task || !in_array($task['status'], ['Active', 'On_Hold', 'Overdue'])) {
                throw new Exception("Task cannot be extended in its current status.");
            }
            
            // Recalcular fecha límite
            $currentDeadline = new DateTime($task['expected_end_time'] ?? 'now');
            $now = new DateTime();
            
            // FASE 24 FIX: Si la tarea ya estaba vencida (Overdue) o el tiempo actual la superó, la extensión suma desde AHORA
            if ($currentDeadline < $now || $task['status'] === 'Overdue') {
                $currentDeadline = clone $now;
            }

            $newDeadline = $timeEngine->calculateDeadline($currentDeadline, $extendHours);
            $newTotalHours = (float)$task['estimated_hours'] + $extendHours;
            
            // Si estaba Overdue, la revivimos a Active para que el cronómetro vuelva a correr
            $newStatus = ($task['status'] === 'Overdue') ? 'Active' : $task['status'];
            
            $pdo->prepare("UPDATE project_tasks SET status = ?, estimated_hours = ?, expected_end_time = ? WHERE id = ?")->execute([$newStatus, $newTotalHours, $newDeadline->format('Y-m-d H:i:s'), $taskId]);
            
            // Log y justificación
            $taskManager->logTaskAction($taskId, (int)$_SESSION['user_id'], 'Extended', "Extended by {$extendHours}h. Reason: {$justification}");
            
            echo json_encode(['status' => 'success', 'message' => 'Task time extended successfully.']);
            break;

        case 'reset_project_tasks':
            $justification = trim($_POST['justification_note'] ?? '');
            if (!$projectId || empty($justification)) {
                throw new Exception("Missing project ID or justification note.");
            }
            
            $result = $controller->resetProjectTasks($projectId, (int)$_SESSION['user_id'], $justification);
            if ($result['status'] === 'error') {
                throw new Exception($result['message']);
            }
            echo json_encode($result);
            break;

        case 'get_user_performance':
            $userId = (int)($_POST['user_id'] ?? $_GET['user_id'] ?? 0);
            if (!$projectId || !$userId) {
                throw new Exception("Missing project ID or user ID for performance report.");
            }
            
            $reportData = $taskManager->getUserPerformanceReport($projectId, $userId);
            echo json_encode(['status' => 'success', 'data' => $reportData]);
            break;
            
        case 'force_overdue_test':
            if (!$projectId) throw new Exception("Missing project ID.");
            $stmt = $pdo->prepare("UPDATE project_tasks SET expected_end_time = DATE_ADD(NOW(), INTERVAL 10 SECOND) WHERE project_id = ? AND status = 'Active'");
            $stmt->execute([$projectId]);
            echo json_encode(['status' => 'success', 'message' => 'Active tasks will overdue in 10 seconds.']);
            break;

        case 'upload_task_attachment':
            $taskId = (int)($_POST['task_id'] ?? 0);
            $folderId = (int)($_POST['folder_id'] ?? 0);
            $file = $_FILES['file'] ?? null;

            if (!$projectId || !$taskId || !$folderId || !$file) {
                throw new Exception("Missing required data for file attachment.");
            }

            $result = $taskManager->attachFileToTask($taskId, $projectId, $folderId, $file, (int)$_SESSION['user_id']);
            echo json_encode($result);
            break;

        default:
            throw new Exception("Invalid Smart PM action requested.");
    }
} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}