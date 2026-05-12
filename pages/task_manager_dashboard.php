<?php
// pages/task_manager_dashboard.php - Command Center para Task Manager
require_once __DIR__ . '/../core/auth/session.php';
require_once __DIR__ . '/../core/db/connection.php';

$userRoleRaw = $_SESSION['role'] ?? 'viewer';
$userRole = strtolower($userRoleRaw);

// Solo administradores y personal técnico (Bloqueo a Viewers)
if ($userRole === 'viewer') {
    header("Location: index.php");
    exit;
}

$isAdmin = ($userRole === 'admin');
$userName = $_SESSION['username'] ?? 'User';
$pageTitle = "Task Manager Dashboard | Brightronix";
include __DIR__ . '/../views/header.php';
?>

<style>
    :root {
        --bg-body: #1b212d;
        --bg-card: #242a38;
        --bg-input: #151a23;
        --primary: #fb5a3a;
        --primary-hover: #e14e32;
        --text-white: #ffffff;
        --text-gray: #94a3b8;
        --border-subtle: #2f384a;
    }
    body.theme-light {
        --bg-body: #e2e8f0;
        --bg-card: #ffffff;
        --bg-input: #f8fafc;
        --text-white: #0f172a;
        --text-gray: #64748b;
        --border-subtle: #cbd5e1;
    }

    .box-card { background: var(--bg-card); border-radius: 16px; border: 1px solid var(--border-subtle); padding: 24px; transition: 0.3s; }
    .form-control, .form-select { background: var(--bg-input) !important; border: 1px solid var(--border-subtle) !important; color: var(--text-white) !important; border-radius: 10px; font-size: 0.95rem; }
    .form-control:focus, .form-select:focus { border-color: var(--primary) !important; box-shadow: 0 0 0 3px rgba(251, 90, 58, 0.2) !important; }
    .form-control::placeholder { color: var(--text-gray) !important; opacity: 1; }
    
    /* FASE 46: UI Improvements */
    .form-control.clean-input {
        background: rgba(255, 255, 255, 0.05) !important;
        border: 1px solid transparent !important;
        border-bottom: 1px solid var(--border-subtle) !important;
        border-radius: 4px 4px 0 0;
        box-shadow: none !important;
        transition: 0.2s;
    }
    .form-control.clean-input:focus {
        background: rgba(255, 255, 255, 0.1) !important;
        border-bottom-color: var(--primary) !important;
    }

    .builder-canvas { background: var(--bg-input); border-radius: 16px; min-height: 600px; padding: 20px; border: 2px dashed var(--border-subtle); }
    
    .stage-card { background: var(--bg-card); border: none; border-radius: 12px; padding: 15px; margin-bottom: 20px; border-left: 4px solid var(--primary); box-shadow: 0 8px 24px rgba(0,0,0,0.15); }
    .stage-card .stage-header { display: flex; gap: 15px; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px dashed var(--border-subtle); }
    
    .task-row { background: var(--bg-body); border: 1px solid var(--border-subtle); border-radius: 8px; padding: 8px 12px; display: flex; gap: 10px; align-items: center; margin-bottom: 8px; transition: 0.2s; }
    .task-row:hover { border-color: var(--primary); }

    .task-hours-badge {
        background: rgba(251, 90, 58, 0.1);
        color: var(--primary);
        border: 1px solid rgba(251, 90, 58, 0.3);
        border-radius: 6px;
        font-weight: bold;
    }
    
    .btn-main { background-color: var(--primary) !important; border-color: var(--primary) !important; color: white !important; font-weight: bold; border-radius: 8px; }
    .btn-main:hover { background-color: var(--primary-hover) !important; border-color: var(--primary-hover) !important; }

    /* FASE 59: Live Radar Animation */
    @keyframes pulse-red {
        0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
        70% { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
        100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
    }
    .live-dot {
        width: 12px;
        height: 12px;
        background-color: #ef4444;
        border-radius: 50%;
        animation: pulse-red 2s infinite;
    }

    /* FASE 61: Builder Canvas Kanban Style */
    .builder-canvas-wrapper {
        display: flex;
        gap: 1.25rem;
        overflow-x: auto;
        padding: 0.5rem 0.5rem 1rem 0.5rem;
        min-height: 500px;
        align-items: flex-start;
    }
    .builder-canvas-wrapper::-webkit-scrollbar { height: 8px; }
    .builder-canvas-wrapper::-webkit-scrollbar-thumb { background: var(--border-subtle); border-radius: 4px; }
    
    .stage-column {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid var(--border-subtle);
        border-radius: 14px;
        width: 320px;
        min-width: 320px;
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        max-height: 75vh;
        transition: transform 0.2s, opacity 0.2s; /* FASE 86/87: Animación suave */
    }

    /* FASE 86/87: Estado visual de Arrastre (Drag) */
    .stage-column.is-dragging { opacity: 0.5; border: 2px dashed var(--primary); background: rgba(0,0,0,0.2); transform: scale(0.98); }
    body.theme-light .stage-column.is-dragging { background: #f8f9fa; }
    .stage-drag-handle { cursor: grab; color: var(--text-gray); margin-right: 10px; font-size: 1.2rem; }
    .stage-drag-handle:active { cursor: grabbing; }

    /* FASE AUDITORIA 1: Optimización de Hover States */
    .btn-remove-item { opacity: 0.5; transition: opacity 0.2s; }
    .btn-remove-item:hover { opacity: 1 !important; color: #ef4444 !important; }
    
    .btn-add-task-stage { background: rgba(255,255,255,0.05); border: 1px dashed var(--border-subtle); color: var(--text-gray); transition: 0.2s; }
    .btn-add-task-stage:hover { border-color: var(--primary) !important; color: var(--text-white) !important; background: rgba(251, 90, 58, 0.05); }

    /* FASE 88: Drag & Drop Tareas (Vertical) */
    .task-row.is-dragging { opacity: 0.5; border: 2px dashed var(--primary); background: rgba(0,0,0,0.2); transform: scale(0.98); }
    body.theme-light .task-row.is-dragging { background: #f8f9fa; }

    .stage-column-header {
        padding: 1rem;
        border-bottom: 2px solid var(--primary);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: rgba(0,0,0,0.15);
        border-radius: 14px 14px 0 0;
    }
    .stage-column-body {
        padding: 1rem;
        overflow-y: auto;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .stage-column-body::-webkit-scrollbar { width: 4px; }
    .stage-column-body::-webkit-scrollbar-thumb { background: var(--border-subtle); border-radius: 4px; }
    
    .stage-column-footer {
        padding: 0.75rem 1rem;
        border-top: 1px solid rgba(255,255,255,0.05);
    }
    .task-card-inline {
        background: var(--bg-card);
        border: 1px solid var(--border-subtle);
        border-radius: 8px;
        padding: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        transition: 0.2s;
    }
    .task-card-inline:hover {
        border-color: var(--primary);
        box-shadow: 0 6px 12px rgba(0,0,0,0.3);
    }
    .input-minimal {
        background: transparent;
        border: none;
        color: var(--text-white);
        font-weight: 600;
        font-size: 0.95rem;
        width: 100%;
        outline: none;
        padding: 2px 0;
    }
    .input-minimal:focus {
        border-bottom: 1px solid var(--primary);
    }
    .input-badge-hours {
        background: rgba(255,255,255,0.05);
        border: 1px solid transparent;
        color: var(--text-white);
        border-radius: 12px;
        padding: 2px 4px;
        width: 45px;
        text-align: center;
        font-size: 0.75rem;
        font-weight: bold;
        outline: none;
        transition: 0.2s;
    }
    .input-badge-hours:focus {
        background: var(--primary);
        color: white;
    }
    .btn-add-stage-column {
        width: 320px;
        min-width: 320px;
        flex-shrink: 0;
        border: 2px dashed var(--border-subtle);
        background: rgba(255,255,255,0.02);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        height: 60px;
        color: var(--text-gray);
        font-weight: bold;
        cursor: pointer;
        transition: 0.2s;
    }
    .btn-add-stage-column:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: rgba(251, 90, 58, 0.05);
    }

    /* FASE 62: Toast Message */
    #toast-container { position: fixed; bottom: 30px; left: 30px; z-index: 9999; }
    .toast-msg {
        background: var(--bg-card); border: 1px solid var(--border-subtle);
        padding: 12px 20px; border-radius: 12px; margin-top: 10px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5); color: white;
        display: flex; align-items: center; gap: 10px;
        animation: slideIn 0.3s;
    }
    @keyframes slideIn { from { transform: translateX(-50px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

    /* FASE 65: Timeline Styles */
    .timeline-item {
        border-left: 2px solid var(--border-subtle);
        padding-left: 20px;
        position: relative;
        margin-bottom: 20px;
    }
    .timeline-item::before {
        content: ''; position: absolute; left: -8px; top: 0;
        width: 14px; height: 14px; border-radius: 50%;
        background: var(--bg-card); border: 2px solid var(--border-subtle);
    }
    .timeline-item.overdue::before { border-color: #ef4444; background: #ef4444; }
    .timeline-item.hold::before { border-color: #f59e0b; background: #f59e0b; }

    /* --- THEME LIGHT OVERRIDES (Contraste y Legibilidad) --- */
    body.theme-light .box-card { box-shadow: 0 4px 12px rgba(15,23,42,0.04); }
    body.theme-light .stage-column { background: #f8fafc; border-color: #cbd5e1; }
    body.theme-light .stage-column-header { background: #e2e8f0; border-bottom-color: var(--primary); }
    body.theme-light .task-row, 
    body.theme-light .task-card-inline { background: #ffffff; border-color: #cbd5e1; box-shadow: 0 2px 4px rgba(15,23,42,0.04); }
    body.theme-light .btn-add-stage-column { background: #ffffff; border-color: #cbd5e1; color: #475569; }
    body.theme-light .btn-add-stage-column:hover { background: #f1f5f9; border-color: var(--primary); color: var(--primary); }
    body.theme-light .input-minimal { color: #0f172a; }
    body.theme-light .input-badge-hours { background: #e2e8f0; color: #0f172a; border-color: #cbd5e1; }
    body.theme-light .input-badge-hours:focus { background: var(--primary); color: white; }
    
    
    body.theme-light .bg-dark.border.border-secondary { background-color: #f1f5f9 !important; border-color: #e2e8f0 !important; }
    body.theme-light .table-dark { color: #0f172a; background-color: transparent; }
    body.theme-light .table-dark th, body.theme-light .table-dark td { border-color: #e2e8f0 !important; color: #0f172a !important; }
    body.theme-light .table-dark thead th { background-color: #e2e8f0 !important; }
    body.theme-light .timeline-item::before { background: #ffffff; }
    
    /* --- USER PERFORMANCE REPORT STYLES --- */
    .perf-stat-card {
        background: var(--bg-input);
        border: 1px solid var(--border-subtle);
        border-radius: 12px;
        padding: 1.25rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .perf-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        border-color: var(--primary);
    }
    .perf-stat-icon {
        width: 48px; height: 48px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
    }
    .perf-stat-icon.est { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
    .perf-stat-icon.act { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    .perf-stat-icon.rat { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }

    .perf-table-wrapper {
        background: var(--bg-input); border: 1px solid var(--border-subtle);
        border-radius: 12px; overflow: hidden;
    }
    .perf-table { width: 100%; border-collapse: collapse; margin: 0; font-size: 0.9rem; }
    .perf-table th {
        background: rgba(0,0,0,0.2); color: var(--text-gray); font-weight: 700;
        text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px;
        padding: 12px 16px; border-bottom: 1px solid var(--border-subtle); white-space: nowrap;
    }
    .perf-table td { padding: 12px 16px; color: var(--text-white); border-bottom: 1px solid var(--border-subtle); vertical-align: middle; }
    .perf-table tr:last-child td { border-bottom: none; }
    .perf-table tr:hover td { background: rgba(255,255,255,0.02); }
    
    body.theme-light .perf-stat-card .text-white, body.theme-light .perf-table td .text-white { color: #0f172a !important; }
    body.theme-light .perf-table th { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }
    body.theme-light .perf-table td { color: #0f172a; border-color: #e2e8f0; }
    body.theme-light .perf-table tr:hover td { background: #f8fafc; }
    body.theme-light .perf-table-wrapper { border-color: #cbd5e1; }

    /* --- FASE 84: QUICK TASK FLOATING PANEL --- */
    .quick-task-panel { 
        position: fixed; top: 0; right: 0; width: 400px; height: 100vh; 
        background: var(--bg-card); border-left: 1px solid var(--border-subtle); 
        z-index: 1100; box-shadow: -10px 0 30px rgba(0,0,0,0.3); 
        transform: translateX(100%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
        display: flex; flex-direction: column; 
    }
    body.qt-panel-active .quick-task-panel { transform: translateX(0); }
    .qt-overlay { 
        position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); 
        z-index: 1090; opacity: 0; visibility: hidden; transition: all 0.3s ease; 
    }
    body.qt-panel-active .qt-overlay { opacity: 1; visibility: visible; }
    @media (max-width: 576px) { .quick-task-panel { width: 100vw; } }
</style>

<main class="main-content p-4 pt-5">
    <header class="header mb-4">
        <div class="d-flex align-items-center gap-3">
            <button class="mobile-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <div class="breadcrumbs">
                <a href="index.php">Home</a>
                <i class="fas fa-chevron-right mx-2" style="font-size:0.7rem"></i>
                <span class="text-primary fw-bold">Task Manager</span>
            </div>
        </div>
        <a href="../admin/settings.php?tab=users" class="user-pill text-decoration-none d-none d-md-inline-flex">
            <div class="avatar"><?= strtoupper(substr($userName,0,1)) ?></div>
            <div class="user-pill-info">
                <span class="user-pill-name"><?= htmlspecialchars($userName) ?></span>
                <span class="user-pill-role"><?= ucfirst($userRole) ?></span>
            </div>
        </a>
    </header>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Task Manager Dashboard</h2>
            <p class="text-gray mb-0">Central Command Center for Projects and Templates.</p>
        </div>
        <?php if($isAdmin): ?>
            <div class="d-flex gap-2">
                <a href="../admin/settings.php" class="btn btn-outline-secondary rounded-pill px-4 py-2 fw-bold shadow-sm" title="System Settings">
                    <i class="fas fa-cog me-2"></i> Settings
                </a>
                <button class="btn btn-warning rounded-pill px-4 py-2 fw-bold text-dark shadow-sm" onclick="openQuickTaskPanel()">
                    <i class="fas fa-bolt me-2"></i> Quick Task
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- FASE 63: CONTENEDOR PADRE 1 (Dashboard) -->
    <div id="dashboard-main-view">
        <!-- FASE 59: SECCIÓN LIVE OPERATIONS RADAR -->
        <section id="live-operations-radar" class="mb-5">
            <div class="d-flex align-items-center mb-3">
                <div class="live-dot me-3"></div>
                <h4 class="fw-bold text-white mb-0">Live Tasks (Running)</h4>
            </div>
            <div id="live-tasks-container" class="row g-3">
                <div class="text-gray small py-3"><i class="fas fa-spinner fa-spin me-2"></i> Searching for live operations...</div>
            </div>
        </section>
        <hr class="border-secondary my-5" style="opacity: 0.5;">

        <!-- SECCIÓN SUPERIOR: Panel de Proyectos Activos -->
        <section id="projects-master-view" class="mb-5">
            <div class="d-flex align-items-center mb-4">
                <h4 class="fw-bold text-white mb-0"><i class="fas fa-briefcase text-primary me-2"></i>Projects Health Panel</h4>
            </div>
            <div id="projects-health-container">
                <div class="text-center text-gray py-5">
                    <i class="fas fa-hard-hat fa-3x mb-3 opacity-25"></i>
                    <p>Active projects data will be loaded here.</p>
                </div>
            </div>
        </section>
        <hr class="border-secondary my-5" style="opacity: 0.5;">

        <?php if($isAdmin): ?>
        <!-- INITIAL STATE (Template Manager) -->
        <section id="template-initial-state" class="text-center py-5 mb-5">
            <div class="d-flex align-items-center mb-4 justify-content-center">
                <h4 class="fw-bold text-white mb-0"><i class="fas fa-magic text-warning me-2"></i>Master Template Manager</h4>
            </div>
            <button class="btn btn-lg btn-primary rounded-pill fw-bold px-5 py-3 mb-4 shadow-lg" onclick="openTemplateSetupModal()" style="font-size: 1.2rem;">
                <i class="fas fa-plus-circle me-2"></i> Create New Template
            </button>
            <br>
            <button class="btn btn-outline-success fw-bold rounded-pill px-4" onclick="openImportCsvModal()">
                <i class="fas fa-file-excel me-2"></i> Upload via CSV/Excel
            </button>
        </section>
        <?php endif; ?>
    </div>

    <!-- FASE 63: CONTENEDOR PADRE 2 (Builder Canvas) -->
    <div id="template-builder-view" class="d-none">
        <section id="template-builder-workspace" style="opacity: 0; transition: opacity 0.5s ease;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5 class="fw-bold text-white mb-1" id="builder_display_name">Template Name</h5>
                    <p class="text-gray small mb-0" id="builder_display_desc">Template Description</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-secondary fw-bold rounded-pill px-3" onclick="cancelBuilder()">
                        Cancel
                    </button>
                    <button id="btn-update-template" class="btn btn-sm btn-warning rounded-pill px-3 fw-bold text-dark" onclick="saveTemplate('update')" style="display: none;">
                        <i class="fas fa-sync-alt me-1"></i> Update
                    </button>
                    <button id="btn-save-template" class="btn btn-sm btn-main rounded-pill px-3" onclick="saveTemplate('clone')">
                        <i class="fas fa-save me-1"></i> Save
                    </button>
                    <button class="btn btn-sm btn-outline-info rounded-pill px-3" onclick="exportTemplateCSV()" title="Export Loaded Template to CSV">
                        <i class="fas fa-download"></i>
                    </button>
                </div>
            </div>
            
            <!-- FASE 61: Kanban Builder Canvas -->
            <div class="builder-canvas-wrapper pb-3" id="stages-container">
                <div id="empty-canvas-state" class="w-100 text-center text-gray py-5 mt-5">
                    <i class="fas fa-layer-group fa-3x mb-3 opacity-25"></i>
                    <p>Your canvas is empty. Add a Stage to begin.</p>
                    <button class="btn btn-main mt-3" onclick="addStage()"><i class="fas fa-plus me-2"></i> Add First Stage</button>
                </div>
                <div id="btn-add-stage-wrapper" style="display: none;">
                    <button class="btn-add-stage-column" onclick="addStage()">
                        <i class="fas fa-plus fa-lg me-2"></i> New Stage
                    </button>
                </div>
            </div>
        </section>
    </div>

    <?php if($isAdmin): ?>
    <!-- FASE 84: ⚡ Quick Task Floating Panel -->
    <div class="qt-overlay" onclick="closeQuickTaskPanel()"></div>
    <div class="quick-task-panel" id="quickTaskPanel">
        <div class="p-3 border-bottom border-secondary d-flex justify-content-between align-items-center" style="background: rgba(245, 158, 11, 0.1);">
            <h5 class="fw-bold text-warning mb-0"><i class="fas fa-bolt me-2"></i> Quick Task Creator</h5>
            <button type="button" class="btn-close btn-close-white" onclick="closeQuickTaskPanel()"></button>
        </div>
        <div class="p-4 flex-grow-1 overflow-auto">
            <p class="text-gray small mb-4">Create a task on the fly and send it directly to the end of any active project's timeline.</p>
            <form id="quickTaskForm" onsubmit="submitQuickTask(event)">
                <div class="mb-3">
                    <label class="text-gray small mb-2 fw-bold">Select Project <span class="text-danger">*</span></label>
                    <select id="qt_project_id" name="project_id" class="form-select" required>
                        <option value="">Loading projects...</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="text-gray small mb-2 fw-bold">Task Name <span class="text-danger">*</span></label>
                    <textarea id="qt_name" name="name" class="form-control" rows="3" placeholder="e.g. Call client to verify details..." required style="resize:none;"></textarea>
                </div>
                <div class="mb-4">
                    <label class="text-gray small mb-2 fw-bold">Estimated Time (Minutes) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text border-end-0" style="background: var(--bg-input); border-color: var(--border-subtle); color: var(--text-gray); border-radius: 10px 0 0 10px;"><i class="fas fa-clock"></i></span>
                        <input type="number" id="qt_minutes" name="estimated_minutes" class="form-control border-start-0 ps-1" placeholder="e.g. 60" required min="1" value="60" style="border-radius: 0 10px 10px 0;">
                    </div>
                </div>
                <button type="submit" class="btn btn-warning w-100 fw-bold text-dark rounded-pill py-2 shadow-sm">
                    <i class="fas fa-paper-plane me-2"></i> Send to Project
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</main>

<!-- Modal Importar CSV -->
<div class="modal fade" id="importCsvModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-file-csv text-success me-2"></i>Import Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="importCsvForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="text-gray small mb-2 fw-bold">Template Name <span class="text-danger">*</span></label>
                        <input type="text" name="template_name" class="form-control" required placeholder="e.g. Master Floor Plan Flow">
                    </div>
                    <div class="mb-3">
                        <label class="text-gray small mb-2 fw-bold">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Brief description..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="text-gray small mb-2 fw-bold">CSV File <span class="text-danger">*</span></label>
                        <input type="file" name="csv_file" accept=".csv" class="form-control" style="padding: 6px 12px;" required>
                    </div>
                    <div class="bg-dark border border-secondary rounded p-3 mt-3">
                        <p class="text-gray small fw-bold mb-2">Required CSV Format:</p>
                        <table class="table table-sm table-bordered table-dark mb-2" style="font-size:0.75rem; border-color: var(--border-subtle);">
                            <thead><tr><th class="text-gray">Stage Name</th><th class="text-gray">Task Name</th><th class="text-gray">Estimated Hours</th></tr></thead>
                            <tbody>
                                <tr><td>Pre-Construction</td><td>Permitting</td><td>8.5</td></tr>
                                <tr><td>Rough-in</td><td>Layout lines</td><td>12</td></tr>
                            </tbody>
                        </table>
                        <a href="data:text/csv;charset=utf-8,Stage%20Name%2CTask%20Name%2CEstimated%20Hours%0APre-Construction%2CPermitting%2C8.5%0ARough-in%2CLayout%20lines%2C12" download="template_sample.csv" class="small text-info text-decoration-none"><i class="fas fa-download me-1"></i> Download Sample CSV</a>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold"><i class="fas fa-upload me-2"></i>Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Setup Template (FASE 60) -->
<div class="modal fade" id="templateSetupModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-cog text-primary me-2"></i>Template Configuration</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="text-gray small fw-bold mb-1">Template Name <span class="text-danger">*</span></label>
                    <input type="text" id="setup_template_name" class="form-control" placeholder="e.g. Master Install Flow">
                </div>
                <div class="mb-3">
                    <label class="text-gray small fw-bold mb-1">Template Type <span class="text-danger">*</span></label>
                    <select id="setup_template_type" class="form-select">
                        <option value="general">General Template (Project Flow)</option>
                        <option value="rfi">RFI Block (Resolution and Sub-tasks)</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="text-gray small fw-bold mb-1">Description</label>
                    <textarea id="setup_template_desc" class="form-control" rows="3" placeholder="Brief explanation..."></textarea>
                </div>
                <div class="mb-3 p-3 border rounded border-secondary" style="background: rgba(0,0,0,0.2);">
                    <label class="form-label text-gray small fw-bold"><i class="fas fa-copy me-2 text-info"></i>Use existing template as base...</label>
                    <div class="input-group">
                        <select id="setup_baseTemplateSelect" class="form-select">
                            <option value="">-- Select Template (Optional) --</option>
                        </select>
                        <button type="button" class="btn btn-outline-danger" onclick="deleteSelectedTemplate()" title="Delete Selected Template">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary rounded-pill px-4 fw-bold" onclick="continueToBuilder()">Continue to Builder <i class="fas fa-arrow-right ms-2"></i></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Project Alerts (FASE 65) -->
<div class="modal fade" id="projectAlertsModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content p-3" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-chart-bar text-info me-2"></i>Audit & Notes - <span id="alertsModalProjectName" class="text-primary"></span></h5>
                <div class="d-flex align-items-center">
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
                <!-- FASE 74: Filtros de Categoría para Auditoría -->
                <div class="mb-3 flex-shrink-0 d-flex gap-2 overflow-auto" style="padding-bottom: 5px; scrollbar-width: none;">
                    <button class="btn btn-sm btn-primary rounded-pill px-3 filter-alert-btn text-nowrap" data-filter="all" onclick="filterProjectAlerts('all', this)">All</button>
                    <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 filter-alert-btn text-nowrap" data-filter="notes" onclick="filterProjectAlerts('notes', this)">Manual Notes</button>
                    <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 filter-alert-btn text-nowrap" data-filter="rfis" onclick="filterProjectAlerts('rfis', this)">RFIs</button>
                    <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 filter-alert-btn text-nowrap" data-filter="time" onclick="filterProjectAlerts('time', this)">Time & Delays</button>
                    <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 filter-alert-btn text-nowrap" data-filter="status" onclick="filterProjectAlerts('status', this)">Status</button>
                </div>

                <div id="projectAlertsTimeline" class="mt-3">
                    <!-- Timeline content injected here -->
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Reporte de Rendimiento Global -->
<div class="modal fade" id="globalPerformanceReportModal" tabindex="-1" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content p-3" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-white"><i class="fas fa-chart-pie text-success me-2"></i>User Performance Report - <span id="perfModalProjectName" class="text-primary"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="text-gray small mb-2">Select User to Analyze</label>
                        <select id="global_perf_report_user_select" class="form-select" onchange="generateGlobalPerformanceReport(this.value)">
                            <option value="">-- Select a User --</option>
                        </select>
                    </div>
                    <div class="col-md-8 mb-3 d-flex align-items-end justify-content-end">
                        <button id="btnExportGlobalPerformancePdf" class="btn btn-outline-danger fw-bold d-none" onclick="exportGlobalPerformanceToPdf()">
                            <i class="fas fa-file-pdf me-2"></i> Export to PDF
                        </button>
                    </div>
                </div>
                <div id="globalPerformanceReportContent" class="mt-3" style="display:none;">
                    <!-- Estadísticas Clave -->
                    <div class="row mb-3 g-3">
                        <div class="col-md-4">
                            <div class="perf-stat-card">
                                <div><div class="text-gray small fw-bold text-uppercase mb-1">Estimated Hours</div><div class="fs-4 fw-bold text-white" id="g_stat_total_estimated">0</div></div>
                                <div class="perf-stat-icon est"><i class="fas fa-bullseye"></i></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="perf-stat-card">
                                <div><div class="text-gray small fw-bold text-uppercase mb-1">Actual Hours</div><div class="fs-4 fw-bold text-white" id="g_stat_total_actual">0</div></div>
                                <div class="perf-stat-icon act"><i class="fas fa-stopwatch"></i></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="perf-stat-card">
                                <div><div class="text-gray small fw-bold text-uppercase mb-1">Performance Ratio</div><div class="fs-4 fw-bold text-white" id="g_stat_performance_ratio">0%</div></div>
                                <div class="perf-stat-icon rat"><i class="fas fa-tachometer-alt"></i></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="perf-stat-card">
                                <div><div class="text-gray small fw-bold text-uppercase mb-1">Completed Tasks</div><div class="fs-4 fw-bold text-white" id="g_stat_completed_tasks">0 / 0</div></div>
                                <div class="perf-stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;"><i class="fas fa-check-double"></i></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="perf-stat-card">
                                <div><div class="text-gray small fw-bold text-uppercase mb-1">Tasks Left to Complete</div><div class="fs-4 fw-bold text-white" id="g_stat_left_tasks">0</div></div>
                                <div class="perf-stat-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;"><i class="fas fa-tasks"></i></div>
                            </div>
                        </div>
                    </div>
                    <!-- Gráfico -->
                    <div class="mb-4 d-flex justify-content-center align-items-center" style="width: 100%; height: 350px; max-height: 400px;">
                        <canvas id="globalPerformanceChart"></canvas>
                    </div>
                    <!-- Tabla de Desglose -->
                    <h6 class="text-white fw-bold mb-3" id="g_perfBreakdownTitle">Assigned Tasks Breakdown</h6>
                    <div id="globalPerformanceTableBody" class="d-flex flex-column gap-3" style="max-height: 400px; overflow-y: auto; padding-right: 5px;"></div>
                </div>
                <div id="globalPerformanceReportEmpty" class="text-center text-gray py-5">
                    <i class="fas fa-user-check fa-2x mb-3"></i>
                    <p>Select a user to view their complete assigned workload performance.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="toast-container"></div>

<script>
    const userIsAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
    let stageCounter = 0;

    // --- FASE 59: LÓGICA DE RADAR EN VIVO ---
    let liveDashTimerInterval = null;

    function loadLiveOperations() {
        const container = document.getElementById('live-tasks-container');
        
        const fd = new FormData();
        fd.append('action', 'get_global_active_tasks');
        
        fetch('../task_manager/api.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.status === 'success') {
                    if (!d.data || d.data.length === 0) {
                        container.innerHTML = `
                            <div class="col-12">
                                <div class="p-4 rounded text-center" style="background: rgba(255,255,255,0.02); border: 1px dashed var(--border-subtle);">
                                    <div class="text-gray mb-1"><i class="fas fa-bed fa-2x mb-2 opacity-50"></i></div>
                                    <div class="text-gray fw-bold">No tasks are currently running. The team is on pause.</div>
                                </div>
                            </div>`;
                        return;
                    }
                    
                    let html = '';
                    d.data.forEach(t => {
                        let timerHtml = '';
                        let cardStyle = 'border-left: 4px solid var(--primary); background: rgba(251, 90, 58, 0.03);';
                        
                        if (t.status === 'On_Hold' || t.status === 'System_Pause') {
                            const pauseText = t.status === 'System_Pause' ? 'PAUSED (SYSTEM)' : 'ON HOLD';
                            cardStyle = 'border-left: 4px solid #eab308; background: rgba(234, 179, 8, 0.05);';
                            timerHtml = `<div class="dash-countdown-timer" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; padding: 6px 12px; border-radius: 8px; font-family: monospace; font-weight: bold; font-size: 1rem; display: inline-block;">
                                <i class="fas fa-pause-circle me-1"></i> <span class="time-display">⏸️ ${pauseText}</span>
                            </div>`;
                        } else if (t.status === 'Overdue') {
                            cardStyle = 'border-left: 4px solid #ef4444; background: rgba(239, 68, 68, 0.05);';
                            timerHtml = `<div class="dash-countdown-timer" data-est="${t.estimated_minutes}" data-worked="${t.worked_minutes}" data-start="${t.start_time}" data-status="${t.status}" style="background: rgba(239, 68, 68, 0.2); color: #ef4444; padding: 6px 12px; border-radius: 8px; font-family: monospace; font-weight: bold; font-size: 1rem; display: inline-block;">
                                <i class="fas fa-exclamation-triangle me-1"></i> <span class="time-display">00:00:00:00 (OVERDUE)</span>
                            </div>`;
                        } else {
                            timerHtml = `<div class="dash-countdown-timer" data-est="${t.estimated_minutes}" data-worked="${t.worked_minutes}" data-start="${t.start_time}" data-status="${t.status}" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 6px 12px; border-radius: 8px; font-family: monospace; font-weight: bold; font-size: 1rem; display: inline-block;">
                                <i class="fas fa-stopwatch me-1"></i> <span class="time-display">Calculating...</span>
                            </div>`;
                        }

                        html += `
                        <div class="col-md-6 col-lg-4 col-xl-3">
                            <div class="box-card h-100 d-flex flex-column" style="cursor: pointer; padding: 1.25rem; ${cardStyle}" onclick="window.location.href='project_dashboard.php?id=${t.project_id}&open_task_manager=true'">
                                <div class="small text-accent fw-bold text-truncate mb-1" style="color: #3b82f6;"><i class="fas fa-folder-open me-1"></i> ${t.project_name}</div>
                                <div class="fw-bold text-white mb-2" style="font-size: 1.1rem; line-height: 1.2;">${t.task_name}</div>
                                <div class="text-gray small mb-3"><i class="fas fa-hard-hat me-1 text-warning"></i> ${t.assigned_user_name || 'Unassigned'}</div>
                                
                                <div class="mt-auto">
                                    ${timerHtml}
                                </div>
                            </div>
                        </div>`;
                    });
                    container.innerHTML = html;
                    startLiveDashTimers();
                } else {
                    container.innerHTML = `<div class="col-12 text-danger small">Error loading radar: ${d.message}</div>`;
                }
            })
            .catch(e => {
                container.innerHTML = `<div class="col-12 text-danger small">Connection error loading live radar.</div>`;
            });
    }

    // --- FASE 60: MODAL SETUP Y FLUJO BUILDER ---
    let currentTemplateName = '';
    let currentTemplateDesc = '';

    function openTemplateSetupModal() {
        document.getElementById('setup_template_name').value = '';
        document.getElementById('setup_template_desc').value = '';
        document.getElementById('setup_baseTemplateSelect').value = '';
        const typeSelect = document.getElementById('setup_template_type');
        if (typeSelect) typeSelect.value = 'general';
        currentLoadedTemplateId = null;
        const modal = new bootstrap.Modal(document.getElementById('templateSetupModal'));
        modal.show();
    }

    document.getElementById('setup_baseTemplateSelect').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (this.value) {
            document.getElementById('setup_template_name').value = selectedOption.text;
        }
    });

    function continueToBuilder() {
        const nameInput = document.getElementById('setup_template_name');
        const descInput = document.getElementById('setup_template_desc');
        const baseSelect = document.getElementById('setup_baseTemplateSelect');
        const typeSelect = document.getElementById('setup_template_type');
        
        let name = nameInput.value.trim();
        if (!name) {
            appAlert('Please enter the template name.', 'Notice', 'warning');
            nameInput.focus();
            return;
        }
        
        // FASE 76: Asegurar nomenclatura para que el backend la asigne al Banco RFI
        if (typeSelect && typeSelect.value === 'rfi' && !name.toUpperCase().includes('RFI')) {
            name = 'RFI - ' + name;
        }

        currentTemplateName = name;
        currentTemplateDesc = descInput.value.trim();
        
        document.getElementById('builder_display_name').innerText = currentTemplateName;
        document.getElementById('builder_display_desc').innerText = currentTemplateDesc || 'No description';
        
        const baseTemplateId = baseSelect.value;
        
        bootstrap.Modal.getInstance(document.getElementById('templateSetupModal')).hide();
        
        // FASE 63: Strict Layout Isolation
        document.getElementById('dashboard-main-view').classList.add('d-none');
        
        const builderView = document.getElementById('template-builder-view');
        builderView.classList.remove('d-none');
        
        const workspace = document.getElementById('template-builder-workspace');
        setTimeout(() => { workspace.style.opacity = '1'; }, 50);
        
        document.getElementById('stages-container').innerHTML = `
            <div id="empty-canvas-state" class="w-100 text-center text-gray py-5 mt-5"><i class="fas fa-layer-group fa-3x mb-3 opacity-25"></i><p>Your canvas is empty. Add a Stage to begin.</p><button class="btn btn-main mt-3" onclick="addStage()"><i class="fas fa-plus me-2"></i> Add First Stage</button></div>
            <div id="btn-add-stage-wrapper" style="display: none;">
                <button class="btn-add-stage-column" onclick="addStage()"><i class="fas fa-plus fa-lg me-2"></i> New Stage</button>
            </div>
        `;
        stageCounter = 0;
        
        if (baseTemplateId) {
            loadBaseTemplate(baseTemplateId, true);
        } else {
            document.getElementById('btn-update-template').style.display = 'none';
        }
    }

    function cancelBuilder() {
        appConfirm("Are you sure you want to cancel? Unsaved changes will be lost.", "Confirm Cancellation", () => {
            const workspace = document.getElementById('template-builder-workspace');
            workspace.style.opacity = '0';
            
            setTimeout(() => {
                document.getElementById('template-builder-view').classList.add('d-none');
                document.getElementById('dashboard-main-view').classList.remove('d-none');
                
                // FASE 62: Limpieza de Canvas al cancelar
                document.getElementById('stages-container').innerHTML = `
                    <div id="empty-canvas-state" class="w-100 text-center text-gray py-5 mt-5"><i class="fas fa-layer-group fa-3x mb-3 opacity-25"></i><p>Your canvas is empty. Add a Stage to begin.</p><button class="btn btn-main mt-3" onclick="addStage()"><i class="fas fa-plus me-2"></i> Add First Stage</button></div>
                    <div id="btn-add-stage-wrapper" style="display: none;">
                        <button class="btn-add-stage-column" onclick="addStage()"><i class="fas fa-plus fa-lg me-2"></i> New Stage</button>
                    </div>
                `;
                stageCounter = 0;
            }, 300);
        });
    }

    // Agrega una nueva "Tarjeta" de Etapa al Lienzo
    function addStage() {
        document.getElementById('empty-canvas-state').style.display = 'none';
        document.getElementById('btn-add-stage-wrapper').style.display = 'block';
        stageCounter++;
        const stageId = 'stage-' + stageCounter;
        
        const stageHtml = `
            <div class="stage-column" id="${stageId}">
                <div class="stage-column-header">
                    <i class="fas fa-grip-lines stage-drag-handle" title="Drag stage"></i>
                    <input type="text" class="input-minimal stage-name-input" placeholder="Stage Name (e.g. Rough-in)...">
                    <button type="button" class="btn btn-sm text-danger p-0 ms-2 btn-remove-item" onclick="removeStage('${stageId}')" title="Delete Stage"><i class="fas fa-trash"></i></button>
                </div>
                <div class="stage-column-body tasks-container">
                </div>
                <div class="stage-column-footer">
                    <button type="button" class="btn btn-sm w-100 rounded-pill fw-bold btn-add-task-stage" onclick="addTask('${stageId}')">
                        <i class="fas fa-plus me-1"></i> Add Task
                    </button>
                </div>
            </div>
        `;
        
        document.getElementById('btn-add-stage-wrapper').insertAdjacentHTML('beforebegin', stageHtml);
        addTask(stageId); // Inyecta la primera tarea por defecto
    }

    // Remueve una Etapa completa
    function removeStage(stageId) {
        const stageEl = document.getElementById(stageId);
        if(stageEl) {
            stageEl.remove();
            if (document.querySelectorAll('.stage-column').length === 0) {
                document.getElementById('empty-canvas-state').style.display = 'block';
                document.getElementById('btn-add-stage-wrapper').style.display = 'none';
            }
        }
    }

    // Agrega una Tarea a una Etapa
    function addTask(stageId, name = '', hours = 8) {
        const container = document.querySelector(`#${stageId} .tasks-container`);
        const formattedHours = Number.isInteger(hours) ? hours : hours.toFixed(1);
        const taskHtml = `
            <div class="task-card-inline task-row">
                <i class="fas fa-grip-vertical text-muted opacity-50" style="cursor: grab; font-size: 0.8rem;"></i>
                <input type="text" class="input-minimal task-name-input flex-grow-1" placeholder="Task name..." value="${name.replace(/"/g, '&quot;')}">
                <div class="d-flex align-items-center gap-1 flex-shrink-0 bg-dark rounded-pill px-2 py-1" style="border: 1px solid var(--border-subtle);" title="Estimated Hours">
                    <i class="fas fa-stopwatch text-warning small"></i>
                    <input type="number" class="input-badge-hours task-hours-input" min="0.5" step="0.5" value="${formattedHours}">
                </div>
                <button type="button" class="btn btn-sm text-danger p-0 ms-1 btn-remove-item" onclick="this.closest('.task-row').remove()" title="Remove Task"><i class="fas fa-times"></i></button>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', taskHtml);
    }

    let currentLoadedTemplateId = null;
    
    function refreshTemplatesList() {
        const fd = new FormData();
        fd.append('action', 'get_templates');
        fetch('../task_manager/api.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.status === 'success') {
                    const sel = document.getElementById('setup_baseTemplateSelect');
                    if (sel) {
                        sel.innerHTML = '<option value="">-- Select Template (Optional) --</option>';
                        d.data.forEach(t => {
                            sel.innerHTML += `<option value="${t.id}">${t.name}</option>`;
                        });
                    }
                }
            });
    }

    // --- FUNCIÓN: ELIMINAR PLANTILLA ---
    function deleteSelectedTemplate() {
        const select = document.getElementById('setup_baseTemplateSelect');
        const templateId = select.value;
        if (!templateId) {
            appAlert('Please select a template from the list to delete.', 'Notice', 'warning');
            return;
        }
        
        appConfirm('Are you sure you want to delete this template? This action cannot be undone.', 'Delete Template', () => {
            const fd = new FormData();
            fd.append('action', 'delete_template');
            fd.append('template_id', templateId);

            fetch('../task_manager/api.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.status === 'success') {
                        appAlert('Template successfully deleted.', 'Success', 'success');
                        refreshTemplatesList(); // Refresca el Dropdown
                        document.getElementById('setup_template_name').value = ''; // Limpia el título
                    } else {
                        appAlert('Error: ' + (d.message || d.msg), 'Error', 'error');
                    }
                })
                .catch(e => { appAlert('Connection error.', 'Error', 'error'); });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        loadLiveOperations();
        setInterval(loadLiveOperations, 60000); // Refrescar radar cada 60s
        
        refreshTemplatesList();
            
        loadActiveProjectsHealth(); // FASE 45: Cargar panel general
        loadQuickTaskProjects(); // FASE 84: Cargar dropdown de Quick Task

        initDragAndDrop(); // FASE 87: Inicializar lógica de arrastrar y soltar
    });

    function loadBaseTemplate(templateId, bypassConfirm = false) {
        if (!templateId) return;

        const loadContent = () => {
            const fd = new FormData();
            fd.append('action', 'get_template_full');
            fd.append('template_id', templateId);

            fetch('../task_manager/api.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if (d.status === 'success') {
                        currentLoadedTemplateId = templateId;
                        document.getElementById('btn-update-template').style.display = 'inline-block';
                        loadTemplateToCanvas(d.data);
                    } else {
                        appAlert("Error loading template: " + d.message, "Error", "error");
                    }
                });
        };

        if (!bypassConfirm && document.querySelectorAll('.stage-column').length > 0) {
            appConfirm("Loading a template will replace the current canvas content. Do you want to continue?", "Load Template", () => {
                loadContent();
            });
            document.getElementById('setup_baseTemplateSelect').value = '';
            return;
        }
        
        loadContent();
    }

    function loadTemplateToCanvas(templateData) {
        const canvas = document.getElementById('stages-container');
        if(canvas) {
            canvas.innerHTML = `
                <div id="empty-canvas-state" class="w-100 text-center text-gray py-5 mt-5" style="display: none;"><i class="fas fa-layer-group fa-3x mb-3 opacity-25"></i><p>Your canvas is empty. Add a Stage to begin.</p><button class="btn btn-main mt-3" onclick="addStage()"><i class="fas fa-plus me-2"></i> Add First Stage</button></div>
                <div id="btn-add-stage-wrapper">
                    <button class="btn-add-stage-column" onclick="addStage()"><i class="fas fa-plus fa-lg me-2"></i> New Stage</button>
                </div>
            `;
        }

        stageCounter = 0;
        templateData.stages.forEach(stage => {
            stageCounter++;
            const stageId = 'stage-' + stageCounter;
            
            const stageHtml = `
                <div class="stage-column" id="${stageId}">
                    <div class="stage-column-header">
                        <i class="fas fa-grip-lines stage-drag-handle" title="Drag stage"></i>
                        <input type="text" class="input-minimal stage-name-input" placeholder="Stage Name (e.g. Rough-in)..." value="${stage.name.replace(/"/g, '&quot;')}">
                        <button type="button" class="btn btn-sm text-danger p-0 ms-2 btn-remove-item" onclick="removeStage('${stageId}')" title="Delete Stage"><i class="fas fa-trash"></i></button>
                    </div>
                    <div class="stage-column-body tasks-container">
                    </div>
                    <div class="stage-column-footer">
                        <button type="button" class="btn btn-sm w-100 rounded-pill fw-bold btn-add-task-stage" onclick="addTask('${stageId}')">
                            <i class="fas fa-plus me-1"></i> Add Task
                        </button>
                    </div>
                </div>
            `;
            
            document.getElementById('btn-add-stage-wrapper').insertAdjacentHTML('beforebegin', stageHtml);
            
            const container = document.querySelector(`#${stageId} .tasks-container`);
            stage.tasks.forEach(task => {
                const hours = task.estimated_minutes ? (task.estimated_minutes / 60) : 8;
                addTask(stageId, task.name, hours);
            });
        });

        document.getElementById('setup_baseTemplateSelect').value = '';
    }

    // --- FASE 47: EXPORTAR PLANTILLA A CSV ---
    function exportTemplateCSV() {
        if (!currentLoadedTemplateId) {
            appAlert("Please select and load a template first to export it.", "Notice", "warning");
            return;
        }
        window.location.href = '../task_manager/api.php?action=export_template_csv&template_id=' + currentLoadedTemplateId;
    }

    function startLiveDashTimers() {
        if (liveDashTimerInterval) clearInterval(liveDashTimerInterval);
        
        // AUDITORÍA 2: Caché de variables y Single Date Instantiation
        const timers = document.querySelectorAll('.dash-countdown-timer');
        const timerData = [];
        
        timers.forEach(timer => {
            const status = timer.getAttribute('data-status');
            const estMins = parseInt(timer.getAttribute('data-est')) || 0;
            const workedMins = parseInt(timer.getAttribute('data-worked')) || 0;
            const startStr = timer.getAttribute('data-start');
            
            timerData.push({
                element: timer,
                displaySpan: timer.querySelector('.time-display'),
                status: status,
                estSecs: estMins * 60,
                baseWorkedSecs: workedMins * 60,
                startObj: startStr ? new Date(startStr.replace(/-/g, '/')) : null
            });
        });

        if (timerData.length === 0) return;

        liveDashTimerInterval = setInterval(() => {
            const now = new Date(); // Una sola vez por tick
            timerData.forEach(data => {
                if (!data.displaySpan) return;
                
                let totalWorkedSecs = data.baseWorkedSecs;
                if (data.status === 'Active' && data.startObj) {
                    const diffSecs = Math.max(0, Math.floor((now - data.startObj) / 1000));
                    totalWorkedSecs += diffSecs;
                }
                
                const remainingSecs = data.estSecs - totalWorkedSecs;

                if (remainingSecs <= 0 || data.status === 'Overdue') {
                    data.displaySpan.innerHTML = "00:00:00:00 (OVERDUE)";
                    data.element.style.background = 'rgba(239, 68, 68, 0.2)';
                    return;
                }
                
                const hours = Math.floor(remainingSecs / 3600);
                const mins = Math.floor((remainingSecs % 3600) / 60);
                const secs = remainingSecs % 60;
                
                data.displaySpan.innerHTML = `${String(hours).padStart(2, '0')}h ${String(mins).padStart(2, '0')}m ${String(secs).padStart(2, '0')}s`;
            });
        }, 1000);
    }

    // --- FASE 45: MASTER VIEW DE PROYECTOS Y HEALTH DATA ---
    function loadActiveProjectsHealth() {
        const container = document.getElementById('projects-health-container');
        container.innerHTML = '<div class="text-center text-gray py-5"><i class="fas fa-spinner fa-spin fa-2x text-primary mb-3"></i><p>Loading projects health data...</p></div>';
        
        const fd = new FormData();
        fd.append('action', 'get_all_projects_health');
        
        fetch('../task_manager/api.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.status === 'success') {
                    if (!d.data || d.data.length === 0) {
                        container.innerHTML = '<div class="box-card text-center text-gray py-5"><i class="fas fa-hard-hat fa-3x mb-3 opacity-25"></i><p>No active projects found.</p></div>';
                        return;
                    }
                    let html = '<div class="row g-3">';
                    let separatorInjected = false; // "Without tasks"
                    let completedSeparatorInjected = false; // "Completed"
                    
                    d.data.forEach((p, idx) => {
                        const h = p.health;
                        const isCompleted = p.project_status === 'Completed';
                        const hasTasks = h.total_tasks > 0;

                        // Inyectar separadores visuales dinámicamente
                        if (isCompleted && !completedSeparatorInjected) {
                            html += `
                                <div class="col-12 my-3 mt-4">
                                    <div class="d-flex align-items-center">
                                        <hr class="flex-grow-1 border-secondary" style="opacity: 0.3;">
                                        <span class="px-3 text-gray small fw-bold text-uppercase" style="letter-spacing: 1px;"><i class="fas fa-check-double text-success me-1"></i> Completed Projects</span>
                                        <hr class="flex-grow-1 border-secondary" style="opacity: 0.3;">
                                    </div>
                                </div>`;
                            completedSeparatorInjected = true;
                        } else if (!isCompleted && !hasTasks && !separatorInjected) {
                            html += `
                                <div class="col-12 my-3 mt-4">
                                    <div class="d-flex align-items-center">
                                        <hr class="flex-grow-1 border-secondary" style="opacity: 0.3;">
                                        <span class="px-3 text-gray small fw-bold text-uppercase" style="letter-spacing: 1px;">Active Projects without Task Manager</span>
                                        <hr class="flex-grow-1 border-secondary" style="opacity: 0.3;">
                                    </div>
                                </div>`;
                            separatorInjected = true;
                        }

                        const pct = h.total_tasks > 0 ? Math.round((h.completed_tasks / h.total_tasks) * 100) : 0;
                        const opacityStyle = isCompleted ? 'opacity: 0.75;' : '';
                        const badgeColor = isCompleted ? 'bg-success' : (pct === 100 ? 'bg-success' : 'bg-primary');
                        const statusIcon = isCompleted ? '<i class="fas fa-trophy text-warning ms-2" title="Completed"></i>' : '';
                        
                        let adminBtns = '';
                        if (userIsAdmin) {
                            adminBtns = `
                                <button class="btn btn-sm btn-outline-info flex-grow-1 rounded-pill fw-bold" onclick="openProjectAlertsModal(${p.project_id}, '${p.project_name.replace(/'/g, "\\'")}')" title="Audit & Notes"><i class="fas fa-chart-bar me-1"></i> Audit</button>
                                <button class="btn btn-sm btn-outline-success flex-grow-1 rounded-pill fw-bold" onclick="openGlobalPerformanceReportModal(${p.project_id}, '${p.project_name.replace(/'/g, "\\'")}')" title="Performance"><i class="fas fa-chart-pie me-1"></i> Perf.</button>
                            `;
                        }

                        html += `
                        <div class="col-md-6 col-xl-4">
                            <div class="box-card h-100 d-flex flex-column" style="padding: 1.25rem; ${opacityStyle}">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h6 class="fw-bold text-white mb-0 text-truncate" style="max-width: 75%;">${p.project_name} ${statusIcon}</h6>
                                    <span class="badge ${badgeColor} rounded-pill">${pct}%</span>
                                </div>
                                
                                <div class="d-flex flex-column mb-4" style="gap: 6px;">
                                    <div style="font-size: 2rem; font-weight: bold; line-height: 1; color: var(--text-white);">
                                        ${h.completed_tasks} / ${h.total_tasks}
                                    </div>
                                    <div style="color: #6c757d; font-size: 0.9rem; font-weight: 500;">
                                        <i class="fas fa-stopwatch me-1 text-warning"></i> ${h.hours_worked}h Worked / ${h.hours_remaining}h Left
                                    </div>
                                    <div style="color: var(--text-gray); font-size: 0.85rem; font-weight: 500;">
                                        <i class="fas fa-flag-checkered me-1"></i> Est. Finish: ${isCompleted ? 'Finished' : (h.project_estimated_end_date || 'N/A')}
                                    </div>
                                </div>
                                
                                <div class="mt-auto d-flex gap-2">
                                    <a href="project_dashboard.php?id=${p.project_id}&open_task_manager=true" class="btn btn-sm btn-outline-primary flex-grow-1 rounded-pill fw-bold" title="View Project Workflow"><i class="fas fa-project-diagram"></i></a>
                                    ${adminBtns}
                                </div>
                            </div>
                        </div>`;
                    });
                    html += '</div>';
                    container.innerHTML = html;
                } else {
                    container.innerHTML = `<div class="box-card text-danger py-4 text-center">Error: ${d.message}</div>`;
                }
            }).catch(e => {
                container.innerHTML = `<div class="box-card text-danger py-4 text-center">Connection error.</div>`;
            });
    }

    // --- FASE 65: ABRIR MODAL DE ALERTAS Y REPORTES ---
    let allProjectAlerts = [];
    function openProjectAlertsModal(projectId, projectName) {
        window.currentAlertsProjectId = projectId;
        window.currentAlertsProjectName = projectName;
        document.getElementById('alertsModalProjectName').textContent = projectName;
        const container = document.getElementById('projectAlertsTimeline');
        container.innerHTML = '<div class="text-center text-gray py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-3">Loading notes and audit...</p></div>';
        
        const modal = new bootstrap.Modal(document.getElementById('projectAlertsModal'));
        modal.show();

        const fd = new FormData();
        fd.append('action', 'get_project_notes');
        fd.append('project_id', projectId);

        fetch('../task_manager/api.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.status === 'success') {
                    allProjectAlerts = d.data;
                    renderProjectAlerts('all');
                    
                    document.querySelectorAll('.filter-alert-btn').forEach(b => {
                        b.classList.remove('btn-primary');
                        b.classList.add('btn-outline-secondary');
                    });
                    const allBtn = document.querySelector('.filter-alert-btn[data-filter="all"]');
                    if (allBtn) {
                        allBtn.classList.add('btn-primary');
                        allBtn.classList.remove('btn-outline-secondary');
                    }
                } else { container.innerHTML = `<div class="text-danger text-center py-4">${d.message}</div>`; }
            }).catch(e => { container.innerHTML = `<div class="text-danger text-center py-4">Connection error.</div>`; });
    }

    // --- FASE 84: LÓGICA DE QUICK TASK CREATOR ---
    function loadQuickTaskProjects() {
        fetch('../task_manager/api.php', {
            method: 'POST',
            body: new URLSearchParams({ action: 'get_active_projects' })
        })
        .then(r => r.json())
        .then(d => {
            const sel = document.getElementById('qt_project_id');
            if (!sel) return;
            if (d.status === 'success') {
                sel.innerHTML = '<option value="">-- Select Active Project --</option>';
                d.data.forEach(p => {
                    sel.innerHTML += `<option value="${p.id}">${p.name} (${p.status})</option>`;
                });
            } else {
                sel.innerHTML = '<option value="">Error loading projects</option>';
            }
        });
    }

    function submitQuickTask(e) {
        e.preventDefault();
        const form = e.target;
        const btn = form.querySelector('button[type="submit"]');
        const originalHtml = btn.innerHTML;
        
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.disabled = true;

        const fd = new FormData(form);
        fd.append('action', 'create_quick_task');

        fetch('../task_manager/api.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.status === 'success') {
                    showToast(d.message, 'success');
                    document.getElementById('qt_name').value = '';
                    document.getElementById('qt_minutes').value = '';
                    loadActiveProjectsHealth(); // Refrescar métricas inferiores
                    closeQuickTaskPanel(); // FASE 84: Cerrar el panel
                } else { appAlert('Error: ' + d.message, 'Error', 'error'); }
            })
            .catch(err => { console.error(err); appAlert('Connection error.', 'Error', 'error'); })
            .finally(() => { btn.innerHTML = originalHtml; btn.disabled = false; });
    }
    
    // Toggle Sidebar Funciones
    function openQuickTaskPanel() { document.body.classList.add('qt-panel-active'); }
    function closeQuickTaskPanel() { document.body.classList.remove('qt-panel-active'); }

    function filterProjectAlerts(category, btnElement) {
        document.querySelectorAll('.filter-alert-btn').forEach(b => {
            b.classList.remove('btn-primary');
            b.classList.add('btn-outline-secondary');
        });
        if (btnElement) {
            btnElement.classList.add('btn-primary');
            btnElement.classList.remove('btn-outline-secondary');
        }
        renderProjectAlerts(category);
    }

    function renderProjectAlerts(category) {
        const container = document.getElementById('projectAlertsTimeline');
        let filteredNotes = allProjectAlerts;
        
        if (category === 'notes') {
            filteredNotes = allProjectAlerts.filter(log => log.action_type === 'Note');
        } else if (category === 'rfis') {
            filteredNotes = allProjectAlerts.filter(log => log.action_type === 'RFI_Justification');
        } else if (category === 'time') {
            filteredNotes = allProjectAlerts.filter(log => ['Hold', 'Overdue', 'Extend'].includes(log.action_type));
        } else if (category === 'status') {
            filteredNotes = allProjectAlerts.filter(log => ['Completed', 'Completed_Late', 'Bypassed', 'Reset'].includes(log.action_type));
        }

        if (filteredNotes.length === 0) {
            container.innerHTML = '<div class="text-center text-gray py-4"><i class="fas fa-check-circle fa-3x text-success opacity-50 mb-3"></i><p>No notes or alerts found for this category.</p></div>';
            return;
        }
        
        let html = '';
        filteredNotes.forEach(log => {
            let badgeColor = 'bg-secondary text-white';
            let badgeIcon = 'fa-info-circle';
            let typeLabel = log.action_type.replace('_', ' ');
            
            if (log.action_type === 'Hold') {
                badgeColor = 'bg-warning text-dark'; badgeIcon = 'fa-pause-circle'; typeLabel = 'Pausado: Retención';
            } else if (log.action_type === 'Overdue') {
                badgeColor = 'bg-danger text-white'; badgeIcon = 'fa-exclamation-triangle'; typeLabel = 'Retraso Detectado';
            } else if (log.action_type === 'Note') {
                badgeColor = 'bg-info text-dark'; badgeIcon = 'fa-sticky-note'; typeLabel = 'Nota Manual';
            } else if (log.action_type === 'RFI_Justification') {
                badgeColor = 'bg-primary text-white'; badgeIcon = 'fa-question-circle'; typeLabel = 'Justificación RFI';
            } else if (log.action_type === 'Extend') {
                badgeColor = 'bg-info text-dark'; badgeIcon = 'fa-clock'; typeLabel = 'Extensión de Tiempo';
            } else if (log.action_type === 'Completed') {
                badgeColor = 'bg-success text-white'; badgeIcon = 'fa-check-circle'; typeLabel = 'Completado';
            } else if (log.action_type === 'Completed_Late') {
                badgeColor = 'bg-danger text-white'; badgeIcon = 'fa-check-double'; typeLabel = 'Completado con Retraso';
            } else if (log.action_type === 'Bypassed') {
                badgeColor = 'bg-secondary text-white'; badgeIcon = 'fa-forward'; typeLabel = 'Bypassed';
            } else if (log.action_type === 'Reset') {
                badgeColor = 'bg-danger text-white'; badgeIcon = 'fa-skull-crossbones'; typeLabel = 'Reset de Tareas';
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
    }

     // --- FASE 87: LÓGICA DE DRAG & DROP PARA ETAPAS ---
    let draggedStage = null;
    let draggedTask = null; // FASE 88

    function initDragAndDrop() {
        const stagesContainer = document.getElementById('stages-container');
        if (!stagesContainer) return;

        // --- FASE 88: Activar arrastre solo desde el grip handle ---
        stagesContainer.addEventListener('mousedown', e => {
            const handle = e.target.closest('.stage-drag-handle, .fa-grip-vertical');
            if (handle) {
                const draggableParent = handle.closest('.stage-column, .task-row');
                if (draggableParent) draggableParent.setAttribute('draggable', 'true');
            }
        });

        stagesContainer.addEventListener('mouseup', e => {
            const handle = e.target.closest('.stage-drag-handle, .fa-grip-vertical');
            if (handle) {
                const draggableParent = handle.closest('.stage-column, .task-row');
                if (draggableParent && !draggableParent.classList.contains('is-dragging')) {
                    draggableParent.removeAttribute('draggable');
                }
            }
        });

        stagesContainer.addEventListener('mouseout', e => {
            const handle = e.target.closest('.stage-drag-handle, .fa-grip-vertical');
            if (handle) {
                const draggableParent = handle.closest('.stage-column, .task-row');
                if (draggableParent && !draggableParent.classList.contains('is-dragging')) {
                    draggableParent.removeAttribute('draggable');
                }
            }
        });

        stagesContainer.addEventListener('dragstart', e => {
            // Solo permitir arrastrar si se hizo clic en el icono de agarre o el contenedor
            if (e.target.classList && e.target.classList.contains('stage-column')) {
                draggedStage = e.target;
                setTimeout(() => draggedStage.classList.add('is-dragging'), 0);
            } else if (e.target.classList && e.target.classList.contains('task-row')) {
                draggedTask = e.target;
                e.stopPropagation(); // Evitar que el contenedor padre (etapa) intente moverse
                setTimeout(() => draggedTask.classList.add('is-dragging'), 0);
            }
        });

        stagesContainer.addEventListener('dragend', e => {
            if (draggedStage) {
                draggedStage.classList.remove('is-dragging');
                draggedStage.removeAttribute('draggable');
                draggedStage = null;
            }
            if (draggedTask) {
                draggedTask.classList.remove('is-dragging');
                draggedTask.removeAttribute('draggable');
                draggedTask = null;
            }
        });

        stagesContainer.addEventListener('dragover', e => {
            e.preventDefault(); // Crítico para permitir el Drop

            if (draggedStage) {
                const afterElement = getDragAfterElement(stagesContainer, e.clientX);
                const btnAddWrapper = document.getElementById('btn-add-stage-wrapper');
                
                if (afterElement == null) {
                    stagesContainer.insertBefore(draggedStage, btnAddWrapper);
                } else {
                    stagesContainer.insertBefore(draggedStage, afterElement);
                }
            } else if (draggedTask) {
                const taskContainer = e.target.closest('.tasks-container');
                if (!taskContainer) return; // Solo permitir soltar dentro de un contenedor de tareas válidos

                const afterElement = getDragAfterTaskElement(taskContainer, e.clientY); // Usar Y (Vertical)
                
                if (afterElement == null) {
                    taskContainer.appendChild(draggedTask);
                } else {
                    taskContainer.insertBefore(draggedTask, afterElement);
                }
            }
        });
    }

    function getDragAfterElement(container, x) {
        const draggableElements = [...container.querySelectorAll('.stage-column:not(.is-dragging)')];

        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = x - box.left - box.width / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

        function getDragAfterTaskElement(container, y) {
        const draggableElements = [...container.querySelectorAll('.task-row:not(.is-dragging)')];

        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2; // Eje vertical Y
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }


    // --- FASE 82: GLOBAL PERFORMANCE REPORT ---
    let globalPerfProjectId = null;
    let globalPerformanceChartInstance = null;
    let currentGlobalPerformanceReportData = null; // FASE 85: Almacenar datos nativos

    function openGlobalPerformanceReportModal(projectId, projectName) {
        globalPerfProjectId = projectId;
        document.getElementById('perfModalProjectName').textContent = projectName;
        const select = document.getElementById('global_perf_report_user_select');
        select.innerHTML = '<option value="">Loading users...</option>';
        select.disabled = true;

        document.getElementById('globalPerformanceReportContent').style.display = 'none';
        document.getElementById('globalPerformanceReportEmpty').style.display = 'block';

        const modal = new bootstrap.Modal(document.getElementById('globalPerformanceReportModal'));
        modal.show();

        const fd = new FormData();
        fd.append('action', 'get_project_users');
        fd.append('project_id', projectId);
        
        fetch('../task_manager/api.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.status === 'success') {
                    select.innerHTML = '<option value="">-- Select a User --</option>';
                    d.data.forEach(u => {
                        select.innerHTML += `<option value="${u.id}">${u.username} (${u.role})</option>`;
                    });
                    select.disabled = false;
                } else {
                    select.innerHTML = '<option value="">Error loading users</option>';
                }
            })
            .catch(e => { select.innerHTML = '<option value="">Error loading users</option>'; });
    }

    function generateGlobalPerformanceReport(userId) {
        if (!userId || !globalPerfProjectId) {
            document.getElementById('globalPerformanceReportContent').style.display = 'none';
            document.getElementById('globalPerformanceReportEmpty').style.display = 'block';
            return;
        }

        document.getElementById('globalPerformanceReportContent').style.display = 'none';
        document.getElementById('globalPerformanceReportEmpty').innerHTML = '<i class="fas fa-spinner fa-spin fa-2x"></i><p>Generating report...</p>';
        document.getElementById('globalPerformanceReportEmpty').style.display = 'block';

        const fd = new FormData();
        fd.append('action', 'get_user_performance');
        fd.append('project_id', globalPerfProjectId);
        fd.append('user_id', userId);

        fetch('../task_manager/api.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.status === 'success' && d.data) {
                    const report = d.data;
                    currentGlobalPerformanceReportData = report;
                    document.getElementById('g_stat_total_estimated').innerText = report.total_estimated_hours;
                    document.getElementById('g_stat_total_actual').innerText = report.total_actual_hours;
                    document.getElementById('g_stat_performance_ratio').innerText = `${(report.performance_ratio * 100).toFixed(0)}%`;
                    document.getElementById('g_stat_completed_tasks').innerText = `${report.completed_tasks_count} / ${report.total_assigned_tasks}`;
                    document.getElementById('g_stat_left_tasks').innerText = report.total_assigned_tasks - report.completed_tasks_count;

                    let cardsHtml = '';
                    report.tasks.forEach(t => {
                        let isPending = t.status === 'Pending';
                        let actHours = isPending ? '-' : t.actual_hours;
                        let leftHours = isPending ? '-' : t.remaining_hours;
                        let variance = isPending ? '-' : t.variance;
                        let varianceClass = isPending ? 'text-gray' : (t.variance > 0 ? 'text-success' : (t.variance < 0 ? 'text-danger' : 'text-gray'));
                        let varianceSign = (!isPending && t.variance > 0) ? '+' : '';
                        
                        let statusBadgeColor = 'bg-secondary';
                        if(t.status === 'Completed' || t.status === 'Bypassed') statusBadgeColor = 'bg-success';
                        else if(t.status === 'Completed_Late') statusBadgeColor = 'bg-danger';
                        else if(t.status === 'Active') statusBadgeColor = 'bg-primary';
                        else if(t.status === 'Overdue') statusBadgeColor = 'bg-danger';
                        else if(t.status === 'On Hold') statusBadgeColor = 'bg-warning text-dark';
                        
                        let statusBadge = `<span class="badge ${statusBadgeColor} px-2 py-1" style="font-size:0.7rem;">${t.status}</span>`;
                        
                        cardsHtml += `
                            <div class="p-3 rounded pdf-avoid-break" style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-subtle);">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="fw-bold text-white"><i class="fas fa-tasks text-primary me-2"></i> ${t.task_order} - ${t.name}</div>
                                    <div>${statusBadge}</div>
                                </div>
                                <div class="row g-2 mb-2 text-center">
                                    <div class="col-3 border-end border-secondary">
                                        <div class="text-gray small text-uppercase">Est. (h)</div>
                                        <div class="fw-bold text-white">${t.estimated_hours}</div>
                                    </div>
                                    <div class="col-3 border-end border-secondary">
                                        <div class="text-gray small text-uppercase">Act. (h)</div>
                                        <div class="fw-bold text-white">${actHours}</div>
                                    </div>
                                    <div class="col-3 border-end border-secondary">
                                        <div class="text-gray small text-uppercase">Left (h)</div>
                                        <div class="fw-bold text-white">${leftHours}</div>
                                    </div>
                                    <div class="col-3">
                                        <div class="text-gray small text-uppercase">Variance</div>
                                        <div class="fw-bold ${varianceClass}">${varianceSign}${variance}</div>
                                    </div>
                                </div>
                                <div class="text-gray small mt-2 pt-2 border-top border-secondary" style="line-height: 1.4;">
                                    <i class="fas fa-comment-dots text-info me-1"></i> ${t.justification}
                                </div>
                            </div>`;
                    });
                    document.getElementById('globalPerformanceTableBody').innerHTML = cardsHtml;
                    document.getElementById('g_perfBreakdownTitle').innerText = `Assigned Tasks Breakdown: ${report.user.username} (${report.user.role})`;

                    // Chart.js loading if missing
                    if (typeof Chart === 'undefined') {
                        const script = document.createElement('script');
                        script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
                        script.onload = () => drawGlobalPerformanceChart(report);
                        document.head.appendChild(script);
                    } else {
                        drawGlobalPerformanceChart(report);
                    }

                    document.getElementById('globalPerformanceReportContent').style.display = 'block';
                    document.getElementById('globalPerformanceReportEmpty').style.display = 'none';
                    document.getElementById('btnExportGlobalPerformancePdf').classList.remove('d-none');
                } else {
                    document.getElementById('globalPerformanceReportEmpty').innerHTML = `<i class="fas fa-info-circle fa-2x text-warning"></i><p>${d.message || 'No tasks found or user not in directory.'}</p>`;
                    document.getElementById('btnExportGlobalPerformancePdf').classList.add('d-none');
                }
            })
            .catch(e => {
                console.error(e);
                document.getElementById('globalPerformanceReportEmpty').innerHTML = `<i class="fas fa-times-circle fa-2x text-danger"></i><p>Failed to generate report.</p>`;
                document.getElementById('btnExportGlobalPerformancePdf').classList.add('d-none');
            });
    }

    function drawGlobalPerformanceChart(report) {
        const ctx = document.getElementById('globalPerformanceChart').getContext('2d');
        const isLightMode = document.body.classList.contains('theme-light');
        const fontColor = isLightMode ? '#0f172a' : '#fff';
        const gridColor = isLightMode ? 'rgba(15,23,42,0.1)' : 'rgba(255,255,255,0.1)';
        
        if (globalPerformanceChartInstance) {
            globalPerformanceChartInstance.destroy();
        }
        
        let remainingChart = Math.max(0, report.total_estimated_hours - report.total_actual_hours);
        
        globalPerformanceChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Actual Hours Worked', 'Remaining Est. Hours'],
                datasets: [{
                    data: [report.total_actual_hours, remainingChart],
                    backgroundColor: ['rgba(16, 185, 129, 0.8)', 'rgba(59, 130, 246, 0.8)'],
                    borderColor: ['#10b981', '#3b82f6'],
                    borderWidth: 1
                }]
            },
            options: { 
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                cutout: '65%',
                plugins: { legend: { position: 'right', labels: { color: fontColor, font: { size: 16 } } } } 
            }
        });
    }

    // --- FASE 85: NUEVA ESTRATEGIA DE EXPORTACIÓN NATIVA ---
    async function exportGlobalPerformanceToPdf() {
        if (!currentGlobalPerformanceReportData) return;
        const btn = document.getElementById('btnExportGlobalPerformancePdf');
        const origText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Exporting...';
        btn.disabled = true;
        
        try {
            if (typeof window.jspdf === 'undefined') {
                await new Promise((res, rej) => { const s = document.createElement('script'); s.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js'; s.onload = res; s.onerror = rej; document.head.appendChild(s); });
            }
            if (typeof window.jspdf.jsPDF.API.autoTable === 'undefined') {
                await new Promise((res, rej) => { const s = document.createElement('script'); s.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js'; s.onload = res; s.onerror = rej; document.head.appendChild(s); });
            }
            executeGlobalNativePdfExport(btn, origText, currentGlobalPerformanceReportData);
        } catch (e) {
            console.error('Error loading PDF libraries', e);
            appAlert('Error loading export libraries.', 'Error', 'error');
            btn.innerHTML = origText;
            btn.disabled = false;
        }
    }

    function executeGlobalNativePdfExport(btn, origText, report) {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
        
        const PW = doc.internal.pageSize.getWidth();
        const MARGIN = 15;
        let y = MARGIN;

        doc.setFillColor(15, 23, 42); 
        doc.rect(0, 0, PW, 30, 'F');
        doc.setTextColor(255, 255, 255);
        doc.setFontSize(16);
        doc.setFont('helvetica', 'bold');
        doc.text('USER PERFORMANCE REPORT', MARGIN, 15);
        
        doc.setFontSize(10);
        doc.setFont('helvetica', 'normal');
        doc.text(new Date().toLocaleDateString() + ' ' + new Date().toLocaleTimeString(), PW - MARGIN, 15, { align: 'right' });
        const prjName = document.getElementById('perfModalProjectName').textContent;
        doc.text(`Project: ${prjName}`, MARGIN, 23);

        y = 40;

        doc.setTextColor(15, 23, 42);
        doc.setFontSize(12);
        doc.setFont('helvetica', 'bold');
        doc.text(`Target User: ${report.user.username} (${report.user.role})`, MARGIN, y);
        
        y += 10;
        doc.setFontSize(10);
        doc.setFont('helvetica', 'normal');
        doc.text(`Estimated Hours: ${report.total_estimated_hours} h`, MARGIN, y);
        doc.text(`Actual Hours: ${report.total_actual_hours} h`, MARGIN + 60, y);
        doc.setFont('helvetica', 'bold');
        doc.text(`Performance Ratio: ${(report.performance_ratio * 100).toFixed(0)}%`, PW - MARGIN, y, { align: 'right' });

        y += 8;
        doc.setFont('helvetica', 'normal');
        doc.text(`Completed Tasks: ${report.completed_tasks_count} / ${report.total_assigned_tasks}`, MARGIN, y);
        doc.text(`Tasks Left to Complete: ${report.total_assigned_tasks - report.completed_tasks_count}`, MARGIN + 60, y);

        y += 15;

        if (globalPerformanceChartInstance) {
            const oldAnim = globalPerformanceChartInstance.options.animation;
            const oldColor = globalPerformanceChartInstance.options.plugins.legend.labels.color;
            const oldFont = globalPerformanceChartInstance.options.plugins.legend.labels.font;
            
            globalPerformanceChartInstance.options.animation = false;
            globalPerformanceChartInstance.options.plugins.legend.labels.color = '#0f172a';
            globalPerformanceChartInstance.options.plugins.legend.labels.font = { size: 26, weight: 'bold' };
            globalPerformanceChartInstance.update();
            
            const chartCanvas = document.getElementById('globalPerformanceChart');
            if (chartCanvas) {
                const chartImgData = chartCanvas.toDataURL('image/png', 1.0);
                const imgWidth = 145;
                const imgHeight = (chartCanvas.height * imgWidth) / chartCanvas.width;
                doc.addImage(chartImgData, 'PNG', (PW - imgWidth) / 2, y, imgWidth, imgHeight);
                y += imgHeight + 15;
            }
            
            globalPerformanceChartInstance.options.animation = oldAnim;
            globalPerformanceChartInstance.options.plugins.legend.labels.color = oldColor;
            globalPerformanceChartInstance.options.plugins.legend.labels.font = oldFont;
            globalPerformanceChartInstance.update();
        }

        const tableBody = report.tasks.map(t => {
            let isPending = t.status === 'Pending';
            return [
                `${t.task_order} - ${t.name}`,
                t.status.replace('_', ' '),
                t.estimated_hours.toString(),
                isPending ? '-' : t.actual_hours.toString(),
                isPending ? '-' : t.remaining_hours.toString(),
                isPending ? '-' : (t.variance > 0 ? '+' : '') + t.variance.toString(),
                t.justification || ''
            ];
        });

        doc.autoTable({
            startY: y,
            head: [['Task', 'Status', 'Est(h)', 'Act(h)', 'Left(h)', 'Var', 'Note']],
            body: tableBody,
            theme: 'grid',
            styles: { fontSize: 8, cellPadding: 3, font: 'helvetica' },
            headStyles: { fillColor: [241, 245, 249], textColor: [15, 23, 42], fontStyle: 'bold', lineColor: [203, 213, 225] },
            bodyStyles: { textColor: [15, 23, 42], lineColor: [203, 213, 225] },
            columnStyles: {
                0: { cellWidth: 50 },
                1: { cellWidth: 20 },
                2: { halign: 'center', cellWidth: 15 },
                3: { halign: 'center', cellWidth: 15 },
                4: { halign: 'center', cellWidth: 15 },
                5: { halign: 'center', fontStyle: 'bold', cellWidth: 15 },
                6: { cellWidth: 'auto' }
            },
            didParseCell: function(data) {
                if (data.section === 'body' && data.column.index === 5) {
                    const val = parseFloat(data.cell.raw);
                    if (val > 0) {
                        data.cell.styles.textColor = [16, 185, 129];
                    } else if (val < 0) {
                        data.cell.styles.textColor = [239, 68, 68];
                    }
                }
                if (data.section === 'body' && data.column.index === 1) {
                    const status = data.cell.raw;
                    if (status === 'Completed' || status === 'Bypassed') data.cell.styles.textColor = [16, 185, 129];
                    else if (status === 'Completed_Late') data.cell.styles.textColor = [239, 68, 68];
                    else if (status === 'Active') data.cell.styles.textColor = [59, 130, 246];
                    else if (status === 'Overdue') data.cell.styles.textColor = [239, 68, 68];
                    else if (status === 'On Hold') data.cell.styles.textColor = [245, 158, 11];
                }
            }
        });

        doc.save(`Performance_Report_${report.user.username.replace(/[^a-zA-Z0-9]/g, '_')}.pdf`);
        btn.innerHTML = origText;
        btn.disabled = false;
    }

    // Lógica de Guardado (Estructuración JSON y POST)
    function saveTemplate(mode = 'clone') {
        const templateName = currentTemplateName;
        const templateDesc = currentTemplateDesc;
        
        if (!templateName) {
            return;
        }
        
        const stages = [];
        document.querySelectorAll('.stage-column').forEach(stageEl => {
            const sName = stageEl.querySelector('.stage-name-input').value.trim();
            
            const tasks = [];
            stageEl.querySelectorAll('.task-row').forEach(taskEl => {
                const tName = taskEl.querySelector('.task-name-input').value.trim();
                const tHours = parseFloat(taskEl.querySelector('.task-hours-input').value);
                
                if(tName !== '') {
                    tasks.push({ name: tName, minutes: isNaN(tHours) ? 480 : Math.round(tHours * 60) });
                }
            });
            
            if(sName !== '' || tasks.length > 0) {
                stages.push({ name: sName || 'Unnamed Stage', tasks: tasks });
            }
        });
        
        if (stages.length === 0) {
            appAlert('Your template needs at least one Stage and Task to be saved.', "Notice", "warning");
            return;
        }
        
        const payload = {
            template_name: templateName,
            description: templateDesc,
            stages: stages,
            mode: mode,
            template_id: currentLoadedTemplateId
        };

        // Enviar al Backend
        const btn = mode === 'update' ? document.getElementById('btn-update-template') : document.getElementById('btn-save-template');
        const origBtnText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';
        btn.disabled = true;
        
        const fd = new FormData();
        fd.append('action', 'save_template');
        fd.append('template_data', JSON.stringify(payload));

        fetch('../task_manager/api.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast('Template saved successfully!', 'success');
                    
                    // FASE 62: Cerrar el Canvas suavemente y volver al Dashboard
                    const workspace = document.getElementById('template-builder-workspace');
                    workspace.style.opacity = '0';
                    setTimeout(() => {
                        document.getElementById('template-builder-view').classList.add('d-none');
                        document.getElementById('dashboard-main-view').classList.remove('d-none');
                        
                        document.getElementById('stages-container').innerHTML = `
                            <div id="empty-canvas-state" class="w-100 text-center text-gray py-5 mt-5"><i class="fas fa-layer-group fa-3x mb-3 opacity-25"></i><p>Your canvas is empty. Add a Stage to begin.</p><button class="btn btn-main mt-3" onclick="addStage()"><i class="fas fa-plus me-2"></i> Add First Stage</button></div>
                            <div id="btn-add-stage-wrapper" style="display: none;">
                                <button class="btn-add-stage-column" onclick="addStage()"><i class="fas fa-plus fa-lg me-2"></i> New Stage</button>
                            </div>
                        `;
                        stageCounter = 0;
                    }, 300);
                    
                    refreshTemplatesList();
                } else {
                    showToast('Error saving template: ' + (data.msg || data.message || 'Unknown error'), 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showToast('Connection Error. Check console.', 'error');
            }).finally(() => { btn.innerHTML = origBtnText; btn.disabled = false; });
    }

    function showToast(msg, type) {
        const box = document.getElementById('toast-container'); 
        const el = document.createElement('div'); el.className = `toast-msg`;
        el.style.borderLeft = `4px solid ${type==='success'?'#10b981':'#ef4444'}`;
        el.innerHTML = (type==='success'?'<i class="fas fa-check-circle text-success"></i>':'<i class="fas fa-exclamation-circle text-danger"></i>')+`<span>${msg}</span>`;
        box.appendChild(el); setTimeout(() => el.remove(), 4000);
    }

    // Abrir Modal de Importación
    function openImportCsvModal() {
        const modal = new bootstrap.Modal(document.getElementById('importCsvModal'));
        modal.show();
    }

    // Lógica de Envío del Formulario de Importación CSV
    document.addEventListener('DOMContentLoaded', () => {
        const importForm = document.getElementById('importCsvForm');
        if (importForm) {
            importForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const btn = this.querySelector('button[type="submit"]');
                const origText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Importing...';
                btn.disabled = true;

                const fd = new FormData(this);
                fd.append('action', 'import_template_csv');

                fetch('../task_manager/api.php', { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                                appAlert('Template successfully imported!', "Success", "success");
                            setTimeout(() => { location.reload(); }, 1500);
                        } else {
                                appAlert('Error: ' + (data.msg || data.message || 'Failed to import.'), "Error", "error");
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        appAlert('Connection error. Check console.', "Error", "error");
                    }).finally(() => { btn.innerHTML = origText; btn.disabled = false; });
            });
        }
    });
</script>

<?php include __DIR__ . '/../views/footer.php'; ?>