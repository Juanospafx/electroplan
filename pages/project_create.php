<?php
// pages/project_create.php
require_once __DIR__ . '/../core/auth/session.php';
require_once __DIR__ . '/../core/db/connection.php';

// Solo administradores
if ($_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$editId = (int)($_GET['id'] ?? 0);
$isEdit = $editId > 0;

$project = [];
if ($isEdit) {
    $stmtProj = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND deleted_at IS NULL");
    $stmtProj->execute([$editId]);
    $project = $stmtProj->fetch(PDO::FETCH_ASSOC) ?: [];
    if (empty($project)) { header("Location: index.php"); exit; }
}

$createUsers = [];
$stmtUsers = $pdo->query("SELECT id, username, role FROM users ORDER BY username ASC");
$createUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

$existingFolders = [];
if ($isEdit) {
    $stmtFolders = $pdo->prepare("SELECT name FROM folders WHERE project_id = ? AND deleted_at IS NULL");
    $stmtFolders->execute([$editId]);
    $existingFolders = array_map('strtolower', $stmtFolders->fetchAll(PDO::FETCH_COLUMN));
}

$folderMap = [
    'bom' => 'bom',
    'schedule_values' => 'schedule of values',
    'rfi' => 'rfi',
    'drawings' => 'drawings',
    'photos' => 'photos',
    'panel_schedule' => 'panel schedule',
    'panel_tags' => 'panel tags',
    'noc' => 'noc',
    'submittal' => 'submittal',
    'permit' => 'permit',
    'acknowledgement' => 'acknowledgement',
    'payapp' => 'payapp',
    'insurance' => 'certificate of insurance',
    'fault_calc' => 'fault current calc',
    'labor_record' => 'labor record',
    'expenses' => 'expenses',
    'warranty_sup' => 'warranty supplier',
    'clock_in' => 'clock in'
];

$projectDesc = $project['description'] ?? '';
if ($projectDesc === '' && !empty($project['notes'])) $projectDesc = $project['notes'];
$projectNotes = $project['notes'] ?? '';
if ($projectNotes === '' && !empty($project['description'])) $projectNotes = $project['description'];
$projectAddress = $project['address'] ?? ($project['job_address'] ?? '');
$projectContactName = $project['contact_name'] ?? ($project['site_contact_name'] ?? '');
$projectContactPhone = $project['contact_phone'] ?? ($project['site_contact_phone'] ?? '');
$projectCompanyName = $project['company_name'] ?? ($project['gc_company'] ?? '');
$projectCompanyPhone = $project['company_phone'] ?? ($project['office_phone'] ?? '');
$projectCompanyAddress = $project['company_address'] ?? ($project['hq_address'] ?? '');

$pageTitle = $isEdit ? "Edit Project" : "Create New Project";
$userName = $_SESSION['username'] ?? 'User';
$userRole = $_SESSION['role'] ?? 'viewer';
include __DIR__ . '/../views/header.php'; 
?>

<style>
    :root {
        /* Paleta Dark Mode (Deep Matte) */
        --bg-body: #1b212d;
        --bg-card: #242a38;
        --bg-input: #151a23;
        --primary: #fb5a3a;
        --primary-hover: #e14e32;
        --text-white: #ffffff;
        --text-gray: #94a3b8;
        --text-muted: #58657a;
        --border-subtle: #2f384a;
        --radius-box: 20px;
    }

    body.theme-light {
        --bg-body: #e2e8f0;
        --bg-card: #ffffff;
        --bg-input: #f8fafc;
        --text-white: #0f172a;
        --text-gray: #64748b;
        --text-muted: #94a3b8;
        --border-subtle: #cbd5e1;
    }

    body.theme-light .bg-dark { background-color: var(--bg-input) !important; color: var(--text-white) !important; border-color: var(--border-subtle) !important; }
    body.theme-light .text-white { color: var(--text-white) !important; }

    /* Estilos de Tarjetas */
    .box-card-compact { 
        background: var(--bg-card); 
        border-radius: var(--radius-box); 
        border: 1px solid var(--border-subtle); 
        padding: 24px; 
        margin-bottom: 20px; 
        transition: 0.3s; 
    }

    /* Encabezados de sección */
    .section-header {
        display: flex;
        align-items: center;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--border-subtle);
    }
    .section-icon {
        font-size: 1.1rem;
        margin-right: 10px;
        color: var(--primary);
    }
    .section-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--text-white);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Form Controls */
    .form-label {
        color: var(--text-gray);
        font-size: 0.8rem;
        font-weight: 600;
        margin-bottom: 6px;
        display: block;
    }
    
    .form-control, .form-select { 
        background: var(--bg-input) !important; 
        border: 1px solid var(--border-subtle) !important; 
        color: var(--text-white) !important; 
        border-radius: 10px; 
        padding: 10px 15px;
        font-size: 0.95rem;
        min-height: 42px;
    }
    .form-control:focus, .form-select:focus { 
        border-color: var(--primary) !important; 
        box-shadow: 0 0 0 3px rgba(251, 90, 58, 0.2) !important; 
    }
    .form-control::placeholder { color: var(--text-gray) !important; opacity: 1; }

    .btn-main { 
        background-color: var(--primary) !important; 
        border-color: var(--primary) !important; 
        color: white !important; 
        border-radius: 8px; 
        padding: 8px 16px; 
        border: 1px solid transparent; 
        transition: 0.2s; 
        font-weight: 600;
    }
    .btn-main:hover { 
        background-color: var(--primary-hover) !important; 
        border-color: var(--primary-hover) !important; 
        transform: translateY(-2px); 
        box-shadow: 0 4px 12px rgba(251, 90, 58, 0.3); 
    }
    
    .btn-outline-light { border-color: var(--border-subtle); color: var(--text-gray); }
    .btn-outline-light:hover { background: var(--bg-input); color: var(--primary); border-color: var(--primary); }

    /* Checkboxes y Listas */
    .check-item {
        padding: 8px 12px;
        border-radius: 8px;
        transition: 0.2s;
        border: 1px solid transparent;
        cursor: pointer;
    }
    .check-item:hover { background: var(--bg-body); border-color: var(--border-subtle); }
    .check-item span { color: var(--text-white); }
    .form-check-input { margin-top: 0.15em; cursor: pointer; }

    .folder-list {
        max-height: 480px; 
        overflow-y: auto;
        padding-right: 5px;
    }
    .folder-list::-webkit-scrollbar { width: 6px; }
    .folder-list::-webkit-scrollbar-thumb { background: var(--border-subtle); border-radius: 6px; }

    /* WIZARD STYLES */
    .wizard-step { display: none; animation: fadeIn 0.4s ease; }
    .wizard-step.active { display: block; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .wizard-header { position: relative; margin-bottom: 2.5rem; padding: 0 5%; display: flex; justify-content: space-between; }
    .wizard-header::before { content: ''; position: absolute; top: 17px; left: 8%; right: 8%; height: 2px; background: var(--border-subtle); z-index: 1; }
    .wizard-indicator { position: relative; z-index: 2; background: var(--bg-body); padding: 0 10px; display: flex; flex-direction: column; align-items: center; gap: 8px; color: var(--text-gray); transition: 0.3s; cursor: pointer; }
    .wizard-indicator .step-num { width: 36px; height: 36px; border-radius: 50%; background: var(--bg-card); border: 2px solid var(--border-subtle); display: flex; align-items: center; justify-content: center; font-weight: bold; transition: 0.3s; }
    .wizard-indicator:hover { color: var(--text-white); }
    .wizard-indicator.active { color: var(--primary); }
    .wizard-indicator.active .step-num { background: var(--primary); border-color: var(--primary); color: white; box-shadow: 0 0 15px rgba(251,90,58,0.3); }
    .wizard-indicator.completed { color: var(--text-white); }
    .wizard-indicator.completed .step-num { background: var(--bg-card); border-color: var(--primary); color: var(--primary); }
    body.theme-light .wizard-indicator.completed .step-num { background: #fff; }
    body.theme-light .wizard-indicator { background: var(--bg-body); }

    .folder-list {
        max-height: 480px;
        }   

    @media (max-width: 768px) {
        .main-content { padding: 20px !important; }
        .box-card-compact { padding: 16px; }
        .folder-list { max-height: 260px; }
    }
</style>

<div class="main-content p-4 pt-5">
    
    <header class="header mb-4">
        <div class="d-flex align-items-center gap-3">
            <button class="mobile-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <div class="breadcrumbs">
                <a href="index.php">Home</a>
                <i class="fas fa-chevron-right mx-2" style="font-size:0.7rem"></i>
                <span><?= $isEdit ? 'Edit Project' : 'Create Project' ?></span>
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
        <h4 class="fw-bold text-white mb-0"><?= $isEdit ? 'Edit Project' : 'Create Project' ?></h4>
        <a href="<?= $isEdit ? 'project_dashboard.php?id=' . (int)$editId : 'index.php' ?>" class="btn btn-outline-danger btn-sm rounded-pill px-4"><i class="fas fa-times me-2"></i> Cancel</a>
    </div>

    <form id="createProjectForm">
        <input type="hidden" name="is_edit" value="<?= $isEdit ? '1' : '0' ?>">
        <?php if($isEdit): ?>
            <input type="hidden" name="project_id" value="<?= (int)$editId ?>">
        <?php endif; ?>

        <!-- WIZARD HEADER PROGRESS -->
        <div class="wizard-header d-none d-md-flex">
            <div class="wizard-indicator active" id="ind-1" onclick="jumpToStep(1)"><div class="step-num"><i class="fas fa-info"></i></div><small class="fw-bold">General Info</small></div>
            <div class="wizard-indicator" id="ind-2" onclick="jumpToStep(2)"><div class="step-num"><i class="fas fa-users"></i></div><small class="fw-bold">Team & Contacts</small></div>
            <div class="wizard-indicator" id="ind-3" onclick="jumpToStep(3)"><div class="step-num"><i class="fas fa-calendar"></i></div><small class="fw-bold">Timeline & Folders</small></div>
            <div class="wizard-indicator" id="ind-4" onclick="jumpToStep(4)"><div class="step-num"><i class="fas fa-check-double"></i></div><small class="fw-bold">Final Review</small></div>
        </div>

        <!-- STEP 1: GENERAL INFO -->
        <div class="wizard-step active" id="step-1">
            <div class="box-card-compact mx-auto" style="max-width: 800px;">
                <div class="section-header">
                    <i class="fas fa-info-circle section-icon"></i>
                    <span class="section-title">General Information</span>
                </div>
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label">Project Name *</label>
                        <input type="text" name="project_name" class="form-control" required placeholder="Project Title" value="<?= htmlspecialchars($project['name'] ?? '') ?>">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Job Address</label>
                        <input type="text" name="address" class="form-control" placeholder="City, State, Zip" value="<?= htmlspecialchars($projectAddress) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Scope of Work / Description</label>
                        <textarea name="notes" class="form-control" rows="4" placeholder="Brief scope..."><?= htmlspecialchars($projectNotes) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- STEP 2: CONTACTS & USERS -->
        <div class="wizard-step" id="step-2">
            <div class="box-card-compact mx-auto" style="max-width: 800px;">
                <div class="section-header">
                    <i class="fas fa-address-book section-icon text-info"></i>
                    <span class="section-title">Contacts & Assignments</span>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Site Contact Name</label>
                        <input type="text" name="contact_name" class="form-control" placeholder="Name" value="<?= htmlspecialchars($projectContactName) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Site Contact Phone</label>
                        <input type="text" name="contact_phone" class="form-control" placeholder="Phone" value="<?= htmlspecialchars($projectContactPhone) ?>">
                    </div>
                    <div class="col-12"><div style="border-top:1px dashed var(--border-subtle); margin: 10px 0;"></div></div>
                    <div class="col-md-12">
                        <label class="form-label">Company (GC)</label>
                        <input type="text" name="company_name" class="form-control" placeholder="Company Name" value="<?= htmlspecialchars($projectCompanyName) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Office Phone</label>
                        <input type="text" name="company_phone" class="form-control" placeholder="Phone" value="<?= htmlspecialchars($projectCompanyPhone) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">HQ Address</label>
                        <input type="text" name="company_address" class="form-control" placeholder="Address" value="<?= htmlspecialchars($projectCompanyAddress) ?>">
                    </div>
                </div>
            </div>

            <?php if(!$isEdit): ?>
            <div class="box-card-compact mx-auto mt-3" style="max-width: 800px;">
                <div class="section-header">
                    <i class="fas fa-user-plus section-icon text-success"></i>
                    <span class="section-title">Assign Internal Team</span>
                </div>
                <div class="border border-secondary rounded p-2" style="max-height:180px; overflow:auto;">
                    <?php foreach($createUsers as $u): ?>
                        <label class="check-item d-flex align-items-center gap-2 small text-gray mb-2">
                            <input class="form-check-input" type="checkbox" name="user_ids[]" value="<?= (int)$u['id'] ?>" data-role="<?= htmlspecialchars($u['role']) ?>">
                            <span><?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['role']) ?>)</span>
                        </label>
                    <?php endforeach; ?>
                    <?php if(empty($createUsers)): ?>
                        <div class="text-gray small">No users available.</div>
                    <?php endif; ?>
                </div>
                <small class="text-muted d-block mt-2" style="font-size:0.75rem;">Note: At least one Administrator must be assigned to manage the project.</small>
            </div>
            <?php endif; ?>
        </div>

        <!-- STEP 3: TIMELINE -->
        <div class="wizard-step" id="step-3">
            <div class="box-card-compact mx-auto" style="max-width: 800px;">
                <div class="section-header">
                    <i class="fas fa-calendar-alt section-icon text-warning"></i>
                    <span class="section-title">Project Timeline</span>
                </div>
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label">Bid Sent Date</label>
                        <input type="date" name="date_bid_send" class="form-control" value="<?= htmlspecialchars($project['date_bid_sent'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Bid Awarded</label>
                        <input type="date" name="date_bid_awarded" class="form-control" value="<?= htmlspecialchars($project['date_bid_awarded'] ?? '') ?>">
                    </div>
                    <div class="col-12"><div style="border-top:1px dashed var(--border-subtle); margin: 5px 0;"></div></div>
                    <div class="col-md-4">
                        <label class="form-label">Project Start Date</label>
                        <input type="date" name="date_started" class="form-control" value="<?= htmlspecialchars($project['date_started'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Target Finish</label>
                        <input type="date" name="date_finished" class="form-control" value="<?= htmlspecialchars($project['date_finished'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Warranty End</label>
                        <input type="date" name="date_warranty_end" class="form-control" value="<?= htmlspecialchars($project['date_warranty_end'] ?? '') ?>">
                    </div>
                </div>
            </div>
            
            <div class="box-card-compact mx-auto mt-4" style="max-width: 800px;">
                <div class="section-header">
                    <i class="fas fa-folder-tree section-icon text-primary"></i>
                    <span class="section-title">Select Initial Folders</span>
                    <small class="ms-auto text-muted fw-normal" style="font-size:0.75rem"><?= $isEdit ? 'Current selection' : 'Uncheck unused' ?></small>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-4"><label class="check-item d-flex align-items-center gap-2"><input class="form-check-input" type="checkbox" name="folders[]" value="bom" <?= $isEdit ? (in_array($folderMap['bom'], $existingFolders, true) ? 'checked' : '') : 'checked' ?>> <span class="small">BoM</span></label></div>
                    <div class="col-md-4"><label class="check-item d-flex align-items-center gap-2"><input class="form-check-input" type="checkbox" name="folders[]" value="schedule_values" <?= $isEdit ? (in_array($folderMap['schedule_values'], $existingFolders, true) ? 'checked' : '') : 'checked' ?>> <span class="small">Schedule of Values</span></label></div>
                    <div class="col-md-4"><label class="check-item d-flex align-items-center gap-2"><input class="form-check-input" type="checkbox" name="folders[]" value="rfi" <?= $isEdit ? (in_array($folderMap['rfi'], $existingFolders, true) ? 'checked' : '') : 'checked' ?>> <span class="small">RFI</span></label></div>
                    <div class="col-md-4"><label class="check-item d-flex align-items-center gap-2"><input class="form-check-input" type="checkbox" name="folders[]" value="drawings" <?= $isEdit ? (in_array($folderMap['drawings'], $existingFolders, true) ? 'checked' : '') : 'checked' ?>> <span class="small">Drawings</span></label></div>
                    <div class="col-md-4"><label class="check-item d-flex align-items-center gap-2"><input class="form-check-input" type="checkbox" name="folders[]" value="photos" <?= $isEdit ? (in_array($folderMap['photos'], $existingFolders, true) ? 'checked' : '') : 'checked' ?>> <span class="small">Photos</span></label></div>
                    <div class="col-md-4"><label class="check-item d-flex align-items-center gap-2"><input class="form-check-input" type="checkbox" name="folders[]" value="panel_schedule" <?= $isEdit ? (in_array($folderMap['panel_schedule'], $existingFolders, true) ? 'checked' : '') : 'checked' ?>> <span class="small">Panel Schedule</span></label></div>
                    <div class="col-md-4"><label class="check-item d-flex align-items-center gap-2"><input class="form-check-input" type="checkbox" name="folders[]" value="panel_tags" <?= $isEdit ? (in_array($folderMap['panel_tags'], $existingFolders, true) ? 'checked' : '') : '' ?>> <span class="small">Panel/Meter Tags</span></label></div>
                    <div class="col-md-4"><label class="check-item d-flex align-items-center gap-2"><input class="form-check-input" type="checkbox" name="folders[]" value="noc" <?= $isEdit ? (in_array($folderMap['noc'], $existingFolders, true) ? 'checked' : '') : '' ?>> <span class="small">NOC</span></label></div>
                    <div class="col-md-4"><label class="check-item d-flex align-items-center gap-2"><input class="form-check-input" type="checkbox" name="folders[]" value="submittal" <?= $isEdit ? (in_array($folderMap['submittal'], $existingFolders, true) ? 'checked' : '') : '' ?>> <span class="small">Submittal</span></label></div>
                    <div class="col-md-4"><label class="check-item d-flex align-items-center gap-2"><input class="form-check-input" type="checkbox" name="folders[]" value="permit" <?= $isEdit ? (in_array($folderMap['permit'], $existingFolders, true) ? 'checked' : '') : '' ?>> <span class="small">Permit</span></label></div>
                    <div class="col-md-4"><label class="check-item d-flex align-items-center gap-2"><input class="form-check-input" type="checkbox" name="folders[]" value="acknowledgement" <?= $isEdit ? (in_array($folderMap['acknowledgement'], $existingFolders, true) ? 'checked' : '') : '' ?>> <span class="small">Acknowledgement</span></label></div>
                    <div class="col-md-4"><label class="check-item d-flex align-items-center gap-2"><input class="form-check-input" type="checkbox" name="folders[]" value="payapp" <?= $isEdit ? (in_array($folderMap['payapp'], $existingFolders, true) ? 'checked' : '') : '' ?>> <span class="small">Payapp</span></label></div>
                    <div class="col-md-4"><label class="check-item d-flex align-items-center gap-2"><input class="form-check-input" type="checkbox" name="folders[]" value="insurance" <?= $isEdit ? (in_array($folderMap['insurance'], $existingFolders, true) ? 'checked' : '') : '' ?>> <span class="small">Insurance</span></label></div>
                    <div class="col-md-4"><label class="check-item d-flex align-items-center gap-2"><input class="form-check-input" type="checkbox" name="folders[]" value="fault_calc" <?= $isEdit ? (in_array($folderMap['fault_calc'], $existingFolders, true) ? 'checked' : '') : '' ?>> <span class="small">Fault Calc</span></label></div>
                    <div class="col-md-4"><label class="check-item d-flex align-items-center gap-2"><input class="form-check-input" type="checkbox" name="folders[]" value="labor_record" <?= $isEdit ? (in_array($folderMap['labor_record'], $existingFolders, true) ? 'checked' : '') : '' ?>> <span class="small">Labor Record</span></label></div>
                    <div class="col-md-4"><label class="check-item d-flex align-items-center gap-2"><input class="form-check-input" type="checkbox" name="folders[]" value="expenses" <?= $isEdit ? (in_array($folderMap['expenses'], $existingFolders, true) ? 'checked' : '') : '' ?>> <span class="small">Expenses</span></label></div>
                    <div class="col-md-4"><label class="check-item d-flex align-items-center gap-2"><input class="form-check-input" type="checkbox" name="folders[]" value="warranty_sup" <?= $isEdit ? (in_array($folderMap['warranty_sup'], $existingFolders, true) ? 'checked' : '') : '' ?>> <span class="small">Warranty Sup</span></label></div>
                    <div class="col-md-4"><label class="check-item d-flex align-items-center gap-2"><input class="form-check-input" type="checkbox" name="folders[]" value="clock_in" <?= $isEdit ? (in_array($folderMap['clock_in'], $existingFolders, true) ? 'checked' : '') : '' ?>> <span class="small">Clock in</span></label></div>
                </div>
                <?php if($isEdit): ?><div class="text-muted small border-top border-secondary pt-2">Note: To add totally new custom folders later, you can use "Add Folder" in the project dashboard.</div><?php endif; ?>
            </div>
        </div>

        <!-- STEP 4: FINAL REVIEW -->
        <div class="wizard-step" id="step-4">
            <div class="box-card-compact mx-auto text-center py-5" style="max-width: 800px; background: var(--bg-card);">
                <i class="fas fa-clipboard-check text-success mb-3" style="font-size: 3rem;"></i>
                <h3 class="fw-bold text-white mb-2">Final Review</h3>
                <p class="text-gray mb-4">Please review the details below before completing the process.</p>
                
                <div id="wizard-summary" class="text-start"></div>
            </div>
        </div>

        <!-- WIZARD NAVIGATION BUTTONS -->
        <div class="d-flex justify-content-between align-items-center mt-4 border-top border-secondary pt-4 mx-auto" style="max-width: 800px;">
            <button type="button" class="btn btn-outline-light px-4 py-2" id="btn-prev" onclick="prevStep()" style="display:none;"><i class="fas fa-arrow-left me-2"></i> Back</button>
            <div class="ms-auto">
                <button type="button" class="btn btn-main px-4 py-2" id="btn-next" onclick="nextStep()">Continue <i class="fas fa-arrow-right ms-2"></i></button>
                <button type="submit" class="btn btn-main px-4 py-2" id="btn-submit" style="display:none;"><i class="fas <?= $isEdit ? 'fa-save' : 'fa-rocket' ?> me-2"></i> <?= $isEdit ? 'Save Changes' : 'Create Project' ?></button>
            </div>
        </div>

    </form>
</div>

<script>
let currentStep = 1;
const totalSteps = 4;

function updateWizard() {
    for(let i=1; i<=totalSteps; i++) {
        document.getElementById('step-'+i).classList.remove('active');
        const ind = document.getElementById('ind-'+i);
        if(ind) {
            ind.classList.remove('active', 'completed');
            if(i < currentStep) ind.classList.add('completed');
            if(i === currentStep) ind.classList.add('active');
        }
    }
    document.getElementById('step-'+currentStep).classList.add('active');
    
    document.getElementById('btn-prev').style.display = currentStep > 1 ? 'inline-flex' : 'none';
    if (currentStep === totalSteps) {
        document.getElementById('btn-next').style.display = 'none';
        document.getElementById('btn-submit').style.display = 'inline-flex';
        generateSummary();
    } else {
        document.getElementById('btn-next').style.display = 'inline-flex';
        document.getElementById('btn-submit').style.display = 'none';
    }
}

function nextStep() {
    if (currentStep === 1) {
        const name = document.querySelector('input[name="project_name"]').value;
        if (!name.trim()) { alert("Please provide a Project Name before continuing."); document.querySelector('input[name="project_name"]').focus(); return; }
    }
    if (currentStep < totalSteps) { currentStep++; updateWizard(); }
}

function prevStep() {
    if (currentStep > 1) { currentStep--; updateWizard(); }
}

function jumpToStep(step) {
    if (step > currentStep && currentStep === 1) {
        const name = document.querySelector('input[name="project_name"]').value;
        if (!name.trim()) { alert("Please provide a Project Name first."); return; }
    }
    currentStep = step;
    updateWizard();
}

function generateSummary() {
    const name = document.querySelector('input[name="project_name"]').value || '<span class="opacity-50">Not set</span>';
    const address = document.querySelector('input[name="address"]').value || '<span class="opacity-50">Not set</span>';
    const desc = document.querySelector('textarea[name="notes"]').value || '<span class="opacity-50">No description provided.</span>';
    
    const start = document.querySelector('input[name="date_started"]').value || '<span class="opacity-50">TBD</span>';
    const finish = document.querySelector('input[name="date_finished"]').value || '<span class="opacity-50">TBD</span>';
    
    const company = document.querySelector('input[name="company_name"]').value || 'N/A';
    const contactName = document.querySelector('input[name="contact_name"]').value || 'N/A';

    let usersList = [];
    document.querySelectorAll('input[name="user_ids[]"]:checked').forEach(el => {
        usersList.push(el.nextElementSibling.textContent.split('(')[0].trim());
    });
    let usersText = usersList.length > 0 ? usersList.join(', ') : '<span class="text-warning">Unassigned</span>';

    let foldersList = [];
    document.querySelectorAll('input[name="folders[]"]:checked').forEach(el => {
        foldersList.push(el.nextElementSibling.textContent.trim());
    });
    let foldersHtml = foldersList.length > 0 
        ? foldersList.map(f => `<span class="badge bg-primary bg-opacity-25 text-primary me-1 mb-1 px-2 py-1">${f}</span>`).join('') 
        : '<span class="opacity-50">None selected</span>';

    document.getElementById('wizard-summary').innerHTML = `
        <div class="row g-4 justify-content-center text-center mb-4">
            <div class="col-md-10">
                <h3 class="text-white fw-bold mb-1">${name}</h3>
                <p class="text-gray small mb-3"><i class="fas fa-map-marker-alt me-1 text-accent"></i> ${address}</p>
                <div class="p-3 rounded border border-secondary text-start small text-gray" style="background: var(--bg-input);">
                    ${desc}
                </div>
            </div>
        </div>
        
        <div class="row g-3 justify-content-center mx-auto" style="max-width: 650px;">
            <div class="col-md-6 text-center">
                <div class="p-3 border border-secondary rounded h-100 position-relative" style="background: var(--bg-input);">
                    <button type="button" class="btn btn-sm text-gray position-absolute top-0 end-0 m-1" onclick="jumpToStep(2)" title="Edit"><i class="fas fa-pen"></i></button>
                    <div class="text-gray small mb-2 text-uppercase fw-bold ls-1">Team & Contacts</div>
                    <div class="text-white fw-bold small">${company}</div>
                    <div class="text-gray small mb-2">${contactName}</div>
                    <div class="text-info small fw-bold mt-2 pt-2 border-top border-secondary"><i class="fas fa-users me-1"></i> ${usersText}</div>
                </div>
            </div>
            <div class="col-md-6 text-center">
                <div class="p-3 border border-secondary rounded h-100 position-relative" style="background: var(--bg-input);">
                    <button type="button" class="btn btn-sm text-gray position-absolute top-0 end-0 m-1" onclick="jumpToStep(3)" title="Edit"><i class="fas fa-pen"></i></button>
                    <div class="text-gray small mb-2 text-uppercase fw-bold ls-1">Timeline</div>
                    <div class="text-white fw-bold small mb-1"><i class="fas fa-play text-success me-1"></i> Start: ${start}</div>
                    <div class="text-white fw-bold small"><i class="fas fa-flag-checkered text-danger me-1"></i> Finish: ${finish}</div>
                </div>
            </div>
            <div class="col-12 text-center mt-3">
                <div class="p-3 border border-secondary rounded position-relative" style="background: var(--bg-input);">
                    <button type="button" class="btn btn-sm text-gray position-absolute top-0 end-0 m-1" onclick="jumpToStep(3)" title="Edit"><i class="fas fa-pen"></i></button>
                    <div class="text-gray small mb-2 text-uppercase fw-bold ls-1">Initial Folders (${foldersList.length})</div>
                    <div class="d-flex flex-wrap justify-content-center gap-1 mt-2">${foldersHtml}</div>
                </div>
            </div>
        </div>
    `;
}

document.getElementById('createProjectForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    const original = btn.innerHTML;

    const checked = Array.from(this.querySelectorAll('input[name="user_ids[]"]:checked'));
    const hasAdmin = checked.some(i => i.dataset.role === 'admin');
    if (checked.length > 0 && !hasAdmin) {
        alert('At least one admin must be assigned to the project.');
        return;
    }
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Creating...';
    btn.disabled = true;

    const fd = new FormData(this);
    const isEdit = this.querySelector('input[name="is_edit"]')?.value === '1';
    if (isEdit) {
        const projectId = fd.get('project_id');
        fd.append('action', 'update_project_info');
        fetch('../api/api.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if(res.status !== 'success') {
                    alert('Error: ' + res.msg);
                    btn.innerHTML = original;
                    btn.disabled = false;
                    return;
                }
                const folders = Array.from(document.querySelectorAll('input[name="folders[]"]:checked')).map(i => i.value);
                if (folders.length === 0) {
                    window.location.href = 'project_dashboard.php?id=' + projectId;
                    return;
                }
                const fd2 = new FormData();
                fd2.append('action', 'add_project_folders');
                fd2.append('project_id', projectId);
                folders.forEach(f => fd2.append('folders[]', f));
                fetch('../api/api.php', { method: 'POST', body: fd2 })
                    .then(r => r.json())
                    .then(r2 => {
                        if(r2.status === 'success') window.location.href = 'project_dashboard.php?id=' + projectId;
                        else {
                            alert('Error: ' + (r2.msg || 'Unknown'));
                            btn.innerHTML = original;
                            btn.disabled = false;
                        }
                    })
                    .catch(() => {
                        alert('Connection Error');
                        btn.innerHTML = original;
                        btn.disabled = false;
                    });
            })
            .catch(() => {
                alert('Connection Error');
                btn.innerHTML = original;
                btn.disabled = false;
            });
        return;
    }

    fetch('../api/create_project.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.status === 'success') {
                window.location.href = 'project_dashboard.php?id=' + res.id;
            } else {
                alert('Error: ' + res.msg);
                btn.innerHTML = original;
                btn.disabled = false;
            }
        })
        .catch(() => {
            alert('Connection Error');
            btn.innerHTML = original;
            btn.disabled = false;
        });
});
</script>

<?php include __DIR__ . '/../views/footer.php'; ?>
