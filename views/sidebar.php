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
$isDirectory = ($currentScript === 'directorio.php');
$isFiles     = ($currentScript === 'archivos.php');
// Dashboard solo se enciende si es index.php Y no hay proyecto Y no es papelera
$isDashboard = ($currentScript === 'index.php' && !$pId && !$isTrash);

// Definimos si el usuario es admin (asumiendo que $isAdmin viene del archivo padre, si no, lo recalculamos seguro)
$userRoleRawSidebar = $_SESSION['role'] ?? 'viewer';
$isAdminSidebar = (strtolower($userRoleRawSidebar) === 'admin');
?>

<style>
    /* Estilo del Sidebar Ampliado y Mimificado */
    .sidebar { 
        width: 380px !important; 
        background: var(--bg-card, #242a38) !important; 
        border-right: 1px solid var(--border-subtle, #2f384a) !important; 
        box-shadow: 4px 0 24px rgba(0,0,0,0.02);
        transition: background 0.3s, border 0.3s, transform 0.3s ease, width 0.25s ease, padding 0.25s ease !important;
    }

    /* Ajuste dinámico del contenido principal para que no se solape con el nuevo sidebar de 380px */
    @media (min-width: 992px) {
        .main-content { margin-left: 380px !important; width: calc(100% - 380px) !important; }
    }

    .logo-container {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        flex: 1;
        text-decoration: none;
    }

    .logo-full {
        height: 4.5rem !important; 
        width: 220px !important; /* Ajustado para separar más el botón del logo */
        display: block;
        background-color: var(--text-white, #fff);
        -webkit-mask: url('../assets/logo-text.png') no-repeat left center;
        mask: url('../assets/logo-text.png') no-repeat left center;
        -webkit-mask-size: contain; mask-size: contain; 
        transition: filter 0.3s ease, background-color 0.3s ease;
    }

    .menu-item {
        width: 100%; padding: 1rem 1.25rem;
        border: 1px solid transparent; background: transparent;
        border-radius: 1rem; color: var(--text-gray, #94a3b8); font-weight: 600; font-size: 0.9rem;
        display: flex; align-items: center; justify-content: flex-start; gap: 1rem;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); margin-bottom: 0.5rem; text-decoration: none;
    }
    .menu-item:hover { 
        border-color: var(--border-subtle, #2f384a); color: var(--primary, #fb5a3a); 
        background: var(--bg-body, #1b212d); transform: translateY(-1px);
    }
    .menu-item.active {
        border-color: var(--primary, #fb5a3a); color: white;
        background: var(--primary, #fb5a3a); box-shadow: 0 4px 15px rgba(251, 90, 58, 0.3);
    }
    .menu-item i { font-size: 1.1rem; width: 20px; text-align: center; }
    .theme-btn {
        background: var(--bg-input, #151a23); border: 1px solid var(--border-subtle, #2f384a);
        color: var(--text-white, #ffffff); width: 2.5rem; height: 2.5rem;
        border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;
        transition: all 0.3s ease;
    }
    .theme-btn:hover { background: var(--border-subtle, #2f384a); transform: rotate(15deg); color: var(--primary, #fb5a3a); }
    .sidebar-close-mobile { display:none; }

    @media (max-width: 991.98px) {
        .sidebar-close-mobile {
            display: inline-flex;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: 1px solid rgba(239,68,68,.45);
            background: rgba(239,68,68,.16);
            color: #f87171;
            align-items: center;
            justify-content: center;
        }
    }

    body.theme-light .logo-full { background-color: #0f172a !important; }
    body.theme-light .theme-btn { background: #ffffff; border-color: #cbd5e1; color: #0f172a; }
</style>

<nav class="sidebar" id="mainSidebar">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2.5rem; margin-top: 0.5rem; padding-left: 1.5rem; padding-right: 1rem;">
        <a href="../pages/index.php" class="logo-container">
            <div class="logo-full" role="img" aria-label="Brightronix Logo"></div>
            <div class="app-subtitle" style="font-size: 0.85rem; color: var(--text-gray, #94a3b8); font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; margin-top: -0.8rem; margin-left: 0.6rem;">Electro Plan</div>
        </a>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="sidebar-close-mobile" onclick="toggleSidebar()" aria-label="Close sidebar" title="Close sidebar">
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
