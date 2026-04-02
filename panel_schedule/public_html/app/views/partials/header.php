<?php
use App\Lib\Csrf;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PanelMaster</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= htmlspecialchars(base_url('/assets/css/app.css')) ?>" rel="stylesheet">
  <meta name="csrf-token" content="<?= htmlspecialchars(Csrf::token()) ?>">
  <script>window.BASE_URL = "<?= htmlspecialchars(base_url('')) ?>";</script>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?= htmlspecialchars(base_url('/projects')) ?>">PanelMaster</a>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-light btn-sm" href="<?= htmlspecialchars(base_url('/projects/new')) ?>">New Project</a>
    </div>
  </div>
</nav>

<main class="container py-4">
