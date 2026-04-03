<?php
$context = $context ?? [];
$ctxProjectId = (int)($context['project_id'] ?? 0);
$ctxFolderId = (int)($context['folder_id'] ?? 0);
$ctxProjectName = (string)($context['project_name'] ?? '');
$ctxProjectNumber = (string)($context['project_number'] ?? '');
$ctxFolderName = (string)($context['folder_name'] ?? '');
?>
<div class="card shadow-sm">
  <div class="card-body">
    <h5 class="mb-3">Create Project</h5>

    <?php if ($ctxProjectId > 0 || $ctxFolderId > 0): ?>
      <div class="alert alert-info py-2 px-3 small" role="alert">
        Context detected from Electroplan:
        <?php if ($ctxProjectName !== ''): ?><strong>Project <?= htmlspecialchars($ctxProjectName) ?></strong><?php endif; ?>
        <?php if ($ctxProjectId > 0): ?>(ID #<?= (int)$ctxProjectId ?>)<?php endif; ?>
        <?php if ($ctxFolderName !== ''): ?> · Folder <strong><?= htmlspecialchars($ctxFolderName) ?></strong><?php endif; ?>
        <?php if ($ctxFolderId > 0): ?>(ID #<?= (int)$ctxFolderId ?>)<?php endif; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars(base_url('/projects')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="electroplan_project_id" value="<?= (int)$ctxProjectId ?>">
      <input type="hidden" name="electroplan_folder_id" value="<?= (int)$ctxFolderId ?>">

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Project Name</label>
          <input name="project_name" class="form-control" required value="<?= htmlspecialchars($ctxProjectName) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Project Number</label>
          <input name="project_number" class="form-control" required value="<?= htmlspecialchars($ctxProjectNumber) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Basis of Design</label>
          <select name="basis_of_design" class="form-select">
            <option value="">Select</option>
            <option value="SQUARE D">SQUARE D</option>
            <option value="SIEMENS">SIEMENS</option>
            <option value="GE">GE</option>
            <option value="CUTLER-HAMMER">CUTLER-HAMMER</option>
            <option value="OTHER">OTHER</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Last Update</label>
          <input type="date" name="last_update" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>
      </div>

      <hr class="my-4">
      <h6>Project Connected Service Totals</h6>
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Voltage</label>
          <select name="service_voltage" class="form-select js-service-voltage">
            <option value="">Select</option>
            <option value="480Y/277V">480Y/277V</option>
            <option value="208Y/120V">208Y/120V</option>
            <option value="240/120V">240/120V</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Amps</label>
          <input name="service_amps" class="form-control js-service-amps" placeholder="0.00" readonly>
        </div>
        <div class="col-md-3">
          <label class="form-label">KVA</label>
          <input name="service_kva" class="form-control js-service-kva" placeholder="0.00">
        </div>
        <div class="col-md-3">
          <label class="form-label">Total Panels</label>
          <input name="total_panels" class="form-control" placeholder="0">
        </div>
      </div>

      <hr class="my-4">
      <h6>Project Connected Loads by Category</h6>
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Lighting</label>
          <input name="load_lighting" class="form-control" placeholder="0.00">
        </div>
        <div class="col-md-3">
          <label class="form-label">Recept</label>
          <input name="load_recept" class="form-control" placeholder="0.00">
        </div>
        <div class="col-md-3">
          <label class="form-label">Cooling</label>
          <input name="load_cooling" class="form-control" placeholder="0.00">
        </div>
        <div class="col-md-3">
          <label class="form-label">Heating</label>
          <input name="load_heating" class="form-control" placeholder="0.00">
        </div>
        <div class="col-md-3">
          <label class="form-label">Motors</label>
          <input name="load_motors" class="form-control" placeholder="0.00">
        </div>
        <div class="col-md-3">
          <label class="form-label">Lg. Mtr.</label>
          <input name="load_lg_mtr" class="form-control" placeholder="0.00">
        </div>
        <div class="col-md-3">
          <label class="form-label">Equip</label>
          <input name="load_equip" class="form-control" placeholder="0.00">
        </div>
      </div>

      <div class="mt-4">
        <button class="btn btn-primary">Save Project</button>
      </div>
    </form>
  </div>
</div>
