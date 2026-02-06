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
include __DIR__ . '/../views/header.php'; 
?>

<style>
    /* 1. INPUTS MÁS FINOS Y COMPACTOS */
    .form-control {
        background-color: rgba(0, 0, 0, 0.2) !important;
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #fff !important;
        font-size: 0.85rem;      /* Texto más pequeño */
        padding: 6px 10px;       /* Relleno reducido */
        border-radius: 6px;
        min-height: 34px;        /* Altura controlada */
    }
    .form-control:focus {
        background-color: rgba(0, 0, 0, 0.3) !important;
        border-color: var(--primary);
        box-shadow: none;
    }
    .form-control::placeholder {
        color: rgba(255, 255, 255, 0.25);
        font-size: 0.8rem;
    }

    /* 2. LABELS PEGADOS AL INPUT */
    .form-label {
        color: #9ca3af;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        margin-bottom: 2px; /* Casi sin espacio abajo */
        display: block;
    }

    /* 3. TARJETAS MÁS DENSAS */
    .box-card-compact {
        background: var(--bg-card);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 10px;
        padding: 15px; /* Padding reducido (antes era p-4) */
        margin-bottom: 15px; /* Margen entre tarjetas reducido */
    }

    /* 4. ENCABEZADOS DE SECCIÓN PEQUEÑOS */
    .section-header {
        display: flex;
        align-items: center;
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .section-icon {
        font-size: 0.9rem;
        margin-right: 8px;
        color: var(--primary);
    }
    .section-title {
        font-size: 0.85rem;
        font-weight: 700;
        color: white;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* BOTONES */
    .btn-create-submit {
        background: var(--primary);
        color: white;
        border: none;
        font-size: 0.9rem;
        font-weight: 600;
        border-radius: 6px;
        transition: 0.2s;
    }
    .btn-create-submit:hover {
        background: #4f46e5;
    }
    
    /* SCROLLBAR FINO */
    .folder-list {
        max-height: 480px; 
        overflow-y: auto;
        padding-right: 5px;
    }
    .folder-list::-webkit-scrollbar { width: 4px; }
    .folder-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }

    /* CHECKBOXES COMPACTOS */
    .check-item {
        padding: 4px 8px;
        border-radius: 4px;
        transition: 0.1s;
    }
    .check-item:hover { background: rgba(255,255,255,0.03); }
    .form-check-input { width: 0.9em; height: 0.9em; margin-top: 0.25em; }
</style>

<div class="main-content p-3">
    
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold text-white mb-0"><?= $isEdit ? 'Edit Project' : 'Create Project' ?></h5>
        <a href="<?= $isEdit ? 'project_dashboard.php?id=' . (int)$editId : 'index.php' ?>" class="text-gray text-decoration-none small hover-white"><i class="fas fa-times me-1"></i> Cancel</a>
    </div>

    <form id="createProjectForm">
        <input type="hidden" name="is_edit" value="<?= $isEdit ? '1' : '0' ?>">
        <?php if($isEdit): ?>
            <input type="hidden" name="project_id" value="<?= (int)$editId ?>">
        <?php endif; ?>
        <div class="row g-3">
            
            <div class="col-lg-8">
                
                <div class="box-card-compact">
                    <div class="section-header">
                        <i class="fas fa-info-circle section-icon"></i>
                        <span class="section-title">General Info</span>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-8">
                            <label class="form-label">Project Name *</label>
                            <input type="text" name="project_name" class="form-control" required placeholder="Project Title" value="<?= htmlspecialchars($project['name'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Job Address</label>
                            <input type="text" name="address" class="form-control" placeholder="City, State" value="<?= htmlspecialchars($projectAddress) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Brief scope..."><?= htmlspecialchars($projectNotes) ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="box-card-compact">
                    <div class="section-header">
                        <i class="fas fa-users section-icon text-info"></i>
                        <span class="section-title">Contacts</span>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label text-white opacity-75">Site Contact Name</label>
                            <input type="text" name="contact_name" class="form-control" placeholder="Name" value="<?= htmlspecialchars($projectContactName) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-white opacity-75">Site Contact Phone</label>
                            <input type="text" name="contact_phone" class="form-control" placeholder="Phone" value="<?= htmlspecialchars($projectContactPhone) ?>">
                        </div>
                        
                        <div class="col-12"><div style="border-top:1px dashed rgba(255,255,255,0.05); margin: 5px 0;"></div></div>

                        <div class="col-md-4">
                            <label class="form-label text-warning opacity-75">Company (GC)</label>
                            <input type="text" name="company_name" class="form-control" placeholder="Company" value="<?= htmlspecialchars($projectCompanyName) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-warning opacity-75">Office Phone</label>
                            <input type="text" name="company_phone" class="form-control" placeholder="Phone" value="<?= htmlspecialchars($projectCompanyPhone) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-warning opacity-75">HQ Address</label>
                            <input type="text" name="company_address" class="form-control" placeholder="Address" value="<?= htmlspecialchars($projectCompanyAddress) ?>">
                        </div>
                    </div>
                </div>

                <?php if(!$isEdit): ?>
                <div class="box-card-compact">
                    <div class="section-header">
                        <i class="fas fa-user-plus section-icon text-success"></i>
                        <span class="section-title">Assign Users</span>
                    </div>
                    <label class="form-label">Select Users</label>
                    <div class="border rounded p-2" style="max-height:180px; overflow:auto;">
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
                    <small class="text-muted d-block mt-2" style="font-size:0.7rem;">At least one admin must be assigned when selecting users.</small>
                </div>
                <?php endif; ?>

                <div class="box-card-compact mb-0"> <div class="section-header">
                        <i class="fas fa-calendar-alt section-icon text-warning"></i>
                        <span class="section-title">Timeline</span>
                    </div>
                    <div class="row g-2">
                        <div class="col-4 col-md-2">
                            <label class="form-label">Bid Sent</label>
                            <input type="date" name="date_bid_send" class="form-control" value="<?= htmlspecialchars($project['date_bid_sent'] ?? '') ?>">
                        </div>
                        <div class="col-4 col-md-2">
                            <label class="form-label">Awarded</label>
                            <input type="date" name="date_bid_awarded" class="form-control" value="<?= htmlspecialchars($project['date_bid_awarded'] ?? '') ?>">
                        </div>
                        <div class="col-4 col-md-2">
                            <label class="form-label">Start</label>
                            <input type="date" name="date_started" class="form-control" value="<?= htmlspecialchars($project['date_started'] ?? '') ?>">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">Target Finish</label>
                            <input type="date" name="date_finished" class="form-control" value="<?= htmlspecialchars($project['date_finished'] ?? '') ?>">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label">Warranty End</label>
                            <input type="date" name="date_warranty_end" class="form-control" value="<?= htmlspecialchars($project['date_warranty_end'] ?? '') ?>">
                        </div>
                    </div>
                </div>

            </div>

            <div class="col-lg-4">
                <div class="box-card-compact h-100 d-flex flex-column">
                    <div class="section-header">
                        <i class="fas fa-folder-tree section-icon text-success"></i>
                        <span class="section-title">Folders</span>
                        <small class="ms-auto text-muted" style="font-size:0.65rem">Uncheck unused</small>
                    </div>

                    <div class="folder-list flex-grow-1">
                        <div class="d-flex flex-column gap-1">
                            <label class="check-item d-flex align-items-center gap-2">
                                <input class="form-check-input" type="checkbox" name="folders[]" value="bom" checked> <span class="small">BoM</span>
                            </label>
                            <label class="check-item d-flex align-items-center gap-2">
                                <input class="form-check-input" type="checkbox" name="folders[]" value="schedule_values" checked> <span class="small">Schedule of Values</span>
                            </label>
                            <label class="check-item d-flex align-items-center gap-2">
                                <input class="form-check-input" type="checkbox" name="folders[]" value="rfi" checked> <span class="small">RFI</span>
                            </label>
                            <label class="check-item d-flex align-items-center gap-2">
                                <input class="form-check-input" type="checkbox" name="folders[]" value="drawings" checked> <span class="small">Drawings</span>
                            </label>
                            <label class="check-item d-flex align-items-center gap-2">
                                <input class="form-check-input" type="checkbox" name="folders[]" value="photos" checked> <span class="small">Photos</span>
                            </label>
                            <label class="check-item d-flex align-items-center gap-2">
                                <input class="form-check-input" type="checkbox" name="folders[]" value="panel_schedule" checked> <span class="small">Panel Schedule</span>
                            </label>
                            <div class="my-1 border-top border-secondary opacity-25"></div>
                            <label class="check-item d-flex align-items-center gap-2">
                                <input class="form-check-input" type="checkbox" name="folders[]" value="panel_tags"> <span class="small">Panel/Meter Tags</span>
                            </label>
                            <label class="check-item d-flex align-items-center gap-2">
                                <input class="form-check-input" type="checkbox" name="folders[]" value="noc"> <span class="small">NOC</span>
                            </label>
                            <label class="check-item d-flex align-items-center gap-2">
                                <input class="form-check-input" type="checkbox" name="folders[]" value="submittal"> <span class="small">Submittal</span>
                            </label>
                            <label class="check-item d-flex align-items-center gap-2">
                                <input class="form-check-input" type="checkbox" name="folders[]" value="permit"> <span class="small">Permit</span>
                            </label>
                            <label class="check-item d-flex align-items-center gap-2">
                                <input class="form-check-input" type="checkbox" name="folders[]" value="acknowledgement"> <span class="small">Acknowledgement</span>
                            </label>
                            <label class="check-item d-flex align-items-center gap-2">
                                <input class="form-check-input" type="checkbox" name="folders[]" value="payapp"> <span class="small">Payapp</span>
                            </label>
                            <label class="check-item d-flex align-items-center gap-2">
                                <input class="form-check-input" type="checkbox" name="folders[]" value="insurance"> <span class="small">Insurance</span>
                            </label>
                            <label class="check-item d-flex align-items-center gap-2">
                                <input class="form-check-input" type="checkbox" name="folders[]" value="fault_calc"> <span class="small">Fault Calc</span>
                            </label>
                            <label class="check-item d-flex align-items-center gap-2">
                                <input class="form-check-input" type="checkbox" name="folders[]" value="labor_record"> <span class="small">Labor Record</span>
                            </label>
                            <label class="check-item d-flex align-items-center gap-2">
                                <input class="form-check-input" type="checkbox" name="folders[]" value="expenses"> <span class="small">Expenses</span>
                            </label>
                            <label class="check-item d-flex align-items-center gap-2">
                                <input class="form-check-input" type="checkbox" name="folders[]" value="warranty_sup"> <span class="small">Warranty Sup</span>
                            </label>
                            <label class="check-item d-flex align-items-center gap-2">
                                <input class="form-check-input" type="checkbox" name="folders[]" value="clock_in"> <span class="small">Clock in</span>
                            </label>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="submit" class="btn btn-create-submit w-100 py-2">
                            <i class="fas <?= $isEdit ? 'fa-save' : 'fa-rocket' ?> me-2"></i> <?= $isEdit ? 'Save Changes' : 'Create Project' ?>
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>

<script>
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
        fd.append('action', 'update_project_info');
        fetch('../api/api.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if(res.status === 'success') {
                    window.location.href = 'project_dashboard.php?id=' + fd.get('project_id');
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
