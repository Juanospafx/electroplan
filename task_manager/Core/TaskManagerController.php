<?php
// task_manager/Core/TaskManagerController.php

require_once __DIR__ . '/../TaskManager.php';
require_once __DIR__ . '/TimeEngine.php';

/**
 * Class TaskManagerController
 * 
 * Acts as the orchestrator for complex task operations, combining business logic
 * from TaskManager and time calculations from TimeEngine.
 */
class TaskManagerController {
    private TaskManager $taskManager;
    private TimeEngine $timeEngine;

    public function __construct(TaskManager $taskManager, TimeEngine $timeEngine) {
        $this->taskManager = $taskManager;
        $this->timeEngine = $timeEngine;
    }

    /**
     * Updates a task's status and triggers the state machine logic.
     *
     * @param int $taskId The ID of the task to update.
     * @param string $newStatus The new status to apply.
     * @param int $userId The ID of the user performing the action.
     * @param string|null $justificationNote A mandatory note for 'On_Hold' or 'Bypassed'.
     * @return array An array with operation status and a message.
     */
    public function updateTaskStatus(int $taskId, string $newStatus, int $userId, ?string $justificationNote = null): array {
        try {
            // 1. VALIDATION: Justification is mandatory for specific states.
            if (in_array($newStatus, ['On_Hold', 'Bypassed']) && empty($justificationNote)) {
                throw new InvalidArgumentException("A justification note is mandatory for 'On_Hold' or 'Bypassed' status.");
            }

            $task = $this->taskManager->getTask($taskId);
            if (!$task) {
                throw new Exception("Task with ID {$taskId} not found.");
            }
            $projectId = (int)$task['project_id'];
            $originalStatus = $task['status'];

            // FASE 20: Bloqueo Estricto de Tareas Vencidas
            if ($originalStatus === 'Overdue') {
                if (in_array($newStatus, ['On_Hold', 'Bypassed'])) {
                    throw new InvalidArgumentException("An overdue task can only be extended or completed.");
                }
                if ($newStatus === 'Completed' && empty(trim((string)$justificationNote))) {
                    throw new InvalidArgumentException("A justification note is mandatory when completing an overdue task.");
                }
            }

            $expectedEndTime = null;

            // Arrancar cronómetro si se activa
            if ($newStatus === 'Active') {
                $startTime = new DateTime();
                $deadline = $this->timeEngine->calculateDeadline($startTime, $task['estimated_hours']);
                $expectedEndTime = $deadline->format('Y-m-d H:i:s');
            }

            $success = $this->taskManager->updateTaskStatus($taskId, $projectId, $newStatus, $expectedEndTime);
            
            $response = ['status' => $success ? 'success' : 'error', 'message' => $success ? 'Status updated.' : 'Failed to update.'];
            
            if ($success) {
                // Guardar registro histórico y justificaciones
                $actionMap = [
                    'Active' => 'Started', 
                    'On_Hold' => 'Paused', 
                    'Completed' => 'Completed', 
                    'Bypassed' => 'Bypassed'
                ];
                $action = $actionMap[$newStatus] ?? 'Resumed';
                
                $this->taskManager->logTaskAction($taskId, $userId, $action, $justificationNote);

                // REGLA FASE 8: Lógica de Sucesión (Encadenamiento).
                if ($newStatus === 'Completed' || $newStatus === 'Bypassed') {
                    // FASE 17: Regla de Bypass en Frío
                    if ($newStatus === 'Bypassed' && $originalStatus === 'Pending') {
                        // Bypass de preparación: No activar la siguiente tarea automáticamente (la cascada aún no llegaba aquí)
                    } else {
                        if ($task['parent_task_id'] !== null) {
                            // Verificar si el padre de esta subtarea ya se completó
                            $this->taskManager->checkParentTaskCompletion((int)$task['parent_task_id']);
                        }

                        // FASE 17: Regla del Menor Órden Estricto (Fix RFI)
                        // Busca estrictamente la tarea Pending con el order más bajo de todo el proyecto.
                        $nextTask = $this->taskManager->getNextTask($projectId);

                        if (!empty($nextTask)) {
                            if ($nextTask['status'] === 'Pending') {
                                $this->updateTaskStatus((int)$nextTask['id'], 'Active', $userId, "Auto-started sequence after previous task was {$newStatus}.");
                            } elseif ($nextTask['status'] === 'Active') {
                                $response['next_task_status'] = 'Active';
                                $response['message'] = 'La siguiente tarea ya se encuentra corriendo.';
                            } elseif ($nextTask['status'] === 'On_Hold') {
                                $response['next_task_status'] = 'On_Hold';
                                $response['next_task_id'] = $nextTask['id'];
                                $response['message'] = 'La siguiente tarea está pausada.';
                            }
                        }
                    }
                }
            }

            return $response;

        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function createSingleSubtask(int $projectId, int $parentTaskId, string $name, float $estimatedHours, ?int $assignedUserId, int $actorUserId): array {
        try {
            $taskId = $this->taskManager->createSubTask($projectId, $parentTaskId, $name, $estimatedHours, $assignedUserId);
            // Log against the parent task that it has been extended with a sub-task
            $this->taskManager->logTaskAction($parentTaskId, $actorUserId, 'Extended', "Sub-task '{$name}' created.");
            return ['status' => 'success', 'message' => 'Sub-task created.', 'task_id' => $taskId];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function applyRfiTemplate(int $projectId, int $parentTaskId, int $rfiTemplateId, int $actorUserId): array {
        try {
            $this->taskManager->applyRfiTemplateToTask($projectId, $parentTaskId, $rfiTemplateId);
            
            $rfiTemplate = $this->taskManager->getTemplate($rfiTemplateId);
            $rfiName = $rfiTemplate ? $rfiTemplate['name'] : "RFI #{$rfiTemplateId}";
            
            // Log against the parent task that it has been extended with an RFI block
            $this->taskManager->logTaskAction($parentTaskId, $actorUserId, 'Extended', "RFI block '{$rfiName}' was inserted.");
            return ['status' => 'success', 'message' => 'RFI block inserted successfully.'];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function resetProjectTasks(int $projectId, int $userId, string $justificationNote): array {
        try {
            if (empty(trim($justificationNote))) {
                throw new InvalidArgumentException("A justification note is strictly required to reset tasks.");
            }

            // 1. Auditoría: Dejar rastro en las notas del proyecto
            $date = date('Y-m-d H:i');
            $auditMessage = "\n[{$date}] Tareas reseteadas por el usuario ID {$userId}. Razón: {$justificationNote}";
            $this->taskManager->appendProjectNote($projectId, $auditMessage);
            
            // 2. Acción destructiva controlada
            $this->taskManager->resetProjectTasks($projectId);

            return ['status' => 'success', 'message' => 'Project tasks have been reset successfully.'];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}