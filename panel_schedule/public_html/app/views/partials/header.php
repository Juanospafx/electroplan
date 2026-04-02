<?php
use App\Lib\Csrf;

$electroplanProjectName = '';
$electroplanProjectId = (int)($_SESSION['electroplan_project_id'] ?? 0);
if ($electroplanProjectId > 0) {
  try {
    $epDb = dirname(__DIR__, 5) . '/core/db/connection.php';
    if (file_exists($epDb)) {
      require $epDb;
      if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare("SELECT name FROM projects WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$electroplanProjectId]);
        $electroplanProjectName = (string)($stmt->fetchColumn() ?: '');
      }
    }
  } catch (Throwable $e) {
    $electroplanProjectName = '';
  }
}
?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Panel Schedule</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= htmlspecialchars(base_url('/assets/css/app.css')) ?>" rel="stylesheet">
  <meta name="csrf-token" content="<?= htmlspecialchars(Csrf::token()) ?>">
  <script>window.BASE_URL = "<?= htmlspecialchars(base_url('')) ?>";</script>
</head>
<body>
<nav class="navbar navbar-expand-lg panel-navbar">
  <div class="container-fluid">
    <div class="d-flex flex-column">
      <a class="navbar-brand fw-bold" href="<?= htmlspecialchars(base_url('/projects')) ?>">Panel Schedule</a>
      <?php if ($electroplanProjectName !== ''): ?>
        <small class="panel-context">Project: <?= htmlspecialchars($electroplanProjectName) ?> (ID #<?= (int)$electroplanProjectId ?>)</small>
      <?php endif; ?>
    </div>
    <div class="d-flex gap-2 align-items-center">
      <button id="themeToggleBtn" class="btn btn-outline-light btn-sm" type="button" title="Toggle theme"><i class="fa-solid fa-moon"></i></button>
      <a class="btn btn-primary btn-sm" href="<?= htmlspecialchars(base_url('/projects/new')) ?>">Create Project</a>
    </div>
  </div>
</nav>

<main class="container py-4">
