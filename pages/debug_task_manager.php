<?php
// ============================================================================
// ARCHIVO COMPLETAMENTE DESHABILITADO (COMENTADO VIRTUALMENTE)
// Se bloquea el acceso en la línea 1 para anular el archivo en producción.
// ============================================================================
die("Acceso Denegado: Este panel de debug ha sido desactivado permanentemente.");

// pages/debug_task_manager.php

require_once __DIR__ . '/../core/auth/session.php';
require_once __DIR__ . '/../core/db/connection.php';
require_once __DIR__ . '/../task_manager/Core/TimeEngine.php';

// Seguridad: Solo administradores pueden acceder a este panel de debug
if (($_SESSION['role'] ?? '') !== 'admin') {
    die("Unauthorized Access: Only administrators can access the Debug Panel.");
}

$msg = '';
$msgType = 'info';
$timeTestResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['debug_action'] ?? '';

    if ($action === 'simulate_close') {
        // Simulador de Cierre de Día (19:01)
        // En producción esto lo haría un CRON Job, aquí forzamos la BD
        $stmt = $pdo->query("UPDATE project_tasks SET status = 'System_Pause' WHERE status = 'Active'");
        $count = $stmt->rowCount();
        
        $msg = "<strong>[19:01] Cierre Simulado:</strong> {$count} tareas activas han sido puestas en 'System_Pause'.";
        $msgType = 'warning';
    } 
    elseif ($action === 'simulate_open') {
        // Simulador de Apertura de Día (07:00)
        $stmt = $pdo->query("UPDATE project_tasks SET status = 'Active' WHERE status = 'System_Pause'");
        $count = $stmt->rowCount();
        
        $msg = "<strong>[07:00] Apertura Simulada:</strong> {$count} tareas en pausa de sistema han vuelto a 'Active'.";
        $msgType = 'success';
    }
    elseif ($action === 'run_cron') {
        // Simulador de Cron Job Inteligente (Fase 22)
        $engine = new TimeEngine();
        $now = new DateTime();
        if ($engine->isTodayHoliday($now) || (int)$now->format('w') === 0) {
            $stmt = $pdo->query("UPDATE project_tasks SET status = 'System_Pause' WHERE status = 'Active'");
            $count = $stmt->rowCount();
            $msg = "<strong>[Cron] Día no laborable (Feriado/Domingo):</strong> {$count} tareas pausadas automáticamente.";
            $msgType = 'danger';
        } else {
            $hour = (int)$now->format('G');
            if ($hour >= 19 || $hour < 7) {
                $stmt = $pdo->query("UPDATE project_tasks SET status = 'System_Pause' WHERE status = 'Active'");
                $count = $stmt->rowCount();
                $msg = "<strong>[Cron] Fuera de horario (19:00 - 07:00):</strong> {$count} tareas pausadas por fin de jornada.";
                $msgType = 'warning';
            } else {
                $stmt = $pdo->query("UPDATE project_tasks SET status = 'Active' WHERE status = 'System_Pause'");
                $count = $stmt->rowCount();
                $msg = "<strong>[Cron] Horario Laborable (07:00 - 19:00):</strong> {$count} tareas reanudadas.";
                $msgType = 'success';
            }
        }
    }
    elseif ($action === 'test_time') {
        // Prueba visual del TimeEngine (Saltos de tiempo, domingos, feriados)
        $startDate = $_POST['start_date'] ?? date('Y-m-d H:i:s');
        $hours = (float)($_POST['hours'] ?? 24);
        
        try {
            $startObj = new DateTime($startDate);
            $engine = new TimeEngine();
            $endObj = $engine->calculateDeadline($startObj, $hours);
            
            $timeTestResult = [
                'start' => $startObj->format('l, M d, Y - H:i:s'),
                'hours' => $hours,
                'end' => $endObj->format('l, M d, Y - H:i:s')
            ];
            $msg = "Cálculo de salto de tiempo completado por el TimeEngine.";
            $msgType = 'info';
        } catch (Exception $e) {
            $msg = "Error parseando la fecha inicial.";
            $msgType = 'danger';
        }
    }
    elseif ($action === 'check_integrity') {
        // Validador de Integridad de Directorio
        $stmt = $pdo->query("
            SELECT pt.id, pt.name, pt.project_id, pt.assigned_user_id 
            FROM project_tasks pt 
            LEFT JOIN directory d ON pt.project_id = d.project_id AND pt.assigned_user_id = d.user_id 
            WHERE pt.assigned_user_id IS NOT NULL AND d.user_id IS NULL
        ");
        $orphans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($orphans)) {
            $msg = "<strong>Validación Superada:</strong> 0 tareas huérfanas. Todos los usuarios asignados existen estrictamente en el directorio de su respectivo proyecto.";
            $msgType = 'success';
        } else {
            $msg = "<strong>¡Fallo de Integridad Detectado!</strong> Se encontraron " . count($orphans) . " asignaciones inválidas que violan la Regla de Directorio:<br><ul class='mb-0 mt-2'>";
            foreach ($orphans as $o) {
                $msg .= "<li>Task ID: <strong>{$o['id']}</strong> ('{$o['name']}') en Project ID <strong>{$o['project_id']}</strong> tiene asignado al User ID <strong>{$o['assigned_user_id']}</strong> (No está en el directorio).</li>";
            }
            $msg .= "</ul>";
            $msgType = 'danger';
        }
    }
}

$pageTitle = "Smart PM - Debug Tools";
$pageTitle = "Task Manager - Debug Tools";
include __DIR__ . '/../views/header.php';
?>

<div class="main-content p-4 pt-5">
    <header class="header mb-4">
        <div class="d-flex align-items-center gap-3">
            <h2 class="fw-bold mb-0 text-white"><i class="fas fa-bug text-danger me-2"></i> Task Manager: Debug & Simulation</h2>
        </div>
    </header>

    <?php if(!empty($msg)): ?>
        <div class="alert alert-<?= $msgType ?> alert-dismissible fade show shadow-sm border-0" role="alert">
            <?= $msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- MÁQUINA DE ESTADOS (Cron Simulation) -->
        <div class="col-md-6">
            <div class="box-card h-100">
                <h5 class="fw-bold text-white mb-3"><i class="fas fa-power-off text-warning me-2"></i> Day Cycle Simulator</h5>
                <p class="text-gray small mb-4">Mueve manualmente las tareas entre estado activo y pausa del sistema para probar la persistencia del cronómetro de BD.</p>
                
                <div class="d-flex gap-3">
                    <form method="POST" class="flex-grow-1">
                        <input type="hidden" name="debug_action" value="simulate_close">
                        <button type="submit" class="btn btn-outline-warning w-100 fw-bold"><i class="fas fa-moon me-2"></i> Simular Cierre (19:01)</button>
                    </form>
                    <form method="POST" class="flex-grow-1">
                        <input type="hidden" name="debug_action" value="simulate_open">
                        <button type="submit" class="btn btn-outline-success w-100 fw-bold"><i class="fas fa-sun me-2"></i> Simular Apertura (07:00)</button>
                    </form>
                </div>
                <div class="mt-3">
                    <form method="POST">
                        <input type="hidden" name="debug_action" value="run_cron">
                        <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="fas fa-robot me-2"></i> Ejecutar Smart Cron Job (Auto-detect)</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- INTEGRITY VALIDATOR -->
        <div class="col-md-6">
            <div class="box-card h-100">
                <h5 class="fw-bold text-white mb-3"><i class="fas fa-shield-alt text-primary me-2"></i> Integrity Validator</h5>
                <p class="text-gray small mb-4">Escanea toda la tabla de <code>project_tasks</code> buscando violaciones de la Regla de Seguridad de Directorios.</p>
                
                <form method="POST">
                    <input type="hidden" name="debug_action" value="check_integrity">
                    <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="fas fa-search me-2"></i> Run Orphan Check</button>
                </form>
            </div>
        </div>

        <!-- TIME ENGINE TESTER -->
        <div class="col-md-6">
            <div class="box-card h-100">
                <h5 class="fw-bold text-white mb-3"><i class="fas fa-clock text-info me-2"></i> TimeEngine Rollover Test (+24H)</h5>
                <p class="text-gray small mb-4">Verifica visualmente cómo el algoritmo salta los feriados, noches y fines de semana para calcular el <code>expected_end_time</code> real.</p>
                
                <form method="POST" class="row g-3 align-items-end">
                    <input type="hidden" name="debug_action" value="test_time">
                    <div class="col-md-5">
                        <label class="text-gray small mb-1">Start Date & Time (Simulated)</label>
                        <input type="datetime-local" name="start_date" class="form-control bg-dark border-secondary text-white" value="<?= date('Y-m-d\TH:i') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="text-gray small mb-1">Hours to Sum</label>
                        <input type="number" name="hours" class="form-control bg-dark border-secondary text-white" value="24" step="0.5" required>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-info w-100 fw-bold text-dark"><i class="fas fa-calculator me-2"></i> Calculate</button>
                    </div>
                </form>

                <?php if($timeTestResult): ?>
                    <div class="mt-4 p-3 rounded" style="background: rgba(14, 165, 233, 0.1); border: 1px solid rgba(14, 165, 233, 0.3);">
                        <h6 class="text-info fw-bold mb-3">TimeEngine Results:</h6>
                        <div class="d-flex justify-content-between align-items-center text-white font-monospace small">
                            <span class="text-gray text-decoration-line-through"><?= $timeTestResult['start'] ?></span>
                            <i class="fas fa-arrow-right text-info mx-3"></i>
                            <span class="badge bg-info text-dark">+<?= $timeTestResult['hours'] ?> Working Hours</span>
                            <i class="fas fa-arrow-right text-info mx-3"></i>
                            <span class="text-success fw-bold fs-6 border-bottom border-success pb-1"><?= $timeTestResult['end'] ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- OVERDUE SIMULATOR -->
        <div class="col-md-6">
            <div class="box-card h-100">
                <h5 class="fw-bold text-white mb-3"><i class="fas fa-stopwatch text-danger me-2"></i> Overdue Simulator</h5>
                <p class="text-gray small mb-4">Fuerza a que todas las tareas activas de un proyecto específico lleguen a su tiempo límite en <strong>10 segundos</strong> para testear el flujo de vencimiento en el Task Manager.</p>
                
                <div class="row g-2 align-items-end mt-2">
                    <div class="col-md-4">
                        <label class="text-gray small mb-1">Project ID</label>
                        <input type="number" id="overdue_project_id" class="form-control bg-dark border-secondary text-white" value="1" min="1" required>
                    </div>
                    <div class="col-md-8">
                        <button type="button" class="btn btn-danger w-100 fw-bold" onclick="forceOverdueTest()">
                            <i class="fas fa-forward me-2"></i> Forzar Overdue (10s)
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function forceOverdueTest() {
    const projectId = document.getElementById('overdue_project_id').value; 
    
    if (!projectId) {
        alert('Por favor, ingresa un ID de proyecto válido.');
        return;
    }

    const fd = new FormData();
    fd.append('action', 'force_overdue_test');
    fd.append('project_id', projectId);

    fetch('../task_manager/api.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                alert('⏳ TEST INICIADO: Las tareas activas del Proyecto ' + projectId + ' vencerán en 10 segundos. ¡Ve rápido al Task Manager!');
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(e => console.error('Error de red:', e));
}
</script>

<?php include __DIR__ . '/../views/footer.php'; ?>