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
     * @param bool $autoStartNext Toggle manual para auto-iniciar la siguiente tarea de la cadena.
     * @param bool $forceOvertime Permite forzar el inicio de una tarea fuera del horario laboral.
     * @return array An array with operation status and a message.
     */
    public function updateTaskStatus(int $taskId, string $newStatus, int $userId, ?string $justificationNote = null, bool $autoStartNext = false, bool $forceOvertime = false): array {
        try {
            // 1. VALIDATION: Justification is mandatory for specific states.
            if (in_array($newStatus, ['On_Hold', 'Bypassed', 'Completed', 'Completed_Late']) && empty(trim((string)$justificationNote))) {
                throw new InvalidArgumentException("A justification note is mandatory for 'On_Hold', 'Bypassed', or 'Completed' status.");
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
                    throw new InvalidArgumentException("An overdue task can only be extended or marked as completed late.");
                }
            }

            // FASE 90: Restricción de Concurrencia de Tareas (Máx. 3 Activas)
            if ($newStatus === 'Active' && $originalStatus !== 'Active') {
                $targetUserId = $task['assigned_user_id'] !== null ? (int)$task['assigned_user_id'] : $userId;
                $activeCount = $this->taskManager->countUserActiveTasks($projectId, $targetUserId);
                if ($activeCount >= 3) {
                    throw new Exception("Limit reached: You cannot have more than 3 active tasks at the same time. Pause or complete one before continuing.");
                }
            }

            $workStart = $task['work_start_time'] ?? '07:00:00';
            $workEnd = $task['work_end_time'] ?? '19:00:00';

            // FASE 50: Advertencia de Excepción (Horas Extras / Fuera de Horario)
            if ($newStatus === 'Active' && !$forceOvertime) {
                if (!$this->timeEngine->isWorkingHour(null, $workStart, $workEnd)) {
                    return ['status' => 'confirm_overtime', 'message' => 'You are trying to start a task outside of working hours.'];
                }
            }

            $expectedEndTime = null;

            // Arrancar cronómetro si se activa
            if ($newStatus === 'Active') {
                $startTime = new DateTime();
                $minutos = isset($task['estimated_minutes']) ? (int)$task['estimated_minutes'] : 0;
                
                // FASE 77: Restar el tiempo ya trabajado para reanudar el cronómetro exactamente donde se quedó.
                $worked = isset($task['worked_minutes']) ? (int)$task['worked_minutes'] : 0;
                $remaining = $minutos - $worked;
                if ($remaining <= 0) $remaining = 1; // Para que expire de inmediato y pase a Overdue
                
                $deadline = $this->timeEngine->calculateDeadline($startTime, $remaining, $workStart, $workEnd);
                $expectedEndTime = $deadline->format('Y-m-d H:i:s');
            }

            $success = $this->taskManager->updateTaskStatus($taskId, $projectId, $newStatus, $expectedEndTime);
            
            $response = ['status' => $success ? 'success' : 'error', 'message' => $success ? 'Status updated.' : 'Failed to update.'];
            
            if ($success) {
                // Guardar registro histórico y justificaciones
                $actionMap = [
                    'Active' => ($originalStatus === 'Pending') ? 'Started' : 'Resumed', 
                    'On_Hold' => 'Paused', 
                    'System_Pause' => 'Paused',
                    'Completed' => 'Completed', 
                    'Completed_Late' => 'Completed_Late', 
                    'Bypassed' => 'Bypassed',
                    'Overdue' => 'Paused' // FASE 64: Overdue detiene el tiempo trabajado
                ];
                $action = $actionMap[$newStatus] ?? 'Resumed';
                
                $this->taskManager->logTaskAction($taskId, $userId, $action, $justificationNote);

                // FASE 64: Inyección Automática en Project Activity Logs
                if ($newStatus === 'On_Hold') {
                    $this->taskManager->addProjectActivityLog($projectId, $taskId, $userId, 'Hold', $justificationNote ?? 'Task put on hold.');
                } elseif ($newStatus === 'Overdue') {
                    $this->taskManager->addProjectActivityLog($projectId, $taskId, $userId, 'Overdue', $justificationNote ?? 'System Auto-marked as Overdue.');
                } elseif (!empty($justificationNote)) {
                    // Log other actions with justifications like Completed, Bypassed
                    $this->taskManager->addProjectActivityLog($projectId, $taskId, $userId, $newStatus, $justificationNote);
                }

                // REGLA FASE 8: Lógica de Sucesión (Encadenamiento).
                if ($newStatus === 'Completed' || $newStatus === 'Completed_Late' || $newStatus === 'Bypassed') {
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

                        if (empty($nextTask)) {
                            // FASE 78: El proyecto llegó al 100% de tareas completadas
                            $response['is_project_completed'] = true;
                        } else {
                            if ($nextTask['status'] === 'Pending') {
                                // FASE 91: Control Manual de Auto-Sucesión
                                $nextTaskFull = $this->taskManager->getTask((int)$nextTask['id']);
                                $nextStart = $nextTaskFull['work_start_time'] ?? '07:00:00';
                                $nextEnd = $nextTaskFull['work_end_time'] ?? '19:00:00';

                                if ($autoStartNext) {
                                    $targetUserId = $task['assigned_user_id'] !== null ? (int)$task['assigned_user_id'] : $userId;
                                    $activeCountAfter = $this->taskManager->countUserActiveTasks($projectId, $targetUserId);
                                    if ($activeCountAfter > 0) {
                                        $response['next_task_status'] = 'Already_Running_Somewhere';
                                        $response['message'] .= ' (The next task was not auto-started because another is already in progress).';
                                    } else {
                                        if ($this->timeEngine->isWorkingHour(null, $nextStart, $nextEnd)) {
                                            $this->updateTaskStatus((int)$nextTask['id'], 'Active', $userId, "Auto-started sequence after previous task was {$newStatus}.", false);
                                        } else {
                                            $response['message'] .= ' (Next task not started automatically because it\'s outside working hours).';
                                        }
                                    }
                                } else {
                                    $response['message'] .= ' (Auto-succession is manually disabled).';
                                }
                            } elseif ($nextTask['status'] === 'Active') {
                                $response['next_task_status'] = 'Active';
                                $response['message'] = 'The next task is already running.';
                            } elseif ($nextTask['status'] === 'On_Hold') {
                                $response['next_task_status'] = 'On_Hold';
                                $response['next_task_id'] = $nextTask['id'];
                                $response['message'] = 'The next task is paused.';
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

    public function createSingleSubtask(int $projectId, int $parentTaskId, string $name, int $estimatedMinutes, ?int $assignedUserId, int $actorUserId): array {
        try {
            $taskId = $this->taskManager->createSubTask($projectId, $parentTaskId, $name, $estimatedMinutes, $assignedUserId);
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
            // FASE 66: Inyección en Activity Logs
            $this->taskManager->addProjectActivityLog($projectId, null, $userId, 'Reset', "Project tasks reset. Reason: {$justificationNote}");
            
            // 2. Acción destructiva controlada
            $this->taskManager->resetProjectTasks($projectId);

            return ['status' => 'success', 'message' => 'Project tasks have been reset successfully.'];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * FASE 37: Project Health API
     * Calcula un panorama macro del proyecto sumando tareas, horas trabajadas y 
     * proyectando la fecha final de entrega usando el TimeEngine.
     */
    public function getProjectHealthSummary(int $projectId, int $userId = 0, string $role = 'admin'): array {
        try {
            $stages = $this->taskManager->getProjectStagesAndTasks($projectId, $userId, $role);
            
            $totalTasks = 0;
            $completedTasks = 0;
            $hoursWorked = 0.0;
            $remainingMinutes = 0;

            // AUDITORÍA 3 (Anti N+1): Obtenemos todas las horas del proyecto pre-calculadas en un arreglo.
            $actualHoursMap = $this->taskManager->getProjectActualHours($projectId, $userId, $role);

            $processTask = function($task) use (&$totalTasks, &$completedTasks, &$hoursWorked, &$remainingMinutes, $actualHoursMap) {
                $totalTasks++;
                if (in_array($task['status'], ['Completed', 'Completed_Late', 'Bypassed'])) {
                    $completedTasks++;
                }

                // Búsqueda en caché array O(1)
                $actualHours = $actualHoursMap[(int)$task['id']] ?? 0.0;
                $hoursWorked += $actualHours;

                if (in_array($task['status'], ['Pending', 'On_Hold'])) {
                    $remainingMinutes += (int)$task['estimated_minutes'];
                } elseif (in_array($task['status'], ['Active', 'System_Pause', 'Overdue'])) {
                    $rem = (int)$task['estimated_minutes'] - (int)round($actualHours * 60);
                    if ($rem > 0) {
                        $remainingMinutes += $rem;
                    }
                }
            };

            foreach ($stages as $stage) {
                foreach ($stage['tasks'] as $task) {
                    $processTask($task);
                    if (!empty($task['sub_tasks'])) {
                        foreach ($task['sub_tasks'] as $sub) {
                            $processTask($sub);
                        }
                    }
                }
            }

            $now = new DateTime();
            $estimatedEndDate = null;
            $weekendsSkipped = 0;
            $holidaysSkipped = 0;
            $workingDaysNeeded = 0;
            
            if ($remainingMinutes > 0) {
                $workingDaysNeeded = round($remainingMinutes / (12 * 60), 1); // 12h shifts
                $deadline = $this->timeEngine->calculateDeadline($now, $remainingMinutes);
                $estimatedEndDate = $deadline->format('d M, Y - H:i');
                
                // Contar días saltados (Transparencia FASE 43)
                $tempDate = clone $now;
                $tempDate->setTime(0, 0, 0);
                $endClone = clone $deadline;
                $endClone->setTime(0, 0, 0);
                
                while ($tempDate < $endClone) {
                    $tempDate->modify('+1 day');
                    if ((int)$tempDate->format('w') === 0) {
                        $weekendsSkipped++;
                    } elseif ($this->timeEngine->isTodayHoliday($tempDate)) {
                        $holidaysSkipped++;
                    }
                }
            } else {
                $estimatedEndDate = $now->format('d M, Y - H:i'); // Terminado o sin tiempo
            }

            return [
                'status' => 'success',
                'data' => [
                    'total_tasks' => $totalTasks,
                    'completed_tasks' => $completedTasks,
                    'hours_worked' => round($hoursWorked, 2),
                    'hours_remaining' => round($remainingMinutes / 60, 2),
                    'working_days_needed' => $workingDaysNeeded,
                    'weekends_skipped' => $weekendsSkipped,
                    'holidays_skipped' => $holidaysSkipped,
                    'project_estimated_end_date' => $estimatedEndDate
                ]
            ];

        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * FASE 45: Master View de Proyectos y Health Data
     */
    public function getAllProjectsHealthSummary(int $userId = 0, string $role = 'admin'): array {
        try {
            $projects = $this->taskManager->getActiveProjects($userId, $role);
            
            $results = [];
            foreach ($projects as $p) {
                $health = $this->getProjectHealthSummary((int)$p['id'], $userId, $role);
                if ($health['status'] === 'success') {
                    $results[] = [
                        'project_id' => $p['id'],
                        'project_name' => $p['name'],
                        'project_status' => $p['status'] ?? 'Active',
                        'health' => $health['data']
                    ];
                }
            }
            
            // Ordenar los proyectos:
            // 1. Proyectos con tareas (Smart PM iniciado) van primero.
            // 2. Proyectos sin tareas van después.
            // 3. Proyectos COMPLETADOS van de último lugar.
            usort($results, function($a, $b) {
                $a_completed = (($a['project_status'] ?? '') === 'Completed');
                $b_completed = (($b['project_status'] ?? '') === 'Completed');

                if ($a_completed && !$b_completed) return 1;
                if (!$a_completed && $b_completed) return -1;

                $a_has_tasks = ($a['health']['total_tasks'] ?? 0) > 0;
                $b_has_tasks = ($b['health']['total_tasks'] ?? 0) > 0;

                if ($a_has_tasks && !$b_has_tasks) return -1;
                if (!$a_has_tasks && $b_has_tasks) return 1;

                if ($a_has_tasks && $b_has_tasks) {
                    $a_completed = $a['health']['completed_tasks'] ?? 0;
                    $b_completed = $b['health']['completed_tasks'] ?? 0;
                    if ($a_completed !== $b_completed) {
                        return $b_completed <=> $a_completed;
                    }
                }
                return strcmp($a['project_name'], $b['project_name']);
            });
            
            return ['status' => 'success', 'data' => $results];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * FASE 58: Endpoint Global de Tareas Activas (Live Radar)
     */
    public function getGlobalActiveTasks(int $userId = 0, string $role = 'admin'): array {
        try {
            $tasks = $this->taskManager->getGlobalActiveTasks($userId, $role);
            return ['status' => 'success', 'data' => $tasks];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function getProjectAlerts(int $projectId): array {
        try {
            $logs = $this->taskManager->getProjectAlertLogs($projectId);
            return ['status' => 'success', 'data' => $logs];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function getProjectNotes(int $projectId): array {
        try {
            $logs = $this->taskManager->getProjectNotesLogs($projectId);
            return ['status' => 'success', 'data' => $logs];
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}