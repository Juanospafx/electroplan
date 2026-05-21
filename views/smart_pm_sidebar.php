<?php
// views/smart_pm_sidebar.php
?>
<style>
    /* --- FASE 51: Rediseño UX - Animación Off-Canvas del Smart PM --- */
    .smart-pm-sidebar {
        position: fixed;
        top: 0;
        right: 0;
        transform: translateX(100%);
        width: 45vw;
        height: 100vh;
        background: var(--bg-card);
        border-left: 1px solid var(--border-subtle);
        z-index: 1050;
        box-shadow: -10px 0 30px rgba(0,0,0,0.1);
        transition: transform 0.3s ease-in-out, width 0.3s ease-in-out;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    body.smart-pm-active .smart-pm-sidebar {
        transform: translateX(0);
    }

    /* --- FASE 51: COMPORTAMIENTO MÓVIL --- */
    @media (max-width: 768px) {
        .smart-pm-sidebar {
            width: 100vw;
        }
    }

    .spm-header {
        background: var(--bg-card);
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-subtle);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-shrink: 0;
    }

    .spm-close-btn {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    .spm-close-btn:hover {
        background: #ef4444;
        color: white;
        transform: rotate(90deg);
    }

    .spm-body {
        padding: 1.5rem;
        overflow-y: auto;
        flex-grow: 1;
        /* Renderizado en Cascada (Línea de tiempo) */
        position: relative;
    }
    

    /* --- RENDERIZADO EN CASCADA (CSS DINÁMICO) --- */
    .task-card {
        position: relative;
        background: var(--bg-card);
        border: 1px solid var(--border-subtle);
        border-radius: 12px;
        padding: 1rem 1.2rem;
        margin-bottom: 1rem;
        margin-left: 3rem;
        z-index: 1;
        transition: all 0.3s ease;
    }
    
    /* Círculo conector de la línea de tiempo */
    .task-card::before {
        content: '';
        position: absolute;
        top: 1.2rem;
        left: -1.75rem;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: var(--bg-card);
        border: 2px solid var(--border-subtle);
    }

    /* 1. Completed / Bypassed */
    .task-completed {
        opacity: 0.65;
        background: rgba(255, 255, 255, 0.02);
        cursor: pointer;
    }
    .task-completed::before { background: #10b981; border-color: #10b981; box-shadow: 0 0 8px rgba(16, 185, 129, 0.4); }
    .task-completed .task-body { display: none; } /* Colapsable */
    .task-completed.expanded .task-body { display: block; }
    .task-completed.expanded { opacity: 0.9; background: rgba(255, 255, 255, 0.04); }

    /* FASE 40: Indicador Visual */
    .status-dot {
        border-radius: 50%;
        width: 10px;
        height: 10px;
        display: inline-block;
        margin-right: 5px;
    }
    .status-dot.green {
        background-color: #28a745;
    }

    /* 2. Active */
    .task-active {
        border-left: 4px solid var(--primary) !important;
        box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        transform: scale(1.02);
        background: rgba(251, 90, 58, 0.03); /* Ligero tinte naranja */
    }
    .task-active::before { background: var(--primary); border-color: var(--primary); box-shadow: 0 0 0 4px rgba(251, 90, 58, 0.2); }
    
    .countdown-timer {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
        padding: 4px 10px;
        border-radius: 6px;
        font-family: monospace;
        font-weight: bold;
        font-size: 0.9rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    /* 3. Pending */
    .task-pending {
        opacity: 0.7;
        border-style: dashed;
        background: transparent;
    }
    .task-pending::before { background: var(--text-muted); border-color: var(--text-muted); }

    /* FASE 29: Conector Visual para Subtareas */
    .subtask-card { margin-left: 3rem; position: relative; }

    /* FASE 29: Acordeones por Etapa */
    .stage-header { cursor: pointer; transition: background 0.2s; border-radius: 8px; padding: 10px 15px; margin-top: 1rem; border: 1px solid transparent; }
    .stage-header:hover { background: rgba(255,255,255,0.05); border-color: var(--border-subtle); }
    .stage-content { display: block; overflow: hidden; transition: max-height 0.3s ease; }

    /* Botón RFI Flotante */
    .btn-insert-rfi {
        margin-left: 3rem;
        margin-bottom: 1rem;
        font-size: 0.8rem;
        font-weight: bold;
        border-radius: 20px;
        padding: 4px 12px;
        background: var(--bg-input);
        color: var(--color-amber);
        border: 1px dashed var(--color-amber);
        transition: 0.2s;
        position: relative;
        z-index: 1;
    }
    .btn-insert-rfi:hover {
        background: var(--color-amber);
        color: white;
    }

    .task-action-buttons {
        margin-left: 3rem;
        margin-bottom: 1rem;
        display: flex;
        gap: 0.5rem;
        position: relative;
        z-index: 1;
    }
    .task-action-buttons button {
        font-size: 0.8rem; font-weight: bold; border-radius: 20px; padding: 4px 12px;
        border: 1px dashed; transition: 0.2s;
    }
    .btn-insert-subtask { background: var(--bg-input); color: var(--primary); border-color: var(--primary); }
    .btn-insert-subtask:hover { background: var(--primary); color: white; }

        /* --- FASE 73: Jerarquía Visual Geométrica --- */
    .task-card.shape-main::before {
        border-radius: 50%; /* Círculo perfecto (predeterminado) */
    }
    .task-card.shape-subtask::before {
        width: 12px; height: 12px; 
        border-radius: 2px; /* Cuadrado con bordes suaves */
        left: -1.7rem; top: 1.3rem;
        background-color: #3b82f6 !important; /* Azul siempre visible */
        border: none !important;
    }
    .task-card.shape-rfi::before {
        width: 16px; height: 14px; 
        left: -1.8rem; top: 1.3rem;
        clip-path: polygon(50% 0%, 0% 100%, 100% 100%); /* Triángulo de advertencia */
        border-radius: 0;
        background-color: #eab308 !important; /* Amarillo siempre visible */
        border: none !important;
    }
    
    /* Pasan a verde si se completan o se les hace bypass */
    .task-card.task-completed.shape-main::before,
    .task-card.task-completed.shape-subtask::before,
    .task-card.task-completed.shape-rfi::before {
        background-color: #10b981 !important; 
        border-color: #10b981 !important;
        box-shadow: 0 0 8px rgba(16, 185, 129, 0.4) !important;
    }
    
    .task-card.task-completed-late.shape-main::before,
    .task-card.task-completed-late.shape-subtask::before,
    .task-card.task-completed-late.shape-rfi::before {
        background-color: #ef4444 !important; 
        border-color: #ef4444 !important;
        box-shadow: 0 0 8px rgba(239, 68, 68, 0.4) !important;
    }
    
    .task-completed-late {
        background: rgba(239, 68, 68, 0.05) !important;
        border-color: rgba(239, 68, 68, 0.3) !important;
    }
    .task-completed-late.expanded {
        background: rgba(239, 68, 68, 0.1) !important;
    }
    body.theme-light .task-completed-late { background: rgba(239, 68, 68, 0.05) !important; border-color: rgba(239, 68, 68, 0.4) !important; }
    body.theme-light .task-completed-late.expanded { background: rgba(239, 68, 68, 0.1) !important; }
    
    .task-card.task-active.shape-rfi {
        border-left-color: #eab308 !important;
        background: rgba(234, 179, 8, 0.03);
    }
    .task-card.task-active.shape-subtask {
        border-left-color: #3b82f6 !important;
        background: rgba(59, 130, 246, 0.03);
    }

     /* --- THEME LIGHT OVERRIDES (Contraste y Legibilidad) --- */
    body.theme-light .smart-pm-sidebar { background: #f8fafc; }
    body.theme-light .spm-header { background: #ffffff; }
    body.theme-light .task-card { background: #ffffff; box-shadow: 0 4px 12px rgba(15,23,42,0.04); }
    body.theme-light .task-card::before { background: #ffffff; }
    body.theme-light .task-completed { background: #f1f5f9; }
    body.theme-light .task-completed.expanded { background: #e2e8f0; }
    body.theme-light .stage-header:hover { background: rgba(15,23,42,0.05); }
    
    /* Modales e Inputs (Cuadros de texto, selects y textareas) */
    body.theme-light .form-control.bg-dark,
    body.theme-light .form-select.bg-dark,
    body.theme-light textarea.bg-dark {
        background-color: #ffffff !important;
        color: #0f172a !important;
        border-color: #cbd5e1 !important;
    }
    body.theme-light .form-control.bg-dark:focus,
    body.theme-light .form-select.bg-dark:focus,
    body.theme-light textarea.bg-dark:focus {
        border-color: var(--primary) !important;
        box-shadow: 0 0 0 0.25rem rgba(99, 102, 241, 0.15) !important;
    }
    body.theme-light .list-group-item.bg-dark {
        background-color: #ffffff !important;
        color: #0f172a !important;
        border-color: #e2e8f0 !important;
    }
    
    /* Botones y Badges específicos */
    body.theme-light .btn-outline-primary.bg-dark { background-color: #ffffff !important; }
    body.theme-light .btn-outline-secondary { color: #475569; border-color: #cbd5e1; }
    body.theme-light .btn-outline-secondary:hover { background-color: #e2e8f0; color: #0f172a; }
    body.theme-light .badge.bg-dark.border-secondary { background-color: #e2e8f0 !important; color: #475569 !important; border-color: #cbd5e1 !important; }
    
    /* Componentes del Reporte y Notas de Auditoría */
    body.theme-light .stat-box.bg-dark { background-color: #f1f5f9 !important; border: 1px solid #e2e8f0; }
    body.theme-light .table-dark { color: #0f172a; background-color: transparent; }
    body.theme-light .table-dark th, body.theme-light .table-dark td { border-color: #e2e8f0 !important; color: #0f172a !important; }
    body.theme-light .table-dark thead th { background-color: #f1f5f9 !important; }
    body.theme-light #spmNotesPanel { background-color: #f8fafc !important; }
    body.theme-light #spmNotesContainer > div { background-color: #ffffff !important; border-color: #e2e8f0 !important; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    
    /* Modal Asignación en Cascada y Estado Vacío */
    body.theme-light .stage-assignment-group > div:first-child { background-color: #f1f5f9 !important; border-bottom: 1px solid #e2e8f0; }
    body.theme-light .border-secondary-subtle { border-color: #e2e8f0 !important; }
    body.theme-light .bg-card { background-color: #ffffff !important; }
    
    /* Filter Bar */
    .spm-filter-bar::-webkit-scrollbar { display: none; }
    .spm-filter-bar { -ms-overflow-style: none; scrollbar-width: none; }
    
</style>


<!-- SIDEBAR COMPONENT -->
<aside id="smartPmSidebar" class="smart-pm-sidebar">
    <!-- FASE 38: Rediseño del Header del Smart PM -->
    <div class="spm-header" style="flex-direction: column; align-items: stretch; gap: 1rem;">
        <div class="d-flex align-items-center justify-content-between w-100">
            <div class="d-flex align-items-center gap-3">
                <button class="spm-close-btn flex-shrink-0" onclick="closeSmartPM()" title="Close Task Manager">
                    <i class="fas fa-times"></i>
                </button>
                <div>
                    <h5 class="fw-bold mb-0 text-white"><i class="fas fa-project-diagram text-warning me-2"></i> Task Manager</h5>
                    <small class="text-gray text-uppercase" style="letter-spacing: 1px;">Workflow</small>
                </div>
            </div>
        </div>

        <div class="smart-pm-header d-flex justify-content-between align-items-center w-100 flex-wrap gap-3">
            <!-- Panel Izquierdo (Métricas) -->
            <div class="spm-metrics d-flex flex-column" style="gap: 4px;" id="spmHealthContainer">
                <span class="text-gray small"><i class="fas fa-spinner fa-spin me-1"></i> Loading health data...</span>
            </div>

            <!-- Panel Derecho (Toolbar de Acciones) -->
            <div class="smart-pm-toolbar d-flex flex-column" style="gap: 8px; width: 100%; max-width: 200px; align-items: flex-end;">
                <!-- Fila 2: Acción Primaria -->
                <div class="d-flex gap-2 w-100">
                    <?php if(($_SESSION['role'] ?? '') === 'admin'): ?>
                    <button class="btn btn-sm btn-primary flex-grow-1 rounded-pill fw-bold" onclick="openProjectNotesPanel()" title="Audit Log & Notes">
                        <i class="fas fa-clipboard-list me-1"></i> Notes
                    </button>
                    <button class="btn btn-sm btn-outline-success flex-grow-1 rounded-pill fw-bold" onclick="exportProjectToCSV()" title="Export Project as CSV Template">
                        <i class="fas fa-download me-1"></i> CSV
                    </button>
                    <?php endif; ?>
                </div>
                <!-- Fila 3: Acción Peligro (Movido) -->
                <?php if(($_SESSION['role'] ?? '') === 'admin'): ?>
                <div id="spmDangerZone" class="w-100" style="display: none;">
                    <button id="btn-danger-reset" class="btn btn-sm btn-outline-danger w-100 rounded-pill fw-bold border-danger" onclick="openResetTasksModal()" title="Reset Project Tasks">
                        <i class="fas fa-trash-alt me-1"></i> Reset
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- FASE 89: SMART FILTER BAR (Sticky) -->
    <div class="spm-filter-bar d-flex justify-content-center flex-wrap align-items-center gap-2 px-3 py-2 border-bottom" style="background: var(--bg-card); border-color: var(--border-subtle); flex-shrink: 0; z-index: 1040; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
        <button id="btnToggleCompleted" class="btn btn-sm btn-success rounded-pill flex-shrink-0 text-white" onclick="toggleCompletedTasks()" title="Toggle Completed Tasks">
            <i class="fas fa-eye"></i>
        </button>
        <button id="btnToggleCollapseAll" class="btn btn-sm btn-outline-secondary rounded-pill flex-shrink-0" onclick="toggleCollapseAll()" title="Collapse All">
            <i class="fas fa-compress-arrows-alt"></i>
        </button>
        <div class="vr bg-secondary mx-1 opacity-25"></div>
        <button class="btn btn-sm btn-primary text-white rounded-pill flex-shrink-0 spm-filter-btn" data-filter="all" onclick="setSpmFilter('all')">All</button>
        <button class="btn btn-sm btn-outline-secondary rounded-pill flex-shrink-0 spm-filter-btn" data-filter="active" onclick="setSpmFilter('active')"><span class="status-dot bg-primary"></span>Active</button>
        <button class="btn btn-sm btn-outline-secondary rounded-pill flex-shrink-0 spm-filter-btn" data-filter="pending" onclick="setSpmFilter('pending')"><span class="status-dot bg-secondary"></span>Pending</button>
        <button class="btn btn-sm btn-outline-secondary rounded-pill flex-shrink-0 spm-filter-btn" data-filter="hold" onclick="setSpmFilter('hold')"><span class="status-dot bg-warning"></span>Hold</button>
        <button class="btn btn-sm btn-outline-secondary rounded-pill flex-shrink-0 spm-filter-btn" data-filter="overdue" onclick="setSpmFilter('overdue')"><span class="status-dot bg-danger"></span>Overdue</button>
        <button class="btn btn-sm btn-outline-secondary rounded-pill flex-shrink-0 spm-filter-btn" data-filter="completed" onclick="setSpmFilter('completed')"><span class="status-dot bg-success"></span>Completed</button>
        <div class="vr bg-secondary mx-1 opacity-25"></div>
        <button class="btn btn-sm btn-outline-secondary rounded-pill flex-shrink-0 spm-filter-btn" data-filter="subtask" onclick="setSpmFilter('subtask')"><i class="fas fa-square text-primary me-1" style="font-size:0.6rem"></i>Sub-tasks</button>
        <button class="btn btn-sm btn-outline-secondary rounded-pill flex-shrink-0 spm-filter-btn" data-filter="rfi" onclick="setSpmFilter('rfi')"><i class="fas fa-exclamation-triangle text-warning me-1" style="font-size:0.6rem"></i>RFIs</button>
    </div>

    <div class="spm-body" id="spmTaskContainer">
        <div class="text-center text-gray py-5 mt-5">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p class="mt-3">Loading Task Manager data...</p>
        </div>
    </div>

    <!-- FASE 66: PANEL DE AUDITORÍA Y NOTAS -->
    <div id="spmNotesPanel" class="d-none" style="padding: 1.5rem; flex-grow: 1; flex-direction: column; overflow: hidden; background: var(--bg-card);">
        <div class="mb-3 flex-shrink-0 d-flex justify-content-between align-items-center">
            <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-bold" onclick="closeProjectNotesPanel()"><i class="fas fa-arrow-left me-1"></i> Back</button>
            <h6 class="fw-bold text-white mb-0"><i class="fas fa-clipboard-list text-primary me-2"></i> Audit & Notes</h6>
        </div>
        
        <!-- FASE 74 & 102: Filtros de Categoría Estilo Task Manager -->
        <div class="mb-3 flex-shrink-0 d-flex justify-content-center flex-wrap align-items-center gap-2 px-1 py-2 border-bottom border-secondary">
            <button class="btn btn-sm btn-primary text-white fw-bold rounded-pill flex-shrink-0 filter-note-btn" data-filter="all" onclick="filterProjectNotes('all', this)">All</button>
            <div class="vr bg-secondary mx-1 opacity-25"></div>
            <button class="btn btn-sm btn-outline-secondary rounded-pill flex-shrink-0 filter-note-btn" data-filter="notes" onclick="filterProjectNotes('notes', this)"><i class="fas fa-sticky-note me-1" style="font-size:0.6rem"></i>Notes</button>
            <button class="btn btn-sm btn-outline-secondary rounded-pill flex-shrink-0 filter-note-btn" data-filter="active" onclick="filterProjectNotes('active', this)"><i class="fas fa-circle me-1" style="font-size:0.6rem"></i>Active</button>
            <button class="btn btn-sm btn-outline-secondary rounded-pill flex-shrink-0 filter-note-btn" data-filter="hold" onclick="filterProjectNotes('hold', this)"><i class="fas fa-circle me-1" style="font-size:0.6rem"></i>Hold</button>
            <button class="btn btn-sm btn-outline-secondary rounded-pill flex-shrink-0 filter-note-btn" data-filter="overdue" onclick="filterProjectNotes('overdue', this)"><i class="fas fa-circle me-1" style="font-size:0.6rem"></i>Overdue</button>
            <button class="btn btn-sm btn-outline-secondary rounded-pill flex-shrink-0 filter-note-btn" data-filter="completed" onclick="filterProjectNotes('completed', this)"><i class="fas fa-circle me-1" style="font-size:0.6rem"></i>Completed</button>
            <div class="vr bg-secondary mx-1 opacity-25"></div>
            <button class="btn btn-sm btn-outline-secondary rounded-pill flex-shrink-0 filter-note-btn" data-filter="rfi" onclick="filterProjectNotes('rfi', this)"><i class="fas fa-exclamation-triangle me-1" style="font-size:0.6rem"></i>RFIs</button>
        </div>

        <div id="spmNotesContainer" class="flex-grow-1 overflow-auto mb-3 pe-2">
            <!-- Se inyectarán los mensajes aquí -->
        </div>
        <div class="mt-auto border-top border-secondary pt-3 flex-shrink-0">
            <form id="spmAddNoteForm" onsubmit="submitProjectNote(event)">
                <div class="input-group">
                    <textarea id="spmNewNoteText" class="form-control bg-dark border-secondary text-white" rows="2" placeholder="Write a manual note or justification..." required style="resize: none;"></textarea>
                    <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="fas fa-paper-plane"></i></button>
                </div>
            </form>
        </div>
    </div>
</aside>

<!-- Modal para Editar Tarea -->
<div class="modal fade" id="editTaskModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-edit text-primary me-2"></i>Edit Task Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editTaskForm">
                <input type="hidden" id="edit_task_id" name="task_id" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="text-gray small mb-2">Task Name</label>
                        <input type="text" id="edit_task_name" name="name" class="form-control bg-dark border-secondary text-white" required>
                    </div>
                    <div class="mb-3">
                        <label class="text-gray small mb-2">Estimated Hours</label>
                        <input type="number" step="0.5" min="0.5" id="edit_task_hours" name="estimated_hours" class="form-control bg-dark border-secondary text-white" required>
                    </div>
                    <div class="mb-3">
                        <label class="text-gray small mb-2">Assign To</label>
                        <select id="edit_task_assignee" name="assigned_user_id" class="form-select bg-dark border-secondary text-white">
                            <option value="">-- Unassigned --</option>
                        </select>
                        <small class="text-muted d-block mt-1"><i class="fas fa-shield-alt text-success me-1"></i>Only users linked to this project's directory are available.</small>
                    </div>
                </div>
                <div class="modal-footer border-secondary d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-danger rounded-pill px-3" onclick="openDeleteTaskModal()" title="Delete task">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                    <div>
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold"><i class="fas fa-save me-2"></i>Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Confirmar Eliminación -->
<div class="modal fade" id="deleteTaskModal" tabindex="-1" aria-hidden="true" style="z-index: 1070;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3 border-danger" style="background: var(--bg-card); box-shadow: 0 0 20px rgba(239,68,68,0.2);">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Delete Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="deleteTaskForm">
                <div class="modal-body">
                    <p class="text-white mb-2">Are you sure you want to permanently delete this task?</p>
                    
                    <div class="mb-3 form-check form-switch mt-3">
                        <input class="form-check-input bg-dark border-danger" type="checkbox" id="del_subtasks" name="delete_subtasks" style="cursor: pointer;">
                        <label class="form-check-label text-white small" for="del_subtasks" style="cursor: pointer;">
                            Delete associated sub-tasks and RFIs as well
                        </label>
                    </div>
                    <p class="text-gray small mb-0"><i class="fas fa-info-circle me-1"></i>If unchecked, any existing sub-tasks will be kept and converted into main tasks.</p>
                </div>
                <div class="modal-footer border-secondary mt-2">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold"><i class="fas fa-trash-alt me-2"></i> Delete Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Justificación para Cambios de Estado / Extensión -->
<div class="modal fade" id="justificationModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-white" id="justificationModalTitle">Action Justification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="justificationForm">
                <input type="hidden" id="just_task_id" name="task_id" value="">
                <input type="hidden" id="just_status" name="status" value="">
                <div class="modal-body">
                    <p class="text-gray small mb-3" id="justificationModalDesc">Please provide a reason for this action.</p>
                    <div class="mb-3">
                        <label class="text-gray small mb-2">Justification Note <span class="text-danger">*</span></label>
                        <textarea id="just_note" name="justification_note" class="form-control bg-dark border-secondary text-white" rows="3" required placeholder="Enter your reason here..."></textarea>
                    </div>
                    <div class="mb-3" id="extendHoursContainer" style="display:none;">
                        <label class="text-gray small mb-2">Additional Hours <span class="text-danger">*</span></label>
                        <input type="number" step="0.5" min="0.5" id="just_extend_hours" name="extend_hours" class="form-control bg-dark border-secondary text-white" placeholder="e.g. 12">
                    </div>
                    <div class="mb-3 form-check form-switch mt-3" id="autoStartNextContainer" style="display:none;">
                        <input class="form-check-input" type="checkbox" id="just_auto_start" name="auto_start_next" style="cursor: pointer;">
                        <label class="form-check-label text-white small" for="just_auto_start" style="cursor: pointer;">Automatically start the next task</label>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold text-dark" id="justificationSubmitBtn">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Crear Tarea (FASE 71: Rediseño Tabs Híbridos) -->
<div class="modal fade" id="taskCreationModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-plus-circle text-primary me-2"></i>Add New Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="taskCreationForm">
                <input type="hidden" id="tc_parent_task_id" name="parent_task_id" value="">
                <input type="hidden" id="tc_stage_id" name="stage_id" value="">
                <input type="hidden" id="tc_active_tab" name="active_tab" value="subtask">
                <div class="modal-body">
                    <!-- Tabs -->
                    <div class="d-flex gap-2 mb-4">
                        <button type="button" id="tab_btn_subtask" class="btn btn-primary flex-grow-1 fw-bold" onclick="switchTaskCreationTab('subtask')">
                            <i class="fas fa-level-up-alt fa-rotate-90 me-2"></i> Add Sub-Task
                        </button>
                        <button type="button" id="tab_btn_rfi" class="btn btn-outline-danger flex-grow-1 fw-bold" onclick="switchTaskCreationTab('rfi')">
                            <i class="fas fa-bolt me-2"></i> RFI Block
                        </button>
                    </div>

                    <!-- Sub-Task View -->
                    <div id="form-subtask-view">
                        <div class="mb-3">
                            <label class="text-gray small mb-2">Task Name <span class="text-danger">*</span></label>
                            <input type="text" id="tc_name" name="name" class="form-control bg-dark border-secondary text-white" required placeholder="e.g., Run extra conduit">
                        </div>
                        <div class="mb-3">
                            <label class="text-gray small mb-2">Estimated Hours <span class="text-danger">*</span></label>
                            <input type="number" step="0.5" id="tc_hours" name="estimated_hours" class="form-control bg-dark border-secondary text-white" value="8" required>
                        </div>
                        <div class="mb-3">
                            <label class="text-gray small mb-2">Assign To (Optional)</label>
                            <select id="tc_assignee" name="assigned_user_id" class="form-select bg-dark border-secondary text-white">
                                <option value="">-- Unassigned --</option>
                            </select>
                        </div>
                    </div>

                    <!-- RFI View -->
                    <div id="form-rfi-view" class="d-none">
                        <div class="mb-3">
                            <label class="text-gray small mb-2">RFI Template <span class="text-danger">*</span></label>
                            <select id="tc_rfi_template" name="rfi_template_id" class="form-select bg-dark border-danger text-white">
                                <option value="">Loading templates...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="text-gray small mb-2">Justification / Note <span class="text-danger">*</span></label>
                            <textarea id="tc_rfi_justification" name="justification" class="form-control bg-dark border-danger text-white" rows="3" placeholder="Provide RFI details..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold"><i class="fas fa-plus me-2"></i>Create Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal FASE 79: Crear Nueva Etapa -->
<div class="modal fade" id="newStageModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content p-3" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-layer-group text-primary me-2"></i>Add New Stage</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="newStageForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="text-gray small mb-2">Stage Name <span class="text-danger">*</span></label>
                        <input type="text" id="ns_name" name="name" class="form-control bg-dark border-secondary text-white" required placeholder="e.g., Post-Construction">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Project Completed (FASE 78) -->
<div class="modal fade" id="projectCompletedModal" tabindex="-1" aria-hidden="true" style="z-index: 1080;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-4 text-center border-success" style="background: var(--bg-card); box-shadow: 0 0 40px rgba(16, 185, 129, 0.2);">
            <div class="mb-3">
                <i class="fas fa-trophy fa-4x text-success"></i>
            </div>
            <h4 class="fw-bold text-white mb-2">Project 100% Completed!</h4>
            <p class="text-gray mb-4">All tasks in the workflow have been successfully finished. What do you want to do next?</p>
            
            <div class="d-grid gap-3">
                <button type="button" class="btn btn-success rounded-pill fw-bold py-2" onclick="markProjectAsCompleted()">
                    <i class="fas fa-check-double me-2"></i> Mark Project as Completed
                </button>
                <button type="button" class="btn btn-outline-primary rounded-pill fw-bold py-2" data-bs-dismiss="modal">
                    <i class="fas fa-plus me-2"></i> Continue adding tasks
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Advertencia y Justificación para Reset -->
<div class="modal fade" id="resetTasksModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3 border-danger" style="background: var(--bg-card); box-shadow: 0 0 20px rgba(239,68,68,0.2);">
            <div class="modal-header bg-danger text-white border-0 rounded-top">
                <h5 class="modal-title fw-bold"><i class="fas fa-skull-crossbones me-2"></i> Danger Zone</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="resetTasksForm">
                <div class="modal-body mt-2">
                    <p class="text-danger fw-bold mb-3">¡WARNING! This action is irreversible. It will delete all active tasks, time logs, and justifications.</p>
                    <p class="text-gray small mb-3">An audit log will record who performed this action and why.</p>
                    
                    <label class="text-gray small mb-2">Mandatory Justification Note <span class="text-danger">*</span></label>
                    <textarea class="form-control bg-dark border-danger text-white" name="justification_note" rows="3" placeholder="e.g.: Applied the wrong template by mistake..." required></textarea>
                </div>
                <div class="modal-footer border-secondary mt-2">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold"><i class="fas fa-trash-alt me-2"></i> Confirm Reset</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Anti-Collision On Hold -->
<div class="modal fade" id="collisionOnHoldModal" tabindex="-1" aria-hidden="true" style="z-index: 1070;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3 border-warning" style="background: var(--bg-card);">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-warning"><i class="fas fa-pause-circle me-2"></i>Next Task On Hold</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-white mb-0">The next task in the list is <strong>On Hold</strong>. Do you want to Resume it now or leave it paused and skip to the next one?</p>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Leave On Hold</button>
                <button type="button" class="btn btn-warning rounded-pill px-4 fw-bold text-dark" id="btnResumeNextTask">Resume Now</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Overtime Exception (FASE 50) -->
<div class="modal fade" id="overtimeModal" tabindex="-1" aria-hidden="true" style="z-index: 1070;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3 border-warning" style="background: var(--bg-card);">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-warning"><i class="fas fa-moon me-2"></i>Outside Working Hours</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-white mb-0">You are trying to start or resume a task outside of established working hours or on a holiday/weekend.<br><br>Do you want to log this time as an exception (Overtime) or leave the task paused?</p>
            </div>
            <div class="modal-footer border-secondary mt-3">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Leave Paused</button>
                <button type="button" class="btn btn-warning rounded-pill px-4 fw-bold text-dark" id="btnConfirmOvertime">Start Exception</button>
            </div>
        </div>
    </div>
</div>

<!-- New Modal for Template Assignment -->
<div class="modal fade" id="assignTemplateUsersModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content p-3" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-users-cog text-primary me-2"></i>Assign Template Tasks</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="templateAssignmentForm">
                <input type="hidden" id="assign_template_id" name="template_id" value="">
                <div class="modal-body">
                    <p class="text-gray small mb-3">Review the tasks from "<strong id="templateNameForAssignment"></strong>" and assign users to each one. Unassigned tasks will remain pending.</p>
                    <div id="templateAssignmentList" class="mb-3" style="max-height: 500px; overflow-y: auto;">
                        <!-- Content will be loaded dynamically here -->
                        <div class="text-center text-gray py-5"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-3">Loading template details...</p></div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold"><i class="fas fa-magic me-2"></i>Apply Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal FASE 27: Subir Evidencia -->
<div class="modal fade" id="taskEvidenceModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-paperclip text-primary me-2"></i>Upload Evidence</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="taskEvidenceForm">
                <input type="hidden" id="evidence_task_id" name="task_id" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="text-gray small mb-2">Destination Folder <span class="text-danger">*</span></label>
                        <select id="evidence_folder_id" name="folder_id" class="form-select bg-dark border-secondary text-white" required>
                            <option value="">Loading folders...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="text-gray small mb-2" id="evidence_file_label">Select Files (Max 20) <span class="text-danger">*</span></label>
                        <input type="file" id="evidence_file" name="files[]" class="form-control bg-dark border-secondary text-white" required multiple>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold"><i class="fas fa-upload me-2"></i>Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal FASE 43: Time Calculation Breakdown -->
<div class="modal fade" id="timeCalculationModal" tabindex="-1" aria-hidden="true" style="z-index: 1070;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-calculator text-info me-2"></i>TimeEngine Breakdown</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-gray small mb-4">This breakdown explains how the algorithm calculated the estimated completion date by skipping non-working hours and days.</p>
                <ul class="list-group list-group-flush mb-3" style="border-radius: 8px; overflow: hidden;">
                    <li class="list-group-item bg-dark border-secondary text-white d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-stopwatch text-warning me-2"></i> Total Remaining Time</span>
                        <strong id="tc_hours_remaining">0 Hours</strong>
                    </li>
                    <li class="list-group-item bg-dark border-secondary text-white d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-sun text-warning me-2"></i> Working Days Needed (12h Shifts)</span>
                        <strong id="tc_working_days">0 Days</strong>
                    </li>
                    <li class="list-group-item bg-dark border-secondary text-white d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-calendar-times text-danger me-2"></i> Sundays Skipped</span>
                        <strong id="tc_weekends">0 Days</strong>
                    </li>
                    <li class="list-group-item bg-dark border-secondary text-white d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-umbrella-beach text-success me-2"></i> Holidays Skipped</span>
                        <strong id="tc_holidays">0 Days</strong>
                    </li>
                </ul>
                <div class="p-3 rounded text-center border border-info" style="background: rgba(14, 165, 233, 0.1);">
                    <div class="small text-info text-uppercase fw-bold mb-1">Mathematical Delivery Date</div>
                    <h5 class="text-white mb-0 fw-bold" id="tc_final_date">--</h5>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    let isTodayHolidayFlag = false; // Bandera global para Fase 22
    const spmIsAdmin = <?= (($_SESSION['role'] ?? '') === 'admin') ? 'true' : 'false' ?>;
    
    // FASE 32: Registro global de cronómetros
    window.masterSpmTimer = null; // AUDITORÍA 2: Single Master Timer

    function clearAllSmartPmTimers() {
        if (window.masterSpmTimer) {
            clearInterval(window.masterSpmTimer);
            window.masterSpmTimer = null;
        }
    }

    // --- FASE 80: LÓGICA DE DÍAS FERIADOS Y TIEMPO LABORABLE (SYNC CON TIMEENGINE PHP) ---
    function isWorkingHoliday(date) {
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        const md = `${m}-${d}`;
        
        if (['01-01', '07-04', '11-11', '12-25'].includes(md)) return true;
        
        const month = date.getMonth() + 1;
        const dayOfWeek = date.getDay();
        const dateNum = date.getDate();
        
        if (month === 9 && dayOfWeek === 1 && dateNum <= 7) return true; // Labor Day
        if (month === 11 && dayOfWeek === 4 && dateNum >= 22 && dateNum <= 28) return true; // Thanksgiving
        if (month === 5 && dayOfWeek === 1 && dateNum >= 25) return true; // Memorial Day
        
        return false;
    }

</script>

<script>
    // --- HELPER DE API CENTRALIZADO CON MANEJO DE ERRORES ---
    function smartPmApiCall(formData) {
        return fetch('../task_manager/api.php', { method: 'POST', body: formData })
            .then(response => response.text().then(text => {
                try {
                    const data = JSON.parse(text);
                    if (!response.ok && data.status !== 'error') {
                        throw new Error(`HTTP Error ${response.status}`);
                    }
                    return data;
                } catch (e) {
                    console.error('API Error. Raw Response:', text);
                    throw new Error('API returned invalid JSON. Check console for PHP errors.');
                }
            }));
    }

    function openSmartPM() {
        document.body.classList.add('smart-pm-active');
        loadSmartPMTasks();
    }

    function closeSmartPM() {
        document.body.classList.remove('smart-pm-active');
    }

    function toggleSmartPM() {
        if (document.body.classList.contains('smart-pm-active')) {
            closeSmartPM();
        } else {
            openSmartPM();
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('open_task_manager')) {
            openSmartPM();
        }
    });

    // Cargar Tareas desde la API
    function loadSmartPMTasks() {
        const fd = new FormData();
        fd.append('action', 'get_tasks');
        fd.append('project_id', pId); // pId existe globalmente en project_dashboard.php

        refreshProjectHealthDashboard(); // FASE 39: Trigger concurrente para actualizar métricas

        smartPmApiCall(fd)
            .then(d => {
                if (d.is_holiday !== undefined) isTodayHolidayFlag = d.is_holiday;
                if (d.status === 'success') {
                    const dangerZone = document.getElementById('spmDangerZone');
                    if (!d.data || d.data.length === 0) {
                        if (dangerZone) dangerZone.style.display = 'none';
                        showTemplateSelectorUI();
                    } else {
                        if (dangerZone) dangerZone.style.display = 'block';
                        renderSmartPMTasks(d.data);
                    }
                } else {
                    document.getElementById('spmTaskContainer').innerHTML = `<div class="text-center text-danger py-5 mt-5"><i class="fas fa-exclamation-triangle fa-2x mb-3"></i><p>Error: ${d.message}</p></div>`;
                }
            }).catch(e => {
                console.error("Fetch error:", e);
                document.getElementById('spmTaskContainer').innerHTML = `<div class="text-center text-danger py-5 mt-5"><i class="fas fa-times-circle fa-2x mb-3"></i><p>Failed to load data. Check console for details.</p></div>`;
            });
    }

    // FASE 38 & 39: Funciones para las métricas de salud reactivas
    function refreshProjectHealthDashboard() {
        const container = document.getElementById('spmHealthContainer');
        if (!container) return;

        const fd = new FormData();
        fd.append('action', 'get_project_health');
        fd.append('project_id', pId);

        smartPmApiCall(fd)
            .then(d => {
                if (d.status === 'success') {
                    const h = d.data;
                    window.lastHealthData = h; // FASE 43: Guardar para el Modal
                    container.innerHTML = `
                        <div style="font-size: 2rem; font-weight: bold; line-height: 1; color: var(--text-white);">
                            ${h.completed_tasks} / ${h.total_tasks}
                        </div>
                        <div style="color: #6c757d; font-size: 0.9rem; font-weight: 500;">
                            <i class="fas fa-stopwatch me-1 text-warning"></i> ${h.hours_worked}h Worked / ${h.hours_remaining}h Left
                        </div>
                        <div style="color: var(--text-gray); font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: 0.2s;" onclick="openTimeCalculationModal()" onmouseover="this.style.color='var(--text-white)'" onmouseout="this.style.color='var(--text-gray)'" title="View TimeEngine breakdown">
                            <i class="fas fa-flag-checkered me-1"></i> Est. Finish: ${h.project_estimated_end_date}
                        </div>
                    `;
                } else {
                    container.innerHTML = `<span class="text-danger small">Error loading metrics</span>`;
                }
            })
            .catch(e => {
                console.error(e);
                container.innerHTML = `<span class="text-danger small">Connection Error</span>`;
            });
    }

    // --- FASE 66: LÓGICA DEL PANEL DE AUDITORÍA (NOTAS) ---
    function openProjectNotesPanel() {
        document.getElementById('spmTaskContainer').style.display = 'none';
        const filterBar = document.querySelector('.spm-filter-bar');
        if (filterBar) filterBar.classList.add('d-none');
        const panel = document.getElementById('spmNotesPanel');
        panel.classList.remove('d-none');
        panel.style.display = 'flex';
        loadProjectNotes();
    }

    function closeProjectNotesPanel() {
        const panel = document.getElementById('spmNotesPanel');
        panel.classList.add('d-none');
        panel.style.display = 'none';
        document.getElementById('spmTaskContainer').style.display = 'block';
        const filterBar = document.querySelector('.spm-filter-bar');
        if (filterBar) filterBar.classList.remove('d-none');
    }

    let allProjectNotes = []; // FASE 74: Almacenamiento local para filtros rápidos

    function loadProjectNotes() {
        const container = document.getElementById('spmNotesContainer');
        container.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin text-gray fa-2x"></i></div>';
        
        const fd = new FormData(); fd.append('action', 'get_project_notes'); fd.append('project_id', pId);
        smartPmApiCall(fd).then(d => {
            if (d.status === 'success') {
                allProjectNotes = d.data;
                renderProjectNotes('all'); // Renderizar todos por defecto
                
                // Resetear estado visual de los botones de filtro
                document.querySelectorAll('.filter-note-btn').forEach(b => {
                    b.className = 'btn btn-sm btn-outline-secondary rounded-pill flex-shrink-0 filter-note-btn';
                });
                const allBtn = document.querySelector('.filter-note-btn[data-filter="all"]');
                if (allBtn) {
                    allBtn.className = 'btn btn-sm btn-primary text-white fw-bold rounded-pill flex-shrink-0 filter-note-btn';
                }
            } else { container.innerHTML = `<div class="text-danger small">${d.message}</div>`; }
        }).catch(e => { container.innerHTML = `<div class="text-danger small">Connection error.</div>`; });
    }
    
    function filterProjectNotes(category, btnElement) {
        document.querySelectorAll('.filter-note-btn').forEach(b => {
            b.className = 'btn btn-sm btn-outline-secondary rounded-pill flex-shrink-0 filter-note-btn';
        });
        if (btnElement) {
            btnElement.className = 'btn btn-sm btn-primary text-white fw-bold rounded-pill flex-shrink-0 filter-note-btn';
        }
        renderProjectNotes(category);
    }

    function renderProjectNotes(category) {
        const container = document.getElementById('spmNotesContainer');
        let filteredNotes = allProjectNotes;
        
        if (category === 'notes') {
            filteredNotes = allProjectNotes.filter(log => log.action_type === 'Note');
        } else if (category === 'active') {
            filteredNotes = allProjectNotes.filter(log => ['Started', 'Resumed'].includes(log.action_type));
        } else if (category === 'hold') {
            filteredNotes = allProjectNotes.filter(log => ['Hold', 'Paused'].includes(log.action_type));
        } else if (category === 'overdue') {
            filteredNotes = allProjectNotes.filter(log => ['Overdue', 'Extend', 'Extended'].includes(log.action_type));
        } else if (category === 'completed') {
            filteredNotes = allProjectNotes.filter(log => ['Completed', 'Completed_Late', 'Bypassed'].includes(log.action_type));
        } else if (category === 'rfi') {
            filteredNotes = allProjectNotes.filter(log => log.action_type === 'RFI_Justification');
        }

        if (filteredNotes.length === 0) {
            container.innerHTML = '<div class="text-center text-gray py-5"><i class="fas fa-comment-slash fa-3x mb-3 opacity-25"></i><p>No notes found for this category.</p></div>';
            return;
        }
        
        let html = '';
        filteredNotes.forEach(log => {
            let badgeColor = 'bg-secondary text-white';
            let badgeIcon = 'fa-info-circle';
            let typeLabel = log.action_type.replace('_', ' ');
            
            if (log.action_type === 'Hold') {
                badgeColor = 'bg-warning text-dark'; badgeIcon = 'fa-pause-circle'; typeLabel = 'Paused: Hold';
            } else if (log.action_type === 'Overdue') {
                badgeColor = 'bg-danger text-white'; badgeIcon = 'fa-exclamation-triangle'; typeLabel = 'Delay Detected';
            } else if (log.action_type === 'Note') {
                badgeColor = 'bg-info text-dark'; badgeIcon = 'fa-sticky-note'; typeLabel = 'Manual Note';
            } else if (log.action_type === 'RFI_Justification') {
                badgeColor = 'bg-primary text-white'; badgeIcon = 'fa-question-circle'; typeLabel = 'RFI Justification';
            } else if (log.action_type === 'Extend') {
                badgeColor = 'bg-info text-dark'; badgeIcon = 'fa-clock'; typeLabel = 'Time Extension';
            } else if (log.action_type === 'Completed') {
                badgeColor = 'bg-success text-white'; badgeIcon = 'fa-check-circle'; typeLabel = 'Completed';
            } else if (log.action_type === 'Completed_Late') {
                badgeColor = 'bg-danger text-white'; badgeIcon = 'fa-check-double'; typeLabel = 'Completed Late';
            } else if (log.action_type === 'Bypassed') {
                badgeColor = 'bg-secondary text-white'; badgeIcon = 'fa-forward'; typeLabel = 'Bypassed';
            } else if (log.action_type === 'Reset') {
                badgeColor = 'bg-danger text-white'; badgeIcon = 'fa-skull-crossbones'; typeLabel = 'Task Reset';
            }
            
            const taskRef = log.task_name ? `<span class="ms-2 text-warning fw-bold"><i class="fas fa-hashtag"></i> ${log.task_name}</span>` : '';
            
            html += `
                <div class="mb-3 p-3 rounded" style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-subtle);">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="small fw-bold text-white"><i class="fas fa-user-circle text-primary me-1"></i> ${log.username || 'System'}</div>
                        <div class="small text-gray">${log.created_at}</div>
                    </div>
                    <div class="mb-2"><span class="badge ${badgeColor}"><i class="fas ${badgeIcon} me-1"></i> ${typeLabel}</span> ${taskRef}</div>
                    <div class="text-gray small" style="line-height: 1.5; white-space: pre-wrap;">${log.description}</div>
                </div>`;
        });
        container.innerHTML = html;
        container.scrollTop = container.scrollHeight;
    }

    function submitProjectNote(e) {
        e.preventDefault();
        const text = document.getElementById('spmNewNoteText').value.trim();
        if (!text) return;
        
        const btn = e.target.querySelector('button[type="submit"]');
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        const fd = new FormData(); fd.append('action', 'add_project_log');
        fd.append('project_id', pId); fd.append('action_type', 'Note'); fd.append('description', text);

        smartPmApiCall(fd).then(d => {
            if (d.status === 'success') {
                document.getElementById('spmNewNoteText').value = '';
                loadProjectNotes();
            } else { appAlert('Error: ' + d.message, 'Error', 'error'); }
        }).catch(e => appAlert('Connection error.', 'Error', 'error')).finally(() => {
            btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i>';
        });
    }

    // Mostrar el Empty State (Selector de Plantillas)
    function showTemplateSelectorUI() {
        const container = document.getElementById('spmTaskContainer');
        container.innerHTML = `
            <div class="text-center py-5 mt-3">
                <i class="fas fa-project-diagram fa-3x text-gray mb-3 opacity-25"></i>
                <h5 class="text-white fw-bold">No Tasks Yet</h5>
                <p class="text-gray small mb-4">Start by applying a master template flow to this project.</p>
                
                <div class="text-start bg-card border border-secondary p-4 rounded-3 mx-auto shadow-sm" style="max-width: 380px;">
                    <label class="text-gray small fw-bold mb-2">Select Template</label>
                    <select id="spmTemplateSelect" class="form-select bg-dark border-secondary text-white mb-3">
                        <option value="">Loading templates...</option>
                    </select>
                    <button class="btn-main w-100 fw-bold mb-3" onclick="applySmartPMTemplate()">
                        <i class="fas fa-magic me-2"></i> Apply Template
                    </button>
                    
                    ${spmIsAdmin ? `
                    <div class="text-center mb-3">
                        <span class="text-muted small">----- OR -----</span>
                    </div>
                    <button class="btn btn-outline-secondary w-100 fw-bold" onclick="openNewStageModal()">
                        <i class="fas fa-pen me-2"></i> Start from scratch
                    </button>
                    ` : ''}
                </div>
            </div>
        `;

        // Poblar Dropdown
        const fd = new FormData(); fd.append('action', 'get_templates');
        smartPmApiCall(fd)
            .then(d => {
                const sel = document.getElementById('spmTemplateSelect');
                if (d.status === 'success') {
                    sel.innerHTML = '<option value="">-- Choose a standard flow --</option>';
                    d.data.forEach(t => { sel.innerHTML += `<option value="${t.id}">${t.name}</option>`; });
                } else {
                    sel.innerHTML = '<option value="">Error loading templates</option>';
                }
            })
            .catch(e => console.error("Fetch error:", e));
    }

    // Disparar la inserción masiva
    function applySmartPMTemplate() {
        const sel = document.getElementById('spmTemplateSelect');
        const templateId = sel.value;
        if (!templateId) { appAlert('Please select a template first.', 'Aviso', 'warning'); return; }
        
        // Guardar el templateId seleccionado para usarlo en el nuevo modal
        document.getElementById('assign_template_id').value = templateId;
        // Obtener el nombre del template para mostrarlo en el modal
        const templateName = sel.options[sel.selectedIndex].text;
        document.getElementById('templateNameForAssignment').innerText = templateName;

        openAssignTemplateUsersModal(templateId);
    }

    // --- FLUJO: ADJUNTAR ARCHIVOS A TAREAS (FASE 27) ---
    function triggerTaskAttachment(taskId, existingCount = 0) {
        const evidenceTaskIdEl = document.getElementById('evidence_task_id');
        evidenceTaskIdEl.value = taskId;
        evidenceTaskIdEl.dataset.existingCount = existingCount;
        
        document.getElementById('evidence_file').value = '';
        
        const remaining = 20 - existingCount;
        const label = document.getElementById('evidence_file_label');
        const fileInput = document.getElementById('evidence_file');
        const submitBtn = document.querySelector('#taskEvidenceForm button[type="submit"]');

        if (label) {
            if (remaining <= 0) {
                label.innerHTML = `Maximum limit reached (20 files). <span class="text-danger">*</span>`;
                fileInput.disabled = true;
                if(submitBtn) submitBtn.disabled = true;
            } else {
                label.innerHTML = `Select Files (Max ${remaining} more) <span class="text-danger">*</span>`;
                fileInput.disabled = false;
                if(submitBtn) submitBtn.disabled = false;
            }
        }

        const select = document.getElementById('evidence_folder_id');
        select.innerHTML = '<option value="">Loading folders...</option>';
        select.disabled = true;

        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('taskEvidenceModal'));
        modal.show();

        // Obtener las carpetas del proyecto consultando a la API Global
        const fd = new FormData();
        fd.append('action', 'get_folders_list');
        fd.append('project_id', pId);

        fetch('../api/api.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success' && res.data.length > 0) {
                    select.innerHTML = '<option value="">-- Select a Folder --</option>';
                    res.data.forEach(f => {
                        select.innerHTML += `<option value="${f.id}">${f.name}</option>`;
                    });
                    select.disabled = false;
                } else {
                    select.innerHTML = '<option value="">No folders available. Please create one first.</option>';
                }
            })
            .catch(e => { console.error(e); select.innerHTML = '<option value="">Error loading folders</option>'; });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const evidenceForm = document.getElementById('taskEvidenceForm');
        if (evidenceForm) {
            evidenceForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const fileInput = document.getElementById('evidence_file');
                const existingCount = parseInt(document.getElementById('evidence_task_id').dataset.existingCount || 0);
                const remaining = 20 - existingCount;
                
                if (fileInput.files.length > remaining) {
                    appAlert(`You can only upload up to ${remaining} more file(s). (Limit is 20 per task)`, 'Limit Exceeded', 'warning');
                    return;
                }

                const btn = this.querySelector('button[type="submit"]');
                const origText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Uploading...';
                btn.disabled = true;

                const taskId = document.getElementById('evidence_task_id').value;
                const fd = new FormData(this);
                fd.append('action', 'upload_task_attachment');
                fd.append('project_id', pId); // Variable global pId

                const taskCardBody = document.querySelector(`.task-card[data-task-id="${taskId}"] .task-body`);
                let statusDiv = document.querySelector(`.task-card[data-task-id="${taskId}"] .attachment-status`);
                if (!statusDiv) {
                    statusDiv = document.createElement('div');
                    statusDiv.className = 'text-info small mt-2 attachment-status fw-bold';
                    if (taskCardBody) taskCardBody.appendChild(statusDiv);
                }
                statusDiv.innerHTML = `<i class="fas fa-spinner fa-spin me-1"></i> Uploading evidence...`;

                try {
                    const d = await smartPmApiCall(fd);
                    if (d.status === 'success') {
                        bootstrap.Modal.getInstance(document.getElementById('taskEvidenceModal')).hide();
                        statusDiv.innerHTML = `<i class="fas fa-check-circle text-success me-1"></i> Evidence uploaded successfully!`;
                        statusDiv.className = 'text-success small mt-2 attachment-status fw-bold';
                    loadSmartPMTasks(); // FASE 41: Recargar vista para mostrar el botón de evidencia
                    } else {
                        statusDiv.innerHTML = `<i class="fas fa-times-circle text-danger me-1"></i> Error: ${d.message}`;
                        statusDiv.className = 'text-danger small mt-2 attachment-status fw-bold';
                    }
                } catch (err) {
                    console.error(err);
                    statusDiv.innerHTML = `<i class="fas fa-times-circle text-danger me-1"></i> Upload failed. Check console.`;
                } finally {
                    btn.innerHTML = origText;
                    btn.disabled = false;
                    setTimeout(() => statusDiv.remove(), 6000);
                }
            });
        }
    });

    // --- ASIGNACIÓN EN CASCADA Y MANEJO DEL MODAL DE PLANTILLA ---
    function cascadeAssign(stageSelect) {
        const selectedUserId = stageSelect.value;
        // Buscar el contenedor padre de la etapa específica (Aísla la jerarquía)
        const stageGroup = stageSelect.closest('.stage-assignment-group');
        if (stageGroup) {
            // Encontrar todos los selects de tareas dentro de esta etapa y actualizarlos
            const taskSelects = stageGroup.querySelectorAll('.task-user-assignee');
            taskSelects.forEach(select => {
                select.value = selectedUserId;
            });
        }
    }

    // FASE 28: Toggle de visibilidad para Tareas dentro de una Etapa
    function toggleStageTasks(containerId, headerEl) {
        const container = document.getElementById(containerId);
        const icon = headerEl.querySelector('i.fa-chevron-right, i.fa-chevron-down');
        if (container.style.display === 'none') {
            container.style.display = 'block';
            if (icon) { icon.classList.replace('fa-chevron-right', 'fa-chevron-down'); }
        } else {
            container.style.display = 'none';
            if (icon) { icon.classList.replace('fa-chevron-down', 'fa-chevron-right'); }
        }
    }

    function openAssignTemplateUsersModal(templateId) {
        const modalBodyContent = document.getElementById('templateAssignmentList');
        modalBodyContent.innerHTML = `<div class="text-center text-gray py-5"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-3">Loading template details...</p></div>`;
        
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('assignTemplateUsersModal'));
        modal.show();

        const fd = new FormData();
        fd.append('action', 'get_template_details_for_assignment');
        fd.append('project_id', pId);
        fd.append('template_id', templateId);

        smartPmApiCall(fd).then(response => {
            if (response.status === 'success') {
                const { template_items_structured, project_users } = response.data;
                let html = ''; 
                
                template_items_structured.forEach((stage, idx) => {
                    const userOptions = project_users.map(u => `<option value="${u.id}">${u.username} (${u.role})</option>`).join('');
                    
                    // Contenedor principal de la etapa para aislar el DOM (stage-assignment-group)
                    html += `
                        <div class="stage-assignment-group mb-3 border border-secondary rounded">
                            <div class="d-flex align-items-center justify-content-between p-2 rounded-top" style="background-color: rgba(255,255,255,0.05); cursor: pointer;" onclick="toggleStageTasks('stage-tasks-${idx}', this)">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-chevron-right text-gray me-2" style="transition: transform 0.2s;"></i>
                                    <h6 class="fw-bold text-white text-uppercase mb-0" style="letter-spacing: 1px;">${stage.name}</h6>
                                </div>
                                <div class="d-flex align-items-center flex-shrink-0" style="width: 250px;" onclick="event.stopPropagation()">
                                    <label class="text-gray small me-2 mb-0 fw-bold">Assign Stage:</label>
                                    <select class="form-select bg-dark border-secondary text-white form-select-sm stage-user-assigner" name="stage_assignments[${stage.name}]" onchange="cascadeAssign(this)">
                                        <option value="">-- Unassigned --</option>
                                        ${userOptions}
                                    </select>
                                </div>
                            </div>
                            <div class="stage-tasks-list p-2" id="stage-tasks-${idx}" style="display: none;">
                    `;

                    stage.tasks.forEach(task => {
                        html += `
                            <div class="d-flex align-items-center justify-content-between py-2 px-2 border-bottom border-secondary-subtle">
                                <div class="flex-grow-1 me-3">
                                    <p class="mb-0 text-gray fw-bold">${task.name}</p>
                                    <small class="text-muted"><i class="fas fa-clock me-1"></i>${task.estimated_minutes ? (task.estimated_minutes / 60).toFixed(1).replace(/\.0$/, '') : 0}h estimated</small>
                                </div>
                                <div class="flex-shrink-0" style="width: 200px;">
                                    <select class="form-select bg-dark border-secondary text-white form-select-sm task-user-assignee" name="assignments[${task.template_item_id}]">
                                        <option value="">-- Unassigned --</option>
                                        ${userOptions}
                                    </select>
                                </div>
                            </div>
                        `;
                    });
                    html += `</div></div>`;
                });
                modalBodyContent.innerHTML = html;
            } else {
                modalBodyContent.innerHTML = `<div class="text-center text-danger py-4"><i class="fas fa-exclamation-circle fa-2x mb-2"></i><p>${response.message}</p></div>`;
            }
        }).catch(err => {
            console.error(err);
            modalBodyContent.innerHTML = `<div class="text-center text-danger py-4"><i class="fas fa-times-circle fa-2x mb-2"></i><p>Failed to load data. Check console.</p></div>`;
        });
    }

    // FASE AUDITORIA 1: Event Delegation para expandir tareas completadas
    document.addEventListener('DOMContentLoaded', () => {
        const taskContainer = document.getElementById('spmTaskContainer');
        if (taskContainer) {
            taskContainer.addEventListener('click', function(e) {
                const completedCard = e.target.closest('.task-completed');
                // Ignorar si el clic fue en un enlace (ej. Ver Evidencia) o un botón dentro de la tarjeta
                if (completedCard && !e.target.closest('button, a')) {
                    completedCard.classList.toggle('expanded');
                }
            });
        }
    });

    // --- FASE 83: VISUALIZACIÓN DE TIEMPO CONSUMIDO ---
    function getWorkedTimeHtml(task) {
        const workedMins = task.worked_minutes ? parseInt(task.worked_minutes) : 0;
        const estMins = task.estimated_minutes ? parseInt(task.estimated_minutes) : 0;
        
        if (['Completed', 'Completed_Late', 'Bypassed'].includes(task.status)) {
            const wHours = Math.floor(workedMins / 60);
            const wMins = workedMins % 60;
            return `<div class="mt-1 time-consumed-log"><i class="fas fa-stopwatch text-warning me-1"></i> Tiempo Total: <strong class="text-white">${wHours}h ${wMins}m</strong></div>`;
        } else if (task.status === 'Active') {
            return `<div class="mt-1 time-consumed-log text-info elapsed-time-display" data-start="${task.actual_start_time || ''}" data-worked="${workedMins}" data-estimated="${estMins}" data-task-id="${task.id}">
                <i class="fas fa-hourglass-half me-1"></i> Transcurrido: <strong class="text-white">Calculando...</strong>
            </div>`;
        } else if (['System_Pause', 'On_Hold', 'Overdue'].includes(task.status)) {
            const wHours = Math.floor(workedMins / 60);
            const wMins = workedMins % 60;
            return `<div class="mt-1 time-consumed-log text-info"><i class="fas fa-hourglass-half me-1"></i> Transcurrido: <strong class="text-white">${wHours}h ${wMins}m</strong></div>`;
        }
        return '';
    }

    // Renderizador Dinámico de Cascada
    function renderSmartPMTasks(stages) {
        clearAllSmartPmTimers(); // FASE 32: Limpieza de timers al re-renderizar
        const container = document.getElementById('spmTaskContainer');
        let html = '';
        window.currentTasksMap = {}; // Mapa global para edicion rápida
        
        stages.forEach((stage, sIdx) => {
            // FASE 29: Título de la Etapa (Stage Group) convertido en Acordeón
            html += `<div class="stage-header d-flex justify-content-between align-items-center mt-3 mb-2" onclick="toggleStageContent('stage-content-${sIdx}', this)">
                        <h6 class="fw-bold text-muted mb-0 text-uppercase" style="letter-spacing: 1px;">
                            <i class="fas fa-chevron-down me-2 transition-icon"></i> ${stage.name}
                        </h6>
                        <span class="badge bg-dark border border-secondary">${Object.keys(stage.tasks || {}).length} Tasks</span>
                     </div>
                     <div id="stage-content-${sIdx}" class="stage-content" style="display: block;">`;
            
            // Renderizado de Tareas
            const tasks = Object.values(stage.tasks || {});
            tasks.forEach(task => {
                window.currentTasksMap[task.id] = task;
                let taskClass = 'task-pending';
                let badgeClass = 'bg-secondary';
                let statusIcon = '';
                let innerContent = '';
                
                // Display assigned user name
                const assignedUserDisplay = task.assigned_user_name ? `<div class="mb-2"><i class="fas fa-user-circle text-primary me-1"></i> Assigned to: <strong class="text-white">${task.assigned_user_name}</strong></div>` : '';

                // FASE 41 Alternativa: Botón a la Carpeta de Evidencias (usando folder_id)
                let evidenceBadge = '';
                let totalAttachedFiles = 0;
                if (task.attached_folders && task.attached_folders.length > 0) {
                    evidenceBadge += `<div class="mt-2 d-flex flex-column gap-2">`;
                    let filesRendered = 0;
                    task.attached_folders.forEach(folder => {
                        totalAttachedFiles += folder.files.length;
                        if (filesRendered >= 20) return;
                        evidenceBadge += `
                            <div class="border border-secondary rounded p-2" style="background: rgba(255,255,255,0.02);">
                                <a href="../pages/project_dashboard.php?id=${pId}&view=files&folder_id=${folder.folder_id}" class="badge bg-info text-dark text-decoration-none px-2 py-1 mb-1 d-inline-block" style="font-size: 0.75rem;" title="Open Folder">📁 ${folder.folder_name}</a>
                                <div class="d-flex flex-column gap-1 mt-1">`;
                        folder.files.forEach(f => {
                            if (filesRendered < 20) {
                                let fileUrl = `../${f.filepath}`;
                                const ext = (f.filename.split('.').pop() || '').toLowerCase();
                                const isPreviewable = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'xlsx', 'xls', 'xlsm', 'csv'].includes(ext);
                                const isExcel = ['xlsx', 'xls', 'xlsm', 'csv'].includes(ext);
                                if (isPreviewable && f.id) {
                                    fileUrl = `../pages/preview.php?id=${f.id}${isExcel ? '&mode=spreadsheet' : ''}`;
                                }
                                evidenceBadge += `<a href="${fileUrl}" class="text-info small text-decoration-none text-truncate" title="Open ${f.filename}"><i class="fas fa-file-alt me-1"></i> ${f.filename}</a>`;
                                filesRendered++;
                            }
                        });
                        evidenceBadge += `</div></div>`;
                    });
                    evidenceBadge += `</div>`;
                } else if (task.folder_id) {
                    evidenceBadge = `<div class="mt-2"><a href="../pages/project_dashboard.php?id=${pId}&view=files&folder_id=${task.folder_id}" class="badge bg-info text-dark text-decoration-none px-2 py-1" style="font-size: 0.75rem;" title="Open Evidence Folder">📁 View Task Folder</a></div>`;
                }

                // Mapeo CSS según Estado
                if (['Completed', 'Completed_Late', 'Bypassed'].includes(task.status)) {
                    taskClass = task.status === 'Completed_Late' ? 'task-completed task-completed-late' : 'task-completed';
                    badgeClass = task.status === 'Completed_Late' ? 'bg-danger' : 'bg-success';
                    statusIcon = task.status === 'Completed_Late' ? '<span class="status-dot" style="background-color:#ef4444;"></span><i class="fas fa-check-double text-danger me-2"></i>' : '<span class="status-dot green"></span><i class="fas fa-check-circle text-success me-2"></i>';
                    innerContent = `
                        ${assignedUserDisplay}
                        <div class="task-body mt-2 text-gray small">
                            <i class="fas fa-flag-checkered me-1"></i> Status changed on: <strong>${task.actual_end_time || 'N/A'}</strong>.
                            ${getWorkedTimeHtml(task)}
                            ${evidenceBadge}
                        </div>`;
                } else if (['Active', 'System_Pause', 'On_Hold', 'Overdue'].includes(task.status)) {
                    taskClass = 'task-active';
                    badgeClass = 'bg-primary';
                    if (task.status === 'On_Hold') badgeClass = 'bg-warning text-dark';
                    if (task.status === 'System_Pause') badgeClass = 'bg-warning text-dark';
                    if (task.status === 'Overdue') badgeClass = 'bg-danger';
                    
                    let timerHtml = '';
                    if (task.expected_end_time) {
                        timerHtml = `<div class="countdown-timer mt-1" data-deadline="${task.expected_end_time}" data-task-id="${task.id}" data-status="${task.status}"><i class="fas fa-stopwatch"></i> <span class="time-display">Calculating...</span></div>`;
                    }

                    // Botones Dinámicos basados en estado
                    let buttonsHtml = '';
                    if (task.status === 'Overdue') {
                        buttonsHtml = `
                            <button class="btn btn-sm btn-success rounded-pill px-3 fw-bold" onclick="promptJustification(${task.id}, 'Completed')"><i class="fas fa-check me-1"></i> Complete</button>
                            <button class="btn btn-sm btn-outline-info rounded-pill px-3 ms-1" onclick="promptJustification(${task.id}, 'Extend')"><i class="fas fa-clock me-1"></i> Extend</button>
                        `;
                    } else if (task.status === 'On_Hold' || task.status === 'System_Pause') {
                        buttonsHtml = `<button class="btn btn-sm btn-info rounded-pill px-3 fw-bold text-dark" onclick="updateTaskStatus(${task.id}, 'Active')"><i class="fas fa-play me-1"></i> Resume</button>`;
                    } else if (task.status === 'Active') {
                        buttonsHtml = `
                            <button class="btn btn-sm btn-success rounded-pill px-3 fw-bold" onclick="promptJustification(${task.id}, 'Completed')"><i class="fas fa-check me-1"></i> Complete</button>
                            <button class="btn btn-sm btn-outline-warning rounded-pill px-3" onclick="promptJustification(${task.id}, 'On_Hold')"><i class="fas fa-pause me-1"></i> Hold</button>
                            <button class="btn btn-sm btn-outline-info rounded-pill px-3 ms-1" onclick="promptJustification(${task.id}, 'Extend')"><i class="fas fa-clock me-1"></i> Extend</button>
                        `;
                    }

                    // FASE 25: Botón de adjuntar archivo si la tarea tiene una carpeta asociada
                    if (!['Completed', 'Bypassed', 'Overdue'].includes(task.status)) {
                        buttonsHtml += `<button class="btn btn-sm btn-outline-secondary rounded-pill px-3 ms-1" onclick="triggerTaskAttachment(${task.id}, ${totalAttachedFiles})" title="Attach File"><i class="fas fa-paperclip"></i></button>`;
                    }

                    innerContent = `
                        <div class="task-body text-gray small mb-3 mt-2">
                            ${assignedUserDisplay}
                            ${timerHtml}
                            ${getWorkedTimeHtml(task)}
                            ${evidenceBadge}
                        </div>
                        <div class="d-flex gap-2">
                            ${buttonsHtml}
                        </div>`;
                } else if (task.status === 'Pending') {
                    innerContent = `
                        <div class="task-body text-gray small mb-3 mt-2">
                            <div class="mb-1"><i class="fas fa-clock text-info me-1"></i> Est. Hours: <strong class="text-white">${task.estimated_minutes ? (task.estimated_minutes / 60).toFixed(1).replace(/\.0$/, '') : 0}</strong></div>                            ${assignedUserDisplay}
                            ${getWorkedTimeHtml(task)}
                            ${evidenceBadge}
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-primary rounded-pill px-3 fw-bold" onclick="updateTaskStatus(${task.id}, 'Active')"><i class="fas fa-play me-1"></i> Start Task</button>
                            <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 ms-1" onclick="promptJustification(${task.id}, 'Bypassed')"><i class="fas fa-forward me-1"></i> Bypass</button>
                        </div>`;
                }

                let editBtn = '';
                if (!['Completed', 'Completed_Late', 'Bypassed'].includes(task.status) && spmIsAdmin) {
                    editBtn = `<button class="btn btn-sm btn-link text-gray p-0 ms-2" onclick="openEditTaskModal(${task.id})" title="Edit Task"><i class="fas fa-edit"></i></button>`;
                }

                // --- Renderizar Sub-Tareas (RFI) Anidadas ---
                const subTasks = task.sub_tasks || [];
                let subTasksHtml = '';
                subTasks.forEach(sub => {
                    window.currentTasksMap[sub.id] = sub;
                    let subClass = 'task-pending';
                    let subBadge = 'bg-secondary';
                    let subIcon = '<i class="fas fa-level-up-alt fa-rotate-90 text-gray me-2"></i>';
                    let subInner = '';
                    const subAssignedUserDisplay = sub.assigned_user_name ? `<div class="mb-2"><i class="fas fa-user-circle text-primary me-1"></i> Assigned to: <strong class="text-white">${sub.assigned_user_name}</strong></div>` : '';

                    // FASE 41 Alternativa: Botón a la Carpeta de Evidencias (usando folder_id)
                    let subEvidenceBadge = '';
                    let subTotalAttachedFiles = 0;
                    if (sub.attached_folders && sub.attached_folders.length > 0) {
                        subEvidenceBadge += `<div class="mt-2 d-flex flex-column gap-2">`;
                        let filesRendered = 0;
                        sub.attached_folders.forEach(folder => {
                            subTotalAttachedFiles += folder.files.length;
                            if (filesRendered >= 20) return;
                            subEvidenceBadge += `
                                <div class="border border-secondary rounded p-2" style="background: rgba(255,255,255,0.02);">
                                    <a href="../pages/project_dashboard.php?id=${pId}&view=files&folder_id=${folder.folder_id}" class="badge bg-info text-dark text-decoration-none px-2 py-1 mb-1 d-inline-block" style="font-size: 0.75rem;" title="Open Folder">📁 ${folder.folder_name}</a>
                                    <div class="d-flex flex-column gap-1 mt-1">`;
                            folder.files.forEach(f => {
                                if (filesRendered < 20) {
                                    let fileUrl = `../${f.filepath}`;
                                    const ext = (f.filename.split('.').pop() || '').toLowerCase();
                                    const isPreviewable = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'xlsx', 'xls', 'xlsm', 'csv'].includes(ext);
                                    const isExcel = ['xlsx', 'xls', 'xlsm', 'csv'].includes(ext);
                                    if (isPreviewable && f.id) {
                                        fileUrl = `../pages/preview.php?id=${f.id}${isExcel ? '&mode=spreadsheet' : ''}`;
                                    }
                                    subEvidenceBadge += `<a href="${fileUrl}" class="text-info small text-decoration-none text-truncate" title="Open ${f.filename}"><i class="fas fa-file-alt me-1"></i> ${f.filename}</a>`;
                                    filesRendered++;
                                }
                            });
                            subEvidenceBadge += `</div></div>`;
                        });
                        subEvidenceBadge += `</div>`;
                    } else if (sub.folder_id) {
                        subEvidenceBadge = `<div class="mt-2"><a href="../pages/project_dashboard.php?id=${pId}&view=files&folder_id=${sub.folder_id}" class="badge bg-info text-dark text-decoration-none px-2 py-1" style="font-size: 0.75rem;" title="Open Evidence Folder">📁 View Task Folder</a></div>`;
                    }

                    if (['Completed', 'Completed_Late', 'Bypassed'].includes(sub.status)) {
                        subClass = sub.status === 'Completed_Late' ? 'task-completed task-completed-late' : 'task-completed';
                        subBadge = sub.status === 'Completed_Late' ? 'bg-danger' : 'bg-success';
                        subIcon = sub.status === 'Completed_Late' ? '<span class="status-dot" style="background-color:#ef4444;"></span><i class="fas fa-check-double text-danger me-2"></i>' : '<span class="status-dot green"></span><i class="fas fa-check-circle text-success me-2"></i>';
                        subInner = `<div class="task-body mt-2 text-gray small">${subAssignedUserDisplay}<i class="fas fa-flag-checkered me-1"></i> Status changed on: <strong>${sub.actual_end_time || 'N/A'}</strong> ${getWorkedTimeHtml(sub)} ${subEvidenceBadge}</div>`;
                    } else if (['Active', 'System_Pause', 'On_Hold', 'Overdue'].includes(sub.status)) {
                        subClass = 'task-active';
                        subBadge = 'bg-primary';
                        if (sub.status === 'On_Hold') subBadge = 'bg-warning text-dark';
                        if (sub.status === 'System_Pause') subBadge = 'bg-warning text-dark';
                        if (sub.status === 'Overdue') subBadge = 'bg-danger';
                        
                        let subTimer = sub.expected_end_time ? `<div class="countdown-timer mt-1" data-deadline="${sub.expected_end_time}" data-task-id="${sub.id}" data-status="${sub.status}"><i class="fas fa-stopwatch"></i> <span class="time-display">Calculating...</span></div>` : '';
                        let subBtns = '';
                        
                        if (sub.status === 'Overdue') {
                            subBtns = `
                                <button class="btn btn-sm btn-success rounded-pill px-3 fw-bold" onclick="promptJustification(${sub.id}, 'Completed')"><i class="fas fa-check me-1"></i> Complete</button>
                                <button class="btn btn-sm btn-outline-info rounded-pill px-3 ms-1" onclick="promptJustification(${sub.id}, 'Extend')"><i class="fas fa-clock me-1"></i> Extend</button>
                            `;
                        } else if (sub.status === 'On_Hold' || sub.status === 'System_Pause') {
                            subBtns = `<button class="btn btn-sm btn-info rounded-pill px-3 fw-bold text-dark" onclick="updateTaskStatus(${sub.id}, 'Active')"><i class="fas fa-play me-1"></i> Resume</button>`;
                        } else if (sub.status === 'Active') {
                            subBtns = `
                                <button class="btn btn-sm btn-success rounded-pill px-3 fw-bold" onclick="promptJustification(${sub.id}, 'Completed')"><i class="fas fa-check me-1"></i> Complete</button>
                                <button class="btn btn-sm btn-outline-warning rounded-pill px-3" onclick="promptJustification(${sub.id}, 'On_Hold')"><i class="fas fa-pause me-1"></i> Hold</button>
                                <button class="btn btn-sm btn-outline-info rounded-pill px-3 ms-1" onclick="promptJustification(${sub.id}, 'Extend')"><i class="fas fa-clock me-1"></i> Extend</button>
                            `;
                        }

                        if (!['Completed', 'Bypassed', 'Overdue'].includes(sub.status)) {
                            subBtns += `<button class="btn btn-sm btn-outline-secondary rounded-pill px-3 ms-1" onclick="triggerTaskAttachment(${sub.id}, ${subTotalAttachedFiles})" title="Attach File"><i class="fas fa-paperclip"></i></button>`;
                        }

                        subInner = `<div class="task-body text-gray small mb-3 mt-2">${subAssignedUserDisplay}${subTimer} ${getWorkedTimeHtml(sub)} ${subEvidenceBadge}</div><div class="d-flex gap-2">${subBtns}</div>`;
                    } else if (sub.status === 'Pending') {
                        subInner = `
                            <div class="task-body text-gray small mb-3 mt-2">
                                <div class="mb-1"><i class="fas fa-clock text-info me-1"></i> Est. Hours: <strong class="text-white">${sub.estimated_minutes ? (sub.estimated_minutes / 60).toFixed(1).replace(/\.0$/, '') : 0}</strong></div>
                                ${subAssignedUserDisplay}
                                ${getWorkedTimeHtml(sub)}
                                ${subEvidenceBadge}
                            </div>                            
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-primary rounded-pill px-3 fw-bold" onclick="updateTaskStatus(${sub.id}, 'Active')"><i class="fas fa-play me-1"></i> Start Task</button>
                                <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 ms-1" onclick="promptJustification(${sub.id}, 'Bypassed')"><i class="fas fa-forward me-1"></i> Bypass</button>
                            </div>
                        `;
                    }

                    let subEditBtn = '';
                    if (!['Completed', 'Completed_Late', 'Bypassed'].includes(sub.status) && spmIsAdmin) {
                        subEditBtn = `<button class="btn btn-sm btn-link text-gray p-0 ms-2" onclick="openEditTaskModal(${sub.id})" title="Edit Task"><i class="fas fa-edit"></i></button>`;
                    }

                    // FASE 69: Limpieza Visual de Etiquetas (RFI vs Sub-Task regular)
                    const isRfi = sub.name.toUpperCase().includes('RFI');
                    const typeBadge = isRfi 
                        ? `<span class="badge bg-dark border border-warning ms-2 text-warning"><i class="fas fa-bolt me-1"></i>RFI</span>`
                        : `<span class="badge bg-dark border border-secondary ms-2 text-gray"><i class="fas fa-level-up-alt fa-rotate-90 me-1"></i>Sub Task</span>`;
                    
                    const subShapeClass = isRfi ? 'shape-rfi' : 'shape-subtask';

                    subTasksHtml += `
                        <div class="task-card subtask-card ${subClass} ${subShapeClass} mt-2 mb-2" data-task-id="${sub.id}" data-status="${sub.status}">
                            <div class="d-flex justify-content-between align-items-start">
                                <h6 class="mb-0 fw-bold ${subClass === 'task-pending' ? 'text-gray' : 'text-white'}" style="font-size: 0.9rem;">${subIcon} ${sub.task_order} - ${sub.name} ${typeBadge} ${subEditBtn}</h6>
                                <div class="text-end ms-3 flex-shrink-0"><span class="badge ${subBadge} mb-1" style="font-size: 0.65rem;">${sub.status.replace('_', ' ')}</span></div>
                            </div>
                            ${subInner}
                        </div>
                    `;
                });

                // Construcción de la Tarjeta HTML
                html += `
                    <div class="task-card ${taskClass} shape-main" data-task-id="${task.id}" data-status="${task.status}">
                        <div class="d-flex justify-content-between align-items-start">
                            <h6 class="mb-0 fw-bold ${taskClass === 'task-pending' ? 'text-gray' : 'text-white'}" style="line-height: 1.4;">
                                ${statusIcon} ${task.task_order} - ${task.name} ${editBtn}
                            </h6>
                            <div class="text-end ms-3 flex-shrink-0">
                                <span class="badge ${badgeClass} mb-1">${task.status.replace('_', ' ')}</span>
                            </div>
                        </div>
                        ${innerContent}
                    </div>
                    ${subTasksHtml}
                `;

                // Solo mostrar botones de acción en tareas que no están completas
                if (!['Completed', 'Completed_Late', 'Bypassed'].includes(task.status) && spmIsAdmin) {
                    html += `
                        <div class="task-action-buttons">
                            <button class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold bg-dark" title="Add Sub-Task or RFI" onclick="openTaskCreationModal(${task.id})"><i class="fas fa-plus me-1"></i> Add Sub-Task</button>
                        </div>
                    `;
                }
            });
            
            // Botón para añadir Tarea a esta Etapa (FASE 79)
            if (spmIsAdmin) {
                html += `
                    <div class="d-flex justify-content-center mt-2 mb-4">
                        <button class="btn btn-sm btn-outline-secondary rounded-pill fw-bold text-gray" onclick="openTaskCreationModal(null, ${stage.id}, '${stage.name.replace(/'/g, "\\'")}')" style="border-style: dashed;">
                            <i class="fas fa-plus me-1"></i> Add Task to ${stage.name}
                        </button>
                    </div>
                `;
            }
            html += `</div>`; // Cerrar div.stage-content
        });

        // Botón para añadir una nueva Etapa al final del proyecto (FASE 79)
        if (stages.length > 0 && spmIsAdmin) {
            html += `
                <div class="d-flex justify-content-center mt-4 mb-5 pt-3 border-top border-secondary">
                    <button class="btn btn-outline-primary rounded-pill fw-bold px-4" onclick="openNewStageModal()">
                        <i class="fas fa-layer-group me-2"></i> Add New Stage
                    </button>
                </div>
            `;
        }

        container.innerHTML = html;
        startSmartPMCountdown(); // Activar el cronómetro una vez que la vista se renderiza
        applySpmFilters(); // Reaplicar el filtro activo después del renderizado
    }

    // --- LÓGICA DE EDICIÓN (TAILORING) ---
    let spmProjectUsers = null;
    
    function loadProjectUsers() {
        if (spmProjectUsers !== null) return Promise.resolve(spmProjectUsers);
        const fd = new FormData();
        fd.append('action', 'get_project_users');
        fd.append('project_id', pId);
        return smartPmApiCall(fd)
            .then(d => {
                if (d.status === 'success') { spmProjectUsers = d.data; return d.data; }
                throw new Error(d.message);
            });
    }

    function openEditTaskModal(taskId) {
        const task = window.currentTasksMap[taskId];
        if (!task) return;

        document.getElementById('edit_task_id').value = task.id;
        document.getElementById('edit_task_name').value = task.name;
        document.getElementById('edit_task_hours').value = task.estimated_minutes ? (task.estimated_minutes / 60) : 24;
        
        const select = document.getElementById('edit_task_assignee');
        select.innerHTML = '<option value="">Loading users...</option>';
        select.disabled = true;
        
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('editTaskModal'));
        modal.show();

        loadProjectUsers().then(users => {
            select.innerHTML = '<option value="">-- Unassigned --</option>';
            users.forEach(u => {
                const selected = (task.assigned_user_id == u.id) ? 'selected' : '';
                select.innerHTML += `<option value="${u.id}" ${selected}>${u.username} (${u.role})</option>`;
            });
            select.disabled = false;
        }).catch(e => { select.innerHTML = '<option value="">Error loading users</option>'; });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const editForm = document.getElementById('editTaskForm');
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const btn = this.querySelector('button[type="submit"]');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...'; btn.disabled = true;

                const fd = new FormData(this);
                const hours = parseFloat(fd.get('estimated_hours')) || 0;
                fd.delete('estimated_hours');
                fd.append('estimated_minutes', Math.round(hours * 60));
                fd.append('action', 'update_task_details');
                fd.append('project_id', pId);

                smartPmApiCall(fd)
                    .then(d => {
                        if (d.status === 'success') {
                            bootstrap.Modal.getInstance(document.getElementById('editTaskModal')).hide();
                            loadSmartPMTasks(); // Actualizar vista
                        } else { appAlert('Error: ' + d.message, 'Error', 'error'); }
                    }).catch(e => { 
                        console.error(e); 
                        appAlert('Connection error. Check console.', 'Error', 'error'); 
                    }).finally(() => { btn.innerHTML = originalText; btn.disabled = false; });
            });
        }
    });

    function openDeleteTaskModal() {
        const taskId = document.getElementById('edit_task_id').value;
        if (!taskId) return;
        
        document.getElementById('del_subtasks').checked = false;
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteTaskModal'));
        modal.show();
    }

    // --- FASE 71: MODAL UNIFICADO DE CREACIÓN CON TABS ---
    function switchTaskCreationTab(tab) {
        document.getElementById('tc_active_tab').value = tab;
        
        const btnSub = document.getElementById('tab_btn_subtask');
        const btnRfi = document.getElementById('tab_btn_rfi');
        const viewSub = document.getElementById('form-subtask-view');
        const viewRfi = document.getElementById('form-rfi-view');
        
        const inputName = document.getElementById('tc_name');
        const inputHours = document.getElementById('tc_hours');
        const inputTemplate = document.getElementById('tc_rfi_template');
        const inputJust = document.getElementById('tc_rfi_justification');

        if (tab === 'subtask') {
            btnSub.className = 'btn btn-primary flex-grow-1 fw-bold';
            btnRfi.className = 'btn btn-outline-danger flex-grow-1 fw-bold';
            viewSub.classList.remove('d-none');
            viewRfi.classList.add('d-none');
            
            inputName.required = true;
            inputHours.required = true;
            inputTemplate.required = false;
            inputJust.required = false;
        } else {
            btnSub.className = 'btn btn-outline-primary flex-grow-1 fw-bold';
            btnRfi.className = 'btn btn-danger flex-grow-1 fw-bold';
            viewSub.classList.add('d-none');
            viewRfi.classList.remove('d-none');
            
            inputName.required = false;
            inputHours.required = false;
            inputTemplate.required = true;
            inputJust.required = true;
        }
    }

    function openTaskCreationModal(parentTaskId, stageId = null, stageName = '') {
        document.getElementById('tc_parent_task_id').value = parentTaskId || '';
        document.getElementById('tc_stage_id').value = stageId || '';
        document.getElementById('tc_name').value = '';
        document.getElementById('tc_hours').value = '8';
        document.getElementById('tc_rfi_justification').value = '';
        
        const title = document.querySelector('#taskCreationModal .modal-title');
        const subTaskBtn = document.getElementById('tab_btn_subtask');
        
        if (stageId) {
            title.innerHTML = `<i class="fas fa-plus-circle text-primary me-2"></i>Add Task to ${stageName}`;
            subTaskBtn.innerHTML = `<i class="fas fa-tasks me-2"></i> Add Task`;
        } else {
            title.innerHTML = `<i class="fas fa-plus-circle text-primary me-2"></i>Add New Sub-Task`;
            subTaskBtn.innerHTML = `<i class="fas fa-level-up-alt fa-rotate-90 me-2"></i> Add Sub-Task`;
        }

        switchTaskCreationTab('subtask');
        
        const select = document.getElementById('tc_assignee');
        select.innerHTML = '<option value="">Loading users...</option>';
        select.disabled = true;
        
        const selectTemplate = document.getElementById('tc_rfi_template');
        selectTemplate.innerHTML = '<option value="">Loading templates...</option>';
        selectTemplate.disabled = true;
        
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('taskCreationModal'));
        modal.show();

        loadProjectUsers().then(users => {
            select.innerHTML = '<option value="">-- Unassigned --</option>';
            users.forEach(u => {
                select.innerHTML += `<option value="${u.id}">${u.username} (${u.role})</option>`;
            });
            select.disabled = false;
        }).catch(e => { select.innerHTML = '<option value="">Error loading users</option>'; });

        // --- FASE 72: Carga de Plantillas RFI ---
        const fdTpl = new FormData();
        fdTpl.append('action', 'get_templates');
        smartPmApiCall(fdTpl).then(d => {
            if (d.status === 'success') {
                selectTemplate.innerHTML = '<option value="">-- Select RFI Template --</option>';
                d.data.forEach(t => {
                    // Filtramos client-side para asegurar que solo se muestren plantillas RFI
                    if (t.name.toUpperCase().includes('RFI')) {
                        selectTemplate.innerHTML += `<option value="${t.id}">${t.name}</option>`;
                    }
                });
                selectTemplate.disabled = false;
            } else {
                selectTemplate.innerHTML = '<option value="">Error loading templates</option>';
            }
        }).catch(e => { selectTemplate.innerHTML = '<option value="">Error loading templates</option>'; });
    }

    document.addEventListener('DOMContentLoaded', () => {
        // --- FASE 72: LÓGICA DE SUBMIT DUAL (Sub-Task vs RFI) ---
        const tcForm = document.getElementById('taskCreationForm');
        if (tcForm) {
            tcForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const btn = this.querySelector('button[type="submit"]');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
                btn.disabled = true;

                const activeTab = document.getElementById('tc_active_tab').value;
                const parentTaskId = document.getElementById('tc_parent_task_id').value;
                const stageId = document.getElementById('tc_stage_id').value;

                if (activeTab === 'subtask') {
                    // --- RUTA 1: CREACIÓN DE SUB-TAREA O TAREA DE ETAPA ---
                    const name = document.getElementById('tc_name').value.trim();
                    const hours = parseFloat(document.getElementById('tc_hours').value) || 0;
                    const assignee = document.getElementById('tc_assignee').value;

                    const fd = new FormData();
                    if (stageId) {
                        fd.append('action', 'create_stage_task');
                        fd.append('stage_id', stageId);
                    } else {
                        fd.append('action', 'create_subtask');
                        fd.append('parent_task_id', parentTaskId);
                    }
                    fd.append('project_id', pId);
                    fd.append('name', name);
                    fd.append('estimated_minutes', Math.round(hours * 60));
                    if (assignee) fd.append('assigned_user_id', assignee);

                    smartPmApiCall(fd)
                        .then(d => {
                            if (d.status === 'success') {
                                bootstrap.Modal.getInstance(document.getElementById('taskCreationModal')).hide();
                                loadSmartPMTasks(); // Refrescar renderizado del Task Manager
                            } else { throw new Error(d.message); }
                        })
                        .catch(err => { 
                            console.error(err); 
                            appAlert('Error: ' + err.message, 'Error', 'error'); 
                        })
                        .finally(() => { btn.innerHTML = originalText; btn.disabled = false; });

                } else {
                    // --- RUTA 2: INYECCIÓN DE BLOQUE RFI ---
                    const templateId = document.getElementById('tc_rfi_template').value;
                    const justification = document.getElementById('tc_rfi_justification').value.trim();

                    const fd = new FormData();
                    fd.append('action', 'apply_rfi_template');
                    fd.append('project_id', pId);
                    if (stageId) {
                        fd.append('stage_id', stageId);
                    } else {
                        fd.append('parent_task_id', parentTaskId);
                    }
                    fd.append('rfi_template_id', templateId);
                    fd.append('justification', justification); // Se envía en el payload principal

                    smartPmApiCall(fd)
                        .then(d => {
                            if (d.status === 'success') {
                                // Añadir la justificación como un log a la tarea padre (Integración Fase 66)
                                if (justification) {
                                    const logFd = new FormData();
                                    logFd.append('action', 'add_project_log');
                                    logFd.append('project_id', pId);
                                    logFd.append('task_id', d.parent_task_id || parentTaskId); 
                                    logFd.append('action_type', 'RFI_Justification');
                                    logFd.append('description', justification);
                                    return smartPmApiCall(logFd);
                                }
                                return d;
                            } else { throw new Error(d.message); }
                        })
                        .then(() => {
                            bootstrap.Modal.getInstance(document.getElementById('taskCreationModal')).hide();
                            loadSmartPMTasks(); // Refrescar renderizado del Task Manager
                        })
                        .catch(err => { 
                            console.error(err); 
                            appAlert('Error: ' + err.message, 'Error', 'error'); 
                        })
                        .finally(() => { btn.innerHTML = originalText; btn.disabled = false; });
                }
            });
        }
        
        const delForm = document.getElementById('deleteTaskForm');
        if (delForm) {
            delForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const taskId = document.getElementById('edit_task_id').value;
                if (!taskId) return;

                const btn = this.querySelector('button[type="submit"]');
                const origText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                btn.disabled = true;

                const fd = new FormData();
                fd.append('action', 'delete_task');
                fd.append('project_id', pId);
                fd.append('task_id', taskId);
                fd.append('delete_subtasks', document.getElementById('del_subtasks').checked ? 1 : 0);

                smartPmApiCall(fd)
                    .then(d => {
                        if (d.status === 'success') {
                            bootstrap.Modal.getInstance(document.getElementById('deleteTaskModal')).hide();
                            bootstrap.Modal.getInstance(document.getElementById('editTaskModal')).hide();
                            loadSmartPMTasks(); 
                        } else { appAlert('Error: ' + d.message, 'Error', 'error'); }
                    }).catch(e => { appAlert('Connection error.', 'Error', 'error'); })
                    .finally(() => { btn.innerHTML = origText; btn.disabled = false; });
            });
        }
    });

    // --- FASE 79: CREAR NUEVA ETAPA ---
    function openNewStageModal() {
        document.getElementById('ns_name').value = '';
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('newStageModal'));
        modal.show();
    }

    // --- FASE 79: EXPORTAR A CSV ---
    function exportProjectToCSV() {
        window.location.href = '../task_manager/api.php?action=export_project_csv&project_id=' + pId;
    }

    document.addEventListener('DOMContentLoaded', () => {
        const nsForm = document.getElementById('newStageForm');
        if (nsForm) {
            nsForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const btn = this.querySelector('button[type="submit"]');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
                btn.disabled = true;

                const name = document.getElementById('ns_name').value.trim();

                const fd = new FormData();
                fd.append('action', 'create_project_stage');
                fd.append('project_id', pId);
                fd.append('name', name);

                smartPmApiCall(fd)
                    .then(d => {
                        if (d.status === 'success') {
                            bootstrap.Modal.getInstance(document.getElementById('newStageModal')).hide();
                            loadSmartPMTasks(); 
                        } else { throw new Error(d.message); }
                    })
                    .catch(err => { 
                        console.error(err); 
                        appAlert('Error: ' + err.message, 'Error', 'error'); 
                    })
                    .finally(() => { btn.innerHTML = originalText; btn.disabled = false; });
            });
        }
    });

    // --- FASE 29: FUNCIONES DE FILTRO Y ACORDEÓN ---
    function toggleStageContent(contentId, headerEl) {
        const content = document.getElementById(contentId);
        const icon = headerEl.querySelector('.transition-icon');
        if (content.style.display === 'none') {
            content.style.display = 'block';
            if (icon) icon.classList.replace('fa-chevron-right', 'fa-chevron-down');
        } else {
            content.style.display = 'none';
            if (icon) icon.classList.replace('fa-chevron-down', 'fa-chevron-right');
        }
    }

    let spmIsCompletedHidden = false;
    function toggleCompletedTasks() {
        spmIsCompletedHidden = !spmIsCompletedHidden;
        const btn = document.getElementById('btnToggleCompleted');
        if (spmIsCompletedHidden) {
            btn.innerHTML = '<i class="fas fa-eye-slash"></i>';
            btn.className = 'btn btn-sm btn-secondary rounded-pill flex-shrink-0 text-white';
        } else {
            btn.innerHTML = '<i class="fas fa-eye"></i>';
            btn.className = 'btn btn-sm btn-success rounded-pill flex-shrink-0 text-white';
        }
        applySpmFilters();
    }

    let spmIsAllCollapsed = false;
    function toggleCollapseAll() {
        spmIsAllCollapsed = !spmIsAllCollapsed;
        const headers = document.querySelectorAll('.stage-header .transition-icon');
        const btn = document.getElementById('btnToggleCollapseAll');
        
        headers.forEach(i => {
            if (spmIsAllCollapsed) {
                i.classList.replace('fa-chevron-down', 'fa-chevron-right');
            } else {
                i.classList.replace('fa-chevron-right', 'fa-chevron-down');
            }
        });
        
        if (spmIsAllCollapsed) {
            btn.innerHTML = '<i class="fas fa-expand-arrows-alt"></i>';
            btn.classList.replace('btn-outline-secondary', 'btn-secondary');
            btn.classList.add('text-white');
        } else {
            btn.innerHTML = '<i class="fas fa-compress-arrows-alt"></i>';
            btn.classList.replace('btn-secondary', 'btn-outline-secondary');
            btn.classList.remove('text-white');
        }
        applySpmFilters();
    }

    // --- FASE 89: LÓGICA DE FILTRADO INTELIGENTE ---
    let currentSpmFilter = 'all';
    function setSpmFilter(filterName) {
        currentSpmFilter = filterName;
        document.querySelectorAll('.spm-filter-btn').forEach(btn => {
            if (btn.dataset.filter === filterName) {
                btn.classList.add('btn-primary', 'text-white');
                btn.classList.remove('btn-outline-secondary');
            } else {
                btn.classList.remove('btn-primary', 'text-white');
                btn.classList.add('btn-outline-secondary');
            }
        });
        applySpmFilters();
    }

    function applySpmFilters() {
        const stages = document.querySelectorAll('.stage-content');
        let totalVisibleTasks = 0;

        stages.forEach(stage => {
            let visibleCount = 0;
            const tasks = stage.querySelectorAll('.task-card');
            tasks.forEach(task => {
                let isVisible = true;
                const status = task.getAttribute('data-status');
                
                const isMain = task.classList.contains('shape-main');
                const isSubtask = task.classList.contains('shape-subtask');
                const isRfi = task.classList.contains('shape-rfi');
                
                const isCompleted = ['Completed', 'Completed_Late', 'Bypassed'].includes(status);
                const isActive = status === 'Active';
                const isHold = ['On_Hold', 'System_Pause'].includes(status);
                const isOverdue = status === 'Overdue';
                const isPending = status === 'Pending';

                if (spmIsCompletedHidden && isCompleted) {
                    isVisible = false;
                }

                if (isVisible && currentSpmFilter !== 'all') {
                    if (currentSpmFilter === 'active' && !isActive) isVisible = false;
                    if (currentSpmFilter === 'hold' && !isHold) isVisible = false;
                    if (currentSpmFilter === 'overdue' && !isOverdue) isVisible = false;
                    if (currentSpmFilter === 'completed' && !isCompleted) isVisible = false;
                    if (currentSpmFilter === 'pending' && !isPending) isVisible = false;
                    if (currentSpmFilter === 'rfi' && !isRfi) isVisible = false;
                    if (currentSpmFilter === 'subtask' && !isSubtask) isVisible = false;
                }

                task.style.display = isVisible ? 'block' : 'none';
                if (isVisible) visibleCount++;
            });

            const actionBtns = stage.querySelectorAll('.task-action-buttons');
            actionBtns.forEach(btn => {
                btn.style.display = (currentSpmFilter === 'all' && !spmIsCompletedHidden) ? 'flex' : 'none';
            });
            
            const stageAddBtn = stage.querySelector('.btn-outline-secondary[onclick^="openTaskCreationModal"]');
            if (stageAddBtn && stageAddBtn.parentElement) {
                stageAddBtn.parentElement.style.display = (currentSpmFilter === 'all' && !spmIsCompletedHidden) ? 'flex' : 'none';
            }

            const header = stage.previousElementSibling;
            if (header && header.classList.contains('stage-header')) {
                if (visibleCount === 0) {
                    header.style.display = 'none';
                    stage.style.display = 'none';
                } else {
                    header.style.display = 'flex';
                    stage.style.display = spmIsAllCollapsed ? 'none' : 'block';
                }
            }
            totalVisibleTasks += visibleCount;
        });

        let emptyMsg = document.getElementById('spm-filter-empty-msg');
        if (totalVisibleTasks === 0 && document.querySelectorAll('.stage-content').length > 0) {
            if (!emptyMsg) {
                emptyMsg = document.createElement('div');
                emptyMsg.id = 'spm-filter-empty-msg';
                emptyMsg.className = 'text-center text-gray py-5 mt-4';
                emptyMsg.innerHTML = '<i class="fas fa-filter fa-3x mb-3 opacity-25"></i><p>No tasks match the selected filter.</p>';
                document.getElementById('spmTaskContainer').appendChild(emptyMsg);
            }
            emptyMsg.style.display = 'block';
        } else {
            if (emptyMsg) emptyMsg.style.display = 'none';
        }
    }

    // --- EVENTO SUBMIT PARA GUARDAR LA PLANTILLA (Tanto Etapas como Tareas) ---
    document.addEventListener('DOMContentLoaded', () => {
        const templateAssignmentForm = document.getElementById('templateAssignmentForm');
        if (templateAssignmentForm) {
            templateAssignmentForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const btn = this.querySelector('button[type="submit"]');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Applying...';
                btn.disabled = true;

                const templateId = document.getElementById('assign_template_id').value;
                
                // Recopilar asignaciones individuales de tareas
                const assignments = {};
                this.querySelectorAll('.task-user-assignee').forEach(select => {
                    const templateItemIdMatch = select.name.match(/\[(\d+)\]/);
                    if (templateItemIdMatch && select.value) {
                        assignments[templateItemIdMatch[1]] = parseInt(select.value);
                    }
                });

                // Recopilar asignaciones directas de las Etapas
                const stageAssignments = {};
                this.querySelectorAll('.stage-user-assigner').forEach(select => {
                    const stageNameMatch = select.name.match(/\[(.*?)\]/);
                    if (stageNameMatch && select.value) {
                        stageAssignments[stageNameMatch[1]] = parseInt(select.value);
                    }
                });

                const fd = new FormData();
                fd.append('action', 'apply_template');
                fd.append('project_id', pId);
                fd.append('template_id', templateId);
                fd.append('assignments', JSON.stringify(assignments));
                fd.append('stage_assignments', JSON.stringify(stageAssignments));

                smartPmApiCall(fd).then(d => {
                    if (d.status === 'success') {
                        bootstrap.Modal.getInstance(document.getElementById('assignTemplateUsersModal')).hide();
                        loadSmartPMTasks();
                    } else { appAlert('Error: ' + d.message, 'Error', 'error'); }
                }).catch(e => { 
                    console.error(e); 
                    appAlert('Connection error.', 'Error', 'error'); 
                }).finally(() => { btn.innerHTML = originalText; btn.disabled = false; });
            });
        }
    });

    // --- ACCIONES DE ESTADO (UPDATE) ---
    function updateTaskStatus(taskId, newStatus, justification = null, forceOvertime = 0) {
        const fd = new FormData();
        fd.append('action', 'update_task_status');
        fd.append('task_id', taskId);
        fd.append('status', newStatus);
        if (justification) fd.append('justification_note', justification);
        if (forceOvertime) fd.append('force_overtime', 1);

        smartPmApiCall(fd)
            .then(d => {
                if (d.status === 'confirm_overtime') {
                    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('overtimeModal'));
                    document.getElementById('btnConfirmOvertime').onclick = function() {
                        modal.hide();
                        updateTaskStatus(taskId, newStatus, justification, 1);
                    };
                    modal.show();
                } else if (d.status === 'success') {
                    loadSmartPMTasks(); 
                    
                    if (d.is_project_completed) {
                        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('projectCompletedModal'));
                        modal.show();
                    } else if (d.next_task_status === 'Already_Running_Somewhere') {
                                appAlert('Notice: You already have a task in progress. The next one will not auto-start to prevent conflicts.', 'Notice', 'warning');
                    } else if (d.next_task_status === 'Active') {
                                appAlert('Notice: The next task in the workflow is already running. Close it to continue.', 'Notice', 'warning');
                    } else if (d.next_task_status === 'On_Hold') {
                        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('collisionOnHoldModal'));
                        document.getElementById('btnResumeNextTask').onclick = function() {
                            modal.hide();
                            updateTaskStatus(d.next_task_id, 'Active');
                        };
                        modal.show();
                    }
                } else {
                    appAlert('Error: ' + d.message, 'Error', 'error');
                }
            }).catch(e => console.error(e));
    }

    function promptJustification(taskId, action) {
        // Interceptor Estricto: Si la tarea está Overdue, forzar SIEMPRE el modal de resolución rojo.
        const task = window.currentTasksMap[taskId];
        if (task && task.status === 'Overdue') {
            if (action === 'Completed') {
                action = 'Completed_Late'; // Redirige internamente a Completado con Retraso
            } else {
                promptOverdueResolution(taskId);
                return;
            }
        }

        document.getElementById('just_task_id').value = taskId;
        document.getElementById('just_status').value = action;
        document.getElementById('just_note').value = '';
        
        const extContainer = document.getElementById('extendHoursContainer');
        const extInput = document.getElementById('just_extend_hours');
        const title = document.getElementById('justificationModalTitle');
        const desc = document.getElementById('justificationModalDesc');
        const btn = document.getElementById('justificationSubmitBtn');
        const modalEl = document.getElementById('justificationModal');
        const modalContent = modalEl.querySelector('.modal-content');
        const autoStartContainer = document.getElementById('autoStartNextContainer');
        const autoStartInput = document.getElementById('just_auto_start');

        if (action === 'Extend') {
            extContainer.style.display = 'block';
            extInput.required = true;
            title.innerHTML = '<i class="fas fa-clock text-info me-2"></i>Extend Task Time';
            desc.innerText = 'Please provide a reason for the extension and the additional hours needed.';
            btn.className = 'btn btn-info rounded-pill px-4 fw-bold text-dark';
            btn.innerText = 'Extend Time';
        } else if (action === 'Bypassed') {
            extContainer.style.display = 'none';
            extInput.required = false;
            title.innerHTML = '<i class="fas fa-forward text-secondary me-2"></i>Bypass Task';
            desc.innerText = 'Provide a reason for skipping this task.';
            btn.className = 'btn btn-secondary rounded-pill px-4 fw-bold';
            btn.innerText = 'Bypass Task';
        } else if (action === 'On_Hold') {
            extContainer.style.display = 'none';
            extInput.required = false;
            title.innerHTML = '<i class="fas fa-pause text-warning me-2"></i>Put Task On Hold';
            desc.innerText = 'Why is this task being put on hold?';
            btn.className = 'btn btn-warning rounded-pill px-4 fw-bold text-dark';
            btn.innerText = 'Hold Task';
        } else if (action === 'Completed') {
            extContainer.style.display = 'none';
            extInput.required = false;
            title.innerHTML = '<i class="fas fa-check-circle text-success me-2"></i>Complete Task';
            desc.innerHTML = 'Please provide a completion note or summary before marking this task as completed.';
            btn.className = 'btn btn-success rounded-pill px-4 fw-bold text-white';
            btn.innerText = 'Complete Task';
            autoStartContainer.style.display = 'block';
            autoStartInput.checked = false; // Por defecto desmarcado
        } else if (action === 'Completed_Late') {
            extContainer.style.display = 'none';
            extInput.required = false;
            title.innerHTML = '<i class="fas fa-check-double text-danger me-2"></i>Complete Task (Late)';
            title.classList.replace('text-white', 'text-danger');
            desc.innerHTML = '<strong class="text-danger">This task is overdue.</strong><br>Please provide a reason for the delay before marking it as completed.';
            btn.className = 'btn btn-danger rounded-pill px-4 fw-bold text-white';
            btn.innerText = 'Complete Late';
            autoStartContainer.style.display = 'block';
            autoStartInput.checked = false; // Por defecto desmarcado
            
            modalContent.style.border = '2px solid #ef4444';
            modalContent.style.boxShadow = '0 0 25px rgba(239, 68, 68, 0.4)';
            
            modalEl.addEventListener('hidden.bs.modal', function onHide() {
                modalContent.style.border = '1px solid var(--border-subtle)';
                modalContent.style.boxShadow = 'none';
                title.classList.replace('text-danger', 'text-white');
                modalEl.removeEventListener('hidden.bs.modal', onHide);
            });
        }
        if (action !== 'Completed' && action !== 'Completed_Late') {
            autoStartContainer.style.display = 'none';
        }

        let modalInstance = bootstrap.Modal.getInstance(modalEl);
        if (modalInstance) modalInstance.dispose();
        const newModal = new bootstrap.Modal(modalEl);
        newModal.show();
    }

    document.addEventListener('DOMContentLoaded', () => {
        const justForm = document.getElementById('justificationForm');
        if (justForm) {
            justForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const taskId = document.getElementById('just_task_id').value;
                let action = document.getElementById('just_status').value;
                const note = document.getElementById('just_note').value;
                const autoStart = document.getElementById('just_auto_start').checked ? 1 : 0;
                
                const btn = this.querySelector('button[type="submit"]');
                const origHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
                btn.disabled = true;

                const fd = new FormData();
                if (action === 'Extend') {
                    const extHours = parseFloat(document.getElementById('just_extend_hours').value) || 0;
                    fd.append('action', 'extend_task_time');
                    fd.append('task_id', taskId);
                    fd.append('extend_minutes', Math.round(extHours * 60));
                    fd.append('justification_note', note);
                } else {
                    fd.append('action', 'update_task_status');
                    fd.append('task_id', taskId);
                    fd.append('status', action);
                    fd.append('justification_note', note);
                    fd.append('auto_start_next', autoStart);
                }

                smartPmApiCall(fd)
                    .then(d => {
                        if (d.status === 'success') {
                            bootstrap.Modal.getInstance(document.getElementById('justificationModal')).hide();
                            loadSmartPMTasks();
                            
                            if (d.is_project_completed) {
                                const pModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('projectCompletedModal'));
                                pModal.show();
                            } else if (d.next_task_status === 'Already_Running_Somewhere') {
                        appAlert('Notice: You already have a task in progress. The next one will not auto-start to prevent conflicts.', 'Notice', 'warning');
                            } else if (d.next_task_status === 'Active') {
                        appAlert('Notice: The next task in the workflow is already running. Close it to continue.', 'Notice', 'warning');
                            } else if (d.next_task_status === 'On_Hold') {
                                const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('collisionOnHoldModal'));
                                document.getElementById('btnResumeNextTask').onclick = function() {
                                    modal.hide();
                                    updateTaskStatus(d.next_task_id, 'Active');
                                };
                                modal.show();
                            }
                        } else { appAlert('Error: ' + d.message, 'Error', 'error'); }
                    })
                    .catch(e => { 
                        console.error(e); 
                        appAlert('Connection error. Check console.', 'Error', 'error'); 
                    })
                    .finally(() => { btn.innerHTML = origHtml; btn.disabled = false; });
            });
        }
    });

    // --- MOTOR DE CRONÓMETRO (COUNTDOWN ENGINE) ---
    // AUDITORÍA 2: Single Master Timer Engine
    function startSmartPMCountdown() {
        clearAllSmartPmTimers(); // FASE 32: Evitar acumulación
        
        const activeTasks = document.querySelectorAll('.task-active');
        const tasksData = [];
        
        activeTasks.forEach(taskCard => {
            const taskId = taskCard.getAttribute('data-task-id');
            if (!taskId) return;

            const timer = taskCard.querySelector('.countdown-timer');
            const elapsedDisplay = taskCard.querySelector('.elapsed-time-display');
            if (!timer && !elapsedDisplay) return;

            const deadlineStr = timer ? timer.getAttribute('data-deadline') : null;
            const startTimeStr = elapsedDisplay ? elapsedDisplay.getAttribute('data-start') : null;
            
            tasksData.push({
                taskId: taskId,
                timerElement: timer,
                displaySpan: timer ? timer.querySelector('.time-display') : null,
                elapsedDisplay: elapsedDisplay,
                startObj: startTimeStr ? new Date(startTimeStr.replace(/-/g, '/')) : null,
                taskStatus: timer ? timer.getAttribute('data-status') : 'Active',
                baseWorked: elapsedDisplay ? (parseInt(elapsedDisplay.getAttribute('data-worked')) || 0) : 0,
                estMins: elapsedDisplay ? (parseInt(elapsedDisplay.getAttribute('data-estimated')) || 0) : 0
            });
        });

        if (tasksData.length === 0) return;

        const updateDisplays = () => {
            const now = new Date(); // Una sola instancia compartida para todo el bucle

            tasksData.forEach(data => {
                let isOverdue = false;
                
                // FASE 92: Cálculo Universal Absoluto (Soporta Overtime de Madrugada)
                let totalWorkedSecs = data.baseWorked * 60;
                if (data.taskStatus === 'Active' && data.startObj) {
                    const diffSecs = Math.max(0, Math.floor((now - data.startObj) / 1000));
                    totalWorkedSecs += diffSecs;
                }
                
                const estSecs = data.estMins * 60;
                let remainingSecs = estSecs - totalWorkedSecs;

                // --- COUNTDOWN TIMER ---
                if (data.timerElement) {
                    if (data.taskStatus === 'System_Pause') {
                        if (isTodayHolidayFlag) {
                            if (data.displaySpan) data.displaySpan.innerHTML = "HOLIDAY PAUSE";
                            data.timerElement.style.background = 'rgba(239, 68, 68, 0.15)'; 
                            data.timerElement.style.color = '#ef4444';
                        } else {
                            if (data.displaySpan) data.displaySpan.innerHTML = "PAUSED (SYSTEM)";
                            data.timerElement.style.background = 'rgba(245, 158, 11, 0.15)'; 
                            data.timerElement.style.color = '#f59e0b';
                        }
                    } else if (data.taskStatus === 'On_Hold') {
                        if (data.displaySpan) data.displaySpan.innerHTML = "ON HOLD";
                        data.timerElement.style.background = 'rgba(245, 158, 11, 0.15)'; 
                        data.timerElement.style.color = '#f59e0b';
                    } else if (remainingSecs <= 0 || data.taskStatus === 'Overdue') {
                        isOverdue = true;
                        if (data.displaySpan) data.displaySpan.innerHTML = "00:00:00:00 (OVERDUE)";
                        data.timerElement.style.background = 'rgba(239, 68, 68, 0.2)';
                        data.timerElement.style.color = '#ef4444';
                        
                        if (data.taskStatus === 'Active') {
                            data.taskStatus = 'Overdue'; // Evitar loop
                            data.timerElement.setAttribute('data-status', 'Overdue');
                            handleOverdueTask(data.taskId);
                        }
                    } else {
                        const hours = Math.floor(remainingSecs / 3600);
                        const mins = Math.floor((remainingSecs % 3600) / 60);
                        const secs = remainingSecs % 60;
                        
                        const hStr = String(hours).padStart(2, '0');
                        const mStr = String(mins).padStart(2, '0');
                        const sStr = String(secs).padStart(2, '0');
                        
                        if (data.displaySpan) data.displaySpan.innerHTML = `${hStr}h ${mStr}m ${sStr}s`;
                    }
                }
                
                // --- FASE 83: ELAPSED TIME ---
                if (data.elapsedDisplay) {
                    const wH = Math.floor(totalWorkedSecs / 3600);
                    const wM = Math.floor((totalWorkedSecs % 3600) / 60);
                    data.elapsedDisplay.innerHTML = `<i class="fas fa-hourglass-half me-1"></i> Transcurrido: <strong class="text-white">${wH}h ${wM}m</strong>`;
                }
            });
        };

        updateDisplays();
        window.masterSpmTimer = setInterval(updateDisplays, 1000);
    }

    // --- FASE 48 & 92: ALARMA DE FIN DE JORNADA DINÁMICA POR TAREA ---
    if (window.eodCheckTimer) clearInterval(window.eodCheckTimer);
    
    window.eodCheckTimer = setInterval(() => {
        const now = new Date();
        const tasksToPause = [];
        
        if (window.currentTasksMap) {
            Object.values(window.currentTasksMap).forEach(task => {
                if (task.status === 'Active') {
                    const workEnd = task.work_end_time || '19:00:00';
                    const parts = workEnd.split(':');
                    const endH = parseInt(parts[0], 10);
                    const endM = parseInt(parts[1], 10);

                    // Disparar cuando la hora y minuto coincidan, en los primeros 2 segundos
                    if (now.getHours() === endH && now.getMinutes() === endM && now.getSeconds() <= 2) {
                        tasksToPause.push(task.id);
                    }
                }
            });
        }

        if (tasksToPause.length > 0) {
            triggerEndOfDayProtocol(tasksToPause);
        }
    }, 1000);

    function triggerEndOfDayProtocol(taskIds) {
        let processed = 0;
        taskIds.forEach(taskId => {
            // Evitar doble llamada en el mismo tick marcando localmente
            if (window.currentTasksMap[taskId]) window.currentTasksMap[taskId].status = 'System_Pause';

                const fd = new FormData();
                fd.append('action', 'update_task_status');
                fd.append('task_id', taskId);
                fd.append('status', 'System_Pause');
                fd.append('justification_note', 'Auto-paused by System at End of Day.');

                smartPmApiCall(fd).then(() => {
                    processed++;
                    if (processed === taskIds.length) {
                        loadSmartPMTasks(); // Recargar la vista con los nuevos estados consolidados
                        appAlert('⏰ End of workday detected. Active tasks automatically paused.', 'End of Day Protocol', 'info');
                    }
                }).catch(e => console.error('EOD Protocol Error:', e));
            });
    }

    // --- FLUJO ESTRICTO PARA TAREAS VENCIDAS (OVERDUE) ---
    function handleOverdueTask(taskId) {
        const fd = new FormData();
        fd.append('action', 'update_task_status');
        fd.append('task_id', taskId);
        fd.append('status', 'Overdue');
        fd.append('justification_note', 'System Auto-marked as Overdue.'); // Log auto del sistema
        
        smartPmApiCall(fd)
            .then(d => {
                if (d.status === 'success') {
                    promptOverdueResolution(taskId);
                }
            }).catch(e => console.error('Overdue mark failed:', e));
    }

    function promptOverdueResolution(taskId) {
        document.getElementById('just_task_id').value = taskId;
        document.getElementById('just_status').value = 'Extend'; // Obligamos al usuario a solicitar una Extensión de horas
        document.getElementById('just_note').value = '';
        
        const extContainer = document.getElementById('extendHoursContainer');
        const extInput = document.getElementById('just_extend_hours');
        const title = document.getElementById('justificationModalTitle');
        const desc = document.getElementById('justificationModalDesc');
        const btn = document.getElementById('justificationSubmitBtn');
        const modalContent = document.querySelector('#justificationModal .modal-content');

        // Re-estilizar modal a rojo Peligro/Advertencia
        modalContent.style.border = '2px solid #ef4444';
        modalContent.style.boxShadow = '0 0 25px rgba(239, 68, 68, 0.4)';

        extContainer.style.display = 'block';
        extInput.required = true;
        
        title.innerHTML = '<i class="fas fa-exclamation-triangle text-danger me-2"></i>Task Overdue!';
        title.classList.replace('text-white', 'text-danger');
        
        desc.innerHTML = '<strong class="text-danger">This task has exceeded its expected end time.</strong><br>The system has marked it as <strong>Overdue</strong>. Please justify the delay and specify the additional hours needed to extend the task.';
        btn.className = 'btn btn-danger rounded-pill px-4 fw-bold text-white';
        btn.innerText = 'Submit Extension';

        // Configurar para que el usuario NO pueda evadir el Modal (Bloqueo Estricto)
        const modalEl = document.getElementById('justificationModal');
        
        let modalInstance = bootstrap.Modal.getInstance(modalEl);
        if (modalInstance) modalInstance.dispose();
        
        modalEl.setAttribute('data-bs-backdrop', 'static');
        modalEl.setAttribute('data-bs-keyboard', 'false');
        
        const newModal = new bootstrap.Modal(modalEl);
        newModal.show();

        // Event Listener para restaurar los colores originales al cerrar el modal (para no afectar futuras justificaciones)
        modalEl.addEventListener('hidden.bs.modal', function onHide() {
            modalContent.style.border = '1px solid var(--border-subtle)';
            modalContent.style.boxShadow = 'none';
            title.classList.replace('text-danger', 'text-white');
            modalEl.removeAttribute('data-bs-backdrop');
            modalEl.removeAttribute('data-bs-keyboard');
            modalEl.removeEventListener('hidden.bs.modal', onHide);
        });
    }

    function markProjectAsCompleted() {
        const fd = new FormData();
        fd.append('action', 'complete_project');
        fd.append('project_id', pId);
        smartPmApiCall(fd).then(d => {
            if (d.status === 'success') {
                location.reload(); // Recarga la página para actualizar el badge de estado principal
            } else {
                appAlert('Error: ' + d.message, 'Error', 'error');
            }
        }).catch(e => console.error(e));
    }

    // --- FLUJO: RESET PROJECT TASKS (DANGER ZONE) ---
    function openResetTasksModal() {
        const form = document.getElementById('resetTasksForm');
        if (form) form.reset();
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('resetTasksModal'));
        modal.show();
    }

    document.addEventListener('DOMContentLoaded', () => {
        const resetForm = document.getElementById('resetTasksForm');
        if (resetForm) {
            resetForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const btn = this.querySelector('button[type="submit"]');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Deleting...';
                btn.disabled = true;

                const justification = this.querySelector('textarea[name="justification_note"]').value;
                const fd = new FormData();
                fd.append('action', 'reset_project_tasks');
                fd.append('project_id', pId); // variable global pId
                fd.append('justification_note', justification);

                smartPmApiCall(fd)
                    .then(d => {
                        if (d.status === 'success') {
                            clearAllSmartPmTimers(); // FASE 32: Limpiar antes de resetear
                            bootstrap.Modal.getInstance(document.getElementById('resetTasksModal')).hide();
                            loadSmartPMTasks(); // Recargará la vista vacía y ocultará el botón automáticamente
                        } else {
                            appAlert('Error: ' + d.message, 'Error', 'error');
                        }
                    })
                    .catch(e => { 
                        console.error(e); 
                        appAlert('Connection error. Check console.', 'Error', 'error'); 
                    })
                    .finally(() => { btn.innerHTML = originalText; btn.disabled = false; });
            });
        }
    });

    // FASE 43: Modal de Desglose de Cálculo
    function openTimeCalculationModal() {
        if (!window.lastHealthData) return;
        const h = window.lastHealthData;
        
        document.getElementById('tc_hours_remaining').innerText = h.hours_remaining + ' Hours';
        document.getElementById('tc_working_days').innerText = h.working_days_needed + ' Days';
        document.getElementById('tc_weekends').innerText = h.weekends_skipped + ' Days';
        document.getElementById('tc_holidays').innerText = h.holidays_skipped + ' Days';
        document.getElementById('tc_final_date').innerText = h.project_estimated_end_date;
        
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('timeCalculationModal'));
        modal.show();
    }
</script>