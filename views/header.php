<!DOCTYPE html>
<html lang="es">
<head>
    <script>
    // Defensive guard for Capacitor bridge
    if (typeof window.Capacitor === 'undefined') {
        window.Capacitor = {
            triggerEvent: function(name, data) {
                console.log('Capacitor.triggerEvent called before bridge init:', name, data);
                return true;
            }
        };
    } else if (typeof window.Capacitor.triggerEvent !== 'function') {
        window.Capacitor.triggerEvent = function(name, data) {
            console.log('Capacitor.triggerEvent mock:', name, data);
            return true;
        };
    }
    window.BASE_PATH = '/electroplan';
    window.SERVER_BASE_URL = window.location.origin + window.BASE_PATH;
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0b1120">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="ElectroPlan">
    <title><?= isset($pageTitle) ? $pageTitle : 'Brightronix | Workspace' ?></title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/assets/pwa-icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/assets/pwa-icon-192.png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* --- 1. VARIABLES Y BASE --- */
        :root { --bg-body: #0b1120; --bg-sidebar: #111827; --bg-card: #1e293b; --bg-card-hover: #334155; --primary: #6366f1; --primary-hover: #4f46e5; --accent: #0ea5e9; --text-white: #ffffff; --text-gray: #94a3b8; --radius-box: 20px; --radius-btn: 50px; }
        body { background-color: var(--bg-body); color: var(--text-white); font-family: 'Outfit', sans-serif; overflow-x: hidden; }

        body.theme-light {
            --bg-body: #f3f6fb;
            --bg-sidebar: #ffffff;
            --bg-card: #ffffff;
            --bg-card-hover: #edf2f8;
            --text-white: #0f172a;
            --text-gray: #475569;
        }
        body.theme-light .sidebar,
        body.theme-light .app-header,
        body.theme-light .box-card,
        body.theme-light .modal-content,
        body.theme-light .user-pill,
        body.theme-light .bulk-actions-bar,
        body.theme-light .table-responsive,
        body.theme-light .proj-card { border-color: rgba(15,23,42,0.12) !important; }
        body.theme-light .box-card,
        body.theme-light .proj-card { box-shadow: 0 8px 20px rgba(15,23,42,0.08); }

        body.theme-light .btn-icon,
        body.theme-light .btn-back,
        body.theme-light .sidebar-toggle,
        body.theme-light .btn-action { color: #0f172a; border-color: rgba(15,23,42,0.25); }
        body.theme-light .btn-icon:hover,
        body.theme-light .btn-action:hover { background: #0f172a; color: #fff; }

        body.theme-light .form-control,
        body.theme-light .form-select { background: #fff; color: #0f172a; border-color: rgba(15,23,42,0.2); }

        body.theme-light .breadcrumbs,
        body.theme-light .breadcrumbs span,
        body.theme-light .proj-title,
        body.theme-light h1,
        body.theme-light h2,
        body.theme-light h3,
        body.theme-light h4,
        body.theme-light h5,
        body.theme-light h6,
        body.theme-light .fw-bold,
        body.theme-light .small,
        body.theme-light .text-white,
        body.theme-light .menu-item,
        body.theme-light .brand,
        body.theme-light .brand-logo,
        body.theme-light .file-info span,
        body.theme-light .stat-num,
        body.theme-light .report-desc,
        body.theme-light .table-rounded td,
        body.theme-light .table-rounded td * { color: #0f172a !important; }

        body.theme-light .text-muted,
        body.theme-light .text-gray,
        body.theme-light .proj-meta,
        body.theme-light .file-info,
        body.theme-light .breadcrumbs a { color: #475569 !important; }

        body.theme-light .table-rounded { background: #ffffff; }
        body.theme-light .table-rounded th {
            background: #e5e7eb !important;
            color: #334155 !important;
            border-bottom-color: rgba(15,23,42,0.12) !important;
        }
        body.theme-light .table-rounded tr:hover td { background: #f8fafc !important; }
        body.theme-light .info-pill { background: #e2e8f0; color: #334155; }
        body.theme-light .badge.bg-dark { background: #334155 !important; color: #fff !important; border-color: #475569 !important; }
        body.theme-light .menu-item.active { color: #fff !important; }

        /* Bootstrap utility harmonization in light mode */
        body.theme-light .btn-outline-light {
            color: #0f172a !important;
            border-color: rgba(15,23,42,0.25) !important;
            background: transparent !important;
        }
        body.theme-light .btn-outline-light:hover {
            background: #0f172a !important;
            color: #fff !important;
            border-color: #0f172a !important;
        }
        body.theme-light .border-secondary { border-color: rgba(15,23,42,0.18) !important; }
        body.theme-light .bg-dark { background-color: #334155 !important; }
        body.theme-light .text-dark { color: #0f172a !important; }
        body.theme-light .text-light { color: #f8fafc !important; }
        body.theme-light .toast-msg {
            background: #ffffff !important;
            color: #0f172a !important;
            border-color: rgba(15,23,42,0.18) !important;
            box-shadow: 0 10px 24px rgba(15,23,42,0.14) !important;
        }
        body.theme-light .toast-msg span { color: #0f172a !important; }

        a { text-decoration: none; color: inherit; }
        
        /* Wrapper principal */
        .d-flex-wrapper { display: flex; min-height: 100vh; width: 100%; }
        
        /* --- 2. SIDEBAR (MODIFICADO PARA RESPONSIVE) --- */
        .sidebar { 
            width: 260px; 
            background: var(--bg-sidebar); 
            padding: 30px 20px; 
            display: flex; 
            flex-direction: column; 
            border-right: 1px solid rgba(255,255,255,0.05); 
            position: fixed; 
            top: 0; left: 0;
            height: 100vh; 
            z-index: 1050;
            overflow-y: auto; 
            transition: transform 0.3s ease, width 0.25s ease, padding 0.25s ease;
            transform: translateX(0);
        }

        .brand { font-size: 1.5rem; font-weight: 700; margin-bottom: 50px; display: flex; align-items: center; gap: 12px; color: white; }
        .brand-icon { width: 40px; height: 40px; background: linear-gradient(135deg, var(--primary), var(--accent)); border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        
        .menu-item { padding: 14px 20px; border-radius: var(--radius-box); color: var(--text-gray); font-weight: 500; font-size: 0.95rem; margin-bottom: 8px; display: flex; align-items: center; gap: 15px; transition: all 0.3s ease; }
        .menu-item:hover { background: rgba(255,255,255,0.05); color: white; transform: translateX(5px); }
        .menu-item.active { background: var(--primary); color: white; box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3); }
        .menu-item i { width: 20px; text-align: center; }
        .menu-label { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        /* --- 3. LAYOUT PRINCIPAL (MODIFICADO PARA RESPONSIVE) --- */
        .main-content { 
            margin-left: 260px; /* Espacio para el sidebar fijo */
            width: calc(100% - 260px); /* Ancho restante */
            padding: 40px; 
            padding-bottom: 100px; 
            min-height: 100vh; 
            transition: margin-left 0.3s ease, width 0.3s ease;
        }

        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .breadcrumbs { color: var(--text-gray); font-size: 0.9rem; }
        .breadcrumbs span { color: white; font-weight: 600; }
        
        .user-pill { background: var(--bg-input, #151a23); padding: 6px 20px 6px 8px; border-radius: 50px; display: inline-flex; align-items: center; gap: 12px; border: 1px solid var(--border-subtle, #2f384a); transition: all 0.3s ease; text-decoration: none; cursor: pointer; }
        .user-pill:hover { border-color: var(--primary, #fb5a3a); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        <?php 
            $avColor = '#3b82f6'; // Blue
            $ur = strtolower($_SESSION['role'] ?? '');
            if($ur === 'admin') $avColor = '#f59e0b'; // Amber
            elseif($ur === 'technician') $avColor = '#10b981'; // Emerald
            elseif($ur === 'viewer') $avColor = '#8b5cf6'; // Purple
        ?>
        .avatar { width: 38px; height: 38px; background: <?= $avColor ?>; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: 700; }
        .user-pill-info { display: flex; flex-direction: column; line-height: 1.2; }
        .user-pill-name { font-size: 0.9rem; font-weight: 700; color: var(--text-white, #ffffff); }
        .user-pill-role { font-size: 0.7rem; font-weight: 600; color: var(--text-gray, #94a3b8); text-transform: uppercase; letter-spacing: 0.05em; }

        /* --- 4. COMPONENTES GLOBALES --- */
        .box-card { background: var(--bg-card); border-radius: var(--radius-box); padding: 25px; height: 100%; border: 1px solid rgba(255,255,255,0.05); transition: 0.3s; position: relative; overflow: hidden; }
        .box-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.3); background: var(--bg-card-hover); }
        .box-card.selected { border: 2px solid var(--primary); background: rgba(99, 102, 241, 0.1); }
        .selection-check { position: absolute; top: 15px; right: 15px; width: 20px; height: 20px; border-radius: 50%; border: 2px solid rgba(255,255,255,0.3); display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 10; }
        .box-card.selected .selection-check { background: var(--primary); border-color: var(--primary); }
        .box-card.selected .selection-check::after { content: '\f00c'; font-family: "Font Awesome 6 Free"; font-weight: 900; font-size: 0.7rem; color: white; }

        .btn-main { background: var(--primary); color: white; padding: 10px 25px; border-radius: var(--radius-btn); font-weight: 600; border: none; transition: 0.3s; }
        .btn-main:hover { background: var(--primary-hover); transform: translateY(-2px); }
        .btn-icon { width: 35px; height: 35px; border-radius: 50%; border: 1px solid rgba(255,255,255,0.2); color: white; display: flex; align-items: center; justify-content: center; background: transparent; transition: 0.2s; }
        .btn-icon:hover { background: white; color: var(--bg-body); border-color: white; }
        .btn-back { width: 45px; height: 45px; border-radius: 50%; border: 1px solid rgba(255,255,255,0.15); color: white; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.05); transition: 0.2s; font-size: 1.1rem; }
        .btn-back:hover { background: var(--primary); border-color: var(--primary); }

        .modal-content { background: var(--bg-card); border-radius: var(--radius-box); border: 1px solid rgba(255,255,255,0.1); color: white; }
        .form-control { background: var(--bg-body); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; color: white; padding: 12px; }
        .btn-close { filter: invert(1); }
        
        /* --- 5. COMPONENTES GRÁFICOS RESTAURADOS --- */
        .stat-num { font-size: 2.5rem; font-weight: 700; color: white; line-height: 1; margin-bottom: 5px; }
        .stat-label { color: var(--text-gray); font-size: 0.9rem; font-weight: 500; }
        .stat-icon-bg { position: absolute; right: -10px; bottom: -10px; font-size: 5rem; opacity: 0.05; transform: rotate(-15deg); }
        .proj-status { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #4ade80; background: rgba(74, 222, 128, 0.1); padding: 4px 10px; border-radius: 10px; display: inline-block; margin-bottom: 15px; }
        .proj-title { font-size: 1.2rem; font-weight: 700; color: white; margin-bottom: 8px; }
        .proj-desc { color: var(--text-gray); font-size: 0.9rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .box-card-dashed { background: transparent; border: 2px dashed rgba(255,255,255,0.1); display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--text-gray); cursor: pointer; }
        .box-card-dashed:hover { border-color: var(--primary); color: var(--primary); background: rgba(99, 102, 241, 0.05); }
        .folder-card { cursor: pointer; transition: 0.2s; text-decoration: none; }
        .folder-card:hover .folder-icon { transform: scale(1.1); color: #facc15; }
        .folder-icon { transition: 0.2s; color: #eab308; } 
        .file-tile { width: 70px; height: 85px; margin: 0 auto 15px auto; border-radius: 12px; display: flex; align-items: center; justify-content: center; position: relative; }
        .file-tile.pdf { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); } .file-tile.pdf i { color: #f87171; font-size: 2.2rem; }
        .file-tile.img { background: rgba(14, 165, 233, 0.15); border: 1px solid rgba(14, 165, 233, 0.3); } .file-tile.img i { color: #38bdf8; font-size: 2.2rem; }
        .file-tile.file-gen { background: rgba(148, 163, 184, 0.15); border: 1px solid rgba(148, 163, 184, 0.3); } .file-tile.file-gen i { color: #cbd5e1; font-size: 2.2rem; }
        .version-badge { position: absolute; top: -5px; right: -5px; background: #6366f1; color: white; font-size: 0.65rem; font-weight: bold; padding: 2px 6px; border-radius: 10px; border: 2px solid var(--bg-card); box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        
        .bulk-actions-bar { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%) translateY(150%); background: var(--bg-sidebar); border: 1px solid rgba(255,255,255,0.1); padding: 15px 30px; border-radius: 50px; display: flex; align-items: center; gap: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.6); z-index: 1000; transition: 0.3s; }
        .bulk-actions-bar.visible { transform: translateX(-50%) translateY(0); }

        /* --- 6. MOBILE RESPONSIVE LOGIC (NUEVO) --- */
        .mobile-toggle { display: none; border: none; background: none; color: white; font-size: 1.5rem; cursor: pointer; }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1040; backdrop-filter: blur(3px); }
        .sidebar-overlay.show { display: block; }

        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); width: 280px; }
            .sidebar.show { transform: translateX(0); box-shadow: 10px 0 30px rgba(0,0,0,0.5); }
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 20px; }
            .mobile-toggle { display: block; }
            .sidebar-toggle { display: none; }
        }
    
/* Global override: Bootstrap text-muted → paleta Deep Matte */
.text-muted { color: var(--text-gray, #94a3b8) !important; }
body.theme-light .text-muted { color: var(--text-gray, #64748b) !important; }
</style>
</head>
<body>
<script>
(function(){
  try {
    if ((localStorage.getItem('app_theme') || localStorage.getItem('editor_theme')) === 'light') {
      document.body.classList.add('theme-light');
    }
  } catch(e) {}
})();
</script>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('mainSidebar')
            || document.getElementById('sidebar')
            || document.querySelector('.sidebar')
            || document.querySelector('.side-menu')
            || document.querySelector('.mobile-sidebar');

        if (!sidebar) {
            console.error('Sidebar no encontrado: revisa el id/clase del contenedor lateral.');
            return;
        }

        sidebar.classList.toggle('show');
        sidebar.classList.toggle('active');
        sidebar.classList.toggle('open');

        const overlay = document.getElementById('sidebarOverlay');
        if (overlay) {
            overlay.classList.toggle('show');
        }
    }

    function applyAppTheme(theme) {
        const next = (theme === 'light') ? 'light' : 'dark';
        document.body.classList.toggle('theme-light', next === 'light');
        const btn = document.getElementById('globalThemeToggle');
        const icon = btn ? btn.querySelector('i') : null;
        if (icon) icon.className = next === 'light' ? 'fas fa-moon' : 'fas fa-sun';
        if (btn) btn.title = next === 'light' ? 'Switch to Night Mode' : 'Switch to Day Mode';
        try { localStorage.setItem('app_theme', next); } catch (e) {}
    }

    function toggleAppTheme() {
        applyAppTheme(document.body.classList.contains('theme-light') ? 'dark' : 'light');
    }

    document.addEventListener('DOMContentLoaded', function() {
        let savedTheme = 'dark';
        try { savedTheme = localStorage.getItem('app_theme') || localStorage.getItem('editor_theme') || 'dark'; } catch (e) {}
        applyAppTheme(savedTheme);
    });
</script>

<div class="d-flex-wrapper">
    <?php include __DIR__ . '/sidebar.php'; ?>
