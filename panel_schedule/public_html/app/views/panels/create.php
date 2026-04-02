<div class="card shadow-sm">
  <div class="card-body">
    <h5 class="mb-3">Create Panelboard</h5>
    <form method="post" action="<?= htmlspecialchars(base_url('/projects/' . $project['id'] . '/panels')) ?>">
      <?= csrf_field() ?>

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Panel Name</label>
          <input name="panel_name" class="form-control" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Panel Status</label>
          <select name="panel_status" class="form-select">
            <option value="NEW">NEW</option>
            <option value="EXISTING">EXISTING</option>
            <option value="RELOCATED">RELOCATED</option>
            <option value="DEMO">DEMO</option>
            <option value="FUTURE">FUTURE</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Voltage</label>
          <select name="voltage" class="form-select" required>
            <option value="">Select</option>
            <option value="480Y/277V">480Y/277V</option>
            <option value="208Y/120V">208Y/120V</option>
            <option value="240/120V">240/120V</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Phase/Wire</label>
          <select name="phase_wire" class="form-select" required>
            <option value="">Select</option>
            <option value="3PH, 4W">3PH, 4W</option>
            <option value="3PH, 3W">3PH, 3W</option>
            <option value="1PH, 3W">1PH, 3W</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Poles Config</label>
          <select name="poles_config" class="form-select">
            <option value="12">12</option>
            <option value="18">18</option>
            <option value="24">24</option>
            <option value="30">30</option>
            <option value="42" selected>42</option>
            <option value="54">54</option>
            <option value="60">60</option>
            <option value="72">72</option>
            <option value="84(1)">84(1)</option>
            <option value="84(2)">84(2)</option>
            <option value="90">90</option>
            <option value="126">126</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Panel Type</label>
          <select name="panel_type" class="form-select" required>
            <option value="">Select</option>
            <option value="Lighting/Appliance">Lighting/Appliance</option>
            <option value="Distribution">Distribution</option>
            <option value="Switchboard">Switchboard</option>
            <option value="Switchgear">Switchgear</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Main Type</label>
          <select name="main_type" class="form-select">
            <option value="">Select</option>
            <option value="BREAKER">BREAKER</option>
            <option value="LUGS">LUGS</option>
            <option value="FUSE">FUSE</option>
            <option value="SWITCH">SWITCH</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Mounting</label>
          <select name="mounting" class="form-select">
            <option value="">Select</option>
            <option value="SURFACE">SURFACE</option>
            <option value="FLUSH">FLUSH</option>
            <option value="FLOOR">FLOOR</option>
            <option value="RACK">RACK</option>
          </select>
        </div>
      </div>

      <div class="mt-4">
        <button class="btn btn-primary">Create Panel</button>
        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(base_url('/projects/' . $project['id'])) ?>">Back</a>
      </div>
    </form>
  </div>
</div>
