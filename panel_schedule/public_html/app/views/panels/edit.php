<div class="card shadow-sm mb-4">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0">Panelboard Info</h5>
      <div class="d-flex gap-2">
        <form method="post" action="<?= htmlspecialchars(base_url('/panels/' . $panel['id'])) ?>">
          <?= csrf_field() ?>
          <button class="btn btn-primary btn-sm">Save Panel</button>
          <button type="submit" formaction="<?= htmlspecialchars(base_url('/panels/' . $panel['id'] . '/delete')) ?>" onclick="return confirm('Are you sure you want to delete this panel? This cannot be undone.');" class="btn btn-danger btn-sm">Delete Panel</button>

          <a class="btn btn-outline-success btn-sm" href="<?= htmlspecialchars(base_url('/panels/' . $panel['id'] . '/export.xlsx' . (!empty($_SESSION['electroplan_project_id']) ? ('?project_id=' . (int)$_SESSION['electroplan_project_id']) : ''))) ?>">Export Excel</a>
          <a class="btn btn-outline-danger btn-sm" href="<?= htmlspecialchars(base_url('/panels/' . $panel['id'] . '/export.pdf' . (!empty($_SESSION['electroplan_project_id']) ? ('?project_id=' . (int)$_SESSION['electroplan_project_id']) : ''))) ?>">Export PDF</a>

          <a class="btn btn-outline-success btn-sm" href="<?= htmlspecialchars(base_url('/panels/' . $panel['id'] . '/export.xlsx')) ?>">Export Excel</a>
          <a class="btn btn-outline-danger btn-sm" href="<?= htmlspecialchars(base_url('/panels/' . $panel['id'] . '/export.pdf')) ?>">Export PDF</a>

      </div>
    </div>

    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Panel Name</label>
        <input name="panel_name" class="form-control" value="<?= htmlspecialchars($panel['panel_name'] ?? '') ?>" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Panel Status</label>
        <?php $ps = $panel['panel_status'] ?? 'NEW'; ?>
        <select name="panel_status" class="form-select">
          <option value="NEW" <?= $ps === 'NEW' ? 'selected' : '' ?>>NEW</option>
          <option value="EXISTING" <?= $ps === 'EXISTING' ? 'selected' : '' ?>>EXISTING</option>
          <option value="RELOCATED" <?= $ps === 'RELOCATED' ? 'selected' : '' ?>>RELOCATED</option>
          <option value="DEMO" <?= $ps === 'DEMO' ? 'selected' : '' ?>>DEMO</option>
          <option value="FUTURE" <?= $ps === 'FUTURE' ? 'selected' : '' ?>>FUTURE</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Voltage</label>
        <?php $pv = $panel['voltage'] ?? ''; ?>
        <select name="voltage" class="form-select" required>
          <option value="">Select</option>
          <option value="480Y/277V" <?= $pv === '480Y/277V' ? 'selected' : '' ?>>480Y/277V</option>
          <option value="208Y/120V" <?= $pv === '208Y/120V' ? 'selected' : '' ?>>208Y/120V</option>
          <option value="240/120V" <?= $pv === '240/120V' ? 'selected' : '' ?>>240/120V</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Phase/Wire</label>
        <?php $pw = $panel['phase_wire'] ?? ''; ?>
        <select name="phase_wire" class="form-select" required>
          <option value="">Select</option>
          <option value="3PH, 4W" <?= $pw === '3PH, 4W' ? 'selected' : '' ?>>3PH, 4W</option>
          <option value="3PH, 3W" <?= $pw === '3PH, 3W' ? 'selected' : '' ?>>3PH, 3W</option>
          <option value="1PH, 3W" <?= $pw === '1PH, 3W' ? 'selected' : '' ?>>1PH, 3W</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Poles Config</label>
        <select name="poles_config" class="form-select">
          <?php $pc = $panel['poles_config'] ?? '42'; ?>
          <?php foreach (['12','18','24','30','42','54','60','72','84(1)','84(2)','90','126'] as $opt): ?>
            <option value="<?= $opt ?>" <?= $pc === $opt ? 'selected' : '' ?>><?= $opt ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Panel Type</label>
        <?php $pt = $panel['panel_type'] ?? ''; ?>
        <select name="panel_type" class="form-select" required>
          <option value="">Select</option>
          <option value="Lighting/Appliance" <?= $pt === 'Lighting/Appliance' ? 'selected' : '' ?>>Lighting/Appliance</option>
          <option value="Distribution" <?= $pt === 'Distribution' ? 'selected' : '' ?>>Distribution</option>
          <option value="Switchboard" <?= $pt === 'Switchboard' ? 'selected' : '' ?>>Switchboard</option>
          <option value="Switchgear" <?= $pt === 'Switchgear' ? 'selected' : '' ?>>Switchgear</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Main (Size/Type)</label>
        <?php $mst = $panel['main_size_type'] ?? ''; ?>
        <select name="main_size_type" class="form-select">
          <option value="">Select</option>
          <?php foreach(['30A', '60A', '100A', '125A', '150A', '200A', '225A', '250A', '400A', '600A', '800A', '1000A', '1200A', '1600A', '2000A', '2500A', '3000A', '4000A'] as $opt): ?>
            <option value="<?= $opt ?>" <?= $mst === $opt ? 'selected' : '' ?>><?= $opt ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Main Type</label>
        <?php $mt = $panel['main_type'] ?? ''; ?>
        <select name="main_type" class="form-select">
          <option value="">Select</option>
          <option value="BREAKER" <?= $mt === 'BREAKER' ? 'selected' : '' ?>>BREAKER</option>
          <option value="LUGS" <?= $mt === 'LUGS' ? 'selected' : '' ?>>LUGS</option>
          <option value="FUSE" <?= $mt === 'FUSE' ? 'selected' : '' ?>>FUSE</option>
          <option value="SWITCH" <?= $mt === 'SWITCH' ? 'selected' : '' ?>>SWITCH</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Mounting</label>
        <?php $pm = $panel['mounting'] ?? ''; ?>
        <select name="mounting" class="form-select">
          <option value="">Select</option>
          <option value="SURFACE" <?= $pm === 'SURFACE' ? 'selected' : '' ?>>SURFACE</option>
          <option value="FLUSH" <?= $pm === 'FLUSH' ? 'selected' : '' ?>>FLUSH</option>
          <option value="FLOOR" <?= $pm === 'FLOOR' ? 'selected' : '' ?>>FLOOR</option>
          <option value="RACK" <?= $pm === 'RACK' ? 'selected' : '' ?>>RACK</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Minimum Feeder Size</label>
        <input name="minimum_feeder_size" class="form-control" value="<?= htmlspecialchars($panel['minimum_feeder_size'] ?? '') ?>">
      </div>
    </div>
    </form>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0">Panel Schedule</h5>
      <div class="d-flex gap-2">
        <button id="saveScheduleBtn" class="btn btn-primary btn-sm">Save Schedule</button>
        <button id="recalcBtn" class="btn btn-outline-secondary btn-sm">Recalc Panel</button>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <div class="p-3 border rounded">
          <div class="d-flex align-items-center gap-2 mb-2">
            <strong>Balance</strong>
            <span id="balanceBadge" class="badge bg-secondary">--</span>
          </div>
          <div class="small text-muted" id="balanceMessage">No data</div>
        </div>
      </div>
      <div class="col-md-8">
        <div class="p-3 border rounded">
          <div class="row g-2 small">
            <div class="col-md-4"><strong>Phase A</strong> <span id="phaseA">0.00 A / 0.00 kVA</span></div>
            <div class="col-md-4"><strong>Phase B</strong> <span id="phaseB">0.00 A / 0.00 kVA</span></div>
            <div class="col-md-4"><strong>Phase C</strong> <span id="phaseC">0.00 A / 0.00 kVA</span></div>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2 mb-2">
      <button type="button" id="addRowBtn" class="btn btn-sm btn-secondary">+ Add Row</button>
      <button type="button" id="removeRowBtn" class="btn btn-sm btn-secondary">- Remove Row</button>
    </div>

    <div class="table-responsive">
      <table class="table table-bordered align-middle" id="scheduleTable">
        <thead class="table-light">
          <tr>
            <th class="text-center" style="width:50px;">CKT</th>
            <th style="width:80px;">Span</th>
            <th>Description</th>
            <th style="width:100px;">Load</th>
            <th style="width:80px;">Unit</th>
            <th style="width:100px;">Cat</th>
            <th>Notes</th>
            <th class="text-center" style="width:50px;">PH</th>
            <th>Notes</th>
            <th style="width:100px;">Cat</th>
            <th style="width:80px;">Unit</th>
            <th style="width:100px;">Load</th>
            <th>Description</th>
            <th style="width:80px;">Span</th>
            <th class="text-center" style="width:50px;">CKT</th>
          </tr>
        </thead>
        <tbody id="scheduleTbody"></tbody>
        <tfoot class="table-light">
          <tr>
            <td colspan="15" class="text-center fw-bold">
              Total Amps &mdash; 
              Phase A: <span id="footerAmpsA">0.00</span> A &nbsp;|&nbsp; 
              Phase B: <span id="footerAmpsB">0.00</span> A <span id="footerPhaseCContainer">&nbsp;|&nbsp; 
              Phase C: <span id="footerAmpsC">0.00</span> A</span>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

<script>
  window.panelScheduleConfig = {
    panelId: <?= (int)$panel['id'] ?>,
    polesConfig: "<?= htmlspecialchars($panel['poles_config'] ?? '42') ?>",
    baseUrl: "<?= base_url() ?>",
  };
</script>
<script src="<?= htmlspecialchars(base_url('/assets/js/schedule-config.js')) ?>"></script>
<script src="<?= htmlspecialchars(base_url('/assets/js/schedule-engine.js')) ?>"></script>
<script src="<?= htmlspecialchars(base_url('/assets/js/panel-editor.js')) ?>"></script>
