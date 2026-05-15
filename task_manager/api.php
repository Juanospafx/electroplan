<?php
// task_manager/api.php

// FASE 34: Sincronización Estricta de Zona Horaria
date_default_timezone_set('America/Santo_Domingo');

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

$userRoleRaw = $_SESSION['role'] ?? 'viewer';
$userRole = strtolower($userRoleRaw);

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
            
        case 'get_template_full':
            $templateId = (int)($_POST['template_id'] ?? $_GET['template_id'] ?? 0);
            if (!$templateId) throw new Exception("Missing template ID.");
            
            $templateInfo = $taskManager->getTemplate($templateId);
            $items = $taskManager->getTemplateItems($templateId);
            
            $structuredTemplate = [];
            foreach ($items as $item) {
                if (!isset($structuredTemplate[$item['stage_name']])) {
                    $structuredTemplate[$item['stage_name']] = [
                        'name' => $item['stage_name'],
                        'tasks' => []
                    ];
                }
                $structuredTemplate[$item['stage_name']]['tasks'][] = [
                    'name' => $item['name'],
                    'estimated_minutes' => isset($item['estimated_minutes']) ? (int)$item['estimated_minutes'] : 0,
                    'item_order' => (int)$item['item_order']
                ];
            }
            
            echo json_encode(['status' => 'success', 'data' => [
                'info' => $templateInfo,
                'stages' => array_values($structuredTemplate)
            ]]);
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
            
            $data = $taskManager->getProjectStagesAndTasks($projectId, (int)$_SESSION['user_id'], $userRole);
            $isHoliday = $timeEngine->isTodayHoliday();
            echo json_encode(['status' => 'success', 'data' => $data, 'is_holiday' => $isHoliday]);
            break;
            
        case 'get_active_projects':
            $projects = $taskManager->getActiveProjects((int)$_SESSION['user_id'], $userRole);
            echo json_encode(['status' => 'success', 'data' => $projects]);
            break;

        case 'update_task_status':
            $taskId = (int)($_POST['task_id'] ?? 0);
            $newStatus = trim($_POST['status'] ?? '');
            $justification = $_POST['justification_note'] ?? null;
            $autoStartNext = isset($_POST['auto_start_next']) && $_POST['auto_start_next'] == 1;
            $forceOvertime = isset($_POST['force_overtime']) && $_POST['force_overtime'] == 1;
            if (empty($justification)) $justification = null;

            if (!$taskId || !$newStatus) throw new Exception("Missing task ID or status.");
            
            $result = $controller->updateTaskStatus($taskId, $newStatus, (int)$_SESSION['user_id'], $justification, $autoStartNext, $forceOvertime);
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
            $estimatedMinutes = (int)($_POST['estimated_minutes'] ?? 0);
            $assignedUserId = !empty($_POST['assigned_user_id']) ? (int)$_POST['assigned_user_id'] : null;
            
            if (!$taskId || !$projectId || empty($name) || $estimatedMinutes <= 0) {
                throw new Exception("Invalid task details provided.");
            }
            
            $taskManager->updateTaskDetails($taskId, $projectId, $name, $estimatedMinutes, $assignedUserId);
            echo json_encode(['status' => 'success', 'message' => 'Task updated successfully.']);
            break;
        
        case 'delete_task':
            if ($userRole !== 'admin') throw new Exception("Access Denied: Only administrators can delete tasks.");
            $taskId = (int)($_POST['task_id'] ?? 0);
            $deleteSubtasks = isset($_POST['delete_subtasks']) && $_POST['delete_subtasks'] == '1';
            
            if (!$projectId || !$taskId) {
                throw new Exception("Missing project ID or task ID.");
            }
            
            $taskManager->deleteProjectTask($taskId, $projectId, $deleteSubtasks);
            echo json_encode(['status' => 'success', 'message' => 'Task deleted successfully.']);
            break;

        case 'create_stage_task':
            if ($userRole !== 'admin') throw new Exception("Access Denied: Only administrators can create tasks.");
            $stageId = (int)($_POST['stage_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $estimatedMinutes = (int)($_POST['estimated_minutes'] ?? 480);
            $assignedUserId = !empty($_POST['assigned_user_id']) ? (int)$_POST['assigned_user_id'] : null;
            
            if (!$projectId || !$stageId || empty($name)) {
                throw new Exception("Missing required fields for Stage Task.");
            }
            
            $stmtMax = $pdo->prepare("SELECT MAX(task_order) FROM project_tasks WHERE project_id = ?");
            $stmtMax->execute([$projectId]);
            $currentMaxOrder = (int)$stmtMax->fetchColumn();
            $taskOrder = max(10000, (floor($currentMaxOrder / 10000) + 1) * 10000);
            
            $taskManager->createProjectTask($projectId, $stageId, $name, $taskOrder, $estimatedMinutes, null, null, $assignedUserId);
            echo json_encode(['status' => 'success', 'message' => 'Task created successfully.']);
            break;
            
        case 'create_quick_task':
            if ($userRole !== 'admin') throw new Exception("Access Denied: Only administrators can create quick tasks.");
            $nameQuick = trim($_POST['name'] ?? '');
            $estimatedMinutesQuick = (int)($_POST['estimated_minutes'] ?? 0);
            
            if (!$projectId || empty($nameQuick) || $estimatedMinutesQuick <= 0) {
                throw new Exception("Missing required fields for Quick Task.");
            }
            
            $taskManager->createQuickTask($projectId, $nameQuick, $estimatedMinutesQuick);
            echo json_encode(['status' => 'success', 'message' => 'Quick task sent to project successfully.']);
            break;
            
        case 'get_personal_tasks':
            if ($userRole !== 'admin') throw new Exception("Access Denied");
            $tasks = $taskManager->getPersonalTasks((int)$_SESSION['user_id']);
            echo json_encode(['status' => 'success', 'data' => $tasks]);
            break;

        case 'add_personal_task':
            if ($userRole !== 'admin') throw new Exception("Access Denied");
            $name = trim($_POST['name'] ?? '');
            $minutes = (int)($_POST['estimated_minutes'] ?? 60);
            if (empty($name)) throw new Exception("Task name required.");
            
            $taskManager->addPersonalTask((int)$_SESSION['user_id'], $name, $minutes);
            echo json_encode(['status' => 'success', 'message' => 'Added to scratchpad.']);
            break;

        case 'edit_personal_task':
            if ($userRole !== 'admin') throw new Exception("Access Denied");
            $taskId = (int)($_POST['task_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $pdo->prepare("UPDATE personal_tasks SET name = ? WHERE id = ? AND user_id = ?")->execute([$name, $taskId, (int)$_SESSION['user_id']]);
            echo json_encode(['status' => 'success']);
            break;

        case 'update_personal_task_status':
            if ($userRole !== 'admin') throw new Exception("Access Denied");
            $taskId = (int)($_POST['task_id'] ?? 0);
            $status = trim($_POST['status'] ?? 'Pending');
            $forceOvertime = isset($_POST['force_overtime']) && $_POST['force_overtime'] == '1';
            
            $task = $pdo->prepare("SELECT * FROM personal_tasks WHERE id = ? AND user_id = ?");
            $task->execute([$taskId, (int)$_SESSION['user_id']]);
            if (!$task->fetch()) throw new Exception("Task not found.");
            
            if ($status === 'Active' && !$forceOvertime) {
                $stmtU = $pdo->prepare("SELECT work_start_time, work_end_time FROM users WHERE id = ?");
                $stmtU->execute([(int)$_SESSION['user_id']]);
                $uData = $stmtU->fetch(PDO::FETCH_ASSOC);
                
                if (!$timeEngine->isWorkingHour(null, $uData['work_start_time'] ?? '07:00:00', $uData['work_end_time'] ?? '19:00:00')) {
                    echo json_encode(['status' => 'confirm_overtime', 'message' => 'You are trying to start this task outside of your established working hours.']);
                    exit;
                }
            }
            $taskManager->updatePersonalTaskStatus($taskId, (int)$_SESSION['user_id'], $status);
            echo json_encode(['status' => 'success']);
            break;

        case 'complete_personal_task':
            if ($userRole !== 'admin') throw new Exception("Access Denied");
            $taskManager->completePersonalTask((int)($_POST['task_id'] ?? 0), (int)$_SESSION['user_id']);
            echo json_encode(['status' => 'success']);
            break;

        case 'delete_personal_task':
            if ($userRole !== 'admin') throw new Exception("Access Denied");
            $taskManager->deletePersonalTask((int)($_POST['task_id'] ?? 0), (int)$_SESSION['user_id']);
            echo json_encode(['status' => 'success']);
            break;

        case 'get_project_stages_list':
            $projectId = (int)($_POST['project_id'] ?? 0);
            $stages = $taskManager->getProjectStagesList($projectId);
            echo json_encode(['status' => 'success', 'data' => $stages]);
            break;

        case 'transfer_personal_task':
            if ($userRole !== 'admin') throw new Exception("Access Denied");
            $targetProjectId = (int)($_POST['target_project_id'] ?? 0);
            $targetStageId = !empty($_POST['target_stage_id']) ? (int)$_POST['target_stage_id'] : null;
            $markAsCompleted = isset($_POST['mark_as_completed']) ? ($_POST['mark_as_completed'] == '1') : true;
            
            $newTaskId = $taskManager->transferPersonalTaskToProject((int)($_POST['task_id'] ?? 0), $targetProjectId, $targetStageId, (int)$_SESSION['user_id'], $markAsCompleted);
            
            if (!empty($_FILES['files']['name'][0])) {
                $folderId = (int)($_POST['folder_id'] ?? 0);
                if ($folderId > 0) {
                    $taskManager->attachFilesToTask($newTaskId, $targetProjectId, $folderId, $_FILES['files'], (int)$_SESSION['user_id']);
                }
            }

            echo json_encode(['status' => 'success', 'message' => 'Task deployed successfully!']);
            break;

        case 'create_subtask': // ANTES: create_rfi
            if ($userRole !== 'admin') throw new Exception("Access Denied: Only administrators can create sub-tasks.");
            $parentTaskId = (int)($_POST['parent_task_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $estimatedMinutes = (int)($_POST['estimated_minutes'] ?? 480);
            $assignedUserId = !empty($_POST['assigned_user_id']) ? (int)$_POST['assigned_user_id'] : null;
            
            if (!$projectId || !$parentTaskId || empty($name)) {
                throw new Exception("Missing required fields for Sub-task.");
            }
            
            $result = $controller->createSingleSubtask($projectId, $parentTaskId, $name, $estimatedMinutes, $assignedUserId, (int)$_SESSION['user_id']);
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
            if ($userRole !== 'admin') throw new Exception("Access Denied: Only administrators can create RFI tasks.");
            $parentTaskId = (int)($_POST['parent_task_id'] ?? 0);
            $stageId = (int)($_POST['stage_id'] ?? 0);
            $rfiTemplateId = (int)($_POST['rfi_template_id'] ?? 0);

            if (!$projectId || !$rfiTemplateId) {
                throw new Exception("Missing required fields for applying RFI template.");
            }
            if (!$parentTaskId && !$stageId) {
                throw new Exception("Must provide either parent_task_id or stage_id.");
            }

            if ($stageId && !$parentTaskId) {
                // FASE 79: Crear una "Tarea Envoltorio" para el bloque RFI insertado en la etapa
                $rfiTemplate = $taskManager->getTemplate($rfiTemplateId);
                $rfiName = $rfiTemplate ? "RFI - " . $rfiTemplate['name'] : "RFI Block";
                
                $stmtMax = $pdo->prepare("SELECT MAX(task_order) FROM project_tasks WHERE project_id = ?");
                $stmtMax->execute([$projectId]);
                $currentMaxOrder = (int)$stmtMax->fetchColumn();
                $taskOrder = max(10000, (floor($currentMaxOrder / 10000) + 1) * 10000);
                
                $parentTaskId = $taskManager->createProjectTask($projectId, $stageId, $rfiName, $taskOrder, 0, null, null, null);
            }

            $result = $controller->applyRfiTemplate($projectId, $parentTaskId, $rfiTemplateId, (int)$_SESSION['user_id']);
            $result['parent_task_id'] = $parentTaskId;
            if ($result['status'] === 'error') {
                throw new Exception($result['message']);
            }
            echo json_encode($result);
            break;

        case 'extend_task_time':
            $taskId = (int)($_POST['task_id'] ?? 0);
            $extendMinutes = (int)($_POST['extend_minutes'] ?? 0);
            $justification = trim($_POST['justification_note'] ?? '');
            
            if (!$taskId || $extendMinutes <= 0 || empty($justification)) {
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

            $newDeadline = $timeEngine->calculateDeadline($currentDeadline, $extendMinutes);
            $newTotalMinutes = (int)$task['estimated_minutes'] + $extendMinutes;
            
            // Si estaba Overdue, la revivimos a Active para que el cronómetro vuelva a correr
            $newStatus = ($task['status'] === 'Overdue') ? 'Active' : $task['status'];
            
            if ($newStatus === 'Active' && $task['status'] === 'Overdue') {
                // Si se reactiva, seteamos actual_start_time = NOW() para reiniciar el cronómetro frontal
                $pdo->prepare("UPDATE project_tasks SET status = ?, estimated_minutes = ?, expected_end_time = ?, actual_start_time = NOW() WHERE id = ?")->execute([$newStatus, $newTotalMinutes, $newDeadline->format('Y-m-d H:i:s'), $taskId]);
            } else {
                $pdo->prepare("UPDATE project_tasks SET status = ?, estimated_minutes = ?, expected_end_time = ? WHERE id = ?")->execute([$newStatus, $newTotalMinutes, $newDeadline->format('Y-m-d H:i:s'), $taskId]);
            }
            
            // Log y justificación
            $extH = round($extendMinutes / 60, 2);
            $taskManager->logTaskAction($taskId, (int)$_SESSION['user_id'], 'Extended', "Extended by {$extH}h. Reason: {$justification}");
            if ($newStatus === 'Active' && $task['status'] === 'Overdue') {
                $taskManager->logTaskAction($taskId, (int)$_SESSION['user_id'], 'Resumed', "Task resumed automatically due to time extension.");
            }
            $taskManager->addProjectActivityLog((int)$task['project_id'], $taskId, (int)$_SESSION['user_id'], 'Extend', "Extended by {$extH}h. Reason: {$justification}");
            
            echo json_encode(['status' => 'success', 'message' => 'Task time extended successfully.']);
            break;

        case 'reset_project_tasks':
            if ($userRole !== 'admin') throw new Exception("Access Denied: Only administrators can reset tasks.");
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
            $files = $_FILES['files'] ?? null;

            if (!$projectId || !$taskId || !$folderId || !$files) {
                throw new Exception("Missing required data for file attachment.");
            }

            $result = $taskManager->attachFilesToTask($taskId, $projectId, $folderId, $files, (int)$_SESSION['user_id']);
            echo json_encode($result);
            break;
            
        case 'save_template':
            if (strtolower($_SESSION['role'] ?? '') !== 'admin') throw new Exception("Access Denied");
            
            $dataStr = $_POST['template_data'] ?? '';
            $data = json_decode($dataStr, true);
            if (!$data) throw new Exception("Invalid JSON structure.");
            
            $name = trim($data['template_name'] ?? '');
            $desc = trim($data['description'] ?? '');
            $stages = $data['stages'] ?? [];
            $mode = $data['mode'] ?? 'clone';
            $templateId = (int)($data['template_id'] ?? 0);
            
            if (empty($name)) throw new Exception("Template name is required.");
            
            $pdo->beginTransaction();
            
            if ($mode === 'update' && $templateId > 0) {
                $taskManager->updateTemplate($templateId, $name, $desc);
                $taskManager->clearTemplateItems($templateId);
            } else {
                $templateId = $taskManager->createTemplate($name, $desc, (int)$_SESSION['user_id']);
            }
            
            $order = 1;
            foreach ($stages as $stage) {
                $stageName = trim($stage['name'] ?? 'Unnamed Stage');
                foreach ($stage['tasks'] as $task) {
                    $taskName = trim($task['name'] ?? 'Unnamed Task');
                    $minutes = (int)($task['minutes'] ?? 480);
                    $taskManager->addTemplateItem($templateId, $stageName, $taskName, $order++, $minutes, null);
                }
            }
            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Template successfully saved.']);
            break;
            
        case 'delete_template':
            if ($userRole !== 'admin') throw new Exception("Access Denied");
            $templateId = (int)($_POST['template_id'] ?? 0);
            if (!$templateId) throw new Exception("Missing template ID.");
            
            $taskManager->deleteTemplate($templateId);
            echo json_encode(['status' => 'success', 'message' => 'Template deleted successfully.']);
            break;
            
        case 'create_project_stage':
            if ($userRole !== 'admin') throw new Exception("Access Denied: Only administrators can create stages.");
            $name = trim($_POST['name'] ?? '');
            if (!$projectId || empty($name)) throw new Exception("Missing project ID or stage name.");
            
            $stmtStage = $pdo->prepare("SELECT MAX(stage_order) FROM project_stages WHERE project_id = ?");
            $stmtStage->execute([$projectId]);
            $stageOrder = (int)$stmtStage->fetchColumn() + 1;
            
            $taskManager->createProjectStage($projectId, $name, $stageOrder);
            echo json_encode(['status' => 'success', 'message' => 'Stage created.']);
            break;

        case 'export_project_csv':
            // FASE 79: Función de Exportación a CSV desde Proyecto Vivo
            if (strtolower($_SESSION['role'] ?? '') !== 'admin') throw new Exception("Access Denied");
            
            if (!$projectId) throw new Exception("Missing project ID.");
            
            $project = $pdo->prepare("SELECT name FROM projects WHERE id = ?");
            $project->execute([$projectId]);
            $projName = $project->fetchColumn();
            if (!$projName) throw new Exception("Project not found.");
            
            $stmt = $pdo->prepare("
                SELECT ps.name as stage_name, pt.name as task_name, pt.estimated_minutes
                FROM project_tasks pt
                JOIN project_stages ps ON pt.stage_id = ps.id
                WHERE pt.project_id = ? AND pt.parent_task_id IS NULL
                ORDER BY pt.task_order ASC
            ");
            $stmt->execute([$projectId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $safeName = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($projName));
            $filename = "project_template_{$safeName}.csv";
            
            ob_clean();
            header('Content-Type: text/csv; charset=utf-8', true);
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Stage Name', 'Task Name', 'Estimated Hours']);
            foreach ($items as $item) {
                $hours = isset($item['estimated_minutes']) ? round($item['estimated_minutes'] / 60, 2) : 8;
                fputcsv($output, [$item['stage_name'], $item['task_name'], $hours]);
            }
            fclose($output);
            exit; // Prevenir cualquier salida extra del script

        case 'export_template_csv':
            // FASE 47: Función de Exportación a CSV
            if (strtolower($_SESSION['role'] ?? '') !== 'admin') throw new Exception("Access Denied");
            
            $templateId = (int)($_GET['template_id'] ?? 0);
            if (!$templateId) throw new Exception("Missing template ID.");
            
            $templateInfo = $taskManager->getTemplate($templateId);
            if (!$templateInfo) throw new Exception("Template not found.");
            
            $items = $taskManager->getTemplateItems($templateId);
            
            $safeName = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($templateInfo['name']));
            $filename = "template_{$safeName}.csv";
            
            // Limpiar cualquier JSON previo y forzar headers de descarga CSV
            ob_clean();
            header('Content-Type: text/csv; charset=utf-8', true);
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Stage Name', 'Task Name', 'Estimated Hours']);
            foreach ($items as $item) {
                $hours = isset($item['estimated_minutes']) ? round($item['estimated_minutes'] / 60, 2) : 8;
                fputcsv($output, [$item['stage_name'], $item['name'], $hours]);
            }
            fclose($output);
            exit; // Prevenir cualquier salida extra del script

        case 'import_template_csv':
            if (strtolower($_SESSION['role'] ?? '') !== 'admin') throw new Exception("Access Denied");

            $name = trim($_POST['template_name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            $file = $_FILES['csv_file'] ?? null;

            if (empty($name)) throw new Exception("Template name is required.");
            if (!$file || $file['error'] !== UPLOAD_ERR_OK) throw new Exception("A valid CSV file is required.");
            if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') throw new Exception("File must be a .csv format.");

            $handle = fopen($file['tmp_name'], 'r');
            if (!$handle) throw new Exception("Could not read the uploaded CSV file.");

            $pdo->beginTransaction();
            try {
                $templateId = $taskManager->createTemplate($name, $desc, (int)$_SESSION['user_id']);
                $order = 1;
                $isFirstRow = true;

                while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                    // Omitir la primera línea si es cabecera ("Stage Name")
                    if ($isFirstRow && (stripos($data[0] ?? '', 'stage') !== false || stripos($data[0] ?? '', 'etapa') !== false)) {
                        $isFirstRow = false;
                        continue;
                    }
                    $isFirstRow = false;
                    if (count($data) < 2) continue; // Saltar filas vacías o corruptas

                    $stageName = trim($data[0]);
                    $taskName = trim($data[1]);
                    $hours = isset($data[2]) ? (float)preg_replace('/[^0-9.]/', '', $data[2]) : 8.0;
                    $minutes = (int)round($hours * 60);

                    if ($stageName === '' || $taskName === '') continue;

                    $taskManager->addTemplateItem($templateId, $stageName, $taskName, $order++, $minutes, null);
                }
                fclose($handle);

                if ($order === 1) throw new Exception("No valid tasks found in the CSV file.");

                $pdo->commit();
                echo json_encode(['status' => 'success', 'message' => 'Template successfully imported.']);
            } catch (Exception $e) {
                $pdo->rollBack();
                if (is_resource($handle)) fclose($handle);
                throw $e;
            }
            break;

        case 'get_project_health':
            if (!$projectId) throw new Exception("Missing project ID.");
            
            $result = $controller->getProjectHealthSummary($projectId, (int)$_SESSION['user_id'], $userRole);
            if ($result['status'] === 'error') {
                throw new Exception($result['message']);
            }
            echo json_encode($result);
            break;

        case 'get_all_projects_health':
            $result = $controller->getAllProjectsHealthSummary((int)$_SESSION['user_id'], $userRole);
            if ($result['status'] === 'error') {
                throw new Exception($result['message']);
            }
            echo json_encode($result);
            break;
            
        case 'get_global_active_tasks':
            $result = $controller->getGlobalActiveTasks((int)$_SESSION['user_id'], $userRole);
            if ($result['status'] === 'error') {
                throw new Exception($result['message']);
            }
            echo json_encode($result);
            break;

        case 'add_project_log':
            $taskId = !empty($_POST['task_id']) ? (int)$_POST['task_id'] : null;
            $actionType = trim($_POST['action_type'] ?? 'Note');
            $description = trim($_POST['description'] ?? '');

            if ($userRole !== 'admin' && $actionType === 'Note') {
                throw new Exception("Access Denied: Only administrators can add manual notes.");
            }

            if (!$projectId || empty($description)) {
                throw new Exception("Missing project ID or description.");
            }

            $taskManager->addProjectActivityLog($projectId, $taskId, (int)$_SESSION['user_id'], $actionType, $description);
            echo json_encode(['status' => 'success', 'message' => 'Log added successfully.']);
            break;

        case 'get_project_alerts':
            if (!$projectId) throw new Exception("Missing project ID.");
            $result = $controller->getProjectAlerts($projectId);
            if ($result['status'] === 'error') {
                throw new Exception($result['message']);
            }
            echo json_encode($result);
            break;

        case 'get_project_notes':
            if ($userRole !== 'admin') throw new Exception("Access Denied: Only administrators can view project notes.");
            if (!$projectId) throw new Exception("Missing project ID.");
            $result = $controller->getProjectNotes($projectId);
            if ($result['status'] === 'error') {
                throw new Exception($result['message']);
            }
            echo json_encode($result);
            break;

        case 'complete_project':
            if (!$projectId) throw new Exception("Missing project ID.");
            $stmt = $pdo->prepare("UPDATE projects SET status = 'Completed' WHERE id = ?");
            $stmt->execute([$projectId]);
            echo json_encode(['status' => 'success', 'message' => 'Project marked as Completed.']);
            break;

        case 'update_project_status':
            if (strtolower($_SESSION['role'] ?? '') !== 'admin') throw new Exception("Access Denied");
            $newStatus = trim($_POST['status'] ?? '');
            if (!$projectId || empty($newStatus)) throw new Exception("Missing project ID or status.");
            
            $stmt = $pdo->prepare("UPDATE projects SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $projectId]);
            echo json_encode(['status' => 'success', 'message' => 'Project status updated successfully.']);
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