<?php
use App\Lib\Csrf;
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
    <a class="navbar-brand fw-bold" href="<?= htmlspecialchars(base_url('/projects')) ?>">Panel Schedule</a>
    <div class="d-flex gap-2 align-items-center">
      <button id="themeToggleBtn" class="btn btn-outline-light btn-sm" type="button" title="Toggle theme"><i class="fa-solid fa-moon"></i></button>
      <a class="btn btn-outline-light btn-sm" href="<?= htmlspecialchars(base_url('/projects/new')) ?>">New Project</a>
    </div>
  </div>
</nav>

<main class="container py-4">
