<?php
// preview.php - Preview Profesional V8.2 (UI Update: Header & Floating Controls Position)
require_once __DIR__ . '/../core/auth/session.php';
require_once __DIR__ . '/../core/db/connection.php';

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
// 5. Normalizar ruta publica
$filePath = str_replace('\\', '/', (string)($file['filepath'] ?? ''));
if ($filePath !== '') {
    // Si es ruta absoluta, recortar desde uploads/
    if (preg_match('~(api/)?uploads/[^\\s]+$~', $filePath, $m)) {
        $filePath = $m[0];
    }
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
}
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

    <style>
        /* --- TEMA MIDNIGHT BLUE (V5.0) --- */
        :root { 
            --bg-body: #0b1120;       
            --bg-panel: #111827;      
            --bg-header: rgba(11, 17, 32, 0.95);
            --border: rgba(255,255,255,0.05);        
            --text-main: #ffffff;     
            --text-muted: #c5cad1;
            --primary: #6366f1;        
            --accent: #0ea5e9;
            --danger: #ef4444;
            --success: #10b981;
            --radius-box: 20px;
            --radius-btn: 50px;
        }

        body { 
            background: var(--bg-body); height: 100vh; overflow: hidden; 
            color: var(--text-main); font-family: 'Outfit', sans-serif; 
            margin: 0; display: flex; flex-direction: column; 
            touch-action: none; /* CRÍTICO: Prevenir gestos nativos */
        }

        .app-container {
            display: grid;
            grid-template-columns: 280px 1fr 320px; 
            grid-template-rows: 70px 1fr;
            height: 100vh; width: 100vw;
        }

        /* HEADER */
        .app-header {
            grid-column: 1 / -1; background: var(--bg-header); border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between; padding: 0 20px; z-index: 50;
            backdrop-filter: blur(10px);
        }
        
        .brand-logo { font-weight: 700; font-size: 1.2rem; color: white; display:flex; align-items: center; gap: 10px; }
        .file-info { border-left: 1px solid var(--border); padding-left: 20px; margin-left: 20px; font-size: 0.9rem; color: var(--text-muted); }
        .file-info span { color: white; font-weight: 600; display: block; }

        /* SIDEBARS */
        .sidebar { background: var(--bg-panel); display: flex; flex-direction: column; padding: 25px; overflow-y: auto; }
        .sidebar-left { 
            grid-row: 2; grid-column: 1; background: var(--bg-panel); 
            border-right: 1px solid var(--border); display: flex; flex-direction: column; padding: 25px; 
            height: 100%; overflow: hidden; z-index: 1000;
            transition: transform 0.3s ease;
        }        
        .sidebar-right { 
            grid-row: 2; grid-column: 3; border-left: 1px solid var(--border); padding: 0; 
            z-index: 1000; transition: transform 0.3s ease; background: var(--bg-panel);
            display: flex; flex-direction: column;
        }

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
        .page-item.active { background: var(--primary); color: white; box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3); }

        /* HISTORY LOG */
        .history-header { padding: 20px 25px; border-bottom: 1px solid var(--border); font-weight: 700; font-size: 0.9rem; display: flex; align-items: center; gap: 10px; background: rgba(0,0,0,0.1); justify-content: space-between; }
        .history-list { padding: 20px; overflow-y: auto; flex-grow: 1; }
        
        .report-card { 
            background: #1e293b; border: 1px solid var(--border); border-radius: 15px; 
            padding: 15px; margin-bottom: 15px; transition: 0.3s ease; 
            position: relative; overflow: hidden; 
        }
        .report-card:hover { border-color: var(--accent); transform: scale(1.02); }
        .report-role { color: var(--accent); font-size: 0.7rem; text-transform: uppercase; font-weight: 800; }
        .report-desc { color: var(--text-muted); font-size: 0.85rem; margin: 10px 0; line-height: 1.4; font-style: italic; }
        .report-meta { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 10px; margin-top: 10px; }
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
        .canvas-area { grid-row: 2; grid-column: 2; background: #0f172a; position: relative; overflow: hidden; }
        #map { width: 100%; height: 100%; background: #0f172a; }

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
        .btn-action:hover { background: #4f46e5; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(99, 102, 241, 0.3); color: white; }

        .btn-close-custom { 
            width: 40px; height: 40px; border-radius: 50%; border: 1px solid var(--border); 
            background: transparent; color: var(--danger); display: flex; align-items: center; justify-content: center; 
            text-decoration: none; transition: 0.2s;
        }
        .btn-close-custom:hover { background: var(--danger); color: white; border-color: var(--danger); }
        
        #toast-container { position: absolute; bottom: 80px; left: 30px; z-index: 1100; pointer-events: none; }
        .toast-msg { background: var(--bg-panel); border: 1px solid var(--border); padding: 12px 20px; border-radius: 12px; margin-top: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); color: white; display: flex; align-items: center; gap: 10px; animation: slideIn 0.3s; }
        @keyframes slideIn { from { transform: translateX(-50px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* OVERLAY MÓVIL */
        .sidebar-overlay { 
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(0,0,0,0.5); z-index: 900; backdrop-filter: blur(2px); 
        }
        .mobile-bottom-bar { display: none; }
        .mobile-toggle-header { display: none; background: transparent; border: none; color: white; font-size: 1.2rem; }

        /* --- V8.2 MOBILE HYBRID LAYOUT --- */
        @media (max-width: 991px) {
            .app-container {
                grid-template-columns: 1fr;
                /* Header / Canvas / BottomNav */
                grid-template-rows: 60px 1fr 60px;
            }
            
            /* Header adjustments */
            .app-header { padding: 0 15px; }
            .brand-logo span, .file-info { display: none; }
            .badge-read { display: none; }
            .mobile-toggle-header { display: block; } 

            /* Botón Edit Plan en versión reducida (Círculo) */
            .btn-action { 
                width: 40px; height: 40px; padding: 0; border-radius: 50%; justify-content: center; 
            }
            /* Ocultar texto del botón edit con !important para forzar */
            .btn-action span { display: none !important; }

            /* Sidebar Izquierdo (Sheets) */
            .sidebar-left { 
                position: fixed; top: 0; left: 0; bottom: 0; 
                width: 280px; transform: translateX(-100%); 
                box-shadow: 10px 0 30px rgba(0,0,0,0.5);
                border-right: 1px solid rgba(255,255,255,0.1);
            }
            .sidebar-left.show { transform: translateX(0); }

            /* Sidebar Derecho (History) - Offcanvas desde derecha */
            .sidebar-right {
                position: fixed; top: 0; right: 0; bottom: 0;
                width: 300px; transform: translateX(100%);
                box-shadow: -10px 0 30px rgba(0,0,0,0.5);
                border-left: 1px solid rgba(255,255,255,0.1);
            }
            .sidebar-right.show { transform: translateX(0); }

            .sidebar-overlay.show { display: block; }
            
            .canvas-area { grid-row: 2; grid-column: 1; }
            
            /* Controles Flotantes ajustados al Bottom Bar */
            .floating-controls { 
                bottom: 70px; /* Ajustado para estar justo encima de la barra de 60px */
                right: 15px; 
                padding: 5px 12px;
            } 

            /* Barra Inferior de Navegación */
            .mobile-bottom-bar {
                grid-row: 3; grid-column: 1; background: var(--bg-panel);
                border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-around;
                z-index: 500;
            }
            .nav-icon-btn { color: var(--text-muted); background: none; border: none; font-size: 1.2rem; padding: 10px; width: 100%; }
            .nav-icon-btn.active { color: var(--primary); background: rgba(255,255,255,0.05); }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeAllSidebars()"></div>

<div class="app-container">
    
    <header class="app-header">
        <div class="d-flex align-items-center">
            <a href="<?= $backUrl ?>" class="text-white me-3 d-md-none"><i class="fas fa-chevron-left"></i></a>
            
            <button class="mobile-toggle-header me-2" onclick="toggleSidebar('left')">
                <i class="far fa-file-alt"></i>
            </button>

            <div class="brand-logo">
                <i class="fas fa-bolt text-warning"></i> <span class="d-none d-md-inline ms-2">Brightronix</span>
            </div>
            <div class="file-info d-none d-md-block">
                <small>Viewing Mode</small>
                <span><?= htmlspecialchars($file['filename']) ?></span>
            </div>
            <span class="badge badge-read ms-4 px-3 py-2 d-none d-md-inline"><i class="fas fa-eye me-1"></i> READ ONLY</span>
        </div>

        <div class="d-flex align-items-center gap-2">
            
            <?php if($userRole !== 'viewer'): ?>
                <a href="editor.php?id=<?= $id ?>" class="btn-action">
                    <i class="fas fa-pen"></i>
                    <span class="d-none d-lg-inline">Edit Plan</span>
                </a>
            <?php endif; ?>

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
        <div id="map"></div>
        
        <div class="floating-controls">
            <button class="float-btn text-warning" id="btn-pan" onclick="togglePan()" title="Toggle Pan/Hand"><i class="fas fa-hand-paper"></i></button>
            <div class="border-start border-secondary h-75 mx-2 opacity-50"></div>
            
            <button class="float-btn" onclick="changePage(-1)"><i class="fas fa-chevron-left"></i></button>
            <span class="small fw-bold">Page <span id="p-curr">1</span> / <span id="p-total">--</span></span>
            <button class="float-btn" onclick="changePage(1)"><i class="fas fa-chevron-right"></i></button>
            <div class="border-start border-secondary h-75 mx-2 opacity-50"></div>
            <span class="small text-accent fw-bold" id="zoom-disp">100%</span>
        </div>
    </main>

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
                <div class="text-center text-muted py-5 px-3">
                    <i class="fas fa-clipboard-check fa-3x mb-3 opacity-25"></i><br>
                    <p class="small">No reports have been generated for this file yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </aside>

    <div class="mobile-bottom-bar">
        <button class="nav-icon-btn" onclick="toggleSidebar('left')">
            <i class="far fa-file-alt"></i>
        </button>
        <button class="nav-icon-btn active">
            <i class="fas fa-eye"></i>
        </button>
        <button class="nav-icon-btn" onclick="toggleSidebar('right')">
            <i class="fas fa-history"></i>
        </button>
    </div>

</div>

<div id="toast-container"></div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@v7.4.0/ol.css">
<script src="https://cdn.jsdelivr.net/npm/ol@v7.4.0/dist/ol.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>

<script>
    // --- UI HELPERS (Mobile) ---
    function toggleSidebar(side) {
        closeAllSidebars();
        if(side === 'left') document.getElementById('sidebarLeft').classList.add('show');
        if(side === 'right') document.getElementById('sidebarRight').classList.add('show');
        document.getElementById('sidebarOverlay').classList.add('show');
    }
    
    function closeAllSidebars() {
        document.getElementById('sidebarLeft').classList.remove('show');
        document.getElementById('sidebarRight').classList.remove('show');
        document.getElementById('sidebarOverlay').classList.remove('show');
    }

    // --- SETUP ---
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';

    // --- OL EDITOR ENGINE (INLINED FOR DEMO, SHOULD BE IN assets/editor/ol-editor.js) ---
    class OlEditorEngine {
        constructor(targetId, options = {}) {
            this.targetId = targetId;
            this.map = null;
            this.imageLayer = null;
            this.initMap();
        }

        initMap() {
            // Capa vacía para futuras anotaciones
            const vectorSource = new ol.source.Vector({ wrapX: false });
            const vectorLayer = new ol.layer.Vector({ source: vectorSource });

            this.map = new ol.Map({
                target: this.targetId,
                layers: [vectorLayer],
                view: new ol.View({
                    projection: 'identity',
                    center: [0, 0],
                    zoom: 2
                }),
                controls: [], // Limpio
                interactions: ol.interaction.defaults.defaults({
                    pinchRotate: false, 
                    altShiftDragRotate: false
                })
            });
            
            // Listener para actualizar zoom display
            this.map.getView().on('change:resolution', () => {
                const zoom = this.map.getView().getZoom();
                // Aproximación simple de porcentaje basado en zoom level arbitrario
                // En OL zoom 2 es base, ajustamos visualmente
                const pct = Math.round(Math.pow(2, zoom - 2) * 100); 
                const disp = document.getElementById('zoom-disp');
                if(disp) disp.innerText = pct + '%';
            });
        }

        loadPageBackground(dataUrl, width, height) {
            if (!width || !height || !Number.isFinite(width) || !Number.isFinite(height)) {
                console.error('Invalid image dimensions', width, height);
                return;
            }
            if (!this.map) {
                console.error('Map not initialized');
                return;
            }
            
            // Usar la proyección 'identity' existente en lugar de crear una personalizada
            // Esto evita problemas con getExtent() en proyecciones personalizadas
            const extent = [0, 0, width, height];
            const identityProjection = ol.proj.get('identity');
            
            if (!identityProjection) {
                console.error('Identity projection not found');
                return;
            }

            try {
                const imageSource = new ol.source.ImageStatic({
                    url: dataUrl,
                    projection: identityProjection,
                    imageExtent: extent
                });

                if (this.imageLayer) this.map.removeLayer(this.imageLayer);
                
                this.imageLayer = new ol.layer.Image({ source: imageSource });
                this.map.getLayers().insertAt(0, this.imageLayer);
                imageSource.on('error', (e) => {
                    console.error('Image source error', e);
                });

                // Obtener la vista actual en lugar de crear una nueva
                const currentView = this.map.getView();
                const center = ol.extent.getCenter(extent);
                
                // Calcular zoom manualmente basado en el tamaño del mapa y el extent
                // Esto evita el problema con getExtent() en fit()
                const updateView = () => {
                    try {
                        const mapSize = this.map.getSize();
                        if (mapSize && mapSize[0] > 0 && mapSize[1] > 0) {
                            // Calcular el zoom necesario para mostrar el extent completo con padding
                            const padding = 40; // 20px en cada lado
                            const mapWidth = mapSize[0] - padding;
                            const mapHeight = mapSize[1] - padding;
                            
                            const extentWidth = extent[2] - extent[0];
                            const extentHeight = extent[3] - extent[1];
                            
                            const scaleX = mapWidth / extentWidth;
                            const scaleY = mapHeight / extentHeight;
                            const scale = Math.min(scaleX, scaleY);
                            
                            // Calcular zoom basado en la escala
                            // En OpenLayers con proyección identity, zoom funciona directamente con la escala
                            const calculatedZoom = Math.log2(scale);
                            
                            // Limitar el zoom dentro de los límites permitidos
                            const finalZoom = Math.max(0.5, Math.min(8, calculatedZoom));
                            
                            currentView.setCenter(center);
                            currentView.setZoom(finalZoom);
                        } else {
                            // Si el mapa no tiene tamaño aún, usar valores por defecto
                            currentView.setCenter(center);
                            currentView.setZoom(2);
                        }
                    } catch (e) {
                        console.error('Zoom calculation failed', e);
                        // Fallback: usar valores por defecto
                        try {
                            currentView.setCenter(center);
                            currentView.setZoom(2);
                        } catch (fallbackError) {
                            console.error('Fallback center/zoom failed', fallbackError);
                        }
                    }
                };
                
                // Actualizar el tamaño del mapa primero
                this.map.updateSize();
                
                // Usar requestAnimationFrame para asegurar que el mapa esté completamente renderizado
                requestAnimationFrame(() => {
                    updateView();
                });
                
            } catch (mainError) {
                console.error('Error in loadPageBackground:', mainError);
                // Intentar cargar la imagen como último recurso
                try {
                    const center = ol.extent.getCenter(extent);
                    const currentView = this.map.getView();
                    if (currentView) {
                        currentView.setCenter(center);
                        currentView.setZoom(2);
                    }
                } catch (finalError) {
                    console.error('Final fallback failed:', finalError);
                }
            }
        }
        
        clearAnnotations() {
            // TODO: Limpiar vector source
        }
        
        // Helpers para controles externos
        zoomIn() { 
            const v = this.map.getView(); 
            v.animate({ zoom: v.getZoom() + 0.5, duration: 200 }); 
        }
        zoomOut() { 
            const v = this.map.getView(); 
            v.animate({ zoom: v.getZoom() - 0.5, duration: 200 }); 
        }
        togglePan(enable) {
            // En OL el pan es default, aquí podríamos desactivar interacciones si fuera modo dibujo
            // Por ahora solo cambiamos cursor
            const target = document.getElementById(this.targetId);
            target.style.cursor = enable ? 'grab' : 'default';
        }
    }
    // ---------------------------------------------------------

    // VARIABLES
    const fileUrlRaw = "<?= $filePath ?>";
    const fileUrl = encodeURI(fileUrlRaw);
    const fileExt = "<?= $fileExt ?>";
    let allAnnotations = <?= $annotations ?>; 
    if(typeof allAnnotations !== 'object' || allAnnotations === null) allAnnotations = {};

    // Inicializar Motor OL
    const editor = new OlEditorEngine('map', { readOnly: true });
    
    let pdfDoc = null, pageNum = 1, pdfScale = 2.0; // Scale alto para nitidez en canvas
    
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
        if(editor.map) editor.map.updateSize(); 
    }
    window.addEventListener('resize', resize);
    resize(); 

    // LOAD DOCUMENT
    const imageExts = ['jpg','jpeg','png','gif','webp','bmp','tiff','tif','heic'];

    if(fileExt === 'pdf') {
        pdfjsLib.getDocument(fileUrl).promise.then(pdf => {
            pdfDoc = pdf; document.getElementById('p-total').textContent = pdf.numPages;
            renderPageList(pdf.numPages); renderPage(pageNum);
        }).catch(err => {
            console.error(err);
            showToast("Error loading PDF", "error");
        });
    } else if (fileExt === 'heic') {
        document.getElementById('p-total').textContent = '1'; renderPageList(1);
        fetch(fileUrl).then(res => res.blob()).then(blob => heic2any({ blob, toType: "image/jpeg" })).then(conversionResult => {
            const blob = Array.isArray(conversionResult) ? conversionResult[0] : conversionResult;
            const url = URL.createObjectURL(blob);
            loadSingleImage(url);
        }).catch(e => console.error(e));
    } else if (imageExts.includes(fileExt)) {
        document.getElementById('p-total').textContent = '1'; renderPageList(1);
        loadSingleImage(fileUrl);
    } else {
        showToast("Unsupported file type", "error");
    }

    function loadSingleImage(url) {
        // Obtener dimensiones reales de imagen
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = function() {
            editor.loadPageBackground(url, this.width, this.height);
            loadPageAnnotations(1);
        }
        img.onerror = function() {
            fetch(url)
                .then(r => r.blob())
                .then(b => {
                    const objUrl = URL.createObjectURL(b);
                    const img2 = new Image();
                    img2.onload = function() {
                        editor.loadPageBackground(objUrl, this.width, this.height);
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
            const tempC = document.createElement('canvas'); tempC.width = viewport.width; tempC.height = viewport.height;
            await page.render({ canvasContext: tempC.getContext('2d'), viewport }).promise;
            
            // Convertir a imagen para OL
            const imgData = tempC.toDataURL('image/jpeg', 0.8);
            editor.loadPageBackground(imgData, viewport.width, viewport.height);
            loadPageAnnotations(num);
        }
        updatePageListUI(num);
    }
    function changePage(offset) {
        const max = pdfDoc ? pdfDoc.numPages : 1; const newPage = pageNum + offset;
        if(newPage < 1 || newPage > max) return; jumpToPage(newPage);
    }
    function jumpToPage(targetPage) {
        editor.clearAnnotations(); pageNum = targetPage; if(pdfDoc) renderPage(pageNum); else loadPageAnnotations(pageNum);
    }
    
    function loadPageAnnotations(pg) {
        if(allAnnotations[pg]) {
            console.log("TODO: Implementar adaptador Fabric->OL para página " + pg);
            // Aquí irá la lógica de la Fase 2/8
        }
    }

    // --- V8.0: PANNING LOGIC (MANUAL TOGGLE) ---
    let isPanningMode = false;
    
    function togglePan() {
        isPanningMode = !isPanningMode;
        const btn = document.getElementById('btn-pan');
        if(isPanningMode) {
            btn.classList.add('text-white', 'bg-primary');
            btn.classList.remove('text-warning');
            editor.togglePan(true);
        } else {
            btn.classList.remove('text-white', 'bg-primary');
            btn.classList.add('text-warning');
            editor.togglePan(false);
        }
    }

</script>
</body>
</html>
