<div class="card shadow-sm mb-4">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0">Project Header</h5>
      <div class="d-flex gap-2">
        <form method="post" action="<?= htmlspecialchars(base_url('/api/projects/' . $project['id'] . '/recalc')) ?>" class="d-inline" onsubmit="return app.postForm(event)">
          <?= csrf_field() ?>
          <button class="btn btn-outline-secondary btn-sm" type="submit">Recalc Totals</button>
        </form>
        <a class="btn btn-outline-success btn-sm" href="<?= htmlspecialchars(base_url('/projects/' . $project['id'] . '/export.xlsx')) ?>">Export Excel</a>
        <a class="btn btn-primary btn-sm" href="<?= htmlspecialchars(base_url('/projects/' . $project['id'] . '/panels/new')) ?>">Add Panel</a>
      </div>
    </div>

    <form method="post" action="<?= htmlspecialchars(base_url('/projects/' . $project['id'])) ?>">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Project Name</label>
          <input name="project_name" class="form-control" value="<?= htmlspecialchars($project['project_name']) ?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Project Number</label>
          <input name="project_number" class="form-control" value="<?= htmlspecialchars($project['project_number']) ?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Basis of Design</label>
          <select name="basis_of_design" class="form-select">
            <?php $bod = $project['basis_of_design'] ?? ''; ?>
            <option value="">Select</option>
            <option value="SQUARE D" <?= $bod === 'SQUARE D' ? 'selected' : '' ?>>SQUARE D</option>
            <option value="SIEMENS" <?= $bod === 'SIEMENS' ? 'selected' : '' ?>>SIEMENS</option>
            <option value="GE" <?= $bod === 'GE' ? 'selected' : '' ?>>GE</option>
            <option value="CUTLER-HAMMER" <?= $bod === 'CUTLER-HAMMER' ? 'selected' : '' ?>>CUTLER-HAMMER</option>
            <option value="OTHER" <?= $bod === 'OTHER' ? 'selected' : '' ?>>OTHER</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Last Update</label>
          <input type="date" name="last_update" class="form-control" value="<?= htmlspecialchars($project['last_update'] ?? date('Y-m-d')) ?>">
        </div>
      </div>

      <hr class="my-4">
      <h6>Project Connected Service Totals</h6>
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Voltage</label>
          <?php $sv = $project['service_voltage'] ?? ''; ?>
          <select name="service_voltage" class="form-select js-service-voltage">
            <option value="">Select</option>
            <option value="480Y/277V" <?= $sv === '480Y/277V' ? 'selected' : '' ?>>480Y/277V</option>
            <option value="208Y/120V" <?= $sv === '208Y/120V' ? 'selected' : '' ?>>208Y/120V</option>
            <option value="240/120V" <?= $sv === '240/120V' ? 'selected' : '' ?>>240/120V</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Amps</label>
          <input name="service_amps" class="form-control js-service-amps" value="<?= htmlspecialchars($project['service_amps'] ?? '') ?>" readonly>
        </div>
        <div class="col-md-3">
          <label class="form-label">KVA</label>
          <input name="service_kva" class="form-control js-service-kva" value="<?= htmlspecialchars($project['service_kva'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Total Panels</label>
          <input name="total_panels" class="form-control" value="<?= htmlspecialchars($project['total_panels'] ?? '') ?>">
        </div>
      </div>

      <hr class="my-4">
      <h6>Project Connected Loads by Category</h6>
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Lighting</label>
          <input name="load_lighting" class="form-control" value="<?= htmlspecialchars($project['load_lighting'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Recept</label>
          <input name="load_recept" class="form-control" value="<?= htmlspecialchars($project['load_recept'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Cooling</label>
          <input name="load_cooling" class="form-control" value="<?= htmlspecialchars($project['load_cooling'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Heating</label>
          <input name="load_heating" class="form-control" value="<?= htmlspecialchars($project['load_heating'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Motors</label>
          <input name="load_motors" class="form-control" value="<?= htmlspecialchars($project['load_motors'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Lg. Mtr.</label>
          <input name="load_lg_mtr" class="form-control" value="<?= htmlspecialchars($project['load_lg_mtr'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Equip</label>
          <input name="load_equip" class="form-control" value="<?= htmlspecialchars($project['load_equip'] ?? '') ?>">
        </div>
      </div>

      <div class="mt-4">
        <button class="btn btn-primary">Save Project</button>
      </div>
    </form>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0">Panelboards</h5>
      <a class="btn btn-outline-primary btn-sm" href="<?= htmlspecialchars(base_url('/projects/' . $project['id'] . '/panels/new')) ?>">+ Add Panel</a>
    </div>

    <div class="table-responsive">
      <table class="table table-bordered align-middle">
        <thead>
          <tr>
            <th>#</th>
            <th>Panel Name</th>
            <th>Status</th>
            <th>Voltage</th>
            <th>Phase/Wire</th>
            <th>Poles</th>
            <th>Panel Type</th>
            <th>Main Type</th>
            <th>Main (Size/Type)</th>
            <th>Mounting</th>
            <th>Connected KVA</th>
            <th>Connected Amps</th>
            <th>Demand KVA</th>
            <th>Demand Amps</th>
            <th>% Imbal.</th>
            <th>Balance</th>
            <th>Min Feeder Size</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($panels as $panel): ?>
            <tr>
              <td><?= htmlspecialchars($panel['item_order'] ?? '') ?></td>
              <td><?= htmlspecialchars($panel['panel_name'] ?? '') ?></td>
              <td><?= htmlspecialchars($panel['panel_status'] ?? '') ?></td>
              <td><?= htmlspecialchars($panel['voltage'] ?? '') ?></td>
              <td><?= htmlspecialchars($panel['phase_wire'] ?? '') ?></td>
              <td><?= htmlspecialchars($panel['poles_config'] ?? '') ?></td>
              <td><?= htmlspecialchars($panel['panel_type'] ?? '') ?></td>
              <td><?= htmlspecialchars($panel['main_type'] ?? '') ?></td>
              <td><?= htmlspecialchars($panel['main_size_type'] ?? '') ?></td>
              <td><?= htmlspecialchars($panel['mounting'] ?? '') ?></td>
              <td><?= htmlspecialchars($panel['connected_kva'] ?? '') ?></td>
              <td><?= htmlspecialchars($panel['connected_amps'] ?? '') ?></td>
              <td><?= htmlspecialchars($panel['demand_kva'] ?? '') ?></td>
              <td><?= htmlspecialchars($panel['demand_amps'] ?? '') ?></td>
              <td><?= htmlspecialchars($panel['percent_imbalance'] ?? '') ?></td>
              <td>
                <?php
                  $bs = $panel['balance_status'] ?? 'OK';
                  $bm = $panel['balance_message'] ?? '';
                  $badgeClass = $bs === 'FAIL' ? 'bg-danger' : ($bs === 'WARN' ? 'bg-warning text-dark' : 'bg-success');
                ?>
                <span class="badge <?= $badgeClass ?>" title="<?= htmlspecialchars($bm) ?>"><?= htmlspecialchars($bs) ?></span>
              </td>
              <td><?= htmlspecialchars($panel['minimum_feeder_size'] ?? '') ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(base_url('/panels/' . $panel['id'] . '/edit')) ?>">Edit Schedule</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
