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
<section class="ps-page-shell">
  <div class="ps-main-card">
    <div class="ps-card-header d-flex justify-content-between align-items-start gap-3">
      <div>
        <h1 class="ps-title mb-1">Projects</h1>
        <p class="ps-subtitle mb-0">Project summary list</p>
        <?php if (!empty($_SESSION['electroplan_project_id']) || !empty($_SESSION['electroplan_folder_id'])): ?>
          <div class="ps-context-chip mt-2">
            Context linked from Electroplan tool launch
            <?php if (!empty($_SESSION['electroplan_project_id'])): ?> · Project ID #<?= (int)$_SESSION['electroplan_project_id'] ?><?php endif; ?>
            <?php if (!empty($_SESSION['electroplan_folder_id'])): ?> · Folder ID #<?= (int)$_SESSION['electroplan_folder_id'] ?><?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      <a class="btn btn-accent ps-cta" href="<?= htmlspecialchars(base_url('/projects/new') . $ctxSuffix) ?>">Create Project</a>
    </div>

    <?php if (empty($projects)): ?>
      <div class="ps-empty-state">
        <div class="ps-empty-icon">📁</div>
        <h3 class="ps-empty-title">No projects yet</h3>
        <p class="ps-empty-text">Create your first project to start building panel schedules with the same workspace context from Electroplan.</p>
        <a class="btn btn-accent" href="<?= htmlspecialchars(base_url('/projects/new') . $ctxSuffix) ?>">Create Project</a>
      </div>
    <?php else: ?>
      <div class="table-responsive ps-table-wrap">
        <table class="table ps-table align-middle mb-0">
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
    <?php endif; ?>
  </div>
</section>
