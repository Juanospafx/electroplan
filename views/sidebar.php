<?php
// sidebar.php - Barra lateral centralizada e inteligente
$currentScript = basename($_SERVER['PHP_SELF']);
$pId   = $_GET['project_id'] ?? null;
$view  = $_GET['view'] ?? '';

$isTrash     = ($view === 'trash');
$isTimeline  = ($currentScript === 'timeline.php');
$isSettings  = ($currentScript === 'settings.php');
$isProjects  = ($currentScript === 'projects.php' || ($currentScript === 'index.php' && $pId));
$isDirectory = ($currentScript === 'directorio.php');
$isFiles     = ($currentScript === 'archivos.php');
$isTaskMgr   = ($currentScript === 'task_manager_dashboard.php');
$isDashboard = ($currentScript === 'index.php' && !$pId && !$isTrash);

$userRoleRawSidebar = $_SESSION['role'] ?? 'viewer';
$isAdminSidebar = (strtolower($userRoleRawSidebar) === 'admin');
$isNotViewerSidebar = (strtolower($userRoleRawSidebar) !== 'viewer');
?>

<nav class="sidebar" id="mainSidebar">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2.5rem; margin-top: 0.5rem; padding-left: 1.5rem; padding-right: 1rem;">
        <a href="../pages/index.php" class="logo-container">
            <div class="logo-full" role="img" aria-label="Brightronix Logo"></div>
            <div class="app-subtitle" style="font-size: 0.85rem; color: var(--text-gray, #94a3b8); font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; margin-top: -0.8rem; margin-left: 0.6rem;">Electro Plan</div>
        </a>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="sidebar-close-mobile flex-shrink-0" onclick="toggleSidebar()" aria-label="Close sidebar" title="Close sidebar">
                <i class="fas fa-times"></i>
            </button>
            <button id="globalThemeToggle" class="theme-btn d-none d-md-flex" type="button" onclick="toggleAppTheme()" aria-label="Toggle day/night theme" title="Switch to Day Mode">
                <i class="fas fa-sun"></i>
            </button>
        </div>
    </div>

    <div class="flex-grow-1 px-4">
        <a href="../pages/index.php" class="menu-item <?= $isDashboard ? 'active' : '' ?>">
            <i class="fas fa-th-large"></i><span class="menu-label">Dashboard</span>
        </a>

        <a href="../pages/projects.php" class="menu-item <?= $isProjects ? 'active' : '' ?>">
            <i class="fas fa-layer-group"></i><span class="menu-label">Projects</span>
        </a>

        <a href="../pages/archivos.php" class="menu-item <?= $isFiles ? 'active' : '' ?>">
            <i class="fas fa-file-alt"></i><span class="menu-label">Files</span>
        </a>

        <?php if($isNotViewerSidebar): ?>
            <a href="../pages/task_manager_dashboard.php" class="menu-item <?= $isTaskMgr ? 'active' : '' ?>">
                <i class="fas fa-tasks"></i><span class="menu-label">Task Manager</span>
            </a>
        <?php endif; ?>

        <?php if($isAdminSidebar): ?>
            <a href="../pages/directorio.php" class="menu-item <?= $isDirectory ? 'active' : '' ?>">
                <i class="fas fa-sitemap"></i><span class="menu-label">Directory</span>
            </a>

            <a href="../pages/timeline.php" class="menu-item <?= $isTimeline ? 'active' : '' ?>">
                <i class="far fa-calendar-alt"></i><span class="menu-label">Timeline</span>
            </a>

            <a href="../admin/settings.php" class="menu-item <?= $isSettings ? 'active' : '' ?>">
                <i class="fas fa-users-cog"></i><span class="menu-label">Settings</span>
            </a>

            <hr style="border-color:rgba(255,255,255,0.1)">

            <a href="../pages/index.php?view=trash" class="menu-item <?= $isTrash ? 'active' : '' ?> text-danger">
                <i class="fas fa-trash-alt"></i><span class="menu-label">Recycle Bin</span>
            </a>
        <?php endif; ?>
    </div>

    <div class="px-4 pb-4">
        <a href="../pages/logout.php" class="menu-item text-danger">
            <i class="fas fa-sign-out-alt"></i><span class="menu-label">Logout</span>
        </a>
    </div>
</nav>
