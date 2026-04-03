<?php
$ctx = [];
if (!empty($_SESSION['electroplan_project_id'])) {
  $ctx['project_id'] = (int)$_SESSION['electroplan_project_id'];
}
if (!empty($_SESSION['electroplan_folder_id'])) {
  $ctx['folder_id'] = (int)$_SESSION['electroplan_folder_id'];
}
$ctxSuffix = $ctx ? ('?' . http_build_query($ctx)) : '';
?>
<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h5 class="mb-1">Projects</h5>
        <small class="text-muted">Project summary list</small>
      </div>
      <a class="btn btn-primary" href="<?= htmlspecialchars(base_url('/projects/new') . $ctxSuffix) ?>">Create Project</a>
    </div>

    <div class="table-responsive">
      <table class="table table-striped align-middle">
        <thead>
          <tr>
            <th>Project Name</th>
            <th>Project #</th>
            <th>Basis of Design</th>
            <th>Voltage</th>
            <th>Amps</th>
            <th>KVA</th>
            <th>Total Panels</th>
            <th>Last Update</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($projects as $project): ?>
            <tr>
              <td><?= htmlspecialchars($project['project_name'] ?? '') ?></td>
              <td><?= htmlspecialchars($project['project_number'] ?? '') ?></td>
              <td><?= htmlspecialchars($project['basis_of_design'] ?? '') ?></td>
              <td><?= htmlspecialchars($project['service_voltage'] ?? '') ?></td>
              <td><?= htmlspecialchars($project['service_amps'] ?? '') ?></td>
              <td><?= htmlspecialchars($project['service_kva'] ?? '') ?></td>
              <td><?= htmlspecialchars($project['total_panels'] ?? '') ?></td>
              <td><?= htmlspecialchars($project['last_update'] ?? '') ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars(base_url('/projects/' . $project['id'])) ?>">View</a>
                <form action="<?= htmlspecialchars(base_url('/projects/' . $project['id'] . '/delete')) ?>" method="post" class="d-inline" onsubmit="return confirm('¿Estás seguro de eliminar este proyecto? Se borrarán todos los paneles asociados.');">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
