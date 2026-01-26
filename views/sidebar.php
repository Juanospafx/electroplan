<?php
// sidebar.php - Barra lateral centralizada e inteligente
// Detectamos el script actual y los parámetros
$currentScript = basename($_SERVER['PHP_SELF']);
$pId   = $_GET['project_id'] ?? null;
$view  = $_GET['view'] ?? '';

// Lógica de Estado Activo (Calculamos qué botón encender)
$isTrash     = ($view === 'trash');
$isTimeline  = ($currentScript === 'timeline.php');
$isSettings  = ($currentScript === 'settings.php');
$isProjects  = ($currentScript === 'projects.php' || ($currentScript === 'index.php' && $pId));
// Dashboard solo se enciende si es index.php Y no hay proyecto Y no es papelera
$isDashboard = ($currentScript === 'index.php' && !$pId && !$isTrash);

// Definimos si el usuario es admin (asumiendo que $isAdmin viene del archivo padre, si no, lo recalculamos seguro)
$userRoleRawSidebar = $_SESSION['role'] ?? 'viewer';
$isAdminSidebar = (strtolower($userRoleRawSidebar) === 'admin');
?>

<nav class="sidebar">
    <div class="brand">
        <div class="brand-icon"><i class="fas fa-bolt"></i></div>
        Brightronix
    </div>
    
    <div class="flex-grow-1">
        <a href="../pages/index.php" class="menu-item <?= $isDashboard ? 'active' : '' ?>">
            <i class="fas fa-th-large"></i> Dashboard
        </a>
        
        <a href="../pages/projects.php" class="menu-item <?= $isProjects ? 'active' : '' ?>">
            <i class="fas fa-layer-group"></i> Projects
        </a>

        <?php if($isAdminSidebar): ?>
            <a href="../pages/timeline.php" class="menu-item <?= $isTimeline ? 'active' : '' ?>">
                <i class="far fa-calendar-alt"></i> Timeline
            </a>
            
            <a href="../admin/settings.php" class="menu-item <?= $isSettings ? 'active' : '' ?>">
                <i class="fas fa-users-cog"></i> Settings
            </a>
            
            <hr style="border-color:rgba(255,255,255,0.1)">
            
            <a href="../pages/index.php?view=trash" class="menu-item <?= $isTrash ? 'active' : '' ?> text-danger">
                <i class="fas fa-trash-alt"></i> Recycle Bin
            </a>
        <?php endif; ?>
    </div>
    
    <div>
        <a href="../pages/logout.php" class="menu-item text-danger">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</nav>
