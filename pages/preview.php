<?php
// preview.php - Preview Profesional V8.2 (UI Update: Header & Floating Controls Position)
require_once __DIR__ . '/../core/auth/session.php';
require_once __DIR__ . '/../core/db/connection.php';
require_once __DIR__ . '/../core/file_paths.php';

// 1. NORMALIZAR ROL
$userRoleRaw = $_SESSION['role'] ?? 'viewer';
$userRole = strtolower($userRoleRaw); 

$id = $_GET['id'] ?? 0;

// 2. Obtener Datos del Archivo
$stmt = $pdo->prepare("SELECT * FROM files WHERE id=?");
$stmt->execute([$id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$file) die("File not found");

$projectId = $file['project_id'];
$folderId = $file['folder_id'] ?? null;
$backUrl = "project_dashboard.php?id={$projectId}";
$backUrl .= $folderId ? "&view=files&folder_id={$folderId}" : "&view=summary";

// 3. Historial (Solo activos)
$stmtRep = $pdo->prepare("SELECT * FROM file_reports WHERE file_id=? AND is_deleted = 0 ORDER BY created_at DESC");
$stmtRep->execute([$id]);
$reports = $stmtRep->fetchAll(PDO::FETCH_ASSOC);

$latestJson = count($reports) > 0 ? $reports[0]['annotations_json'] : '{}';
$annotations = (empty($latestJson) || $latestJson === 'null') ? '{}' : $latestJson;

// 4. Determinar extension
$fileExt = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
if ($fileExt === '' && !empty($file['file_type'])) {
    $ft = strtolower($file['file_type']);
    if (strpos($ft, '/') !== false) {
        $fileExt = substr($ft, strrpos($ft, '/') + 1);
    } else {
        $fileExt = $ft;
    }
}

$isSpreadsheet = in_array($fileExt, ['xlsx','xls','xlsm','csv'], true);
$isEditMode = (isset($_GET['mode']) && strtolower((string)$_GET['mode']) === 'edit');
$filePath = normalize_file_path((string)($file['filepath'] ?? ''));
$fileProxyUrl = get_file_url((int)$id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Preview V8 | <?= htmlspecialchars($file['filename']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fabric@5.3.0/dist/fabric.min.js"></script>
    <?php if(in_array($fileExt, ['xlsx','xls','xlsm','csv'])): ?>
    <script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>
    <?php endif; ?>

    <style>
        /* --- TEMA DEEP MATTE --- */
        :root { 
            --bg-body: #1b212d;       
            --bg-panel: #242a38;      
            --bg-input: #151a23;      
            --bg-header: rgba(36, 42, 56, 0.95);
            --border: #2f384a;        
            --text-main: #ffffff;     
            --text-muted: #94a3b8;
            --primary: #fb5a3a;
            --primary-hover: #e14e32;        
            --accent: #3b82f6;
            --danger: #ef4444;
            --success: #10b981;
            --radius-box: 20px;
            --radius-btn: 50px;
        }

        body { 
            background: var(--bg-body); height: 100vh; overflow: hidden; 
            color: var(--text-main); font-family: 'Outfit', sans-serif; 
            margin: 0; padding: 0;
            touch-action: none; /* CRÍTICO: Prevenir gestos nativos */
        }

        body.theme-light {
            --bg-body: #f3f6fb;
            --bg-panel: #ffffff;
            --bg-input: #f8fafc;
            --bg-header: rgba(255,255,255,0.96);
            --border: #cbd5e1;
            --text-main: #0f172a;
            --text-muted: #64748b;
        }
        body.theme-light .canvas-area,
        body.theme-light #map { background: #e2e8f0; }
        body.theme-light .report-card { background: var(--bg-input); }
        body.theme-light .text-white,
        body.theme-light .brand-logo,
        body.theme-light .file-info span,
        body.theme-light .sidebar-title,
        body.theme-light .history-header,
        body.theme-light .small,
        body.theme-light .btn-close-custom,
        body.theme-light .btn-outline-light { color: #0f172a !important; }
        body.theme-light .btn-outline-light {
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
        body.theme-light .logo-full { background-color: #0f172a !important; }
        
        .text-muted, .text-gray { color: var(--text-gray) !important; }
body.theme-light .text-muted, body.theme-light .text-gray { color: var(--text-gray) !important; }

        .app-container {
            position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            display: flex; flex-direction: column;
            overflow: hidden;
        }

        /* HEADER */
        .app-header {
            height: 70px; flex-shrink: 0; background: var(--bg-header); border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between; padding: 0 20px; z-index: 1050;
            backdrop-filter: blur(10px);
        }
        
        .brand-logo { display: flex; flex-direction: column; justify-content: center; text-decoration: none; margin-left: 10px; }
        .logo-full {
            height: 28px; width: 140px; 
            background-color: var(--text-main);
            -webkit-mask: url('../assets/logo-text.png') no-repeat left center;
            mask: url('../assets/logo-text.png') no-repeat left center;
            -webkit-mask-size: contain; mask-size: contain; 
            transition: background-color 0.3s ease;
        }
        .app-subtitle {
            font-size: 0.65rem; color: var(--text-muted); font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; margin-top: -0.2rem; margin-left: 0.2rem;
        }
        .file-info { border-left: 1px solid var(--border); padding-left: 20px; margin-left: 20px; font-size: 0.9rem; color: var(--text-muted); }
        .file-info span { color: white; font-weight: 600; display: block; }

        /* SIDEBARS */
        .sidebar { background: var(--bg-panel); display: flex; flex-direction: column; padding: 25px; overflow-y: auto; }
        .sidebar-left { 
            position: fixed; top: 70px; left: 0; bottom: 0; width: 280px;
            background: var(--bg-panel); border-right: 1px solid var(--border); 
            display: flex; flex-direction: column; padding: 25px; 
            z-index: 1000; transition: transform 0.3s ease; transform: translateX(-100%);
        }        
        .sidebar-right { 
            position: fixed; top: 70px; right: 0; bottom: 0; width: 320px;
            background: var(--bg-panel); border-left: 1px solid var(--border); 
            display: flex; flex-direction: column; padding: 0; 
            z-index: 1000; transition: transform 0.3s ease; transform: translateX(100%);
        }
        .sidebar-left.show, .sidebar-right.show { transform: translateX(0); }

        .sidebar-title { font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 20px; display: block; letter-spacing: 1px; }
        
        #page-list-container { flex-grow: 1; overflow-y: auto; min-height: 0; padding-right: 5px; }
        #page-list-container::-webkit-scrollbar { width: 6px; }
        #page-list-container::-webkit-scrollbar-track { background: transparent; }
        #page-list-container::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }

        .page-item { 
            padding: 12px 15px; margin-bottom: 8px; border-radius: 12px; cursor: pointer; 
            color: var(--text-muted); font-size: 0.9rem; display: flex; justify-content: space-between; font-weight: 500;
            transition: 0.2s;
        }
        .page-item:hover { background: rgba(255,255,255,0.05); color: white; }
        .page-item.active { background: var(--primary); color: white; box-shadow: 0 4px 15px rgba(251, 90, 58, 0.3); }

        /* HISTORY LOG */
        .history-header { padding: 20px 25px; border-bottom: 1px solid var(--border); font-weight: 700; font-size: 0.9rem; display: flex; align-items: center; gap: 10px; background: rgba(0,0,0,0.1); justify-content: space-between; }
        .history-list { padding: 20px; overflow-y: auto; flex-grow: 1; }
        
        .report-card { 
            background: var(--bg-input); border: 1px solid var(--border); border-radius: 15px; 
            padding: 15px; margin-bottom: 15px; transition: 0.3s ease; 
            position: relative; overflow: hidden; 
        }
        .report-card:hover { border-color: var(--accent); transform: scale(1.02); }
        .report-role { color: var(--accent); font-size: 0.7rem; text-transform: uppercase; font-weight: 800; }
        .report-desc { color: var(--text-muted); font-size: 0.85rem; margin: 10px 0; line-height: 1.4; font-style: italic; }
        .report-meta { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); padding-top: 10px; margin-top: 10px; }
        .report-date { font-size: 0.7rem; color: var(--text-muted); }

        .btn-del-report {
            background: transparent; border: none; color: var(--text-muted); 
            font-size: 0.8rem; cursor: pointer; transition: 0.2s;
            display: flex; align-items: center; justify-content: center;
            width: 25px; height: 25px; border-radius: 50%;
        }
        .btn-del-report:hover { color: var(--danger); background: rgba(239, 68, 68, 0.1); }
        .btn-del-report:disabled { opacity: 0.5; cursor: not-allowed; }

        /* CANVAS AREA */
        .canvas-area { flex-grow: 1; background: var(--bg-input); position: relative; overflow: hidden; }
        #map { width: 100%; height: 100%; background: var(--bg-input); position: relative; overflow: hidden; cursor: grab; }
        #map:active { cursor: grabbing; }
        .viewer-content { position: absolute; top: 0; left: 0; transform-origin: 0 0; }
        #pdf-canvas { display: none; }
        #img-view { display: none; max-width: none; max-height: none; }
        #image-editor-wrap { display:none; position:absolute; top:0; left:0; transform-origin:0 0; }
        #image-editor-canvas { border:0; }
        .editor-toolbar { position:absolute; top:85px; left:20px; z-index:1100; background:var(--bg-panel); border:1px solid var(--border); border-radius:14px; padding:10px; display:none; gap:8px; flex-wrap:wrap; max-width:calc(100vw - 40px); }
        .editor-toolbar.show { display:flex; }
        .editor-toolbar .btn { border-radius:10px; }

        /* FLOATING CONTROLS */
        .floating-controls {
            position: absolute; bottom: 30px; right: 30px;
            background: var(--bg-panel); border: 1px solid var(--border);
            border-radius: 50px; padding: 8px 15px; display: flex; gap: 15px; align-items: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5); color: white;
            z-index: 100; pointer-events: auto;
        }
        .float-btn { background: transparent; border: none; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
        .float-btn:hover { background: rgba(255,255,255,0.1); }

        .badge-read { background: rgba(234, 179, 8, 0.1); color: #eab308; border: 1px solid rgba(234, 179, 8, 0.2); font-weight: 700; }
        
        /* BOTÓN DE ACCIÓN (Estilo Editor) */
        .btn-action { 
            background: var(--primary); color: white; padding: 10px 25px; border-radius: 50px; 
            font-weight: 600; border: none; display: flex; align-items: center; gap: 10px; transition: 0.3s; white-space: nowrap; text-decoration: none; font-size: 0.9rem;
        }
        .btn-action:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 5px 15px rgba(251, 90, 58, 0.3); color: white; }

        .btn-close-custom { 
            width: 40px; height: 40px; border-radius: 50%; border: 1px solid var(--border); 
            background: transparent; color: var(--danger); display: flex; align-items: center; justify-content: center; 
            text-decoration: none; transition: 0.2s;
        }
        .btn-close-custom:hover { background: var(--danger); color: white; border-color: var(--danger); }

        .btn-theme-custom {
            width: 40px; height: 40px; border-radius: 50%; border: 1px solid var(--border);
            background: transparent; color: var(--text-main); display: flex; align-items: center; justify-content: center;
            text-decoration: none; transition: 0.2s;
        }
        .btn-theme-custom:hover { background: rgba(255,255,255,0.1); }
        
        #toast-container { position: absolute; bottom: 80px; left: 30px; z-index: 1100; pointer-events: none; }
        .toast-msg { background: var(--bg-panel); border: 1px solid var(--border); padding: 12px 20px; border-radius: 12px; margin-top: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); color: white; display: flex; align-items: center; gap: 10px; animation: slideIn 0.3s; }
        @keyframes slideIn { from { transform: translateX(-50px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* OVERLAY MÓVIL Y DESKTOP */
        .sidebar-overlay { 
            display: none; position: fixed; top: 70px; left: 0; width: 100%; height: calc(100vh - 70px); 
            background: rgba(0,0,0,0.3); z-index: 900; backdrop-filter: blur(4px); 
        }
        .sidebar-overlay.show { display: block; }
        
        .mobile-bottom-bar, .mobile-toggle-header { display: none; }

        .toggle-icon-btn {
            background: none !important; border: none !important; color: var(--text-muted); font-size: 1.5rem;
            cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; padding: 0 10px; margin-right: 5px;
        }
        .toggle-icon-btn:hover, .toggle-icon-btn.active { color: var(--text-main); text-shadow: 0 0 8px rgba(255,255,255,0.3); }
        
        /* Color Naranja Primario para el botón de Sheets */
        #btn-toggle-left, #mobile-toggle-left { color: var(--primary); }
        #btn-toggle-left:hover, #btn-toggle-left.active { color: var(--text-main); }

        /* --- V8.2 MOBILE HYBRID LAYOUT --- */
        @media (max-width: 991px) {
            .app-header { padding: 0 15px; height: 60px; }
            .file-info { display: none; }
            .app-subtitle { display: none; }
            .logo-full { height: 22px; width: 110px; margin-top: 4px; }
            .badge-read { display: none; }
            
            #btn-toggle-left, #btn-toggle-right { display: none; }

            .btn-action { 
                width: 40px; height: 40px; padding: 0; border-radius: 50%; justify-content: center; 
            }
            .btn-action span { display: none !important; }

            .sidebar-left, .sidebar-right { top: 60px; }
            .sidebar-right { width: 100%; max-width: 320px; }
            .sidebar-overlay { top: 60px; height: calc(100vh - 60px); }
            
            .floating-controls { bottom: 70px; right: 15px; padding: 5px 12px; } 

            .mobile-bottom-bar {
                display: flex; height: 60px; flex-shrink: 0; background: var(--bg-panel);
                border-top: 1px solid var(--border); align-items: center; justify-content: space-around; z-index: 500;
            }
            .nav-icon-btn { color: var(--text-muted); background: none; border: none; font-size: 1.2rem; padding: 10px; width: 100%; }
            .nav-icon-btn.active { color: var(--primary); background: rgba(255,255,255,0.05); }
        }
    </style>
</head>
<body>
<script>
(function(){
  try {
    var t = localStorage.getItem('app_theme');
    if (t === 'light') document.body.classList.add('theme-light');
  } catch(e) {}
})();
</script>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeAllSidebars()"></div>

<div class="app-container">
    
    <header class="app-header">
        <div class="d-flex align-items-center">
            <a href="<?= $backUrl ?>" class="text-white me-3 d-md-none"><i class="fas fa-chevron-left"></i></a>
            
            <button id="btn-toggle-left" class="toggle-icon-btn me-2" onclick="toggleSidebar('left')" title="Toggle Sheets">
                <i class="far fa-file-alt"></i>
            </button>

            <a href="<?= $backUrl ?>" class="brand-logo">
                <div class="logo-full" role="img" aria-label="Brightronix Logo"></div>
                <div class="app-subtitle">Electro Plan</div>
            </a>
            <div class="file-info d-none d-md-block">
                <small><?= $isEditMode ? 'Editing Mode' : 'Viewing Mode' ?></small>
                <span><?= htmlspecialchars($file['filename']) ?></span>
            </div>
            <?php if($isEditMode): ?>
                <span class="badge badge-read ms-4 px-3 py-2 d-none d-md-inline"><i class="fas fa-pen me-1"></i> EDIT</span>
            <?php else: ?>
                <span class="badge badge-read ms-4 px-3 py-2 d-none d-md-inline"><i class="fas fa-eye me-1"></i> READ ONLY</span>
            <?php endif; ?>
        </div>

        <div class="d-flex align-items-center gap-2">
            <?php if(!$isSpreadsheet): ?>
            <button id="btn-toggle-right" class="toggle-icon-btn me-2" onclick="toggleSidebar('right')" title="Toggle Activity Log">
                <i class="fas fa-history"></i>
            </button>
            <?php endif; ?>

            <button type="button" id="btn-theme-toggle" class="btn-theme-custom" onclick="toggleTheme()" title="Switch Day/Night">
                <i class="fas fa-sun"></i>
            </button>


            <a href="<?= $backUrl ?>" class="btn-close-custom ms-2">
                <i class="fas fa-times"></i>
            </a>
        </div>
    </header>

    <aside class="sidebar sidebar-left" id="sidebarLeft">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="sidebar-title mb-0"><i class="far fa-file-alt me-2"></i>Sheets</span>
            <button class="btn-close btn-close-white d-md-none" onclick="closeAllSidebars()"></button>
        </div>
        <div id="page-list-container">
            <div class="page-item active">Loading Pages...</div>
        </div>
        
        <div class="mt-auto pt-4 border-top border-secondary">
            <span class="sidebar-title">File Details</span>
            <div class="d-flex justify-content-between small mb-2">
                <span>Format:</span> <span class="text-white fw-bold"><?= strtoupper($fileExt) ?></span>
            </div>
            <div class="d-flex justify-content-between small">
                <span>Last Activity:</span> 
                <span class="text-white"><?= count($reports)>0 ? date('M d, Y', strtotime($reports[0]['created_at'])) : 'Initial upload' ?></span>
            </div>
        </div>
    </aside>

    <main class="canvas-area" id="canvas-wrapper">
        <div id="map" onclick="closeAllSidebars()">
            <canvas id="pdf-canvas" class="viewer-content"></canvas>
            <img id="img-view" class="viewer-content" alt="Preview">
            <div id="image-editor-wrap" class="viewer-content"><canvas id="image-editor-canvas"></canvas></div>
        </div>
        
        <?php if($isEditMode): ?>
        <div id="editor-toolbar" class="editor-toolbar">
            <button class="btn btn-sm btn-outline-light" onclick="setEditorMode('select')"><i class="fas fa-mouse-pointer"></i></button>
            <button class="btn btn-sm btn-outline-light" onclick="setEditorMode('draw')"><i class="fas fa-pen"></i></button>
            <button class="btn btn-sm btn-outline-light" onclick="setEditorMode('text')"><i class="fas fa-font"></i></button>
            <button class="btn btn-sm btn-outline-light" onclick="addShape('arrow')"><i class="fas fa-arrow-right"></i></button>
            <button class="btn btn-sm btn-outline-light" onclick="addShape('rect')"><i class="far fa-square"></i></button>
            <button class="btn btn-sm btn-outline-light" onclick="addShape('circle')"><i class="far fa-circle"></i></button>
            <button class="btn btn-sm btn-outline-light" onclick="addShape('line')"><i class="fas fa-minus"></i></button>
            <input type="color" id="editor-color" value="#fb5a3a" onchange="setEditorStyle()">
            <input type="range" id="editor-width" min="1" max="20" value="3" onchange="setEditorStyle()">
            <button class="btn btn-sm btn-outline-light" onclick="editorUndo()"><i class="fas fa-undo"></i></button>
            <button class="btn btn-sm btn-outline-light" onclick="editorRedo()"><i class="fas fa-redo"></i></button>
            <button class="btn btn-sm btn-outline-success" onclick="saveImageAnnotations()"><i class="fas fa-save"></i> Save</button>
            <button class="btn btn-sm btn-warning" onclick="exportEditedImage()"><i class="fas fa-file-export"></i> Save Snapshot</button>
        </div>
        <?php endif; ?>

        <div class="floating-controls">
            <div class="border-start border-secondary h-75 mx-2 opacity-50"></div>
            
            <button class="float-btn" onclick="changePage(-1)"><i class="fas fa-chevron-left"></i></button>
            <span class="small fw-bold">Page <span id="p-curr">1</span> / <span id="p-total">--</span></span>
            <button class="float-btn" onclick="changePage(1)"><i class="fas fa-chevron-right"></i></button>
            <div class="border-start border-secondary h-75 mx-2 opacity-50"></div>
            <span class="small text-accent fw-bold" id="zoom-disp">100%</span>
        </div>
    </main>

    <?php if(!$isSpreadsheet): ?>
    <aside class="sidebar sidebar-right" id="sidebarRight">
        <div class="history-header">
            <span><i class="fas fa-history text-accent me-2"></i> Activity Log</span>
            <button class="btn-close btn-close-white d-md-none" onclick="closeAllSidebars()"></button>
        </div>
        <div class="history-list" id="reports-container">
            <?php if(count($reports) > 0): ?>
                <?php foreach($reports as $r): ?>
                <div class="report-card" id="rep-card-<?= $r['id'] ?>">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-bold text-white small"><?= htmlspecialchars($r['technician_name']) ?></span>
                            <?php if($userRole !== 'viewer'): ?>
                                <button class="btn-del-report" onclick="deleteReport(<?= $r['id'] ?>, this)" title="Move to Recycle Bin">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                        <span class="report-role"><?= htmlspecialchars($r['technician_role']) ?></span>
                    </div>
                    <div class="report-desc">
                        "<?= htmlspecialchars($r['description']) ?>"
                    </div>
                    <?php
                        $attachments = [];
                        if (!empty($r['attachments_json'])) {
                            $decoded = json_decode($r['attachments_json'], true);
                            if (is_array($decoded)) $attachments = $decoded;
                        }
                    ?>
                    <?php if (!empty($attachments)): ?>
                        <div class="mb-2">
                            <div class="small text-gray mb-1"><i class="fas fa-paperclip me-1"></i>Attachments</div>
                            <div class="d-flex flex-column gap-1">
                                <?php foreach($attachments as $att): ?>
                                    <?php
                                        $attName = $att['name'] ?? 'attachment';
                                        $attPath = $att['path'] ?? '';
                                        $attMime = strtolower((string)($att['mime'] ?? ''));
                                        $attHref = $attPath;
                                        if ($attHref && strpos($attHref, 'uploads/') === 0) $attHref = '../' . $attHref;
                                        $isImage = strpos($attMime, 'image/') === 0;
                                    ?>
                                    <?php if ($attHref): ?>
                                        <a href="<?= htmlspecialchars($attHref) ?>" target="_blank" class="small text-decoration-none">
                                            <?php if ($isImage): ?>
                                                <span class="d-flex align-items-center gap-2">
                                                    <img src="<?= htmlspecialchars($attHref) ?>" alt="<?= htmlspecialchars($attName) ?>" style="width:34px;height:34px;object-fit:cover;border-radius:6px;">
                                                    <span class="text-white"><?= htmlspecialchars($attName) ?></span>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-accent"><i class="fas fa-file me-1"></i><?= htmlspecialchars($attName) ?></span>
                                            <?php endif; ?>
                                        </a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="report-meta">
                        <span class="report-date"><i class="far fa-clock me-1"></i> <?= date('M d, H:i', strtotime($r['created_at'])) ?></span>
                        <?php if($r['report_pdf_path']): ?>
                            <?php
                                $reportPath = $r['report_pdf_path'];
                                if (strpos($reportPath, 'uploads/') === 0) {
                                    $reportExpected = __DIR__ . '/../' . $reportPath;
                                    $reportLegacy = __DIR__ . '/../api/' . $reportPath;
                                    if (!file_exists($reportExpected) && file_exists($reportLegacy)) {
                                        $reportPath = 'api/' . $reportPath;
                                    }
                                }
                                if (strpos($reportPath, 'uploads/') === 0 || strpos($reportPath, 'api/uploads/') === 0) {
                                    $reportPath = '../' . $reportPath;
                                }
                            ?>
                            <a href="<?= htmlspecialchars($reportPath) ?>" target="_blank" class="btn btn-sm btn-outline-success border-0 p-0" title="Download Report">
                                <i class="fas fa-file-pdf fa-lg"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center text-gray py-5 px-3">
                    <i class="fas fa-clipboard-check fa-3x mb-3 opacity-25"></i><br>
                    <p class="small">No reports have been generated for this file yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </aside>
    <?php endif; ?>

    <div class="mobile-bottom-bar">
        <button id="mobile-toggle-left" class="nav-icon-btn" onclick="toggleSidebar('left')">
            <i class="far fa-file-alt"></i>
        </button>
        <button id="mobile-toggle-center" class="nav-icon-btn active" onclick="closeAllSidebars()">
            <i class="fas fa-eye"></i>
        </button>
        <?php if(!$isSpreadsheet): ?>
        <button id="mobile-toggle-right" class="nav-icon-btn" onclick="toggleSidebar('right')">
            <i class="fas fa-history"></i>
        </button>
        <?php endif; ?>
    </div>

</div>

<div id="toast-container"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>

<script>
    // --- UI HELPERS ---
    function toggleSidebar(side) {
        const fallbackSidebar = document.getElementById('mainSidebar') || document.getElementById('sidebar') || document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebarOverlay') || document.querySelector('.sidebar-overlay');

        // Modo fallback defensivo (si la vista no tiene sidebars left/right)
        if (side !== 'left' && side !== 'right') {
            if (!fallbackSidebar) { console.warn('Sidebar no encontrado en esta página.'); return; }
            fallbackSidebar.classList.toggle('active');
            fallbackSidebar.classList.toggle('open');
            return;
        }

        const el = side === 'left' ? document.getElementById('sidebarLeft') : document.getElementById('sidebarRight');
        const other = side === 'left' ? document.getElementById('sidebarRight') : document.getElementById('sidebarLeft');

        if (!el) {
            if (!fallbackSidebar) { console.warn('Sidebar no encontrado en esta página.'); return; }
            fallbackSidebar.classList.toggle('active');
            fallbackSidebar.classList.toggle('open');
            return;
        }

        const btn = document.getElementById(side === 'left' ? 'btn-toggle-left' : 'btn-toggle-right');
        const otherBtn = document.getElementById(side === 'left' ? 'btn-toggle-right' : 'btn-toggle-left');
        const mobBtn = document.getElementById(side === 'left' ? 'mobile-toggle-left' : 'mobile-toggle-right');
        const otherMobBtn = document.getElementById(side === 'left' ? 'mobile-toggle-right' : 'mobile-toggle-left');
        const centerMobBtn = document.getElementById('mobile-toggle-center');

        if (el.classList.contains('show')) {
            el.classList.remove('show');
            if (btn) btn.classList.remove('active');
            if (mobBtn) mobBtn.classList.remove('active');
            if (!other || !other.classList.contains('show')) {
                if (centerMobBtn) centerMobBtn.classList.add('active');
                if (overlay) overlay.classList.remove('show');
            }
        } else {
            el.classList.add('show');
            if (btn) btn.classList.add('active');
            if (mobBtn) mobBtn.classList.add('active');
            if (centerMobBtn) centerMobBtn.classList.remove('active');
            if (other) other.classList.remove('show');
            if (otherBtn) otherBtn.classList.remove('active');
            if (otherMobBtn) otherMobBtn.classList.remove('active');
            if (overlay) overlay.classList.add('show');
        }
    }

    function closeAllSidebars() {
        const sidebarLeft = document.getElementById('sidebarLeft');
        const sidebarRight = document.getElementById('sidebarRight');
        const sidebar = document.getElementById('mainSidebar') || document.getElementById('sidebar') || document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebarOverlay') || document.querySelector('.sidebar-overlay');

        if (sidebarLeft) sidebarLeft.classList.remove('show');
        if (sidebarRight) sidebarRight.classList.remove('show');
        if (sidebar) { sidebar.classList.remove('active'); sidebar.classList.remove('open'); }
        if (overlay) { overlay.classList.remove('show'); overlay.classList.remove('active'); }

        ['btn-toggle-left', 'btn-toggle-right', 'mobile-toggle-left', 'mobile-toggle-right'].forEach(id => {
            const btn = document.getElementById(id);
            if (btn) btn.classList.remove('active');
        });
        const center = document.getElementById('mobile-toggle-center');
        if (center) center.classList.add('active');
    }

    function applyTheme(theme) {
        const next = (theme === 'light') ? 'light' : 'dark';
        document.body.classList.toggle('theme-light', next === 'light');
        const btn = document.getElementById('btn-theme-toggle');
        const icon = btn ? btn.querySelector('i') : null;
        if (icon) icon.className = next === 'light' ? 'fas fa-moon' : 'fas fa-sun';
        if (btn) btn.title = next === 'light' ? 'Switch to Night Mode' : 'Switch to Day Mode';
        try { localStorage.setItem('app_theme', next); } catch (e) {}
    }

    function toggleTheme() {
        applyTheme(document.body.classList.contains('theme-light') ? 'dark' : 'light');
    }

    function initTheme() {
        let saved = 'dark';
        try { saved = localStorage.getItem('app_theme') || 'dark'; } catch (e) {}
        applyTheme(saved);
    }

    // --- SETUP ---
    initTheme();
    document.addEventListener('DOMContentLoaded', () => {
        if(window.innerWidth > 991) {
            toggleSidebar('right');
        }
    });
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';

    // --- Lightweight Viewer (PDF.js + Image) ---
    const viewer = {
        container: document.getElementById('map'),
        canvas: document.getElementById('pdf-canvas'),
        img: document.getElementById('img-view'),
        mode: null,
        scale: 1,
        minScale: 0.10,
        maxScale: 8,
        translateX: 0,
        translateY: 0,
        naturalW: 0,
        naturalH: 0,
        isDragging: false,
        dragStartX: 0,
        dragStartY: 0,
        startTx: 0,
        startTy: 0
    };

    function setZoomDisplay() {
        const disp = document.getElementById('zoom-disp');
        if (disp) disp.innerText = Math.round(viewer.scale * 100) + '%';
    }

    let _rafId = null;
    function applyTransform() {
        if (_rafId) return; // ya hay un frame pendiente, no duplicar
        _rafId = requestAnimationFrame(() => {
            _rafId = null;
            const el = viewer.mode === 'pdf' ? viewer.canvas : viewer.img;
            if (!el) return;
            el.style.transform = `translate(${viewer.translateX}px, ${viewer.translateY}px) scale(${viewer.scale})`;
            syncEditorViewport();
            setZoomDisplay();
        });
    }

    function fitToContainer() {
        const cw = viewer.container.clientWidth;
        const ch = viewer.container.clientHeight;
        if (!cw || !ch || !viewer.naturalW || !viewer.naturalH) return;

        const scaleX = cw / viewer.naturalW;
        const scaleY = ch / viewer.naturalH;
        viewer.scale = Math.min(scaleX, scaleY);
        viewer.scale = Math.max(viewer.minScale, Math.min(viewer.maxScale, viewer.scale));

        viewer.translateX = Math.round((cw - viewer.naturalW * viewer.scale) / 2);
        viewer.translateY = Math.round((ch - viewer.naturalH * viewer.scale) / 2);
        applyTransform();
    }

    function setMode(mode) {
        viewer.mode = mode;
        viewer.canvas.style.display = mode === 'pdf' ? 'block' : 'none';
        viewer.img.style.display = mode === 'image' ? 'block' : 'none';
    }

    function zoomAt(clientX, clientY, delta) {
        const rect = viewer.container.getBoundingClientRect();
        const px = clientX - rect.left;
        const py = clientY - rect.top;
        const prevScale = viewer.scale;
        const nextScale = Math.max(viewer.minScale, Math.min(viewer.maxScale, viewer.scale * delta));
        if (nextScale === prevScale) return;

        // Mantener el punto bajo el cursor
        const nx = (px - viewer.translateX) / prevScale;
        const ny = (py - viewer.translateY) / prevScale;
        viewer.scale = nextScale;
        viewer.translateX = px - nx * viewer.scale;
        viewer.translateY = py - ny * viewer.scale;
        applyTransform();
    }

    viewer.container.addEventListener('wheel', (e) => {
        if (!viewer.mode) return;
        e.preventDefault();
        const delta = e.deltaY < 0 ? 1.1 : 0.9;
        zoomAt(e.clientX, e.clientY, delta);
    }, { passive: false });

    viewer.container.addEventListener('mousedown', (e) => {
        if (!viewer.mode) return;
        viewer.isDragging = true;
        viewer.dragStartX = e.clientX;
        viewer.dragStartY = e.clientY;
        viewer.startTx = viewer.translateX;
        viewer.startTy = viewer.translateY;
        viewer.container.style.cursor = 'grabbing';
    });
    window.addEventListener('mousemove', (e) => {
        if (!viewer.isDragging) return;
        const dx = e.clientX - viewer.dragStartX;
        const dy = e.clientY - viewer.dragStartY;
        viewer.translateX = viewer.startTx + dx;
        viewer.translateY = viewer.startTy + dy;
        applyTransform();
    });
    window.addEventListener('mouseup', () => {
        viewer.isDragging = false;
        viewer.container.style.cursor = 'grab';
    });

    // --- Touch Support (Mobile) ---
    let lastTouchDist = 0;
    let lastTouchCenter = null;

    function getTouchCenter(t1, t2) {
        return { x: (t1.clientX + t2.clientX) / 2, y: (t1.clientY + t2.clientY) / 2 };
    }

    function getTouchDistance(t1, t2) {
        const dx = t1.clientX - t2.clientX;
        const dy = t1.clientY - t2.clientY;
        return Math.hypot(dx, dy);
    }

    viewer.container.addEventListener('touchstart', (e) => {
        if (!viewer.mode) return;
        if (e.touches.length === 1) {
            // FIX: en mobile, 1 dedo siempre puede hacer pan sin activar botón
            const t = e.touches[0];
            viewer.isDragging = true;
            viewer.dragStartX = t.clientX;
            viewer.dragStartY = t.clientY;
            viewer.startTx = viewer.translateX;
            viewer.startTy = viewer.translateY;
            viewer.container.style.cursor = 'grabbing';
            e.preventDefault();
        } else if (e.touches.length === 2) {
            lastTouchDist = getTouchDistance(e.touches[0], e.touches[1]);
            lastTouchCenter = getTouchCenter(e.touches[0], e.touches[1]);
            e.preventDefault();
        }
    }, { passive: false });

    viewer.container.addEventListener('touchmove', (e) => {
        if (!viewer.mode) return;
        if (e.touches.length === 1) {
            // FIX: en mobile siempre hacer pan con 1 dedo
            if (!viewer.isDragging) return;
            const t = e.touches[0];
            const dx = t.clientX - viewer.dragStartX;
            const dy = t.clientY - viewer.dragStartY;
            viewer.translateX = viewer.startTx + dx;
            viewer.translateY = viewer.startTy + dy;
            applyTransform();
            e.preventDefault();
        } else if (e.touches.length === 2) {
            const dist = getTouchDistance(e.touches[0], e.touches[1]);
            const center = getTouchCenter(e.touches[0], e.touches[1]);
            if (lastTouchDist > 0) {
                const scaleDelta = dist / lastTouchDist;
                zoomAt(center.x, center.y, scaleDelta);
            }
            lastTouchDist = dist;
            lastTouchCenter = center;
            e.preventDefault();
        }
    }, { passive: false });

    viewer.container.addEventListener('touchend', (e) => {
        if (e.touches.length < 2) {
            lastTouchDist = 0;
            lastTouchCenter = null;
        }
        if (e.touches.length === 1) {
            const t = e.touches[0];
            viewer.isDragging = true;
            viewer.dragStartX = t.clientX;
            viewer.dragStartY = t.clientY;
            viewer.startTx = viewer.translateX;
            viewer.startTy = viewer.translateY;
        }
        if (e.touches.length === 0) {
            viewer.isDragging = false;
            viewer.container.style.cursor = 'grab';
        }
    }, { passive: false });

    // VARIABLES
    const fileUrl = "<?= htmlspecialchars($fileProxyUrl, ENT_QUOTES) ?>";
    const fileExt = "<?= $fileExt ?>";
    let allAnnotations = <?= $annotations ?>; 
    if(typeof allAnnotations !== 'object' || allAnnotations === null) allAnnotations = {};

    const fileId = <?= (int)$id ?>;
    const isEditMode = <?= $isEditMode ? 'true' : 'false' ?>;
    let pdfDoc = null, pageNum = 1, pdfScale = 2.0; // Scale alto para nitidez en canvas
    let imageEditor = null;
    let editorHistory = [];
    let editorFuture = [];
    let editorReady = false;
    
    // --- DELETE REPORT LOGIC ---
    async function deleteReport(reportId, btn) {
        if(!confirm("Are you sure you want to move this report to the Recycle Bin?")) return;
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        const formData = new FormData();
        formData.append('action', 'soft_delete_report');
        formData.append('report_id', reportId);

        try {
            const res = await fetch('../api/api.php', { method: 'POST', body: formData });
            const data = await res.json();
            if(data.status === 'success') {
                const card = document.getElementById('rep-card-' + reportId);
                card.style.transition = 'all 0.4s ease'; card.style.opacity = '0'; card.style.transform = 'translateX(20px)';
                card.style.marginBottom = '0'; card.style.paddingTop = '0'; card.style.paddingBottom = '0';
                card.style.height = card.offsetHeight + 'px'; card.offsetHeight; card.style.height = '0px'; 
                setTimeout(() => { card.remove(); showToast("Report moved to Recycle Bin", "success"); }, 400);
            } else { showToast("Error: " + data.msg, "error"); btn.disabled = false; btn.innerHTML = '<i class="fas fa-trash-alt"></i>'; }
        } catch (e) { console.error(e); showToast("Connection error", "error"); btn.disabled = false; btn.innerHTML = '<i class="fas fa-trash-alt"></i>'; }
    }

    function showToast(msg, type) {
        const box = document.getElementById('toast-container'); 
        const el = document.createElement('div'); el.className = `toast-msg`;
        el.style.borderLeft = `4px solid ${type==='success'?'#10b981':'#ef4444'}`;
        el.innerHTML = (type==='success'?'<i class="fas fa-check-circle text-success"></i>':'<i class="fas fa-exclamation-circle text-danger"></i>')+`<span>${msg}</span>`;
        box.appendChild(el); setTimeout(() => el.remove(), 4000);
    }

    // Resize
    function resize() { 
        if (viewer.mode) fitToContainer();
    }
    window.addEventListener('resize', resize);
    resize(); 

    // LOAD DOCUMENT
    const imageExts = ['jpg','jpeg','png','gif','webp','bmp','tiff','tif','heic'];

    if(fileExt === 'pdf') {
        setMode('pdf');
        if (isEditMode) { initImageEditor(); loadImageAnnotations().then(()=>renderPage(pageNum)).catch(()=>renderPage(pageNum)); }
        pdfjsLib.getDocument(fileUrl).promise.then(pdf => {
            pdfDoc = pdf; document.getElementById('p-total').textContent = pdf.numPages;
            renderPageList(pdf.numPages); if(!isEditMode) renderPage(pageNum);
        }).catch(err => {
            console.error(err);
            showToast("Error loading PDF", "error");
        });
    } else if (fileExt === 'heic') {
        setMode('image');
        document.getElementById('p-total').textContent = '1'; renderPageList(1);
        fetch(fileUrl).then(res => res.blob()).then(blob => heic2any({ blob, toType: "image/jpeg" })).then(conversionResult => {
            const blob = Array.isArray(conversionResult) ? conversionResult[0] : conversionResult;
            const url = URL.createObjectURL(blob);
            loadSingleImage(url);
        }).catch(e => console.error(e));
    } else if (imageExts.includes(fileExt)) {
        setMode('image');
        document.getElementById('p-total').textContent = '1'; renderPageList(1);
        if (isEditMode) { loadImageAnnotations().then(()=>loadSingleImage(fileUrl)).catch(()=>loadSingleImage(fileUrl)); }
        else { loadSingleImage(fileUrl); }
    } else if (['xlsx','xls','xlsm','csv'].includes(fileExt)) {
        document.querySelectorAll('.page-nav, #btn-pan, #zoom-controls').forEach(el => { if(el) el.style.display='none'; });
        document.getElementById('p-total').textContent = '1';
        renderPageList(1);
        const wrap = document.getElementById('map');
        wrap.innerHTML = '<div id="sheet-container" style="width:100%;height:100%;overflow:auto;padding:16px;background:#fff;"></div>';

        const styleText = `#sheet-table{border-collapse:collapse;font-size:13px;font-family:Arial,sans-serif;color:#1a202c;}#sheet-table td,#sheet-table th{border:1px solid #d1d5db;padding:4px 10px;white-space:nowrap;min-width:60px;}#sheet-table tr:nth-child(even){background:#f9fafb;}#sheet-table tr:hover{background:#e5e7eb;}#sheet-table tr:first-child td{background:#1e3a5f;color:white;font-weight:600;}#sheet-tabs{display:flex;gap:8px;margin-bottom:10px;flex-wrap:wrap;}#sheet-tabs button{border:1px solid #cbd5e1;background:#f8fafc;padding:4px 10px;border-radius:8px;font-size:12px;}`;
        const ensureStyle = () => {
            if (document.getElementById('sheet-style')) return;
            const st = document.createElement('style'); st.id = 'sheet-style'; st.textContent = styleText; document.head.appendChild(st);
        };

        const renderError = (msg) => {
            document.getElementById('sheet-container').innerHTML = `<div style="color:#111827"><p><strong>Could not render spreadsheet preview.</strong></p><p>${msg}</p></div>`;
        };

        if (typeof XLSX === 'undefined') {
            renderError('Spreadsheet library failed to load.');
        } else {
            fetch(fileUrl).then(r => r.arrayBuffer()).then(buf => {
                const wb = XLSX.read(buf, { type: 'array' });
                ensureStyle();
                const container = document.getElementById('sheet-container');
                const tabs = document.createElement('div'); tabs.id = 'sheet-tabs';
                const tableWrap = document.createElement('div');
                container.innerHTML = '';
                container.appendChild(tabs); container.appendChild(tableWrap);

                const applySheet = (name) => {
                    const sheet = wb.Sheets[name];
                    const originalRange = XLSX.utils.decode_range(sheet['!ref'] || 'A1');
                    const previewRange = {
                        s: originalRange.s,
                        e: {
                            r: Math.min(originalRange.e.r, originalRange.s.r + 4999),
                            c: Math.min(originalRange.e.c, originalRange.s.c + 199)
                        }
                    };
                    const truncated = previewRange.e.r < originalRange.e.r || previewRange.e.c < originalRange.e.c;
                    tableWrap.innerHTML = (truncated
                        ? '<div style="color:#92400e;background:#fffbeb;border:1px solid #fde68a;padding:8px 12px;margin-bottom:10px;border-radius:8px;">Large sheet preview limited to 5,000 rows and 200 columns.</div>'
                        : '') + XLSX.utils.sheet_to_html(sheet, { id: 'sheet-table', editable: false, range: XLSX.utils.encode_range(previewRange) });
                };

                wb.SheetNames.forEach((name, idx) => {
                    const b = document.createElement('button');
                    b.textContent = name;
                    b.onclick = () => applySheet(name);
                    tabs.appendChild(b);
                    if (idx === 0) applySheet(name);
                });
            }).catch((e) => {
                renderError('Invalid or unsupported spreadsheet file.');
                console.error(e);
            });
        }
    } else {
        showToast("Unsupported file type", "error");
    }

    function initImageEditor() {
        if (!isEditMode || editorReady || !window.fabric) return;
        const c = document.getElementById('image-editor-canvas');
        imageEditor = new fabric.Canvas('image-editor-canvas', { selection: true, preserveObjectStacking: true });
        setEditorStyle();
        imageEditor.on('object:added', () => pushHistory());
        imageEditor.on('object:modified', () => pushHistory());
        imageEditor.on('object:removed', () => pushHistory());
        editorReady = true;
    }

    function syncEditorViewport() {
        const wrap = document.getElementById('image-editor-wrap');
        if (!wrap) return;
        wrap.style.transform = `translate(${viewer.translateX}px, ${viewer.translateY}px) scale(${viewer.scale})`;
    }

    function pushHistory() {
        if (!imageEditor) return;
        editorHistory.push(JSON.stringify(imageEditor.toJSON()));
        if (editorHistory.length > 60) editorHistory.shift();
        editorFuture = [];
    }
    function editorUndo(){ if(editorHistory.length<2 || !imageEditor) return; editorFuture.push(editorHistory.pop()); imageEditor.loadFromJSON(editorHistory[editorHistory.length-1], ()=>imageEditor.renderAll()); }
    function editorRedo(){ if(!editorFuture.length || !imageEditor) return; const s = editorFuture.pop(); editorHistory.push(s); imageEditor.loadFromJSON(s, ()=>imageEditor.renderAll()); }
    function setEditorStyle(){ if(!imageEditor) return; const color=document.getElementById('editor-color').value; const w=parseInt(document.getElementById('editor-width').value||3,10); imageEditor.freeDrawingBrush.color=color; imageEditor.freeDrawingBrush.width=w; }
    function setEditorMode(mode){ if(!imageEditor) return; imageEditor.isDrawingMode=(mode==='draw'); imageEditor.selection=(mode==='select'); if(mode==='text'){ const t=new fabric.IText('Text',{left:80,top:80,fill:document.getElementById('editor-color').value,fontSize:26}); imageEditor.add(t); imageEditor.setActiveObject(t);} }
    function addShape(kind){ if(!imageEditor) return; const c=document.getElementById('editor-color').value; const w=parseInt(document.getElementById('editor-width').value||3,10); let o=null; if(kind==='rect')o=new fabric.Rect({left:80,top:80,width:180,height:100,fill:'transparent',stroke:c,strokeWidth:w}); if(kind==='circle')o=new fabric.Circle({left:90,top:90,radius:60,fill:'transparent',stroke:c,strokeWidth:w}); if(kind==='line')o=new fabric.Line([80,80,260,80],{stroke:c,strokeWidth:w}); if(kind==='arrow'){ const line=new fabric.Line([80,80,260,80],{stroke:c,strokeWidth:w}); const tri=new fabric.Triangle({left:252,top:74,width:18,height:18,fill:c,angle:90}); o=new fabric.Group([line,tri],{left:80,top:80}); } if(o) imageEditor.add(o); }

    let persistedAnnotations = {};
    async function loadImageAnnotations(){ 
        const fd=new FormData(); fd.append('action','load_annotations'); fd.append('file_id',fileId); 
        const d=await fetch('../api/api.php',{method:'POST',body:fd}).then(r=>r.json()); 
        if(d.status==='success'&&d.annotations_json){ 
            try { persistedAnnotations = JSON.parse(d.annotations_json) || {}; } catch(_) { persistedAnnotations = {}; }
        } else {
            persistedAnnotations = {};
        }
    }
    function loadEditorStateForPage(pg){
        if(!imageEditor) return;
        const state = persistedAnnotations[String(pg)];
        if(state){ imageEditor.loadFromJSON(state,()=>imageEditor.renderAll()); }
        else { imageEditor.clear(); imageEditor.renderAll(); }
        pushHistory();
    }
    async function saveImageAnnotations(){ 
        if(!imageEditor || !isEditMode) return; 
        persistedAnnotations[String(pageNum)] = imageEditor.toJSON();
        const fd=new FormData(); fd.append('action','save_annotations'); fd.append('file_id',fileId); fd.append('annotations_json',JSON.stringify(persistedAnnotations)); 
        const d=await fetch('../api/api.php',{method:'POST',body:fd}).then(r=>r.json()); 
        showToast(d.status==='success'?'Annotations saved':'Error saving', d.status==='success'?'success':'error'); 
    }
    async function exportEditedImage(){ if(!imageEditor) return; const dataUrl=imageEditor.toDataURL({format:'png',multiplier:1}); const fd=new FormData(); fd.append('action','export_edited_image'); fd.append('file_id',fileId); fd.append('image_data',dataUrl); const d=await fetch('../api/api.php',{method:'POST',body:fd}).then(r=>r.json()); showToast(d.status==='success'?'Snapshot exported':'Export failed', d.status==='success'?'success':'error'); }

    function loadSingleImage(url) {
        // Obtener dimensiones reales de imagen
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = function() {
            viewer.img.src = url;
            viewer.naturalW = this.width;
            viewer.naturalH = this.height;
            fitToContainer();
            if (isEditMode) {
                initImageEditor();
                const wrap = document.getElementById('image-editor-wrap');
                wrap.style.display = 'block';
                const c = document.getElementById('image-editor-canvas');
                c.width = this.width; c.height = this.height;
                imageEditor.setWidth(this.width); imageEditor.setHeight(this.height);
                const tb = document.getElementById('editor-toolbar'); if (tb) tb.classList.add('show');
                loadEditorStateForPage(1);
            }
            loadPageAnnotations(1);
        }
        img.onerror = function() {
            fetch(url)
                .then(r => r.blob())
                .then(b => {
                    const objUrl = URL.createObjectURL(b);
                    const img2 = new Image();
                    img2.onload = function() {
                        viewer.img.src = objUrl;
                        viewer.naturalW = this.width;
                        viewer.naturalH = this.height;
                        fitToContainer();
                        loadPageAnnotations(1);
                    };
                    img2.src = objUrl;
                })
                .catch(e => console.error(e));
        };
        img.src = url;
    }

    function renderPageList(total) {
        const container = document.getElementById('page-list-container'); container.innerHTML = '';
        for(let i=1; i<=total; i++) {
            const div = document.createElement('div'); div.className = `page-item ${i === pageNum ? 'active' : ''}`;
            div.innerHTML = `<span>Page ${i}</span> <i class="fas fa-chevron-right small opacity-50"></i>`;
            div.onclick = () => jumpToPage(i); div.id = `plist-${i}`; container.appendChild(div);
        }
    }
    function updatePageListUI(curr) {
        document.querySelectorAll('.page-item').forEach(el => el.classList.remove('active'));
        const activeEl = document.getElementById(`plist-${curr}`); if(activeEl) activeEl.classList.add('active');
        document.getElementById('p-curr').innerText = curr;
    }
    async function renderPage(num) {
        if(pdfDoc) {
            const page = await pdfDoc.getPage(num); const viewport = page.getViewport({ scale: pdfScale });
            const canvas = viewer.canvas;
            canvas.width = viewport.width;
            canvas.height = viewport.height;
            await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
            viewer.naturalW = viewport.width;
            viewer.naturalH = viewport.height;
            fitToContainer();
            if (isEditMode && imageEditor) {
                const wrap = document.getElementById('image-editor-wrap');
                wrap.style.display = 'block';
                const c = document.getElementById('image-editor-canvas');
                c.width = viewport.width; c.height = viewport.height;
                imageEditor.setWidth(viewport.width); imageEditor.setHeight(viewport.height);
                loadEditorStateForPage(num);
            }
            loadPageAnnotations(num);
        }
        updatePageListUI(num);
    }
    function changePage(offset) {
        const max = pdfDoc ? pdfDoc.numPages : 1; const newPage = pageNum + offset;
        if(newPage < 1 || newPage > max) return; jumpToPage(newPage);
    }
    function jumpToPage(targetPage) {
        pageNum = targetPage; if(pdfDoc) renderPage(pageNum); else loadPageAnnotations(pageNum);
        
        // Auto-cerrar el sidebar izquierdo (Sheets) al seleccionar una página
        const sbLeft = document.getElementById('sidebarLeft');
        if (sbLeft && sbLeft.classList.contains('show')) {
            toggleSidebar('left');
        }
    }
    
    function loadPageAnnotations(pg) {
        if(allAnnotations[pg]) {
            console.log("TODO: Implementar adaptador Fabric->OL para página " + pg);
            // Aquí irá la lógica de la Fase 2/8
        }
    }

    // Pan siempre activo (desktop + mobile)

</script>
</body>
</html>
