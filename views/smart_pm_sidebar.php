<?php
// views/smart_pm_sidebar.php
?>
<style>
    /* --- LAYOUT BASE: SIDEBAR OCULTO --- */
    .smart-pm-sidebar {
        position: fixed;
        top: 0;
        left: -45%; /* Oculto a la izquierda */
        width: 45%;
        height: 100vh;
        background: var(--bg-body);
        border-right: 1px solid var(--border-subtle);
        z-index: 1050;
        box-shadow: 10px 0 30px rgba(0,0,0,0.1);
        transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .smart-pm-sidebar.open {
        left: 0;
    }

    /* --- COMPORTAMIENTO MÓVIL --- */
    @media (max-width: 992px) {
        .smart-pm-sidebar {
            width: 100%;
            left: -100%;
        }
        .smart-pm-sidebar.open {
            left: 0;
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
    
    .spm-body::before {
        content: '';
        position: absolute;
        top: 2rem;
        bottom: 2rem;
        left: 2.4rem;
        width: 2px;
        background: var(--border-subtle);
        z-index: 0;
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
        opacity: 0.5;
        filter: grayscale(100%);
        cursor: pointer;
    }
    .task-completed::before { background: var(--success); border-color: var(--success); }
    .task-completed .task-body { display: none; } /* Colapsable */
    .task-completed.expanded .task-body { display: block; }
    .task-completed.expanded { opacity: 0.8; }

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
</style>

<input type="file" id="taskAttachmentInput" style="display:none;" onchange="handleTaskAttachment(this)">

<!-- SIDEBAR COMPONENT -->
<aside id="smartPmSidebar" class="smart-pm-sidebar">
    <div class="spm-header">
        <button class="spm-close-btn" onclick="toggleSmartPM()" title="Close Smart PM">
            <i class="fas fa-times"></i>
        </button>
        <button class="btn btn-sm btn-outline-info rounded-pill px-3" onclick="openPerformanceReportModal()" title="Performance Reports">
            <i class="fas fa-chart-line me-1"></i> Reports
        </button>
        <div class="text-end">
            <h5 class="fw-bold mb-0 text-white"><i class="fas fa-project-diagram text-warning me-2"></i> Smart PM</h5>
            <small class="text-gray text-uppercase" style="letter-spacing: 1px;">Task Manager</small>
        </div>
    </div>

    <!-- ZONA DE PELIGRO: Botón para Resetear Tareas (Oculto por defecto) -->
    <div id="spmDangerZone" style="display: none; padding: 1rem 1.5rem 0;">
        <button id="btn-danger-reset" class="btn btn-outline-danger w-100 fw-bold border-danger" onclick="openResetTasksModal()">
            <i class="fas fa-exclamation-triangle me-2"></i> Reset Project Tasks
        </button>
    </div>

    <div class="spm-body" id="spmTaskContainer">
        <div class="text-center text-gray py-5 mt-5">
            <i class="fas fa-spinner fa-spin fa-2x"></i>
            <p class="mt-3">Loading Smart PM data...</p>
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
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold"><i class="fas fa-save me-2"></i>Save Changes</button>
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
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold text-dark" id="justificationSubmitBtn">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Insertar Sub-Tarea Simple -->
<div class="modal fade" id="subtaskModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-plus-circle text-primary me-2"></i>Add New Sub-Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="subtaskForm">
                <input type="hidden" id="subtask_parent_task_id" name="parent_task_id" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="text-gray small mb-2">Sub-Task Name</label>
                        <input type="text" id="subtask_name" name="name" class="form-control bg-dark border-secondary text-white" required placeholder="e.g., Run extra conduit for LV">
                    </div>
                    <div class="mb-3">
                        <label class="text-gray small mb-2">Estimated Hours</label>
                        <input type="number" step="0.5" id="subtask_hours" name="estimated_hours" class="form-control bg-dark border-secondary text-white" value="8" required>
                    </div>
                    <div class="mb-3">
                        <label class="text-gray small mb-2">Assign To (Optional)</label>
                        <select id="subtask_assignee" name="assigned_user_id" class="form-select bg-dark border-secondary text-white">
                            <option value="">-- Unassigned --</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold"><i class="fas fa-plus me-2"></i>Create Sub-Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Insertar Bloque de RFI desde Plantilla -->
<div class="modal fade" id="rfiTemplateModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-bolt text-warning me-2"></i>Insert RFI Block</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="rfiTemplateForm">
                <input type="hidden" id="rfi_template_parent_task_id" name="parent_task_id" value="">
                <div class="modal-body">
                    <p class="text-gray small mb-3">Select a standard RFI workflow to insert as sub-tasks under the current task.</p>
                    <label class="text-gray small mb-2">RFI Workflow Template</label>
                    <select id="rfi_template_id" name="rfi_template_id" class="form-select bg-dark border-secondary text-white" required></select>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold text-dark"><i class="fas fa-project-diagram me-2"></i>Insert Block</button>
                </div>
            </form>
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
                <p class="text-white mb-0">La siguiente tarea en la lista está <strong>On Hold</strong>. ¿Deseas Reanudarla ahora o dejarla pausada y saltar a la próxima?</p>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Leave On Hold</button>
                <button type="button" class="btn btn-warning rounded-pill px-4 fw-bold text-dark" id="btnResumeNextTask">Resume Now</button>
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

<!-- Modal para Reporte de Rendimiento -->
<div class="modal fade" id="performanceReportModal" tabindex="-1" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content p-3" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-chart-pie text-info me-2"></i>User Performance Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="text-gray small mb-2">Select User to Analyze</label>
                        <select id="perf_report_user_select" class="form-select bg-dark border-secondary text-white" onchange="generatePerformanceReport(this.value)">
                            <option value="">-- Select a User --</option>
                        </select>
                    </div>
                </div>
                <div id="performanceReportContent" class="mt-3" style="display:none;">
                    <!-- Estadísticas Clave -->
                    <div class="row text-center mb-4">
                        <div class="col-4"><div class="stat-box p-3 rounded bg-dark"><div class="fs-4 fw-bold text-white" id="stat_total_estimated">0</div><small class="text-gray">Total Est. Hours</small></div></div>
                        <div class="col-4"><div class="stat-box p-3 rounded bg-dark"><div class="fs-4 fw-bold text-white" id="stat_total_actual">0</div><small class="text-gray">Total Actual Hours</small></div></div>
                        <div class="col-4"><div class="stat-box p-3 rounded bg-dark"><div class="fs-4 fw-bold text-info" id="stat_performance_ratio">0%</div><small class="text-gray">Performance Ratio</small></div></div>
                    </div>
                    <!-- Gráfico -->
                    <div class="mb-4">
                        <canvas id="performanceChart"></canvas>
                    </div>
                    <!-- Tabla de Desglose -->
                    <h6 class="text-white fw-bold">Completed Tasks Breakdown</h6>
                    <div class="table-responsive" style="max-height: 300px;">
                        <table class="table table-dark table-sm table-borderless">
                            <thead><tr class="border-bottom border-secondary"><th class="text-gray">Task Name</th><th class="text-gray text-end">Estimated</th><th class="text-gray text-end">Actual</th><th class="text-gray text-end">Variance</th></tr></thead>
                            <tbody id="performanceTableBody"></tbody>
                        </table>
                    </div>
                </div>
                <div id="performanceReportEmpty" class="text-center text-gray py-5">
                    <i class="fas fa-user-check fa-2x mb-3"></i>
                    <p>Select a user to view their performance on completed tasks.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let isTodayHolidayFlag = false; // Bandera global para Fase 22
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

    function toggleSmartPM() {
        const sidebar = document.getElementById('smartPmSidebar');
        const mainContent = document.querySelector('.main-content');
        
        sidebar.classList.toggle('open');
        mainContent.classList.toggle('pm-shifted');

        if (sidebar.classList.contains('open')) {
            loadSmartPMTasks();
        }
    }

    // Cargar Tareas desde la API
    function loadSmartPMTasks() {
        const fd = new FormData();
        fd.append('action', 'get_tasks');
        fd.append('project_id', pId); // pId existe globalmente en project_dashboard.php

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
                    <button class="btn-main w-100 fw-bold" onclick="applySmartPMTemplate()">
                        <i class="fas fa-magic me-2"></i> Apply Template
                    </button>
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
        if (!templateId) { alert('Please select a template first.'); return; }
        
        // Guardar el templateId seleccionado para usarlo en el nuevo modal
        document.getElementById('assign_template_id').value = templateId;
        // Obtener el nombre del template para mostrarlo en el modal
        const templateName = sel.options[sel.selectedIndex].text;
        document.getElementById('templateNameForAssignment').innerText = templateName;

        openAssignTemplateUsersModal(templateId);
    }

    // --- FLUJO: ADJUNTAR ARCHIVOS A TAREAS (FASE 25) ---
    function triggerTaskAttachment(taskId) {
        const task = window.currentTasksMap[taskId];
        if (!task || !task.folder_id) {
            alert('This task does not have an associated folder for attachments.');
            return;
        }
        const input = document.getElementById('taskAttachmentInput');
        input.setAttribute('data-task-id', taskId);
        input.click();
    }

    async function handleTaskAttachment(input) {
        const file = input.files[0];
        const taskId = input.getAttribute('data-task-id');
        if (!file || !taskId) return;

        const task = window.currentTasksMap[taskId];
        if (!task || !task.folder_id) {
            alert('Error: Task or folder data not found.');
            return;
        }

        const taskCardBody = document.querySelector(`.task-card[data-task-id="${taskId}"] .task-body`);
        const statusDiv = document.createElement('div');
        statusDiv.className = 'text-info small mt-2 attachment-status';
        statusDiv.innerHTML = `<i class="fas fa-spinner fa-spin me-1"></i> Uploading ${file.name}...`;
        if(taskCardBody) taskCardBody.appendChild(statusDiv);

        const fd = new FormData();
        fd.append('action', 'upload_task_attachment');
        fd.append('task_id', taskId);
        fd.append('project_id', task.project_id);
        fd.append('folder_id', task.folder_id);
        fd.append('file', file);

        try {
            const d = await smartPmApiCall(fd);
            statusDiv.innerHTML = `<i class="fas ${d.status === 'success' ? 'fa-check-circle text-success' : 'fa-times-circle text-danger'} me-1"></i> ${d.message}`;
            setTimeout(() => statusDiv.remove(), 4000);
        } catch (e) {
            console.error(e);
            statusDiv.innerHTML = `<i class="fas fa-times-circle text-danger me-1"></i> Upload failed. Check console.`;
        } finally {
            input.value = ''; // Reset file input
        }
    }

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

    function openAssignTemplateUsersModal(templateId) {
        const modalBodyContent = document.getElementById('templateAssignmentList');
        modalBodyContent.innerHTML = `<div class="text-center text-gray py-5"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-3">Loading template details...</p></div>`;
        
        const modal = new bootstrap.Modal(document.getElementById('assignTemplateUsersModal'));
        modal.show();

        const fd = new FormData();
        fd.append('action', 'get_template_details_for_assignment');
        fd.append('project_id', pId);
        fd.append('template_id', templateId);

        smartPmApiCall(fd).then(response => {
            if (response.status === 'success') {
                const { template_items_structured, project_users } = response.data;
                let html = ''; 
                
                template_items_structured.forEach(stage => {
                    const userOptions = project_users.map(u => `<option value="${u.id}">${u.username} (${u.role})</option>`).join('');
                    
                    // Contenedor principal de la etapa para aislar el DOM (stage-assignment-group)
                    html += `
                        <div class="stage-assignment-group mb-4 border border-secondary rounded">
                            <div class="d-flex align-items-center justify-content-between p-2 rounded-top" style="background-color: rgba(255,255,255,0.05);">
                                <h6 class="fw-bold text-white text-uppercase mb-0" style="letter-spacing: 1px;"><i class="fas fa-layer-group text-primary me-2"></i>${stage.name}</h6>
                                <div class="d-flex align-items-center flex-shrink-0" style="width: 250px;">
                                    <label class="text-gray small me-2 mb-0 fw-bold">Assign Stage:</label>
                                    <select class="form-select bg-dark border-secondary text-white form-select-sm stage-user-assigner" name="stage_assignments[${stage.name}]" onchange="cascadeAssign(this)">
                                        <option value="">-- Unassigned --</option>
                                        ${userOptions}
                                    </select>
                                </div>
                            </div>
                            <div class="stage-tasks-list p-2">
                    `;

                    stage.tasks.forEach(task => {
                        html += `
                            <div class="d-flex align-items-center justify-content-between py-2 px-2 border-bottom border-secondary-subtle">
                                <div class="flex-grow-1 me-3">
                                    <p class="mb-0 text-gray fw-bold">${task.name}</p>
                                    <small class="text-muted"><i class="fas fa-clock me-1"></i>${task.estimated_hours}h estimated</small>
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

    // Disparar la inserción masiva (ahora se hace desde el nuevo modal)
    /*
    function applySmartPMTemplate() {
        const sel = document.getElementById('spmTemplateSelect');
        const templateId = sel.value;
        if (!templateId) { alert('Please select a template first.'); return; }
        
        const btn = document.querySelector('#spmTaskContainer .btn-main');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Generating Tasks...';
        btn.disabled = true;

        const fd = new FormData();
        fd.append('action', 'apply_template');
        fd.append('project_id', pId);
        fd.append('template_id', templateId);

        smartPmApiCall(fd)
            .then(d => {
                if (d.status === 'success') {
                    loadSmartPMTasks(); // Recargar la vista ahora con las tareas creadas
                } else {
                    alert(d.message);
                    btn.innerHTML = '<i class="fas fa-magic me-2"></i> Apply Template';
                    btn.disabled = false;
                }
            })
            .catch(e => { 
                console.error("Fetch error:", e); 
                alert('Connection error. Check console.'); 
                btn.innerHTML = '<i class="fas fa-magic me-2"></i> Apply Template'; 
                btn.disabled = false; 
            });
    } */

    // Renderizador Dinámico de Cascada
    function renderSmartPMTasks(stages) {
        const container = document.getElementById('spmTaskContainer');
        let html = '';
        window.currentTasksMap = {}; // Mapa global para edicion rápida
        
        stages.forEach(stage => {
            // Título de la Etapa (Stage Group)
            html += `<h6 class="fw-bold text-muted mt-4 mb-3 ps-3 pb-2 border-bottom border-secondary text-uppercase" style="letter-spacing: 1px;">
                        <i class="fas fa-layer-group me-2"></i> ${stage.name}
                     </h6>`;
            
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

                // Mapeo CSS según Estado
                if (['Completed', 'Bypassed'].includes(task.status)) {
                    taskClass = 'task-completed';
                    badgeClass = 'bg-success';
                    statusIcon = '<i class="fas fa-check-circle text-success me-2"></i>';
                    innerContent = `
                        ${assignedUserDisplay}
                        <div class="task-body mt-2 text-gray small">
                            <i class="fas fa-flag-checkered me-1"></i> Status changed on: <strong>${task.actual_end_time || 'N/A'}</strong>.
                        </div>`;
                } else if (['Active', 'System_Pause', 'On_Hold', 'Overdue'].includes(task.status)) {
                    taskClass = 'task-active';
                    badgeClass = 'bg-primary';
                    if (task.status === 'On_Hold') badgeClass = 'bg-warning text-dark';
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
                            <button class="btn btn-sm btn-success rounded-pill px-3 fw-bold" onclick="updateTaskStatus(${task.id}, 'Completed')"><i class="fas fa-check me-1"></i> Complete</button>
                            <button class="btn btn-sm btn-outline-warning rounded-pill px-3" onclick="promptJustification(${task.id}, 'On_Hold')"><i class="fas fa-pause me-1"></i> Hold</button>
                            <button class="btn btn-sm btn-outline-info rounded-pill px-3 ms-1" onclick="promptJustification(${task.id}, 'Extend')"><i class="fas fa-clock me-1"></i> Extend</button>
                        `;
                    }

                    // FASE 25: Botón de adjuntar archivo si la tarea tiene una carpeta asociada
                    if (task.folder_id && !['Completed', 'Bypassed', 'Overdue'].includes(task.status)) {
                        buttonsHtml += `<button class="btn btn-sm btn-outline-secondary rounded-pill px-3 ms-1" onclick="triggerTaskAttachment(${task.id})" title="Attach File"><i class="fas fa-paperclip"></i></button>`;
                    }

                    innerContent = `
                        <div class="task-body text-gray small mb-3 mt-2">
                            ${assignedUserDisplay}
                            ${timerHtml}
                        </div>
                        <div class="d-flex gap-2">
                            ${buttonsHtml}
                        </div>`;
                } else if (task.status === 'Pending') {
                    innerContent = `
                        <div class="task-body text-gray small mb-3 mt-2">
                            <div class="mb-1"><i class="fas fa-clock text-info me-1"></i> Est. Hours: <strong class="text-white">${task.estimated_hours || 0}</strong></div>                            ${assignedUserDisplay}
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-primary rounded-pill px-3 fw-bold" onclick="updateTaskStatus(${task.id}, 'Active')"><i class="fas fa-play me-1"></i> Start Task</button>
                            <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 ms-1" onclick="promptJustification(${task.id}, 'Bypassed')"><i class="fas fa-forward me-1"></i> Bypass</button>
                        </div>`;
                }

                let editBtn = '';
                if (!['Completed', 'Bypassed'].includes(task.status)) {
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

                    if (['Completed', 'Bypassed'].includes(sub.status)) {
                        subClass = 'task-completed';
                        subBadge = 'bg-success';
                        subIcon = '<i class="fas fa-check-circle text-success me-2"></i>';
                        subInner = `<div class="task-body mt-2 text-gray small">${subAssignedUserDisplay}<i class="fas fa-flag-checkered me-1"></i> Status changed on: <strong>${sub.actual_end_time || 'N/A'}</strong></div>`;
                    } else if (['Active', 'System_Pause', 'On_Hold', 'Overdue'].includes(sub.status)) {
                        subClass = 'task-active';
                        subBadge = 'bg-primary';
                        if (sub.status === 'On_Hold') subBadge = 'bg-warning text-dark';
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
                                <button class="btn btn-sm btn-success rounded-pill px-3 fw-bold" onclick="updateTaskStatus(${sub.id}, 'Completed')"><i class="fas fa-check me-1"></i> Complete</button>
                                <button class="btn btn-sm btn-outline-warning rounded-pill px-3" onclick="promptJustification(${sub.id}, 'On_Hold')"><i class="fas fa-pause me-1"></i> Hold</button>
                                <button class="btn btn-sm btn-outline-info rounded-pill px-3 ms-1" onclick="promptJustification(${sub.id}, 'Extend')"><i class="fas fa-clock me-1"></i> Extend</button>
                            `;
                        }

                        if (sub.folder_id && !['Completed', 'Bypassed', 'Overdue'].includes(sub.status)) {
                            subBtns += `<button class="btn btn-sm btn-outline-secondary rounded-pill px-3 ms-1" onclick="triggerTaskAttachment(${sub.id})" title="Attach File"><i class="fas fa-paperclip"></i></button>`;
                        }

                        subInner = `<div class="task-body text-gray small mb-3 mt-2">${subAssignedUserDisplay}${subTimer}</div><div class="d-flex gap-2">${subBtns}</div>`;
                    } else if (sub.status === 'Pending') {
                        subInner = `
                            <div class="task-body text-gray small mb-3 mt-2">
                                <div class="mb-1"><i class="fas fa-clock text-info me-1"></i> Est. Hours: <strong class="text-white">${sub.estimated_hours || 0}</strong></div>
                                ${subAssignedUserDisplay}
                            </div>                            
                            <div class="d-flex gap-2">${'' /* FASE 25: Botón de adjuntar archivo para subtareas */}
                                <button class="btn btn-sm btn-primary rounded-pill px-3 fw-bold" onclick="updateTaskStatus(${sub.id}, 'Active')"><i class="fas fa-play me-1"></i> Start Task</button>
                                <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 ms-1" onclick="promptJustification(${sub.id}, 'Bypassed')"><i class="fas fa-forward me-1"></i> Bypass</button>${'' /* FASE 25: Botón de adjuntar archivo para subtareas */}
                                ${ sub.folder_id ? `<button class="btn btn-sm btn-outline-secondary rounded-pill px-3 ms-1" onclick="triggerTaskAttachment(${sub.id})" title="Attach File"><i class="fas fa-paperclip"></i></button>` : '' }
                            </div>
                        `;
                    }

                    let subEditBtn = '';
                    if (!['Completed', 'Bypassed'].includes(sub.status)) {
                        subEditBtn = `<button class="btn btn-sm btn-link text-gray p-0 ms-2" onclick="openEditTaskModal(${sub.id})" title="Edit Task"><i class="fas fa-edit"></i></button>`;
                    }

                    subTasksHtml += `
                        <div class="task-card ${subClass} ms-5 mt-2 mb-2" data-task-id="${sub.id}" style="border-left: 2px dashed var(--primary);" ${subClass === 'task-completed' ? 'onclick="this.classList.toggle(\'expanded\')"' : ''}>
                            <div class="d-flex justify-content-between align-items-start">
                                <h6 class="mb-0 fw-bold ${subClass === 'task-pending' ? 'text-gray' : 'text-white'}" style="font-size: 0.9rem;">${subIcon} ${sub.task_order} - ${sub.name} <span class="badge bg-dark border border-secondary ms-2 text-warning"><i class="fas fa-bolt me-1"></i>RFI</span> ${subEditBtn}</h6>
                                <div class="text-end ms-3 flex-shrink-0"><span class="badge ${subBadge} mb-1" style="font-size: 0.65rem;">${sub.status.replace('_', ' ')}</span></div>
                            </div>
                            ${subInner}
                        </div>
                    `;
                });

                // Construcción de la Tarjeta HTML
                html += `
                    <div class="task-card ${taskClass}" data-task-id="${task.id}" ${taskClass === 'task-completed' ? 'onclick="this.classList.toggle(\'expanded\')"' : ''}>
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
                if (!['Completed', 'Bypassed'].includes(task.status)) {
                    html += `
                        <div class="task-action-buttons">
                            <button class="btn-insert-subtask" title="Add a single, custom sub-task" onclick="openSubtaskModal(${task.id})"><i class="fas fa-plus-circle"></i> Add Sub-Task</button>
                            <button class="btn-insert-rfi" title="Insert a multi-step RFI from a template" onclick="openRfiTemplateModal(${task.id})"><i class="fas fa-bolt"></i> Insert RFI Block</button>
                        </div>
                    `;
                }
            });
        });

        container.innerHTML = html;
        startSmartPMCountdown(); // Activar el cronómetro una vez que la vista se renderiza
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
        document.getElementById('edit_task_hours').value = task.estimated_hours || 24;
        
        const select = document.getElementById('edit_task_assignee');
        select.innerHTML = '<option value="">Loading users...</option>';
        select.disabled = true;
        
        const modal = new bootstrap.Modal(document.getElementById('editTaskModal'));
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
                fd.append('action', 'update_task_details');
                fd.append('project_id', pId);

                smartPmApiCall(fd)
                    .then(d => {
                        if (d.status === 'success') {
                            bootstrap.Modal.getInstance(document.getElementById('editTaskModal')).hide();
                            loadSmartPMTasks(); // Actualizar vista
                        } else { alert('Error: ' + d.message); }
                    }).catch(e => { console.error(e); alert('Connection error. Check console.'); }).finally(() => { btn.innerHTML = originalText; btn.disabled = false; });
            });
        }
    });

    function openSubtaskModal(parentTaskId) {
        document.getElementById('subtask_parent_task_id').value = parentTaskId;
        document.getElementById('subtask_name').value = '';
        document.getElementById('subtask_hours').value = '8';
        
        const select = document.getElementById('subtask_assignee');
        select.innerHTML = '<option value="">Loading users...</option>';
        select.disabled = true;
        
        const modal = new bootstrap.Modal(document.getElementById('subtaskModal'));
        modal.show();

        loadProjectUsers().then(users => {
            select.innerHTML = '<option value="">-- Unassigned --</option>';
            users.forEach(u => {
                select.innerHTML += `<option value="${u.id}">${u.username} (${u.role})</option>`;
            });
            select.disabled = false;
        }).catch(e => { select.innerHTML = '<option value="">Error loading users</option>'; });
    }

    function openRfiTemplateModal(parentTaskId) {
        document.getElementById('rfi_template_parent_task_id').value = parentTaskId;
        const select = document.getElementById('rfi_template_id');
        select.innerHTML = '<option value="">Loading RFI templates...</option>';
        select.disabled = true;

        const modal = new bootstrap.Modal(document.getElementById('rfiTemplateModal'));
        modal.show();

        const fd = new FormData();
        fd.append('action', 'get_rfi_templates');
        smartPmApiCall(fd)
            .then(d => {
                if (d.status === 'success' && d.data.length > 0) {
                    select.innerHTML = '';
                    d.data.forEach(t => {
                        select.innerHTML += `<option value="${t.id}">${t.name}</option>`;
                    });
                    select.disabled = false;
                } else {
                    select.innerHTML = '<option value="">No RFI templates found</option>';
                }
            })
            .catch(e => console.error("Fetch error:", e));
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Handler para el formulario de SUB-TAREA SIMPLE
        const subtaskForm = document.getElementById('subtaskForm');
        if (subtaskForm) {
            subtaskForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const btn = this.querySelector('button[type="submit"]');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating...';
                btn.disabled = true;

                const fd = new FormData(this);
                fd.append('action', 'create_subtask');
                fd.append('project_id', pId);

                smartPmApiCall(fd)
                    .then(d => {
                        if (d.status === 'success') {
                            bootstrap.Modal.getInstance(document.getElementById('subtaskModal')).hide();
                            loadSmartPMTasks(); // Actualizar vista
                        } else { alert('Error: ' + d.message); }
                    }).catch(e => { console.error(e); alert('Connection error. Check console.'); }).finally(() => { btn.innerHTML = originalText; btn.disabled = false; });
            });
        }

        // Handler para el formulario de BLOQUE DE RFI
        const rfiTemplateForm = document.getElementById('rfiTemplateForm');
        if (rfiTemplateForm) {
            rfiTemplateForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const fd = new FormData(this);
                fd.append('action', 'apply_rfi_template');
                fd.append('project_id', pId);
                // Similar fetch logic as above...
                smartPmApiCall(fd)
                    .then(d => {
                        if (d.status === 'success') {
                            bootstrap.Modal.getInstance(document.getElementById('rfiTemplateModal')).hide();
                            loadSmartPMTasks();
                        } else { alert('Error: ' + d.message); }
                    }).catch(e => { console.error(e); alert('Connection error. Check console.'); });
            });
        }
    });

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
                    } else { alert('Error: ' + d.message); }
                }).catch(e => { console.error(e); alert('Connection error.'); }).finally(() => { btn.innerHTML = originalText; btn.disabled = false; });
            });
        }
    });

    // --- ACCIONES DE ESTADO (UPDATE) ---
    function updateTaskStatus(taskId, newStatus, justification = null) {
        const fd = new FormData();
        fd.append('action', 'update_task_status');
        fd.append('task_id', taskId);
        fd.append('status', newStatus);
        if (justification) fd.append('justification_note', justification);

        smartPmApiCall(fd)
            .then(d => {
                if (d.status === 'success') {
                    loadSmartPMTasks(); 
                    
                    if (d.next_task_status === 'Active') {
                        alert('Aviso: La siguiente tarea en la cascada ya está en curso. Ciérrala para continuar.');
                    } else if (d.next_task_status === 'On_Hold') {
                        const modal = new bootstrap.Modal(document.getElementById('collisionOnHoldModal'));
                        document.getElementById('btnResumeNextTask').onclick = function() {
                            modal.hide();
                            updateTaskStatus(d.next_task_id, 'Active');
                        };
                        modal.show();
                    }
                } else {
                    alert('Error: ' + d.message);
                }
            }).catch(e => console.error(e));
    }

    function promptJustification(taskId, action) {
        document.getElementById('just_task_id').value = taskId;
        document.getElementById('just_status').value = action;
        document.getElementById('just_note').value = '';
        
        const extContainer = document.getElementById('extendHoursContainer');
        const extInput = document.getElementById('just_extend_hours');
        const title = document.getElementById('justificationModalTitle');
        const desc = document.getElementById('justificationModalDesc');
        const btn = document.getElementById('justificationSubmitBtn');

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
            title.innerHTML = '<i class="fas fa-check-circle text-success me-2"></i>Complete Task (Late)';
            desc.innerHTML = '<strong class="text-danger">This task is overdue.</strong><br>Please provide a reason for the delay before marking it as completed.';
            btn.className = 'btn btn-success rounded-pill px-4 fw-bold text-white';
            btn.innerText = 'Complete Task';
        }

        const modal = new bootstrap.Modal(document.getElementById('justificationModal'));
        modal.show();
    }

    document.addEventListener('DOMContentLoaded', () => {
        const justForm = document.getElementById('justificationForm');
        if (justForm) {
            justForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const taskId = document.getElementById('just_task_id').value;
                let action = document.getElementById('just_status').value;
                const note = document.getElementById('just_note').value;
                
                const btn = this.querySelector('button[type="submit"]');
                const origHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
                btn.disabled = true;

                const fd = new FormData();
                if (action === 'Extend') {
                    const extHours = document.getElementById('just_extend_hours').value;
                    fd.append('action', 'extend_task_time');
                    fd.append('task_id', taskId);
                    fd.append('extend_hours', extHours);
                    fd.append('justification_note', note);
                } else {
                    fd.append('action', 'update_task_status');
                    fd.append('task_id', taskId);
                    fd.append('status', action);
                    fd.append('justification_note', note);
                }

                smartPmApiCall(fd)
                    .then(d => {
                        if (d.status === 'success') {
                            bootstrap.Modal.getInstance(document.getElementById('justificationModal')).hide();
                            loadSmartPMTasks();
                            
                            if (d.next_task_status === 'Active') {
                                alert('Aviso: La siguiente tarea en la cascada ya está en curso. Ciérrala para continuar.');
                            } else if (d.next_task_status === 'On_Hold') {
                                const modal = new bootstrap.Modal(document.getElementById('collisionOnHoldModal'));
                                document.getElementById('btnResumeNextTask').onclick = function() {
                                    modal.hide();
                                    updateTaskStatus(d.next_task_id, 'Active');
                                };
                                modal.show();
                            }
                        } else { alert('Error: ' + d.message); }
                    })
                    .catch(e => { console.error(e); alert('Connection error. Check console.'); })
                    .finally(() => { btn.innerHTML = origHtml; btn.disabled = false; });
            });
        }
    });

    // --- MOTOR DE CRONÓMETRO (COUNTDOWN ENGINE) ---
    let pmCountdownInterval = null;
    function startSmartPMCountdown() {
        if (pmCountdownInterval) clearInterval(pmCountdownInterval);
        
        pmCountdownInterval = setInterval(() => {
            const timers = document.querySelectorAll('.countdown-timer');
            timers.forEach(timer => {
                const deadlineStr = timer.getAttribute('data-deadline');
                if (!deadlineStr) return;
                
                const taskId = timer.getAttribute('data-task-id');
                const taskStatus = timer.getAttribute('data-status');
                
                // Reemplazamos guiones por slashes para compatibilidad cruzada en navegadores (Safari/iOS)
                const deadline = new Date(deadlineStr.replace(/-/g, '/'));
                const now = new Date();
                const diffMs = deadline - now;
                
                const displaySpan = timer.querySelector('.time-display');
                if (!displaySpan) return;
                
                // FASE 18: Detener visualmente el cronómetro si está pausado
                if (taskStatus === 'System_Pause') {
                    // FASE 22: Mensaje rojo en caso de feriado
                    if (isTodayHolidayFlag) {
                        displaySpan.innerHTML = "⏸️ PAUSA POR FERIADO";
                        timer.style.background = 'rgba(239, 68, 68, 0.15)'; // Rojo
                        timer.style.color = '#ef4444';
                    } else {
                        displaySpan.innerHTML = "⏸️ PAUSED (SYSTEM)";
                        timer.style.background = 'rgba(245, 158, 11, 0.15)'; // Ámbar/Naranja
                        timer.style.color = '#f59e0b';
                    }
                    return;
                }
                if (taskStatus === 'On_Hold') {
                    displaySpan.innerHTML = "⏸️ ON HOLD";
                    timer.style.background = 'rgba(245, 158, 11, 0.15)'; // Ámbar/Naranja
                    timer.style.color = '#f59e0b';
                    return;
                }

                if (diffMs <= 0) {
                    displaySpan.innerHTML = "00:00:00:00 (OVERDUE)";
                    timer.style.background = 'rgba(239, 68, 68, 0.2)';
                    timer.style.color = '#ef4444';
                    
                    // Si se acaba el tiempo y estaba Activa, marcar como Overdue y pedir justificación
                    if (taskStatus === 'Active') {
                        timer.setAttribute('data-status', 'Overdue'); // Evitar un loop de llamadas
                        handleOverdueTask(taskId);
                    }
                    return;
                }
                
                // Calcular Días, Horas, Minutos y Segundos
                const totalSecs = Math.floor(diffMs / 1000);
                const days = Math.floor(totalSecs / 86400);
                const hours = Math.floor((totalSecs % 86400) / 3600);
                const mins = Math.floor((totalSecs % 3600) / 60);
                const secs = totalSecs % 60;
                
                // Formateo a DD:HH:MM:SS
                const dStr = String(days).padStart(2, '0');
                const hStr = String(hours).padStart(2, '0');
                const mStr = String(mins).padStart(2, '0');
                const sStr = String(secs).padStart(2, '0');
                
                displaySpan.innerHTML = `${dStr}:${hStr}:${mStr}:${sStr}`;
            });
        }, 1000);
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
        modalEl.setAttribute('data-bs-backdrop', 'static');
        modalEl.setAttribute('data-bs-keyboard', 'false');
        
        const modal = new bootstrap.Modal(modalEl);
        modal.show();

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

    // --- FLUJO: RESET PROJECT TASKS (DANGER ZONE) ---
    function openResetTasksModal() {
        const form = document.getElementById('resetTasksForm');
        if (form) form.reset();
        const modal = new bootstrap.Modal(document.getElementById('resetTasksModal'));
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
                            bootstrap.Modal.getInstance(document.getElementById('resetTasksModal')).hide();
                            loadSmartPMTasks(); // Recargará la vista vacía y ocultará el botón automáticamente
                        } else {
                            alert('Error: ' + d.message);
                        }
                    })
                    .catch(e => { console.error(e); alert('Connection error. Check console.'); })
                    .finally(() => { btn.innerHTML = originalText; btn.disabled = false; });
            });
        }
    });

    // --- FLUJO: REPORTES DE RENDIMIENTO ---
    let performanceChartInstance = null;

    function openPerformanceReportModal() {
        const select = document.getElementById('perf_report_user_select');
        select.innerHTML = '<option value="">Loading users...</option>';
        select.disabled = true;

        document.getElementById('performanceReportContent').style.display = 'none';
        document.getElementById('performanceReportEmpty').style.display = 'block';

        const modal = new bootstrap.Modal(document.getElementById('performanceReportModal'));
        modal.show();

        loadProjectUsers().then(users => {
            select.innerHTML = '<option value="">-- Select a User --</option>';
            users.forEach(u => {
                select.innerHTML += `<option value="${u.id}">${u.username} (${u.role})</option>`;
            });
            select.disabled = false;
        }).catch(e => { select.innerHTML = '<option value="">Error loading users</option>'; });
    }

    function generatePerformanceReport(userId) {
        if (!userId) {
            document.getElementById('performanceReportContent').style.display = 'none';
            document.getElementById('performanceReportEmpty').style.display = 'block';
            return;
        }

        document.getElementById('performanceReportContent').style.display = 'none';
        document.getElementById('performanceReportEmpty').innerHTML = '<i class="fas fa-spinner fa-spin fa-2x"></i><p>Generating report...</p>';
        document.getElementById('performanceReportEmpty').style.display = 'block';

        const fd = new FormData();
        fd.append('action', 'get_user_performance');
        fd.append('project_id', pId);
        fd.append('user_id', userId);

        smartPmApiCall(fd).then(d => {
            if (d.status === 'success' && d.data) {
                const report = d.data;
                document.getElementById('stat_total_estimated').innerText = report.total_estimated_hours;
                document.getElementById('stat_total_actual').innerText = report.total_actual_hours;
                document.getElementById('stat_performance_ratio').innerText = `${(report.performance_ratio * 100).toFixed(0)}%`;

                let tableHtml = '';
                report.tasks.forEach(t => {
                    const varianceClass = t.variance > 0 ? 'text-success' : (t.variance < 0 ? 'text-danger' : 'text-gray');
                    tableHtml += `<tr><td>${t.name}</td><td class="text-end">${t.estimated_hours}h</td><td class="text-end">${t.actual_hours}h</td><td class="text-end ${varianceClass}">${t.variance}h</td></tr>`;
                });
                document.getElementById('performanceTableBody').innerHTML = tableHtml;

                // Chart.js
                const ctx = document.getElementById('performanceChart').getContext('2d');
                if (performanceChartInstance) {
                    performanceChartInstance.destroy();
                }
                performanceChartInstance = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: report.tasks.map(t => t.name),
                        datasets: [
                            { label: 'Estimated Hours', data: report.tasks.map(t => t.estimated_hours), backgroundColor: 'rgba(148, 163, 184, 0.5)', borderColor: '#94a3b8', borderWidth: 1 },
                            { label: 'Actual Hours', data: report.tasks.map(t => t.actual_hours), backgroundColor: 'rgba(99, 102, 241, 0.7)', borderColor: '#6366f1', borderWidth: 1 }
                        ]
                    },
                    options: { scales: { y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.1)' } }, x: { grid: { display: false } } }, plugins: { legend: { labels: { color: '#fff' } } } }
                });

                document.getElementById('performanceReportContent').style.display = 'block';
                document.getElementById('performanceReportEmpty').style.display = 'none';
            } else {
                document.getElementById('performanceReportEmpty').innerHTML = `<i class="fas fa-info-circle fa-2x text-warning"></i><p>${d.message || 'No completed tasks found for this user.'}</p>`;
            }
        }).catch(e => {
            console.error(e);
            document.getElementById('performanceReportEmpty').innerHTML = `<i class="fas fa-times-circle fa-2x text-danger"></i><p>Failed to generate report.</p>`;
        });
    }
</script>