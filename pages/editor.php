<?php
// editor.php - Editor Profesional V9.6 (Fix: Removed Pan Tool & Added 2-Finger Nav)
require_once __DIR__ . '/../core/auth/session.php';
require_once __DIR__ . '/../core/db/connection.php';

$userRole = $_SESSION['role'] ?? 'Viewer';

if ($userRole === 'Viewer') {
    $id = $_GET['id'] ?? 0;
    header("Location: preview.php?id=$id");
    exit;
}

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM files WHERE id=?");
$stmt->execute([$id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$file) {
    die("<div style='color:white;text-align:center;padding:50px;font-family:sans-serif;'>Error: File not found. ID: $id</div>");
}

$projectId = $file['project_id'];
$folderId = $file['folder_id'];
$backUrl = "project_dashboard.php?id={$projectId}";
$backUrl .= $folderId ? "&view=files&folder_id={$folderId}" : "&view=summary";

$stmtRep = $pdo->prepare("SELECT annotations_json FROM file_reports WHERE file_id=? ORDER BY created_at DESC LIMIT 1");
$stmtRep->execute([$id]);
$lastReport = $stmtRep->fetchColumn();
$annotations = ($lastReport && $lastReport !== 'null') ? $lastReport : '{}';

$fileExt = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
$filePath = $file['filepath'];
if (strpos($filePath, 'uploads/') === 0) {
    $expected = __DIR__ . '/../' . $filePath;
    $legacy = __DIR__ . '/../api/' . $filePath;
    if (!file_exists($expected) && file_exists($legacy)) {
        $filePath = 'api/' . $filePath;
    }
}
if (strpos($filePath, 'uploads/') === 0 || strpos($filePath, 'api/uploads/') === 0) {
    $filePath = '../' . $filePath;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Editor V9.6 | <?= htmlspecialchars($file['filename']) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js"></script>

    <link rel="stylesheet" href="../assets/editor/editor.css">

    <style>
        :root {
            --header-height: 60px;
            --sb-right-w: 70px; /* Ancho Desktop */
            --sb-mobile-h: 65px; /* Alto Mobile */
            --bg-dark: #0f172a;
            --border-color: #334155;
        }

        body { overflow: hidden; background: var(--bg-dark); }

        /* --- LAYOUT GRID (Desktop Default) --- */
        .app-container {
            display: grid;
            height: 100vh;
            width: 100vw;
            grid-template-columns: 1fr var(--sb-right-w); 
            grid-template-rows: var(--header-height) 1fr;
            grid-template-areas: 
                "header header"
                "canvas right";
        }

        .app-header { grid-area: header; z-index: 50; border-bottom: 1px solid var(--border-color); }
        .canvas-area { grid-area: canvas; position: relative; overflow: hidden; background: #1e293b; }

        /* --- SIDEBAR IZQUIERDA (Overlay Universal) --- */
        .sidebar-left {
            position: fixed !important;
            top: var(--header-height); left: 0; bottom: 0; width: 260px;
            background: var(--bg-dark); border-right: 1px solid var(--border-color);
            z-index: 1000; transform: translateX(-100%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; flex-direction: column;
        }
        .sidebar-left.show { transform: translateX(0); }
        .sidebar-overlay {
            position: fixed; top: var(--header-height); left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5); z-index: 999; display: none; opacity: 0; transition: opacity 0.3s;
        }
        .sidebar-overlay.show { display: block; opacity: 1; }

        /* --- SIDEBAR DERECHA (Herramientas) --- */
        .sidebar-right {
            grid-area: right;
            border-left: 1px solid var(--border-color);
            background: var(--bg-dark);
            z-index: 40;
            display: flex;
            flex-direction: column; /* Desktop: Vertical */
            align-items: center;
            padding-top: 15px;
            gap: 10px;
        }

        /* --- CONTROLES FLOTANTES (Zoom/Paginas) --- */
        .floating-controls {
            position: absolute;
            bottom: 20px; right: 20px;
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid var(--border-color);
            border-radius: 50px;
            padding: 5px 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 30;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease; /* Transición suave para ocultar/mostrar */
            color: white;
        }

        .float-btn {
            background: none; border: none; color: white;
            padding: 5px; cursor: pointer; opacity: 0.8;
            transition: opacity 0.2s;
        }
        .float-btn:hover { opacity: 1; }

        /* --- UI ELEMENTS (Botones Icono Grande) --- */
        .toggle-icon-btn {
            background: none !important;
            border: none !important;
            color: rgba(255,255,255,0.7);
            font-size: 1.5rem; /* Icono Grande */
            padding: 0 10px;
            margin-right: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
        }
        .toggle-icon-btn:hover, .toggle-icon-btn.active {
            color: #fff;
            text-shadow: 0 0 8px rgba(255,255,255,0.3);
        }

        /* --- STAMP MENU (FIXED POSITION) --- */
        .stamp-menu {
            position: fixed; /* Fix para Mobile overflow */
            z-index: 2000;
            display: none;
            background: rgba(15, 23, 42, 0.95);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 10px;
            gap: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }
        
        .stamp-item {
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: background 0.2s;
            color: white;
            white-space: nowrap;
            display: flex;
            align-items: center;
        }
        .stamp-item:hover { background: rgba(255,255,255,0.1); }

        /* Desktop: A la izquierda del sidebar */
        @media (min-width: 992px) {
            .stamp-menu {
                right: 80px; /* 70px sidebar + 10px gap */
                top: 50%;
                transform: translateY(-50%);
                flex-direction: column;
            }
        }

        /* --- BOTÓN SAVE (MORADO Y RESPONSIVE) --- */
        #btn-save {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 40px !important;
            padding: 0 20px !important; /* Estilo píldora en Desktop */
            border-radius: 50px !important;
            
            /* COLOR MORADO FUERTE */
            background: #8b5cf6 !important; 
            color: white !important;
            border: none;
            
            min-width: unset;
            transition: transform 0.2s, background 0.2s;
            box-shadow: 0 4px 6px rgba(139, 92, 246, 0.25);
        }
        #btn-save:hover { 
            transform: scale(1.05); 
            background: #7c3aed !important; /* Morado un poco más oscuro al pasar mouse */
        }
        #btn-save span { display: inline-block; font-weight: 600; font-size: 0.9rem; }
        #btn-save i { font-size: 1rem; }

        /* --- MOBILE LAYOUT (Responsive) --- */
        @media (max-width: 991px) {
            .app-container {
                grid-template-columns: 1fr; /* Una sola columna */
                grid-template-areas: 
                    "header"
                    "canvas";
            }

            /* Transformar Sidebar Derecho en Barra Inferior */
            .sidebar-right {
                position: fixed;
                bottom: 0; left: 0; right: 0;
                height: var(--sb-mobile-h);
                width: 100%;
                border-left: none;
                border-top: 1px solid var(--border-color);
                flex-direction: row; /* Mobile: Horizontal */
                justify-content: center; /* Centrar herramientas */
                padding-top: 0;
                gap: 15px;
                transform: translateY(100%); /* Oculto por defecto (abajo) */
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                overflow-x: auto; /* Scroll si hay muchas herramientas */
                padding-left: 10px; padding-right: 10px;
            }

            .sidebar-right.show-mobile {
                transform: translateY(0); /* Mostrar al subir */
            }

            /* Fix Stamp Menu Mobile (Arriba de la barra) */
            .stamp-menu {
                bottom: 80px; /* 65px barra + 15px gap */
                left: 50%;
                transform: translateX(-50%);
                flex-direction: row; /* Horizontal en mobile */
                flex-wrap: wrap;
                justify-content: center;
                width: 90%;
                max-width: 350px;
            }

            /* Botón Save en Mobile: Solo Icono (Círculo más grande) */
            #btn-save {
                width: 40px !important;
                height: 40px !important;
                padding: 0 !important;
                border-radius: 50% !important;
            }
            /* OCULTAR EL TEXTO "SAVE" EN MOBILE */
            #btn-save span { display: none !important; }

            /* Ajustar controles flotantes en Mobile */
            .floating-controls {
                bottom: 20px; right: 15px; /* Ajuste de posición */
                transform: scale(0.85); /* Reducir tamaño un 15% */
                transform-origin: bottom right; 
                padding: 4px 12px;
            }

            /* Clase para ocultar los controles cuando sube la barra */
            .floating-controls.hide-ui {
                opacity: 0;
                pointer-events: none;
                transform: translateY(20px) scale(0.85); /* Se desplaza un poco hacia abajo */
            }

            /* Ajustar separadores en horizontal */
            .tool-separator {
                width: 1px; height: 30px; margin: 0 5px;
                border-bottom: none; border-left: 1px solid #475569;
            }
        }

    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeAllOverlays()"></div>

<div class="stamp-menu" id="stamp-menu">
    <div class="stamp-item text-success" onclick="addStamp('APPROVED', '#22c55e')"><i class="fas fa-check-circle me-2"></i>Approved</div>
    <div class="stamp-item text-danger" onclick="addStamp('REJECTED', '#ef4444')"><i class="fas fa-times-circle me-2"></i>Rejected</div>
    <div class="stamp-item text-warning" onclick="addStamp('REVIEW', '#eab308')"><i class="fas fa-exclamation-circle me-2"></i>Review</div>
    <div class="stamp-item text-info" onclick="addStamp('DRAFT', '#3b82f6')"><i class="fas fa-file-alt me-2"></i>Draft</div>
</div>

<div class="app-container">
    
    <header class="app-header">
        <div class="header-left">
            <a href="<?= $backUrl ?>" class="text-white me-3 d-md-none"><i class="fas fa-chevron-left"></i></a>
            
            <button class="toggle-icon-btn" onclick="toggleSheets()" title="Show Sheets">
                <i class="far fa-file-alt"></i>
            </button>

            <button class="toggle-icon-btn d-lg-none" id="btn-toggle-tools" onclick="toggleMobileTools()" title="Tools">
                <i class="fas fa-tools"></i>
            </button>

            <div class="brand-logo ms-2">
                <i class="fas fa-bolt text-warning"></i> <span class="d-none d-md-inline">Brightronix</span>
            </div>
            
            <div class="file-info d-none d-lg-flex">
                <small>Editing File</small>
                <span><?= htmlspecialchars($file['filename']) ?></span>
            </div>
        </div>

        <div class="properties-bar d-none d-md-flex">
            <div id="prop-smart" class="prop-section active">
                <i class="fas fa-mouse-pointer text-accent me-2"></i>
                <span class="text-white small fw-bold">Selection Mode</span>
            </div>
            
            <div id="prop-draw" class="prop-section">
                <span class="prop-label">Color</span>
                <div class="d-flex gap-2 mx-2">
                    <div class="color-dot active" style="background:#ef4444" onclick="setPenColor('#ef4444', this)"></div>
                    <div class="color-dot" style="background:#3b82f6" onclick="setPenColor('#3b82f6', this)"></div>
                    <div class="color-dot" style="background:#22c55e" onclick="setPenColor('#22c55e', this)"></div>
                    <div class="color-dot" style="background:#eab308" onclick="setPenColor('#eab308', this)"></div>
                </div>
                <div class="border-start border-secondary mx-2 h-50"></div>
                <span class="prop-label">Size</span>
                <input type="range" class="form-range" style="width:80px" min="1" max="10" value="3" oninput="setPenWidth(this.value)">
            </div>
            
            <div id="prop-text" class="prop-section">
                <span class="prop-label">Color</span>
                <div class="d-flex gap-2 mx-2" id="text-color-container">
                    <div class="color-dot" data-col="#ef4444" style="background:#ef4444" onclick="setTextFixedColor('#ef4444', this)"></div>
                    <div class="color-dot" data-col="#3b82f6" style="background:#3b82f6" onclick="setTextFixedColor('#3b82f6', this)"></div>
                    <div class="color-dot" data-col="#22c55e" style="background:#22c55e" onclick="setTextFixedColor('#22c55e', this)"></div>
                    <div class="color-dot" data-col="#eab308" style="background:#eab308" onclick="setTextFixedColor('#eab308', this)"></div>
                </div>
                <div class="border-start border-secondary mx-2 h-50"></div>
                <span class="prop-label">Size</span>
                <input type="number" id="text-size-input" class="form-control py-0 px-2 text-center" value="60" min="8" max="100" style="width:60px; height:30px;" onchange="updateTextProp('fontSize', parseInt(this.value))">
            </div>

            <div id="prop-measure" class="prop-section">
                <span class="prop-label text-success"><i class="fas fa-ruler me-2"></i>Measurement</span>
                <span class="text-white small">Drag nodes to adjust. Dbl-Tap to move.</span>
            </div>
            
            <div id="prop-cal" class="prop-section">
                <span class="prop-label text-warning"><i class="fas fa-ruler-combined me-2"></i>Calibration in ft</span>
                <div id="cal-mode-wrap" class="align-items-center gap-2 ms-2 d-flex">
                    <select id="cal-mode" class="form-select form-select-sm" style="width:110px; height:30px;" onchange="setCalMode(this.value)">
                        <option value="manual">Manual</option>
                        <option value="preset" selected>Preset</option>
                    </select>
                    <select id="cal-preset" class="form-select form-select-sm" style="width:190px; height:30px;" onchange="applyScalePreset(this.value)">
                        <option value="">Preset scale...</option>
                    </select>
                </div>
                <div id="cal-actions" style="display:none;" class="align-items-center gap-2 ms-2">
                    <input type="number" id="cal-val" class="form-control py-0 px-2" placeholder="ft" style="width:60px; height:30px;" min="0.1" step="0.1">
                    <button class="btn btn-sm btn-success rounded-circle" style="width:30px;height:30px" onclick="finishCal(true)"><i class="fas fa-check"></i></button>
                    <button class="btn btn-sm btn-secondary rounded-circle" style="width:30px;height:30px" onclick="finishCal(false)"><i class="fas fa-times"></i></button>
                    <button class="btn btn-sm btn-danger rounded-circle ms-2" id="btn-del-cal" style="display:none; width:30px;height:30px" onclick="clearCalLine()" title="Delete Line"><i class="fas fa-trash"></i></button>
                </div>
                <span id="cal-hint" class="text-main small ms-2">Draw a known line...</span>
            </div>

            <div id="scale-display-wrap" class="prop-section active">
                <span class="prop-label text-warning">Scale</span>
                <span id="scale-display" class="text-white small fw-bold ms-2">--</span>
            </div>
        </div>

        <div class="header-right">
            <button class="btn btn-outline-danger rounded-circle d-none d-md-flex align-items-center justify-content-center" 
                    id="btn-delete-selection" 
                    style="width:35px;height:35px;border-color:var(--danger);" 
                    onclick="deleteSelected()" 
                    title="Delete Selected">
                <i class="fas fa-trash"></i>
            </button>

            <div class="d-flex gap-1 ms-2">
                <button class="btn btn-outline-light rounded-circle" id="btn-undo" style="width:35px;height:35px;border-color:var(--border);" onclick="undo()" title="Undo"><i class="fas fa-undo"></i></button>
                <button class="btn btn-outline-light rounded-circle" id="btn-redo" style="width:35px;height:35px;border-color:var(--border);" onclick="redo()" title="Redo"><i class="fas fa-redo"></i></button>
            </div>
            
            <button class="btn-action" id="btn-save" onclick="openReportModal()" title="Save and Report">
                <i class="fas fa-save"></i> <span>Save</span>
            </button>
            
            <a href="<?= $backUrl ?>" class="btn-close-custom d-none d-md-flex">
                <i class="fas fa-times"></i>
            </a>
        </div>
    </header>

    <aside class="sidebar-left" id="sidebarLeft">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="sidebar-title mb-0"><i class="far fa-file-alt me-2"></i>Sheets</span>
            <button class="btn-close btn-close-white" onclick="toggleSheets()"></button>
        </div>

        <div id="page-list-container">
            <div class="page-item active">Loading Pages...</div>
        </div>
        
        <div class="mt-auto pt-4 border-top border-secondary">
            <span class="sidebar-title">Details</span>
            <div class="d-flex justify-content-between small mb-2">
                <span>Format:</span> <span class="text-white"><?= strtoupper($fileExt) ?></span>
            </div>
            <div class="d-flex justify-content-between small mb-2">
                <span>Uploaded:</span> <span class="text-white"><?= date('M d', strtotime($file['uploaded_at'])) ?></span>
            </div>
        </div>
    </aside>

    <main class="canvas-area" id="canvas-wrapper">
        <canvas id="c"></canvas>
        
        <div class="floating-controls">
            <button class="float-btn" onclick="changePage(-1)"><i class="fas fa-chevron-left"></i></button>
            <span class="small fw-bold"><span id="p-curr">1</span>/<span id="p-total">-</span></span>
            <button class="float-btn" onclick="changePage(1)"><i class="fas fa-chevron-right"></i></button>
            <div class="border-start border-secondary h-75 mx-2 opacity-50"></div>
            <span class="small text-accent fw-bold" id="zoom-disp">100%</span>
        </div>
    </main>

    <aside class="sidebar-right" id="sidebarRight">
        <button class="tool-btn active" id="btn-smart" onclick="setMode('smart')" title="Pointer"><i class="fas fa-mouse-pointer"></i></button>
        <button class="tool-btn" id="btn-draw" onclick="setMode('draw')" title="Pen Tool"><i class="fas fa-pencil-alt"></i></button>
        <button class="tool-btn" id="btn-text" onclick="addText()" title="Add Text"><i class="fas fa-font"></i></button>
        
        <button class="tool-btn" id="btn-stamp" onclick="toggleStampMenu()" title="Stamps"><i class="fas fa-stamp"></i></button>
        
        <div class="tool-separator"></div>
        <button class="tool-btn" id="btn-measure" onclick="setMode('measure')" title="Ruler"><i class="fas fa-ruler"></i></button>
        <button class="tool-btn text-warning" id="btn-cal" onclick="setMode('cal')" title="Calibrate"><i class="fas fa-ruler-combined"></i></button>
    </aside>

</div>

<div class="modal fade" id="reportModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Save Field Report</h5>
                <button type="button" class="btn btn-outline-danger rounded-circle d-flex align-items-center justify-content-center p-0" data-bs-dismiss="modal" style="width: 30px; height: 30px; border-width: 2px;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Technician Name</label>
                    <input type="text" id="rep-name" class="form-control" value="<?= htmlspecialchars($_SESSION['username']) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Role / Title</label>
                    <input type="text" id="rep-role" class="form-control" value="<?= htmlspecialchars($_SESSION['role']) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Activity Description</label>
                    <textarea id="rep-desc" class="form-control" rows="3" placeholder="e.g. Added conduit path to room 102..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-action" id="btn-generate" onclick="submitReport()">
                    <i class="fas fa-check"></i> Generate Report
                </button>
            </div>
        </div>
    </div>
</div>

<div id="toast-container"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // --- UI HELPERS ---
    
    // Toggle Sidebar Izquierda (Sheets)
    function toggleSheets() {
        document.getElementById('sidebarLeft').classList.toggle('show');
        updateOverlay();
        // Cerrar herramientas si abrimos sheets
        closeTools();
    }

    // Toggle Herramientas (Mobile)
    function toggleMobileTools() {
        const sbRight = document.getElementById('sidebarRight');
        const btn = document.getElementById('btn-toggle-tools');
        const floatControls = document.querySelector('.floating-controls'); // Selección de controles flotantes

        sbRight.classList.toggle('show-mobile');
        btn.classList.toggle('active');
        
        // Logica para ocultar controles flotantes
        if (sbRight.classList.contains('show-mobile')) {
            if(floatControls) floatControls.classList.add('hide-ui');
        } else {
            if(floatControls) floatControls.classList.remove('hide-ui');
        }

        // Cerrar sheets si abrimos herramientas
        document.getElementById('sidebarLeft').classList.remove('show');
        updateOverlay();
    }

    function closeTools() {
        document.getElementById('sidebarRight').classList.remove('show-mobile');
        document.getElementById('btn-toggle-tools').classList.remove('active');
        
        // Mostrar de nuevo los controles flotantes si se cerró la barra
        const floatControls = document.querySelector('.floating-controls');
        if(floatControls) floatControls.classList.remove('hide-ui');
    }

    function updateOverlay() {
        const overlay = document.getElementById('sidebarOverlay');
        const sheetsOpen = document.getElementById('sidebarLeft').classList.contains('show');
        if(sheetsOpen) overlay.classList.add('show'); else overlay.classList.remove('show');
    }

    function closeAllOverlays() {
        document.getElementById('sidebarLeft').classList.remove('show');
        updateOverlay();
    }

    // --- SETUP ---
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';

    // SERVER VARIABLES
    const fileUrl = "<?= $filePath ?>";
    const fileExt = "<?= $fileExt ?>"; 
    const fileId = <?= $id ?>;
    let allAnnotations = <?= $annotations ?>;
    if(typeof allAnnotations !== 'object' || allAnnotations === null) allAnnotations = {};

    // FABRIC INIT
    let canvas = new fabric.Canvas('c', { 
        preserveObjectStacking: true,
        fireRightClick: true,  
        stopContextMenu: true,
        allowTouchScrolling: false 
    });
    let pdfDoc = null, pageNum = 1, pdfScale = 2.0;
    const LOW_RES_SCALE = 1.0;
    const MAX_HIGH_CACHE = 4;
    const MAX_LOW_CACHE = 6;
    const pageCache = new Map();
    const highOrder = [];
    const lowOrder = [];
    let renderToken = 0;
    
    // STATES
    let pixelsPerFoot = 0;
    let currentMode = 'smart';
    let lineState = 0, activeLine = null, startPoint = null;
    let calLineObject = null; 
    let calMode = 'preset';

    // Calibration Persistence
    function getCalKey(suffix) {
        return `cal_${suffix}_file_${fileId}_page_${pageNum}`;
    }

    function getLegacyCalKey(suffix) {
        return `cal_${suffix}_file_${fileId}`;
    }

    function loadCalibrationForPage(showNotice) {
        try {
            let savedCal = localStorage.getItem(getCalKey('data'));
            if (savedCal === null) savedCal = localStorage.getItem(getLegacyCalKey('data'));
            if (savedCal && !isNaN(parseFloat(savedCal))) {
                pixelsPerFoot = parseFloat(savedCal);
                if (showNotice) setTimeout(() => showToast("Saved calibration loaded", "success"), 800);
            } else {
                pixelsPerFoot = 0;
            }
        } catch(e) { console.error("Storage error:", e); pixelsPerFoot = 0; }
        loadScaleDisplay();
    }

    function setScaleDisplay(text) {
        const el = document.getElementById('scale-display');
        if (el) el.textContent = text || '';
    }

    function keepScaleDisplayVisible() {
        const wrap = document.getElementById('scale-display-wrap');
        if (wrap) wrap.classList.add('active');
    }

    function loadScaleDisplay() {
        let savedLabel = localStorage.getItem(getCalKey('scale_label'));
        if (!savedLabel) savedLabel = localStorage.getItem(getLegacyCalKey('scale_label'));
        if (savedLabel) {
            setScaleDisplay(savedLabel);
        } else {
            setScaleDisplay('');
        }
    }

    // --- SCALE PRESETS ---
    const RAW_SCALE_PRESETS = [
        { category: 'Architectural', label: '1/128" = 1\'' },
        { category: 'Architectural', label: '1/64" = 1\'' },
        { category: 'Architectural', label: '1/32" = 1\'' },
        { category: 'Architectural', label: '1/16" = 1\'' },
        { category: 'Architectural', label: '3/32" = 1\'' },
        { category: 'Architectural', label: '1/8" = 1\'' },
        { category: 'Architectural', label: '3/16" = 1\'' },
        { category: 'Architectural', label: '1/4" = 1\'' },
        { category: 'Architectural', label: '3/8" = 1\'' },
        { category: 'Architectural', label: '1/2" = 1\'' },
        { category: 'Architectural', label: '3/4" = 1\'' },
        { category: 'Architectural', label: '1" = 1\'' },
        { category: 'Architectural', label: '1 1/2" = 1\'' },
        { category: 'Architectural', label: '3" = 1\'' },
        { category: 'Civil', label: '1" = 10\'' },
        { category: 'Civil', label: '1" = 20\'' },
        { category: 'Civil', label: '1" = 30\'' },
        { category: 'Civil', label: '1" = 40\'' },
        { category: 'Civil', label: '1" = 50\'' },
        { category: 'Civil', label: '1" = 60\'' },
        { category: 'Civil', label: '1" = 70\'' },
        { category: 'Civil', label: '1" = 80\'' },
        { category: 'Civil', label: '1" = 90\'' },
        { category: 'Civil', label: '1" = 100\'' },
        { category: 'Civil', label: '1" = 300\'' },
        { category: 'Civil', label: '1" = 500\'' },
        { category: 'Civil', label: '1" = 1000\'' }
    ];

    function parseFraction(value) {
        const match = value.match(/^(-?\d+(?:\.\d+)?)\s*\/\s*(-?\d+(?:\.\d+)?)$/);
        if (!match) return NaN;
        const numerator = parseFloat(match[1]);
        const denominator = parseFloat(match[2]);
        if (!isFinite(numerator) || !isFinite(denominator) || denominator === 0) return NaN;
        return numerator / denominator;
    }

    function parseMixedNumber(value) {
        const parts = value.trim().split(/\s+/);
        if (parts.length === 1) {
            if (parts[0].includes('/')) return parseFraction(parts[0]);
            return parseFloat(parts[0]);
        }
        if (parts.length === 2) {
            const whole = parseFloat(parts[0]);
            const fraction = parseFraction(parts[1]);
            if (!isFinite(whole) || !isFinite(fraction)) return NaN;
            return whole + fraction;
        }
        return NaN;
    }

    function parseScaleLabel(label) {
        const match = label.match(/^(.+)"\s*=\s*(.+)'$/);
        if (!match) return null;
        const inches = parseMixedNumber(match[1].trim());
        const feet = parseMixedNumber(match[2].trim());
        if (!isFinite(inches) || !isFinite(feet) || inches <= 0 || feet <= 0) return null;
        return { inches, feet, feetPerInch: feet / inches };
    }

    function buildScalePresets() {
        const presets = [];
        RAW_SCALE_PRESETS.forEach(raw => {
            const parsed = parseScaleLabel(raw.label);
            if (!parsed || !isFinite(parsed.feetPerInch) || parsed.feetPerInch <= 0) {
                console.warn("Invalid scale preset:", raw);
                return;
            }
            presets.push({ ...raw, ...parsed });
        });
        return presets;
    }

    const SCALE_PRESETS = buildScalePresets();

    function populateScalePresets() {
        const select = document.getElementById('cal-preset');
        if (!select) return;
        SCALE_PRESETS.forEach((preset, index) => {
            let group = select.querySelector(`optgroup[label="${preset.category}"]`);
            if (!group) {
                group = document.createElement('optgroup');
                group.label = preset.category;
                select.appendChild(group);
            }
            const option = document.createElement('option');
            option.value = String(index);
            option.textContent = preset.label;
            group.appendChild(option);
        });
    }

    async function getPdfPixelsPerInch() {
        if (!pdfDoc) return null;
        const bg = canvas.backgroundImage;
        const page = await pdfDoc.getPage(pageNum);
        const viewport = page.getViewport({ scale: 1 });
        const bgWidth = bg ? bg.width : viewport.width * pdfScale;
        if (!bgWidth || !viewport.width) return null;
        const renderScale = bgWidth / viewport.width;
        if (!isFinite(renderScale) || renderScale <= 0) return null;
        return 72 * renderScale;
    }

    async function applyScalePreset(value) {
        if (!value) return;
        const index = parseInt(value, 10);
        const preset = SCALE_PRESETS[index];
        if (!preset) { showToast("Invalid preset", "error"); return; }
        const pixelsPerInch = await getPdfPixelsPerInch();
        if (!pixelsPerInch) { showToast("Scale presets require a PDF background", "error"); return; }
        const nextPixelsPerFoot = pixelsPerInch / preset.feetPerInch;
        if (!isFinite(nextPixelsPerFoot) || nextPixelsPerFoot <= 0) { showToast("Invalid preset calculation", "error"); return; }
        pixelsPerFoot = nextPixelsPerFoot;
        localStorage.setItem(getCalKey('data'), pixelsPerFoot);
        localStorage.setItem(getCalKey('scale_label'), preset.label);
        setScaleDisplay(preset.label);
        showToast(`Calibrated! 1 ft = ${pixelsPerFoot.toFixed(2)} px`, "success");
        refreshMeasureLabels();
    }

    function resetScalePresetSelection() {
        const preset = document.getElementById('cal-preset');
        if (preset) preset.value = '';
    }

    function updateCalHint() {
        const hint = document.getElementById('cal-hint');
        if (!hint) return;
        hint.textContent = (calMode === 'preset') ? 'Select a preset scale...' : 'Draw a known line...';
    }

    function setCalMode(mode) {
        calMode = (mode === 'preset') ? 'preset' : 'manual';
        const modeSelect = document.getElementById('cal-mode');
        if (modeSelect) modeSelect.value = calMode;
        const preset = document.getElementById('cal-preset');
        if (preset) preset.disabled = (calMode !== 'preset');
        const actions = document.getElementById('cal-actions');
        if (actions) {
            actions.style.display = (calMode === 'manual' && calLineObject) ? 'flex' : 'none';
        }
        const btnDel = document.getElementById('btn-del-cal');
        if (btnDel) btnDel.style.display = (calMode === 'manual' && calLineObject) ? 'inline-block' : 'none';
        if (calMode !== 'preset') resetScalePresetSelection();
        updateCalHint();
        keepScaleDisplayVisible();
    }

    function refreshMeasureLabels() {
        canvas.getObjects().forEach(obj => {
            if (obj.isMeasureLine && obj.label) updateMeasureLabel(obj);
        });
        canvas.requestRenderAll();
    }

    function runScalePresetSelfCheck() {
        const cases = [
            { label: '1/8" = 1\'', expected: 8 },
            { label: '1 1/2" = 1\'', expected: 1 / 1.5 },
            { label: '1" = 500\'', expected: 500 }
        ];
        return cases.map(testCase => {
            const parsed = parseScaleLabel(testCase.label);
            const actual = parsed ? parsed.feetPerInch : null;
            const ok = parsed ? Math.abs(actual - testCase.expected) < 1e-6 : false;
            return { label: testCase.label, feetPerInch: actual, ok };
        });
    }

    window.__scalePresetSelfCheck = runScalePresetSelfCheck;
    populateScalePresets();
    setCalMode(calMode);
    loadCalibrationForPage(true);
    keepScaleDisplayVisible();

    // HISTORY
    const MAX_HISTORY = 21;
    let undoStack = [];
    let historyIndex = -1;  
    let historyProcessing = false; 

    window.addEventListener('contextmenu', e => e.preventDefault());

    function resize() {
        const w = document.getElementById('canvas-wrapper');
        if(w) { canvas.setWidth(w.clientWidth); canvas.setHeight(w.clientHeight); }
    }
    window.addEventListener('resize', resize);
    setTimeout(resize, 100); 

    // --- DOUBLE TAP & NODE LOGIC ---
    let lastTapTime = 0;
    let lastTapTarget = null;
    const DOUBLE_TAP_DELAY = 400;

    // --- CUSTOM CONTROLS FOR LINES (POSITION HANDLER FIXED) ---
    function createLineControls(line) {
        function linePositionHandler(pointName) {
            return function(dim, finalMatrix, fabricObject) {
                const points = fabricObject.calcLinePoints();
                const pt = (pointName === 'p1') ? new fabric.Point(points.x1, points.y1) : new fabric.Point(points.x2, points.y2);
                return fabric.util.transformPoint(pt, finalMatrix);
            };
        }
        function lineActionHandler(pointName) {
            return function(e, transform, x, y) {
                const target = transform.target;
                let localPoint = null;
                if (fabric.controlsUtils && typeof fabric.controlsUtils.getLocalPoint === 'function') {
                    localPoint = fabric.controlsUtils.getLocalPoint(transform, target.originX || 'center', target.originY || 'center', x, y);
                } else {
                    const pt = new fabric.Point(x, y);
                    localPoint = target.toLocalPoint(pt, target.originX || 'center', target.originY || 'center');
                }
                if (!localPoint) return false;
                if (pointName === 'p1') { target.set({ x1: localPoint.x, y1: localPoint.y }); } else { target.set({ x2: localPoint.x, y2: localPoint.y }); }
                updateMeasureLabel(target);
                return true;
            };
        }
        line.controls = {
            p1: new fabric.Control({ positionHandler: linePositionHandler('p1'), actionHandler: lineActionHandler('p1'), cursorStyle: 'crosshair', render: renderCircleControl }),
            p2: new fabric.Control({ positionHandler: linePositionHandler('p2'), actionHandler: lineActionHandler('p2'), cursorStyle: 'crosshair', render: renderCircleControl })
        };
    }

    function renderCircleControl(ctx, left, top, styleOverride, fabricObject) {
        ctx.save(); ctx.translate(left, top); ctx.beginPath();
        ctx.arc(0, 0, 8, 0, Math.PI * 2, false); 
        ctx.fillStyle = "#ffffff"; ctx.strokeStyle = "#22c55e"; ctx.lineWidth = 2;
        ctx.fill(); ctx.stroke(); ctx.restore();
    }

    // --- LOCK HELPERS ---
    function lockObject(obj) {
        if(!obj) return;
        obj.set({
            lockMovementX: obj.isMeasureLine ? false : true,
            lockMovementY: obj.isMeasureLine ? false : true,
            lockRotation: true, lockScalingX: true, lockScalingY: true,
            borderColor: '#22c55e', cornerColor: 'transparent', hasBorders: false, hasControls: true
        });
        if (obj.isMeasureLine) createLineControls(obj);
    }

    function unlockObject(obj) {
        if(!obj) return;
        obj.set({
            lockMovementX: false, lockMovementY: false, lockRotation: true,
            borderColor: '#ef4444', hasBorders: true, hasControls: false, borderDashArray: [5, 5]
        });
    }

    // --- DELETE FUNCTIONALITY ---
    function deleteSelected() {
        const activeObjects = canvas.getActiveObjects();
        if(!activeObjects.length) return;
        
        // MODIFICADO: Eliminado confirm() y agrupado el historial
        historyProcessing = true; // Pausar guardado automático por objeto para agrupar la acción
        
        canvas.discardActiveObject(); // Limpiar selección visual
        
        activeObjects.forEach(obj => {
            // Limpieza de dependencias (Etiquetas de medidas)
            if(obj.isMeasureLine && obj.label) canvas.remove(obj.label);
            
            // Limpieza inversa (Si borro etiqueta, buscar y borrar linea)
            if(obj.isMeasureLabel) {
                 const line = canvas.getObjects().find(o => o.labelId === obj.id);
                 if(line) canvas.remove(line);
            }
            canvas.remove(obj);
        });
        
        historyProcessing = false; // Reactivar historial
        saveHistory(); // Guardar el estado UNA vez con todos los objetos borrados
        showToast("Selection deleted", "success");
    }

    // --- PINCH ZOOM & PAN (GESTOS TÁCTILES MEJORADOS) ---
    const canvasWrapper = document.querySelector('.upper-canvas');
    let lastDist = 0;
    let lastClientX = 0;
    let lastClientY = 0;

    if(canvasWrapper) {
        canvasWrapper.addEventListener('touchstart', function(e) {
            if (e.touches.length === 2) {
                // Calcular distancia inicial
                const dx = e.touches[0].clientX - e.touches[1].clientX;
                const dy = e.touches[0].clientY - e.touches[1].clientY;
                lastDist = Math.sqrt(dx * dx + dy * dy);
                
                // Calcular centro inicial para el Pan
                lastClientX = (e.touches[0].clientX + e.touches[1].clientX) / 2;
                lastClientY = (e.touches[0].clientY + e.touches[1].clientY) / 2;
                
                e.preventDefault(); 
            }
        }, { passive: false });

        canvasWrapper.addEventListener('touchmove', function(e) {
            if (e.touches.length === 2) {
                e.preventDefault();
                
                // 1. CALCULAR ZOOM (Escala)
                const dx = e.touches[0].clientX - e.touches[1].clientX;
                const dy = e.touches[0].clientY - e.touches[1].clientY;
                const dist = Math.sqrt(dx * dx + dy * dy);
                
                // 2. CALCULAR PAN (Movimiento)
                const currentClientX = (e.touches[0].clientX + e.touches[1].clientX) / 2;
                const currentClientY = (e.touches[0].clientY + e.touches[1].clientY) / 2;

                const deltaX = currentClientX - lastClientX;
                const deltaY = currentClientY - lastClientY;

                // Aplicar Pan (Mover el canvas)
                const vpt = canvas.viewportTransform;
                vpt[4] += deltaX;
                vpt[5] += deltaY;

                // Aplicar Zoom
                if(lastDist > 0) {
                    const scale = dist / lastDist;
                    let newZoom = canvas.getZoom() * scale;
                    if (newZoom > 20) newZoom = 20; if (newZoom < 0.1) newZoom = 0.1;
                    
                    // Zoom hacia el punto central de los dedos
                    const point = new fabric.Point(currentClientX, currentClientY);
                    canvas.zoomToPoint(point, newZoom);
                    
                    document.getElementById('zoom-disp').innerText = Math.round(newZoom * 100) + '%';
                    updateTextScales(newZoom);
                }

                // Actualizar referencias para el siguiente frame
                lastDist = dist;
                lastClientX = currentClientX;
                lastClientY = currentClientY;

                canvas.requestRenderAll();
            }
        }, { passive: false });
    }

    canvas.on('mouse:up', function(opt) {
        this.setViewportTransform(this.viewportTransform);
        this.isDragging = false;
        if (currentMode === 'smart') this.selection = true;
        if(this.isDrawingModeWasOn) { canvas.isDrawingMode = true; this.isDrawingModeWasOn = false; }
        
        // MODIFICADO: Refuerzo contra falsos positivos (líneas cortas/basura)
        if (lineState === 1 && activeLine) {
            const ptr = canvas.getPointer(opt.e);
            const dist = Math.sqrt(Math.pow(ptr.x - startPoint.x, 2) + Math.pow(ptr.y - startPoint.y, 2));
            
            if (dist > 10) {
                finishLineLogic();
            } else {
                // BUG FIX: Si la distancia es muy corta (misclick), limpiar el objeto temporal
                canvas.remove(activeLine);
                activeLine = null;
                lineState = 0;
                canvas.requestRenderAll();
            }
        }
        canvas.setCursor('default');
    });

    // --- LOAD LOGIC ---
    if(fileExt === 'pdf') {
        pdfjsLib.getDocument(fileUrl).promise.then(pdf => {
            pdfDoc = pdf;
            document.getElementById('p-total').textContent = pdf.numPages;
            renderPageList(pdf.numPages);
            renderPage(pageNum);
        });
    } else if (fileExt === 'heic') {
        document.getElementById('p-total').textContent = '1'; renderPageList(1);
        fetch(fileUrl).then(res => res.blob()).then(blob => heic2any({ blob, toType: "image/jpeg" })).then(conversionResult => {
            const blob = Array.isArray(conversionResult) ? conversionResult[0] : conversionResult;
            const url = URL.createObjectURL(blob);
            fabric.Image.fromURL(url, img => { setBg(img); loadPageAnnotations(1); });
        }).catch(e => { console.error(e); showToast("Error loading HEIC", "error"); });
    } else {
        document.getElementById('p-total').textContent = '1'; renderPageList(1);
        fabric.Image.fromURL(fileUrl, img => { 
            if(!img) { showToast("Error loading image", "error"); return; }
            setBg(img); loadPageAnnotations(1); 
        });
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
        const activeEl = document.getElementById(`plist-${curr}`);
        if(activeEl) activeEl.classList.add('active');
        document.getElementById('p-curr').innerText = curr;
    }

    function touchOrder(order, num) {
        const idx = order.indexOf(num);
        if(idx >= 0) order.splice(idx, 1);
        order.push(num);
    }

    function trimCache() {
        while(highOrder.length > MAX_HIGH_CACHE) {
            const evict = highOrder.shift();
            const entry = pageCache.get(evict);
            if(entry) { delete entry.high; if(!entry.low) pageCache.delete(evict); }
        }
        while(lowOrder.length > MAX_LOW_CACHE) {
            const evict = lowOrder.shift();
            const entry = pageCache.get(evict);
            if(entry) { delete entry.low; if(!entry.high) pageCache.delete(evict); }
        }
    }

    function setCache(num, type, url) {
        const entry = pageCache.get(num) || {};
        entry[type] = url; pageCache.set(num, entry);
        if(type === 'high') touchOrder(highOrder, num); else touchOrder(lowOrder, num);
        trimCache();
    }
    function getCache(num) { return pageCache.get(num); }

    async function renderPageToDataUrl(num, scale) {
        const page = await pdfDoc.getPage(num);
        const viewport = page.getViewport({ scale });
        const tempC = document.createElement('canvas');
        tempC.width = viewport.width; tempC.height = viewport.height;
        await page.render({ canvasContext: tempC.getContext('2d'), viewport }).promise;
        const quality = scale >= pdfScale ? 0.85 : 0.7;
        return tempC.toDataURL('image/jpeg', quality);
    }

    function applyBackground(url, num, token, loadAnnotations) {
        fabric.Image.fromURL(url, img => {
            if(token !== renderToken) return;
            setBg(img);
            if(loadAnnotations) loadPageAnnotations(num);
        });
    }

    async function renderHigh(num, token) {
        const cached = getCache(num);
        if(cached && cached.high) { applyBackground(cached.high, num, token, false); return; }
        const url = await renderPageToDataUrl(num, pdfScale);
        if(token !== renderToken) return;
        setCache(num, 'high', url);
        applyBackground(url, num, token, false);
    }

    async function renderLowThenHigh(num, token) {
        const cached = getCache(num);
        if(cached && cached.low) {
            applyBackground(cached.low, num, token, true);
            if(!cached.high) renderHigh(num, token);
            return;
        }
        const url = await renderPageToDataUrl(num, LOW_RES_SCALE);
        if(token !== renderToken) return;
        setCache(num, 'low', url);
        applyBackground(url, num, token, true);
        renderHigh(num, token);
    }

    function prefetchNeighbors(num) {
        if(!pdfDoc) return;
        const total = pdfDoc.numPages;
        [num - 1, num + 1].forEach(n => {
            if(n < 1 || n > total) return;
            const cached = getCache(n);
            if(cached && (cached.low || cached.high)) return;
            renderPageToDataUrl(n, LOW_RES_SCALE).then(url => { setCache(n, 'low', url); }).catch(() => {});
        });
    }

    async function renderPage(num) {
        updatePageListUI(num);
        if(!pdfDoc) return;
        renderToken++;
        const token = renderToken;
        const cached = getCache(num);
        if(cached && (cached.high || cached.low)) {
            const url = cached.high || cached.low;
            applyBackground(url, num, token, true);
            if(!cached.high) renderHigh(num, token);
        } else { renderLowThenHigh(num, token); }
        prefetchNeighbors(num);
    }

    function setBg(img) {
        img.excludeFromHistory = true; 
        canvas.setBackgroundImage(img, canvas.renderAll.bind(canvas), { originX: 'left', originY: 'top' });
    }

    function jumpToPage(targetPage) {
        allAnnotations[pageNum] = JSON.stringify(canvas.toJSON(['isMeasureLine','labelId','labelOffsetX','labelOffsetY']));
        canvas.clear(); undoStack = []; historyIndex = -1;
        pageNum = targetPage; 
        loadCalibrationForPage(false);
        if(pdfDoc) renderPage(pageNum); else loadPageAnnotations(pageNum);
        
        // AUTO-HIDE SIDEBAR ON PAGE SELECT (Universal)
        const sb = document.getElementById('sidebarLeft');
        if(sb.classList.contains('show')) {
            toggleSheets();
        }
    }

    function changePage(offset) {
        let max = pdfDoc ? pdfDoc.numPages : 1;
        const newPage = pageNum + offset;
        if(newPage < 1 || newPage > max) return;
        jumpToPage(newPage);
    }

    function loadPageAnnotations(pg) {
        historyProcessing = true;
        if(allAnnotations[pg]) {
            canvas.loadFromJSON(allAnnotations[pg], function() { 
                const objects = canvas.getObjects();
                objects.forEach(obj => {
                    if (obj.isMeasureLine) {
                        lockObject(obj);
                        if(obj.labelId) {
                            const lbl = objects.find(o => o.isMeasureLabel && o.id === obj.labelId);
                            if(lbl) { obj.label = lbl; lbl.selectable = false; lbl.evented = false; }
                        }
                    } else if (!obj.isMeasureLabel) {
                        obj.set({ lockMovementX:true, lockMovementY:true, borderColor:'#22c55e' });
                    }
                });
                  updateTextScales(canvas.getZoom()); 
                  canvas.renderAll(); 
                  refreshMeasureLabels();
                  historyProcessing = false; 
                  saveHistory(); 
              });
        } else {
            historyProcessing = false;
            saveHistory(); 
        }
    }

    // --- HISTORY ---
    function saveHistory() {
        if(historyProcessing) return;
        if (historyIndex < undoStack.length - 1) { undoStack = undoStack.slice(0, historyIndex + 1); }
        const json = JSON.stringify(canvas.toJSON(['isMeasureLine', 'isMeasureLabel', 'labelId', 'id']));
        undoStack.push(json);
        historyIndex++;
        if (undoStack.length > MAX_HISTORY) { undoStack.shift(); historyIndex--; }
        updateHistoryButtons();
    }

    function undo() {
        if (historyIndex > 0) {
            historyProcessing = true; historyIndex--;
            const state = undoStack[historyIndex];
            canvas.loadFromJSON(state, () => {
                reLinkObjects(); historyProcessing = false; updateTextScales(canvas.getZoom()); updateHistoryButtons();
            });
        }
    }

    function redo() {
        if (historyIndex < undoStack.length - 1) {
            historyProcessing = true; historyIndex++;
            const state = undoStack[historyIndex];
            canvas.loadFromJSON(state, () => {
                reLinkObjects(); historyProcessing = false; updateTextScales(canvas.getZoom()); updateHistoryButtons();
            });
        }
    }

    function updateHistoryButtons() {
        const btnUndo = document.getElementById('btn-undo');
        const btnRedo = document.getElementById('btn-redo');
        if(historyIndex > 0) btnUndo.classList.remove('btn-disabled'); else btnUndo.classList.add('btn-disabled');
        if(historyIndex < undoStack.length - 1) btnRedo.classList.remove('btn-disabled'); else btnRedo.classList.add('btn-disabled');
    }

    function reLinkObjects() {
        const objects = canvas.getObjects();
        objects.forEach(obj => {
            if (obj.isMeasureLine) {
                lockObject(obj);
                if(obj.labelId) {
                    const lbl = objects.find(o => o.isMeasureLabel && o.id === obj.labelId);
                    if(lbl) { obj.label = lbl; lbl.selectable = false; lbl.evented = false; }
                }
            }
        });
        canvas.renderAll();
    }

    // --- EVENTS ---
    canvas.on('object:added', e => { if(!e.target.excludeFromHistory) saveHistory(); });
    canvas.on('object:modified', saveHistory);
    canvas.on('object:removed', e => {
        if(e.target.isMeasureLine && e.target.label) canvas.remove(e.target.label);
        saveHistory();
    });

    // Auto-remove empty/default text on creation
    canvas.on('text:editing:exited', function(e) {
        const obj = e.target;
        if(obj && obj.isNew) {
            if(obj.text.trim() === 'Note' || obj.text.trim() === '') {
                canvas.remove(obj);
                canvas.requestRenderAll();
                showToast("Empty note discarded", "warning");
            } else {
                delete obj.isNew;
            }
        }
    });

    function updateMeasureLabel(line) {
        if (!line || !line.label) return;
        const points = line.calcLinePoints();
        const matrix = line.calcTransformMatrix();
        const p1 = fabric.util.transformPoint(new fabric.Point(points.x1, points.y1), matrix);
        const p2 = fabric.util.transformPoint(new fabric.Point(points.x2, points.y2), matrix);
        const distPx = Math.hypot(p2.x - p1.x, p2.y - p1.y);
        let textVal = "";
        if (pixelsPerFoot > 0) { const feet = distPx / pixelsPerFoot; textVal = feet.toFixed(2) + " ft"; } 
        else { textVal = Math.round(distPx) + " px"; }
        line.label.set({ text: textVal });
        const midX = (p1.x + p2.x) / 2;
        const midY = (p1.y + p2.y) / 2;
        line.label.set({ left: midX, top: midY - 15 });
        line.setCoords(); line.label.setCoords();
    }

    canvas.on('object:moving', function(e) {
        const obj = e.target;
        if (obj.isMeasureLine && obj.label) {
            const center = obj.getCenterPoint();
            obj.label.set({ left: center.x, top: center.y - 15 });
        }
    });

    // --- TOOL SWITCHING ---
    function setMode(mode) {
        if (calLineObject && mode !== 'cal') clearCalLine(); 
        
        // FIX: Check for new note before switching tool
        const activeObj = canvas.getActiveObject();
        if(activeObj && activeObj.isNew && (activeObj.type === 'i-text' || activeObj.type === 'text')) {
             canvas.remove(activeObj);
             canvas.requestRenderAll();
             showToast("Empty note discarded", "warning");
        }

        resetToolState();
        currentMode = mode;
        canvas.discardActiveObject(); canvas.requestRenderAll();
        canvas.isDrawingMode = (mode === 'draw');
        canvas.selection = (mode === 'smart'); 
        document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
        if(mode !== 'smart') {
            const btn = document.getElementById('btn-' + mode);
            if(btn) btn.classList.add('active'); 
        } else { document.getElementById('btn-smart').classList.add('active'); }
        document.querySelectorAll('.prop-section').forEach(p => p.classList.remove('active'));
        const propEl = document.getElementById('prop-' + mode);
        if(propEl) propEl.classList.add('active');
        document.getElementById('stamp-menu').style.display = 'none';
        
        // CURSOR LOGIC SIMPLIFIED (No 'pan' mode check needed for cursor style here)
        if(mode === 'draw') { canvas.freeDrawingBrush.color = '#ef4444'; canvas.freeDrawingBrush.width = 3; canvas.defaultCursor = 'crosshair'; } 
        else if(mode === 'measure') {
            if(pixelsPerFoot <= 0) { showToast("Please calibrate first!", "error"); setMode('cal'); return; }
            canvas.defaultCursor = 'crosshair';
        } else if(mode === 'cal') { updateCalHint(); } 
        else { canvas.defaultCursor = 'default'; }
        
        if(mode === 'smart') {
            const active = canvas.getActiveObject();
            if(active && (active.type === 'i-text' || active.type === 'text')) showPropSection('text');
        }
        
        // Auto-close tools on mobile after selecting a tool (Optional UX improvement)
        if(window.innerWidth <= 991 && mode !== 'stamp') {
             // setTimeout(toggleMobileTools, 300); // Uncomment if you prefer auto-close
        }
    }

    function resetToolState() {
        if (activeLine && !calLineObject) { canvas.remove(activeLine); activeLine = null; }
        if(currentMode !== 'cal') {
             document.getElementById('cal-actions').style.display = 'none';
             document.getElementById('cal-hint').style.display = 'block';
             document.getElementById('cal-val').value = '';
             resetScalePresetSelection();
             updateCalHint();
        }
        lineState = 0;
    }
    
    function showPropSection(idPart) {
        document.querySelectorAll('.prop-section').forEach(p => p.classList.remove('active'));
        const el = document.getElementById('prop-' + idPart);
        if(el) el.classList.add('active');
        keepScaleDisplayVisible();
    }

    function toggleStampMenu() {
        const m = document.getElementById('stamp-menu');
        m.style.display = (m.style.display === 'flex') ? 'none' : 'flex';
    }

    function addStamp(text, color) {
        setMode('smart');
        const center = canvas.getVpCenter();
        const rect = new fabric.Rect({ width: 200, height: 80, rx: 10, ry: 10, fill: 'transparent', stroke: color, strokeWidth: 5, originX: 'center', originY: 'center' });
        const lbl = new fabric.Text(text, { fontSize: 40, fill: color, fontWeight: 'bold', fontFamily: 'Arial', originX: 'center', originY: 'center' });
        const group = new fabric.Group([rect, lbl], { left: center.x, top: center.y, opacity: 0.8 });
        lockObject(group);
        canvas.add(group); canvas.setActiveObject(group);
        document.getElementById('stamp-menu').style.display = 'none';
        saveHistory();
    }

    // --- CANVAS INPUTS ---
    canvas.on('mouse:down', function(opt) {
        const evt = opt.e;
        const now = new Date().getTime();
        if (opt.target && currentMode === 'smart') {
            if (lastTapTarget === opt.target && (now - lastTapTime < DOUBLE_TAP_DELAY)) {
                unlockObject(opt.target);
                showToast("Movement Unlocked", "warning"); canvas.renderAll();
                opt.target.isMoving = true;
            } else {
                if(opt.target.isMeasureLine) { lockObject(opt.target); canvas.renderAll(); } else { lockObject(opt.target); }
            }
            lastTapTarget = opt.target; lastTapTime = now;
        }
        const isTouch = evt.type.startsWith('touch');
        let clientX, clientY;
        if(isTouch) { clientX = evt.touches[0].clientX; clientY = evt.touches[0].clientY; } else { clientX = evt.clientX; clientY = evt.clientY; }

        if(evt.button === 2 || (currentMode === 'smart' && evt.altKey)) {
            evt.preventDefault(); evt.stopPropagation();
            this.isDragging = true; this.selection = false;
            this.lastPosX = clientX; this.lastPosY = clientY;
            if(canvas.isDrawingMode) { canvas.isDrawingMode = false; this.isDrawingModeWasOn = true; }
            canvas.setCursor('grabbing'); 
            return;
        }

        if(currentMode === 'cal' || currentMode === 'measure') {
            if(currentMode === 'cal' && calMode === 'preset') { showToast("Switch to Manual to draw a calibration line", "warning"); return; }
            if(currentMode === 'cal' && calLineObject) { showToast("Delete existing line first", "error"); return; }
            const ptr = canvas.getPointer(evt);
            if (lineState === 0) {
                startPoint = ptr;
                activeLine = new fabric.Line([ptr.x, ptr.y, ptr.x, ptr.y], {
                    stroke: (currentMode === 'cal' ? '#eab308' : '#22c55e'), strokeWidth: 3, 
                    selectable: false, evented: false, excludeFromHistory: true, originX: 'center', originY: 'center' 
                });
                canvas.add(activeLine); lineState = 1; 
            } else finishLineLogic();
            return;
        }
        if(currentMode === 'smart' && (!opt.target || evt.altKey)) {
            this.isDragging = true; this.selection = false;
            this.lastPosX = clientX; this.lastPosY = clientY; canvas.defaultCursor = 'grabbing';
        }
    });

    canvas.on('mouse:move', function(opt) {
        const evt = opt.e;
        const isTouch = evt.type.startsWith('touch');
        if(this.isDragging) {
            let clientX, clientY;
            if(isTouch) { evt.preventDefault(); clientX = evt.touches[0].clientX; clientY = evt.touches[0].clientY; } 
            else { clientX = evt.clientX; clientY = evt.clientY; }
            const vpt = this.viewportTransform;
            vpt[4] += clientX - this.lastPosX; vpt[5] += clientY - this.lastPosY;
            this.requestRenderAll();
            this.lastPosX = clientX; this.lastPosY = clientY;
        } else if (lineState === 1 && activeLine) {
            const ptr = canvas.getPointer(evt);
            activeLine.set({ x2: ptr.x, y2: ptr.y });
            canvas.renderAll();
        }
    });

    canvas.on('mouse:up', function(opt) {
        this.setViewportTransform(this.viewportTransform);
        this.isDragging = false;
        if (currentMode === 'smart') this.selection = true;
        if(this.isDrawingModeWasOn) { canvas.isDrawingMode = true; this.isDrawingModeWasOn = false; }
        if (lineState === 1 && activeLine) {
            const ptr = canvas.getPointer(opt.e);
            const dist = Math.sqrt(Math.pow(ptr.x - startPoint.x, 2) + Math.pow(ptr.y - startPoint.y, 2));
            if (dist > 10) finishLineLogic();
        }
        canvas.setCursor('default');
    });

    function finishLineLogic() {
        lineState = 0;
        const dx = activeLine.x2 - activeLine.x1; const dy = activeLine.y2 - activeLine.y1;
        const distPx = Math.sqrt(dx*dx + dy*dy);
        if (currentMode === 'cal') {
            document.getElementById('cal-actions').style.display = 'flex';
            document.getElementById('cal-hint').style.display = 'none';
            document.getElementById('btn-del-cal').style.display = 'inline-block';
            document.getElementById('cal-val').focus();
            canvas.tempDist = distPx; calLineObject = activeLine; 
        } else if (currentMode === 'measure') {
            const feet = distPx / pixelsPerFoot;
            const textVal = feet.toFixed(2) + " ft"; 
            const midX = (activeLine.x1 + activeLine.x2) / 2;
            const midY = (activeLine.y1 + activeLine.y2) / 2;
            const uniqueId = Date.now();
            const lbl = new fabric.Text(textVal, { left: midX, top: midY - 15, fontSize: 24, fill: '#22c55e', backgroundColor: '#0f172a', originX: 'center', originY: 'center', selectable: false, evented: false, isMeasureLabel: true, id: uniqueId + '_lbl' });
            const line = new fabric.Line([activeLine.x1, activeLine.y1, activeLine.x2, activeLine.y2], { stroke: '#22c55e', strokeWidth: 4, selectable: true, evented: true, originX: 'center', originY: 'center', isMeasureLine: true, labelId: lbl.id, label: lbl, id: uniqueId + '_line' });
            canvas.remove(activeLine); canvas.add(line); canvas.add(lbl);
            lockObject(line); canvas.setActiveObject(line);
            activeLine = null; saveHistory();
        }
    }

    function clearCalLine() {
        if(calLineObject) { canvas.remove(calLineObject); calLineObject = null; }
        document.getElementById('cal-actions').style.display = 'none';
        document.getElementById('cal-hint').style.display = 'block';
        document.getElementById('cal-val').value = '';
        resetScalePresetSelection();
        updateCalHint();
        lineState = 0; activeLine = null; canvas.renderAll();
    }
    
    function finishCal(save) {
        if(save) {
            const val = parseFloat(document.getElementById('cal-val').value);
            if(val > 0) {
                pixelsPerFoot = canvas.tempDist / val;
                localStorage.setItem(getCalKey('data'), pixelsPerFoot);
                localStorage.setItem(getCalKey('scale_label'), 'Custom');
                setScaleDisplay('Custom');
                showToast(`Calibrated! 1 ft = ${pixelsPerFoot.toFixed(2)} px`, "success");
                refreshMeasureLabels();
                clearCalLine();
            } else { showToast("Invalid value", "error"); return; }
        } else clearCalLine();
        resetToolState(); setMode('smart');
    }

    // --- UTILS ---
    function setPenColor(c, el) { canvas.freeDrawingBrush.color = c; document.querySelectorAll('.color-dot').forEach(d => d.classList.remove('active')); el.classList.add('active'); }
    function setPenWidth(w) { canvas.freeDrawingBrush.width = parseInt(w); }

    function addText() {
        setMode('smart');
        const center = canvas.getVpCenter();
        const t = new fabric.IText('Note', { left: center.x, top: center.y, fill: '#ef4444', fontSize: 60, isNew: true });
        lockObject(t); canvas.add(t); canvas.setActiveObject(t); t.selectAll(); t.enterEditing();
        showPropSection('text');
        document.getElementById('text-size-input').value = 60;
        document.querySelectorAll('#prop-text .color-dot').forEach(d => d.classList.remove('active'));
        document.querySelector('#prop-text .color-dot[data-col="#ef4444"]').classList.add('active');
    }

    function setTextFixedColor(color, el) {
        document.querySelectorAll('#prop-text .color-dot').forEach(d => d.classList.remove('active'));
        el.classList.add('active');
        updateTextProp('fill', color);
    }

    function openReportModal() {
        allAnnotations[pageNum] = JSON.stringify(canvas.toJSON(['isMeasureLine','labelId']));
        new bootstrap.Modal(document.getElementById('reportModal')).show();
    }

    async function submitReport() {
        const btn = document.getElementById('btn-generate');
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Generating...';
        try {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            doc.setFontSize(22); doc.text("Field Activity Report", 20, 20);
            doc.setFontSize(12);
            doc.text(`Project File: <?= $file['filename'] ?>`, 20, 35);
            doc.text(`Technician: ${document.getElementById('rep-name').value}`, 20, 45);
            doc.text(`Role: ${document.getElementById('rep-role').value}`, 20, 55);
            doc.text(`Date: ${new Date().toLocaleDateString()}`, 20, 65);
            doc.setFontSize(14); doc.text("Activity Description:", 20, 80);
            doc.setFontSize(11);
            const desc = document.getElementById('rep-desc').value;
            const splitText = doc.splitTextToSize(desc, 170);
            doc.text(splitText, 20, 90);
            const dataUrl = canvas.toDataURL({ format: 'jpeg', quality: 0.8 });
            doc.addPage();
            doc.text("Plan Snapshot (Current View)", 20, 20);
            const imgProps = doc.getImageProperties(dataUrl);
            const pdfWidth = doc.internal.pageSize.getWidth() - 40;
            const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;
            doc.addImage(dataUrl, 'JPEG', 20, 30, pdfWidth, pdfHeight);
            const pdfBlob = doc.output('blob');
            const annotationsJson = JSON.stringify(allAnnotations);
            const fd = new FormData();
            fd.append('action', 'save_report_flow');
            fd.append('file_id', fileId);
            fd.append('pdf_file', pdfBlob);
            fd.append('annotations_json', annotationsJson);
            fd.append('tech_name', document.getElementById('rep-name').value);
            fd.append('tech_role', document.getElementById('rep-role').value);
            fd.append('description', desc);
            const res = await fetch('../api/api.php', { method: 'POST', body: fd });
            const d = await res.json();
            if(d.status === 'success') {
                showToast("Report saved successfully!", "success");
                setTimeout(() => location.href = "preview.php?id=" + fileId, 1500);
            } else { showToast("Error saving report: " + d.msg, "error"); btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Generate Report'; }
        } catch (e) { console.error(e); showToast("Critical Error generating report", "error"); btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Generate Report'; }
    }

    function showToast(msg, type) {
        const box = document.getElementById('toast-container'); 
        const el = document.createElement('div'); el.className = `toast-msg`;
        el.style.borderLeft = `4px solid ${type==='success'?'#10b981': (type==='warning'?'#eab308':'#ef4444')}`;
        let icon = '<i class="fas fa-check-circle text-success"></i>';
        if(type === 'error') icon = '<i class="fas fa-exclamation-circle text-danger"></i>';
        if(type === 'warning') icon = '<i class="fas fa-lock-open text-warning"></i>';
        el.innerHTML = icon + `<span>${msg}</span>`;
        box.appendChild(el); setTimeout(() => el.remove(), 4000);
    }

    function updateTextProp(prop, val) {
        const active = canvas.getActiveObject();
        if(active && (active.type === 'i-text' || active.type === 'text')) { active.set(prop, val); canvas.renderAll(); }
    }

    function updateTextScales(zoom) {
        const scale = Math.min(1.5, Math.max(0.2, 1 / zoom));
        canvas.getObjects().forEach(obj => {
            if (obj.isMeasureLabel) { obj.set({ scaleX: scale, scaleY: scale }); }
            if (obj.isMeasureLine) { obj.set({ strokeWidth: 4 * scale }); }
        });
        canvas.requestRenderAll();
    }

    function handleSelectionChange() {
        const active = canvas.getActiveObject();
        if (active) {
            if(active.type === 'i-text' || active.type === 'text') {
                const sInp = document.getElementById('text-size-input');
                if(sInp) sInp.value = active.fontSize;
                const currentColor = active.fill;
                document.querySelectorAll('#prop-text .color-dot').forEach(d => {
                    d.classList.remove('active');
                    if(d.getAttribute('data-col').toLowerCase() === currentColor.toLowerCase()) { d.classList.add('active'); }
                });
                showPropSection('text');
            } else { showPropSection('smart'); }
        } else { showPropSection(currentMode); }
        keepScaleDisplayVisible();
    }

    canvas.on('selection:created', handleSelectionChange);
    canvas.on('selection:updated', handleSelectionChange);
    canvas.on('selection:cleared', handleSelectionChange); 

    window.addEventListener('keydown', e => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'z') { e.preventDefault(); undo(); }
        if ((e.ctrlKey || e.metaKey) && e.key === 'y') { e.preventDefault(); redo(); }
        if(e.key === 'Delete' || e.key === 'Backspace') {
            const activeObj = canvas.getActiveObject();
            if (activeObj && activeObj.isEditing) return; 
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            e.preventDefault(); deleteSelected(); 
        }
    });

    canvas.on('mouse:wheel', function(opt) {
        let delta = opt.e.deltaY; let zoom = canvas.getZoom() * (0.999 ** delta);
        if (zoom > 20) zoom = 20; if (zoom < 0.05) zoom = 0.05;
        canvas.zoomToPoint({ x: opt.e.offsetX, y: opt.e.offsetY }, zoom);
        document.getElementById('zoom-disp').innerText = Math.round(zoom * 100) + '%';
        updateTextScales(zoom);
        opt.e.preventDefault(); opt.e.stopPropagation();
    });

</script>
</body>
</html>
