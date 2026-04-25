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
$stmtProj = $pdo->prepare("SELECT name FROM projects WHERE id=?");
$stmtProj->execute([$projectId]);
$projectName = $stmtProj->fetchColumn() ?: 'Electroplan Project';
$folderId = $file['folder_id'];
$backUrl = "project_dashboard.php?id={$projectId}";
$backUrl .= $folderId ? "&view=files&folder_id={$folderId}" : "&view=summary";

$stmtRep = $pdo->prepare("SELECT annotations_json FROM file_reports WHERE file_id=? ORDER BY created_at DESC LIMIT 1");
$stmtRep->execute([$id]);
$lastReport = $stmtRep->fetchColumn();
$annotations = ($lastReport && $lastReport !== 'null') ? $lastReport : '{}';

$fileExt = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
if ($fileExt === '' && !empty($file['file_type'])) {
    $ft = strtolower($file['file_type']);
    if (strpos($ft, '/') !== false) {
        $fileExt = substr($ft, strrpos($ft, '/') + 1);
    } else {
        $fileExt = $ft;
    }
}
$filePath = str_replace('\\', '/', (string)($file['filepath'] ?? ''));
if ($filePath !== '') {
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
    <title>Editor V9.6 | <?= htmlspecialchars($file['filename']) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js"></script>

    <script>
    if (!HTMLCanvasElement.prototype.toBlob) {
        Object.defineProperty(HTMLCanvasElement.prototype, 'toBlob', {
            value: function(callback, type, quality) {
                const dataURL = this.toDataURL(type, quality);
                const binStr = atob(dataURL.split(',')[1]);
                const arr = new Uint8Array(binStr.length);
                for (let i = 0; i < binStr.length; i++) arr[i] = binStr.charCodeAt(i);
                callback(new Blob([arr], { type: type || 'image/png' }));
            }
        });
    }
    </script>

    <link rel="stylesheet" href="../assets/editor/editor.css">

    
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
            
            <button id="btn-toggle-left" class="toggle-icon-btn me-2" onclick="toggleSheets()" title="Show Sheets">
                <i class="far fa-file-alt"></i>
            </button>

            <a href="<?= $backUrl ?>" class="brand-logo">
                <div class="logo-full" role="img" aria-label="Brightronix Logo"></div>
                <div class="app-subtitle">Electro Plan</div>
            </a>
            
            <div class="file-info d-none d-md-flex">
                <small>Editing File</small>
                <span><?= htmlspecialchars($file['filename']) ?></span>
            </div>
        </div>

        <div class="properties-bar">
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
                <button type="button" class="btn btn-sm btn-outline-light ms-2" id="btn-draw-eraser" onclick="toggleDrawEraser()" title="Erase strokes">
                    <i class="fas fa-eraser"></i>
                </button>
            </div>
            
            <div id="prop-text" class="prop-section">
                <span class="prop-label">Color</span>
                <div class="d-flex gap-2 mx-2" id="text-color-container">
                    <div class="color-dot" data-col="#ef4444" style="background:#ef4444" onclick="setTextFixedColor('#ef4444', this)"></div>
                    <div class="color-dot" data-col="#3b82f6" style="background:#3b82f6" onclick="setTextFixedColor('#3b82f6', this)"></div>
                    <div class="color-dot" data-col="#22c55e" style="background:#22c55e" onclick="setTextFixedColor('#22c55e', this)"></div>
                    <div class="color-dot" data-col="#eab308" style="background:#eab308" onclick="setTextFixedColor('#eab308', this)"></div>
                    <!-- FIX-BUG2: colores adicionales solicitados -->
                    <div class="color-dot" data-col="#ec4899" style="background:#ec4899" onclick="setTextFixedColor('#ec4899', this)"></div>
                    <div class="color-dot" data-col="#f97316" style="background:#f97316" onclick="setTextFixedColor('#f97316', this)"></div>
                    <div class="color-dot" data-col="#8b5cf6" style="background:#8b5cf6" onclick="setTextFixedColor('#8b5cf6', this)"></div>
                    <div class="color-dot" data-col="#ffffff" style="background:#ffffff; border:1px solid #64748b" onclick="setTextFixedColor('#ffffff', this)"></div>
                </div>
                <div class="border-start border-secondary mx-2 h-50"></div>
                <span class="prop-label">Size</span>
                <input type="number" id="text-size-input" class="form-control py-0 px-2 text-center" value="60" min="8" max="100" style="width:60px; height:30px;" onchange="updateTextProp('fontSize', parseInt(this.value))">
            </div>

            <div id="prop-cloud" class="prop-section">
                <span class="prop-label"><i class="fas fa-cloud me-1"></i>Cloud stroke</span>
                <select id="cloud-stroke" class="form-select form-select-sm ms-2" style="width:180px; height:30px;" onchange="setCloudStrokeWidth(this.value)">
                    <option value="1.5">Fina (0.5px)</option>
                    <option value="3" selected>Normal (1px)</option>
                    <option value="6">Gruesa (2px)</option>
                    <option value="9">Extra gruesa (3px)</option>
                </select>
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
            <button class="btn btn-outline-light rounded-circle d-inline-flex align-items-center justify-content-center"
                    id="btn-theme-toggle"
                    style="width:35px;height:35px;border-color:var(--border);"
                    onclick="toggleTheme()"
                    title="Toggle Day/Night">
                <i class="fas fa-sun"></i>
            </button>
            <button class="btn btn-outline-danger rounded-circle d-inline-flex align-items-center justify-content-center" 
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
        <div id="konva-container"></div>
        
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
        <button class="tool-btn" id="btn-cloud" onclick="addCloud()" title="Cloud Mark"><i class="fas fa-cloud"></i></button>
        
        <button class="tool-btn" id="btn-stamp" onclick="toggleStampMenu()" title="Stamps"><i class="fas fa-stamp"></i></button>
        
        <div class="tool-separator"></div>
        <button class="tool-btn" id="btn-measure" onclick="setMode('measure')" title="Ruler"><i class="fas fa-ruler"></i></button>
        <button class="tool-btn text-warning" id="btn-cal" onclick="setMode('cal')" title="Calibrate"><i class="fas fa-ruler-combined"></i></button>
    </aside>

    <div class="mobile-bottom-bar">
        <button id="mobile-toggle-left" class="nav-icon-btn" onclick="toggleSheets()">
            <i class="far fa-file-alt"></i>
        </button>
        <button id="mobile-toggle-center" class="nav-icon-btn active" onclick="closeAllOverlays(); closeTools();">
            <i class="fas fa-pencil-alt"></i>
        </button>
        <button id="mobile-toggle-tools" class="nav-icon-btn" onclick="toggleMobileTools()">
            <i class="fas fa-tools"></i>
        </button>
    </div>

    <!-- MOBILE PROPS PANEL -->
    <div id="mobile-props-panel" class="mobile-props-panel">
        <div class="mobile-prop-section" id="m-prop-draw">
            <div class="color-dot active" style="background:#ef4444" onclick="setPenColor('#ef4444', this)"></div>
            <div class="color-dot" style="background:#3b82f6" onclick="setPenColor('#3b82f6', this)"></div>
            <div class="color-dot" style="background:#22c55e" onclick="setPenColor('#22c55e', this)"></div>
            <div class="color-dot" style="background:#eab308" onclick="setPenColor('#eab308', this)"></div>
            <div class="vr"></div>
            <i class="fas fa-pen text-muted" style="font-size:0.75rem; flex-shrink:0;"></i>
            <input type="range" class="form-range" min="1" max="10" value="3" oninput="setPenWidth(this.value)" id="m-pen-width">
            <div class="vr"></div>
            <button class="btn btn-sm btn-outline-light" id="m-btn-draw-eraser" onclick="toggleDrawEraser()" title="Borrador">
                <i class="fas fa-eraser"></i>
            </button>
        </div>

        <div class="mobile-prop-section" id="m-prop-text">
            <div class="color-dot" data-col="#ef4444" style="background:#ef4444" onclick="setTextFixedColor('#ef4444', this)"></div>
            <div class="color-dot" data-col="#3b82f6" style="background:#3b82f6" onclick="setTextFixedColor('#3b82f6', this)"></div>
            <div class="color-dot" data-col="#22c55e" style="background:#22c55e" onclick="setTextFixedColor('#22c55e', this)"></div>
            <div class="color-dot" data-col="#eab308" style="background:#eab308" onclick="setTextFixedColor('#eab308', this)"></div>
            <div class="color-dot" data-col="#ec4899" style="background:#ec4899" onclick="setTextFixedColor('#ec4899', this)"></div>
            <div class="color-dot" data-col="#f97316" style="background:#f97316" onclick="setTextFixedColor('#f97316', this)"></div>
            <div class="color-dot" data-col="#8b5cf6" style="background:#8b5cf6" onclick="setTextFixedColor('#8b5cf6', this)"></div>
            <div class="color-dot" data-col="#ffffff" style="background:#ffffff; border-color:#64748b" onclick="setTextFixedColor('#ffffff', this)"></div>
            <div class="vr"></div>
            <i class="fas fa-text-height text-muted" style="font-size:0.75rem; flex-shrink:0;"></i>
            <input type="number" class="form-control" value="60" min="8" max="100" id="m-text-size-input" onchange="updateTextProp('fontSize', parseInt(this.value))">
        </div>

        <div class="mobile-prop-section" id="m-prop-cloud">
            <div class="d-flex align-items-center gap-3">
                <span class="mobile-prop-label"><i class="fas fa-cloud me-1"></i>Trazo</span>
                <select class="form-select form-select-sm" style="width:160px; height:34px;" onchange="setCloudStrokeWidth(this.value)" id="m-cloud-stroke">
                    <option value="1.5">Fina</option>
                    <option value="3" selected>Normal</option>
                    <option value="6">Gruesa</option>
                    <option value="9">Extra gruesa</option>
                </select>
            </div>
        </div>

        <div class="mobile-prop-section" id="m-prop-measure">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-ruler text-success"></i>
                <span class="text-white small">Arrastra los nodos para ajustar</span>
            </div>
        </div>

        <div class="mobile-prop-section" id="m-prop-cal">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-ruler-combined text-warning"></i>
                <span class="text-white small">Dibuja una línea conocida en el plano</span>
                <button class="btn btn-sm btn-warning ms-2" onclick="openMobileCalModal()">
                    <i class="fas fa-cog me-1"></i>Config
                </button>
            </div>
        </div>

        <div class="mobile-prop-section" id="m-prop-smart" style="display:none"></div>
    </div>

</div>

<div class="modal fade" id="mobileCalModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content p-2">
            <div class="modal-header">
                <h6 class="modal-title fw-bold"><i class="fas fa-ruler-combined text-warning me-2"></i>Plan Scale</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label small mb-1">Mode</label>
                <select id="mobile-cal-mode" class="form-select form-select-sm mb-2" onchange="mobileCalModeChanged(this.value)">
                    <option value="manual">Manual</option>
                    <option value="preset">Preset</option>
                </select>

                <div id="mobile-cal-preset-wrap" class="mb-2">
                    <label class="form-label small mb-1">Preset scale</label>
                    <select id="mobile-cal-preset" class="form-select form-select-sm" onchange="mobileCalPresetChanged(this.value)">
                        <option value="">Preset scale...</option>
                    </select>
                </div>

                <div id="mobile-cal-manual-wrap" class="mb-2 d-none">
                    <label class="form-label small mb-1">Distance (ft)</label>
                    <input type="number" id="mobile-cal-val" class="form-control form-control-sm" min="0.1" step="0.1" placeholder="e.g. 10">
                    <div class="small text-muted mt-1">Draw a known line on the plan, then apply.</div>
                </div>

                <div class="small text-muted">Current scale: <span id="mobile-cal-current">--</span></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-sm btn-success" onclick="mobileApplyManualCal()">Apply</button>
            </div>
        </div>
    </div>
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
                    <label class="form-label small fw-bold" style="color: var(--text-main, #fff);">Technician Name</label>
                    <input type="text" id="rep-name" class="form-control" value="<?= htmlspecialchars($_SESSION['username']) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold" style="color: var(--text-main, #fff);">Role / Title</label>
                    <input type="text" id="rep-role" class="form-control" value="<?= htmlspecialchars($_SESSION['role']) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold" style="color: var(--text-main, #fff);">Activity Description</label>
                    <textarea id="rep-desc" class="form-control" rows="3" placeholder="e.g. Added conduit path to room 102..."></textarea>
                </div>

                <div class="mb-2">
                    <label class="form-label small fw-bold" style="color: var(--text-main, #fff);">Attachments</label>
                    <div id="rep-attach-dropzone" class="border rounded-3 p-3 text-center" style="border-style:dashed !important; border-color:#475569 !important;">
                        <div class="small text-muted mb-2"><i class="fas fa-paperclip me-1"></i>Drag &amp; drop files here or</div>
                        <button type="button" class="btn btn-sm btn-outline-light" onclick="document.getElementById('rep-attachments').click()">Browse files</button>
                        <input type="file" id="rep-attachments" class="d-none" multiple>
                        <div class="small text-muted mt-2">Accepted: Images, PDF, DOC, XLS</div>
                        <div class="small text-muted">Max: 10MB per file · Up to 5 files</div>
                    </div>
                    <div id="rep-attachments-preview" class="mt-2 d-flex flex-column gap-2"></div>
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

<!-- Fabric eliminado: editor Konva puro -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/konva@9.3.3/konva.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // --- UI HELPERS ---
    
    // Toggle Sidebar Izquierda (Sheets)
    function toggleSheets() {
        const sbLeft = document.getElementById('sidebarLeft');
        const btnLeft = document.getElementById('btn-toggle-left');
        const mobBtnLeft = document.getElementById('mobile-toggle-left');
        const mobBtnCenter = document.getElementById('mobile-toggle-center');

        sbLeft.classList.toggle('show');
        const isShow = sbLeft.classList.contains('show');

        if (btnLeft) btnLeft.classList.toggle('active', isShow);
        if (mobBtnLeft) mobBtnLeft.classList.toggle('active', isShow);

        if (mobBtnCenter && window.innerWidth <= 991) {
            if (isShow) mobBtnCenter.classList.remove('active');
            else if (!document.getElementById('sidebarRight').classList.contains('show-mobile')) mobBtnCenter.classList.add('active');
        }

        if (window.innerWidth <= 991 && isShow) {
            closeTools();
        }

        updateOverlay();
    }

    // Toggle Herramientas (Mobile)
    function toggleMobileTools() {
        const sbRight = document.getElementById('sidebarRight');
        sbRight.classList.toggle('show-mobile');

        const mobBtnTools = document.getElementById('mobile-toggle-tools');
        const mobBtnCenter = document.getElementById('mobile-toggle-center');
        const isShow = sbRight.classList.contains('show-mobile');

        if (mobBtnTools) mobBtnTools.classList.toggle('active', isShow);
        if (mobBtnCenter) {
            if (isShow) mobBtnCenter.classList.remove('active');
            else if (!document.getElementById('sidebarLeft').classList.contains('show')) mobBtnCenter.classList.add('active');
        }

        if (isShow) {
            const sbLeft = document.getElementById('sidebarLeft');
            if (sbLeft && sbLeft.classList.contains('show')) {
                sbLeft.classList.remove('show');
                const mobBtnLeft = document.getElementById('mobile-toggle-left');
                if (mobBtnLeft) mobBtnLeft.classList.remove('active');
                updateOverlay();
            }
        }
    }

    function closeTools() {
        const sbRight = document.getElementById('sidebarRight');
        if (sbRight) sbRight.classList.remove('show-mobile');

        const mobBtnTools = document.getElementById('mobile-toggle-tools');
        if (mobBtnTools) mobBtnTools.classList.remove('active');

        const mobBtnCenter = document.getElementById('mobile-toggle-center');
        const sbLeft = document.getElementById('sidebarLeft');
        if (mobBtnCenter && sbLeft && !sbLeft.classList.contains('show')) {
            mobBtnCenter.classList.add('active');
        }
    }

    function applyTheme(theme) {
        const next = (theme === 'light') ? 'light' : 'dark';
        document.body.classList.toggle('theme-light', next === 'light');
        const btn = document.getElementById('btn-theme-toggle');
        const icon = btn ? btn.querySelector('i') : null;
        if (btn) btn.title = (next === 'light') ? 'Switch to Night Mode' : 'Switch to Day Mode';
        if (icon) icon.className = (next === 'light') ? 'fas fa-moon' : 'fas fa-sun';
        try { localStorage.setItem('app_theme', next); } catch (e) {}
    }

    function toggleTheme() {
        const isLight = document.body.classList.contains('theme-light');
        applyTheme(isLight ? 'dark' : 'light');
    }

    function initTheme() {
        let saved = null;
        try { saved = localStorage.getItem('app_theme') || localStorage.getItem('editor_theme'); } catch (e) {}
        applyTheme(saved === 'light' ? 'light' : 'dark');
    }

    function updateOverlay() {
        const overlay = document.getElementById('sidebarOverlay');
        const sheetsOpen = document.getElementById('sidebarLeft').classList.contains('show');
        if(sheetsOpen) overlay.classList.add('show'); else overlay.classList.remove('show');
    }

    function closeAllOverlays() {
        document.getElementById('sidebarLeft').classList.remove('show');
        updateOverlay();

        const btnLeft = document.getElementById('btn-toggle-left');
        if (btnLeft) btnLeft.classList.remove('active');

        const mobBtnLeft = document.getElementById('mobile-toggle-left');
        if (mobBtnLeft) mobBtnLeft.classList.remove('active');

        const mobBtnCenter = document.getElementById('mobile-toggle-center');
        const sbRight = document.getElementById('sidebarRight');
        if (mobBtnCenter && sbRight && !sbRight.classList.contains('show-mobile')) {
            mobBtnCenter.classList.add('active');
        }
    }

    function openMobileCalModal() {
        if (window.innerWidth > 599) return;
        const desktopMode = document.getElementById('cal-mode');
        const desktopPreset = document.getElementById('cal-preset');
        const mMode = document.getElementById('mobile-cal-mode');
        const mPreset = document.getElementById('mobile-cal-preset');
        const mCurrent = document.getElementById('mobile-cal-current');

        if (desktopMode && mMode) mMode.value = desktopMode.value || 'preset';

        if (desktopPreset && mPreset) {
            mPreset.innerHTML = desktopPreset.innerHTML;
            mPreset.value = desktopPreset.value || '';
        }

        const scaleText = (document.getElementById('scale-display')?.textContent || '--').trim();
        if (mCurrent) mCurrent.textContent = scaleText || '--';

        mobileCalModeChanged(mMode ? mMode.value : 'preset');
        new bootstrap.Modal(document.getElementById('mobileCalModal')).show();
    }

    function mobileCalModeChanged(mode) {
        const desktopMode = document.getElementById('cal-mode');
        if (desktopMode) desktopMode.value = mode;
        setCalMode(mode);

        document.getElementById('mobile-cal-preset-wrap')?.classList.toggle('d-none', mode !== 'preset');
        document.getElementById('mobile-cal-manual-wrap')?.classList.toggle('d-none', mode !== 'manual');
    }

    function mobileCalPresetChanged(value) {
        const desktopPreset = document.getElementById('cal-preset');
        if (desktopPreset) desktopPreset.value = value;
        applyScalePreset(value);
        const scaleText = (document.getElementById('scale-display')?.textContent || '--').trim();
        const mCurrent = document.getElementById('mobile-cal-current');
        if (mCurrent) mCurrent.textContent = scaleText || '--';
    }

    function mobileApplyManualCal() {
        const v = document.getElementById('mobile-cal-val')?.value;
        const desktopVal = document.getElementById('cal-val');
        if (desktopVal) desktopVal.value = v || '';
        finishCal(true);
        const scaleText = (document.getElementById('scale-display')?.textContent || '--').trim();
        const mCurrent = document.getElementById('mobile-cal-current');
        if (mCurrent) mCurrent.textContent = scaleText || '--';
    }

    // --- SETUP ---
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';

    // SERVER VARIABLES
    const fileUrl = "<?= $filePath ?>";
    const fileExt = "<?= $fileExt ?>"; 
    const fileId = <?= $id ?>;
    let allAnnotations = <?= $annotations ?>;
    if(typeof allAnnotations !== 'object' || allAnnotations === null) allAnnotations = {};

    // Persistencia explícita: solo se guarda al presionar Save (reporte).
    // Cualquier edición sin Save se mantiene únicamente en memoria y se pierde al refrescar/salir.
    const draftAnnotationsKey = `ep_annotations_draft_file_${fileId}`;
    try { localStorage.removeItem(draftAnnotationsKey); } catch (e) {}


    // KONVA PURE INIT
    const viewport = { x: 0, y: 0, scale: 1 };
    let pdfImageSize = { width: 0, height: 0 };
    const useKonvaRuler = true;
    const konvaContainer = document.getElementById('konva-container');
    if (konvaContainer) {
        konvaContainer.style.width = '100%';
        konvaContainer.style.height = '100%';
    }
    let konvaStage = null;
    let bgLayer = null;
    let konvaLayer = null;
    let bgImage = null;
    let konvaRulers = [];
    let konvaNotes = [];
    let konvaClouds = [];
    // MIGRATED: nuevas herramientas Konva
    let konvaStamps = [];
    let konvaDrawPaths = [];
    let isKonvaDrawing = false;
    let konvaCurrentPath = null;
    let konvaCurrentPoints = [];
    let drawColor = '#ef4444';
    let drawWidth = 3;
    let drawEraserMode = false;
    let konvaIsErasing = false;
    let konvaErasedInGesture = false;
    const konvaRulersByPage = {};
    let konvaTransformer = null;
    let konvaEditingTextarea = null;
    let konvaSelectedNote = null;
    let noteEditViewportBefore = null;
    let noteEditDidAutoZoom = false;
    let konvaSelectedNode = null;
    let konvaDrawing = null;
    let konvaIsPanning = false;
    const isMobileViewport = window.innerWidth <= 768;
    const dpr = Math.min(window.devicePixelRatio || 1, 1.6);
    let pdfDoc = null, pageNum = 1, pdfScale = (isMobileViewport ? 1.35 : 1.8) * dpr;
    const LOW_RES_SCALE = (isMobileViewport ? 0.72 : 0.95) * Math.min(dpr, 1.25);
    const PREFETCH_DISTANCE = isMobileViewport ? 1 : 2;
    const MAX_HIGH_CACHE = isMobileViewport ? 3 : 5;
    const MAX_LOW_CACHE = isMobileViewport ? 5 : 8;
    const pageCache = new Map();
    const highOrder = [];
    const lowOrder = [];
    let renderToken = 0;
    const tempRenderCanvas = document.createElement('canvas');
    const tempRenderCtx = tempRenderCanvas.getContext('2d', { alpha: false, desynchronized: true });
    let prefetchBusy = false;
    const prefetchQueue = [];

    // Compat shim temporal para llamadas heredadas a `canvas`
    const canvas = {
        backgroundImage: null,
        viewportTransform: [1, 0, 0, 1, 0, 0],
        setWidth(w) { if (konvaStage) konvaStage.width(w); },
        setHeight(h) { if (konvaStage) konvaStage.height(h); },
        getZoom() { return konvaStage ? konvaStage.scaleX() : viewport.scale; },
        zoomToPoint(point, zoom) { zoomToPoint(point.x, point.y, zoom); },
        setViewportTransform(vpt) {
            this.viewportTransform = vpt;
            if (!konvaStage) return;
            konvaStage.scale({ x: vpt[0], y: vpt[3] });
            konvaStage.position({ x: vpt[4], y: vpt[5] });
            konvaStage.batchDraw();
        },
        requestRenderAll() { if (konvaStage) konvaStage.batchDraw(); },
        renderAll() { if (konvaStage) konvaStage.batchDraw(); },
        clear() { if (bgLayer) bgLayer.destroyChildren(); },
        discardActiveObject() {},
        getVpCenter() { return getViewportCenter(); },
        setBackgroundImage() {}
    };
    
    // STATES
    let pixelsPerFoot = 0;
    let currentMode = 'smart';
    let pendingPlacementTool = null;
    let pendingPlacementStart = null;
    let pendingPlacementPreview = null;
    // MIGRATED: calibración visual en Konva
    let konvaCalLine = null;
    let konvaCalFinished = null;
    let konvaCalPoints = null;
    let calLineObject = null; 
    let calMode = 'preset';
    let cloudStrokeWidth = 3; // default actual behavior

    // Calibration Persistence (solo en memoria de sesión; no localStorage)
    const calibrationByPage = {};

    function loadCalibrationForPage(showNotice) {
        const saved = calibrationByPage[pageNum];
        if (saved && !isNaN(parseFloat(saved.data))) {
            pixelsPerFoot = parseFloat(saved.data);
            if (showNotice) setTimeout(() => showToast("Saved calibration loaded", "success"), 800);
        } else {
            pixelsPerFoot = 0;
        }
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
        const savedLabel = calibrationByPage[pageNum]?.label || '';
        setScaleDisplay(savedLabel);
    }

    function getActiveScaleLabel() {
        const el = document.getElementById('scale-display');
        return (el?.textContent || '').trim();
    }

    function gcd(a, b) {
        let x = Math.abs(a);
        let y = Math.abs(b);
        while (y) {
            const t = y;
            y = x % y;
            x = t;
        }
        return x || 1;
    }

    function getArchitecturalInchStep(scaleLabel) {
        const parsed = parseScaleLabel(scaleLabel || '');
        if (!parsed) return 1 / 16;

        // Civil scales (ej. 1" = 10') se muestran en pies enteros
        if (parsed.feet > 1) return null;

        const inchesPerFoot = parsed.inches;
        if (inchesPerFoot <= (1 / 8)) return 1;      // 1/8" o menor -> 1"
        if (inchesPerFoot <= (1 / 4)) return 1 / 2;  // 3/16", 1/4" -> 1/2"
        if (inchesPerFoot <= (1 / 2)) return 1 / 4;  // 3/8", 1/2" -> 1/4"
        if (inchesPerFoot < 1) return 1 / 8;         // 3/4" -> 1/8"
        return 1 / 16;                                // 1" o mayor -> 1/16"
    }

    function formatFeetForDisplay(feetDecimal) {
        if (!isFinite(feetDecimal)) return '--';

        const step = getArchitecturalInchStep(getActiveScaleLabel());

        // Civil: solo pies enteros redondeados
        if (step === null) {
            return `${Math.round(feetDecimal)}'`;
        }

        let feetWhole = Math.floor(feetDecimal);
        let inches = (feetDecimal - feetWhole) * 12;
        inches = Math.round(inches / step) * step;

        if (inches >= 12 - 1e-9) {
            feetWhole += 1;
            inches = 0;
        }

        let wholeInches = Math.floor(inches + 1e-9);
        const frac = inches - wholeInches;

        if (frac < 1e-9) {
            return `${feetWhole}' ${wholeInches}"`;
        }

        let den = Math.round(1 / step);
        let num = Math.round(frac * den);

        if (num === den) {
            wholeInches += 1;
            num = 0;
        }
        if (wholeInches >= 12) {
            feetWhole += 1;
            wholeInches = 0;
        }
        if (num === 0) {
            return `${feetWhole}' ${wholeInches}"`;
        }

        const factor = gcd(num, den);
        num /= factor;
        den /= factor;

        return `${feetWhole}' ${wholeInches} ${num}/${den}"`;
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
        const page = await pdfDoc.getPage(pageNum);
        const viewport = page.getViewport({ scale: 1 });
        const bgWidth = pdfImageSize.width || (viewport.width * pdfScale);
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
        calibrationByPage[pageNum] = { data: pixelsPerFoot, label: preset.label };
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
        konvaRulers.forEach(r => updateKonvaLabel(r));
        syncKonvaToFabric();
    }

    function syncCloudStrokeControl(value = cloudStrokeWidth) {
        const ctrl = document.getElementById('cloud-stroke');
        if (ctrl) ctrl.value = String(value);
        syncCloudStrokeSelect(String(value));
    }

    function setCloudStrokeWidth(value) {
        const next = parseFloat(value);
        if (!isFinite(next) || next <= 0) return;
        cloudStrokeWidth = next;
        syncCloudStrokeControl(cloudStrokeWidth);

        if (konvaSelectedNode?.type === 'cloud' && konvaSelectedNode.ref?.shape) {
            konvaSelectedNode.ref.shape.strokeWidth(cloudStrokeWidth);
            if (konvaLayer) konvaLayer.batchDraw();
            saveCurrentPageAnnotations();
        }
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
    initTheme();
    populateScalePresets();
    setCalMode(calMode);
    loadCalibrationForPage(true);
    keepScaleDisplayVisible();
    syncCloudStrokeControl();

    // HISTORY
    const MAX_HISTORY = 21;
    let undoStack = [];
    let historyIndex = -1;  
    let historyProcessing = false; 

    window.addEventListener('contextmenu', e => e.preventDefault());

    function resize() {
        const w = document.getElementById('canvas-wrapper');
        if(w) { canvas.setWidth(w.clientWidth); canvas.setHeight(w.clientHeight); }
        if (konvaStage && w) {
            konvaStage.width(w.clientWidth);
            konvaStage.height(w.clientHeight);
            konvaStage.draw();
        }
    }
    window.addEventListener('resize', resize);
    setTimeout(resize, 100);
    initKonvaRuler();

    function setKonvaActive(active) {
        if (!konvaStage || !konvaStage.container()) return;
        konvaStage.container().style.pointerEvents = 'auto';
        if (active) updateRulerScales();
    }

    function getViewport() {
        if (!konvaStage) return { scaleX: 1, scaleY: 1, translateX: 0, translateY: 0 };
        return {
            scaleX: konvaStage.scaleX(),
            scaleY: konvaStage.scaleY(),
            translateX: konvaStage.x(),
            translateY: konvaStage.y()
        };
    }

    function zoomToPoint(screenX, screenY, newScale) {
        if (!konvaStage) return;
        newScale = Math.min(10, Math.max(0.10, newScale));
        const oldScale = konvaStage.scaleX() || 1;
        const stageX = konvaStage.x();
        const stageY = konvaStage.y();
        const worldX = (screenX - stageX) / oldScale;
        const worldY = (screenY - stageY) / oldScale;
        const newX = screenX - worldX * newScale;
        const newY = screenY - worldY * newScale;
        konvaStage.scale({ x: newScale, y: newScale });
        konvaStage.position({ x: newX, y: newY });
        konvaStage.batchDraw();
        viewport.scale = newScale;
        viewport.x = newX;
        viewport.y = newY;
        document.getElementById('zoom-disp').innerText = Math.round(newScale * 100) + '%';
        updateRulerScales();
        canvas.viewportTransform = [newScale, 0, 0, newScale, newX, newY];
    }

    function screenToWorld(pos) {
        if (!konvaStage) return { x: pos.x, y: pos.y };
        const scale = konvaStage.scaleX() || 1;
        const tx = konvaStage.x();
        const ty = konvaStage.y();
        return {
            x: (pos.x - tx) / scale,
            y: (pos.y - ty) / scale
        };
    }

    function getViewportCenter() {
        const wrapper = document.getElementById('canvas-wrapper');
        const w = wrapper ? wrapper.clientWidth : window.innerWidth;
        const h = wrapper ? wrapper.clientHeight : window.innerHeight;
        return screenToWorld({ x: w / 2, y: h / 2 });
    }

    function updateRulerScales() {
        if (!konvaLayer || !konvaStage) return;
        const invScale = 1 / (konvaStage.scaleX() || 1);
        konvaRulers.forEach(r => {
            r.line.strokeWidth(4 * invScale);
            r.a1.radius(6 * invScale);
            r.a2.radius(6 * invScale);
            const LABEL_MIN_PX = 11;
            const LABEL_MAX_PX = 22;
            const rawFontSize = 16 * invScale;
            const clampedFontSize = Math.max(LABEL_MIN_PX * invScale, Math.min(LABEL_MAX_PX * invScale, rawFontSize));
            r.label.fontSize(clampedFontSize);
            r.label.padding(Math.max(2 * invScale, Math.min(6 * invScale, 4 * invScale)));
            // FIX: hitbox táctil estable en pantalla
            const hitRadius = Math.max(20, 28 * invScale);
            r.a1.hitFunc(function(context) {
                context.beginPath();
                context.arc(0, 0, hitRadius, 0, Math.PI * 2, true);
                context.closePath();
                context.fillStrokeShape(this);
            });
            r.a2.hitFunc(function(context) {
                context.beginPath();
                context.arc(0, 0, hitRadius, 0, Math.PI * 2, true);
                context.closePath();
                context.fillStrokeShape(this);
            });
        });
        konvaLayer.batchDraw();
    }

    function syncKonvaToFabric() {
        updateRulerScales();
    }

    function updateKonvaLabel(r) {
        const p1 = r.a1.position();
        const p2 = r.a2.position();
        const distPx = Math.hypot(p2.x - p1.x, p2.y - p1.y);
        let textVal = "";
        if (pixelsPerFoot > 0) {
            const feet = distPx / pixelsPerFoot;
            textVal = formatFeetForDisplay(feet);
        } else {
            textVal = Math.round(distPx) + " px";
        }
        r.label.text(textVal);
        const vpt = getViewport();
        const invScale = vpt.scaleX ? 1 / vpt.scaleX : 1;
        const midX = (p1.x + p2.x) / 2;
        const midY = (p1.y + p2.y) / 2 - (15 * invScale);
        r.label.position({ x: midX, y: midY });
    }

    function serializeKonvaForPage(pg) {
        const notes = konvaNotes
            .filter(n => n.page === pg)
            .map(n => ({
                x: n.group.x(),
                y: n.group.y(),
                text: n.label.text(),
                fill: n.label.fill(),
                fontSize: n.label.fontSize(),
                // FIX-BUG2/FIX-BUG3: persistir resize real de notas
                scaleX: n.group.scaleX(),
                scaleY: n.group.scaleY(),
                width: n.label.width()
            }));
        const rulers = konvaRulers
            .filter(r => r.page === pg)
            .map(r => ({ p1: r.a1.position(), p2: r.a2.position() }));
        const clouds = konvaClouds
            .filter(c => c.page === pg)
            .map(c => ({
                x: c.group.x(),
                y: c.group.y(),
                scaleX: c.group.scaleX(),
                scaleY: c.group.scaleY(),
                strokeWidth: c.shape ? c.shape.strokeWidth() : cloudStrokeWidth
            }));
        const stamps = konvaStamps
            .filter(s => s.page === pg)
            .map(s => ({
                x: s.group.x(),
                y: s.group.y(),
                text: s.group.getAttr('stampText'),
                color: s.group.getAttr('stampColor')
            }));
        const freePaths = konvaDrawPaths
            .filter(p => p.page === pg)
            .map(p => ({
                points: p.points,
                color: p.color,
                width: p.width
            }));
        return { notes, rulers, clouds, stamps, freePaths };
    }

    function clearKonvaPage(pg) {
        konvaNotes.filter(n => n.page === pg).forEach(n => n.group.destroy());
        konvaRulers.filter(r => r.page === pg).forEach(r => r.group.destroy());
        konvaClouds.filter(c => c.page === pg).forEach(c => c.group.destroy());
        konvaStamps.filter(s => s.page === pg).forEach(s => s.group.destroy());
        konvaDrawPaths.filter(p => p.page === pg).forEach(p => p.path.destroy());
        konvaNotes = konvaNotes.filter(n => n.page !== pg);
        konvaRulers = konvaRulers.filter(r => r.page !== pg);
        konvaClouds = konvaClouds.filter(c => c.page !== pg);
        konvaStamps = konvaStamps.filter(s => s.page !== pg);
        konvaDrawPaths = konvaDrawPaths.filter(p => p.page !== pg);
    }

    function loadKonvaForPage(pg, data) {
        if (!useKonvaRuler) return;
        initKonvaRuler();
        clearKonvaPage(pg);
        if (!data || typeof data !== 'object') return;
        (data.rulers || []).forEach(r => createKonvaRuler(r.p1, r.p2, pg));
        (data.notes || []).forEach(n => {
            const note = createKonvaNote({ x: n.x, y: n.y }, n.text || 'annotation', pg);
            if (n.fill) note.label.fill(n.fill);
            if (n.fontSize) note.label.fontSize(n.fontSize);
            // FIX-BUG2/FIX-BUG3: restaurar width/scale de notas
            if (n.width) note.label.width(Math.max(40, n.width));
            note.group.scaleX(n.scaleX || 1);
            note.group.scaleY(n.scaleY || 1);
            if (typeof updateKonvaNoteBox === 'function') updateKonvaNoteBox(note);
        });
        (data.clouds || []).forEach(c => {
            const cloud = createKonvaCloud({ x: c.x, y: c.y }, pg, c.strokeWidth || cloudStrokeWidth);
            cloud.group.scaleX(c.scaleX || 1);
            cloud.group.scaleY(c.scaleY || 1);
        });
        (data.stamps || []).forEach(sd => {
            createKonvaStamp(sd.x, sd.y, sd.text, sd.color, pg);
        });
        (data.freePaths || []).forEach(pd => {
            createKonvaFreePath(pd.points || [], pd.color || '#ef4444', pd.width || 3, pg);
        });
        setKonvaPage(pg);
    }

    function saveCurrentPageAnnotations() {
        allAnnotations[pageNum] = {
            konva: serializeKonvaForPage(pageNum)
        };
    }

    function getSavedPageState(pg) {
        const raw = allAnnotations[pg];
        if (!raw) return { konva: null };
        // Compatibilidad con datos legacy {fabric, konva}
        if (typeof raw === 'object' && raw.konva !== undefined) return { konva: raw.konva };
        return { konva: null };
    }

    function updateKonvaInteractivity() {
        const allowEdit = (currentMode === 'smart');
        konvaRulers.forEach(r => {
            r.group.draggable(allowEdit);
            r.a1.draggable(allowEdit);
            r.a2.draggable(allowEdit);
        });
        konvaNotes.forEach(n => {
            n.group.draggable(allowEdit);
        });
        konvaClouds.forEach(c => {
            c.group.draggable(allowEdit);
        });
        konvaStamps.forEach(s => {
            s.group.draggable(allowEdit);
        });
        if (konvaTransformer) {
            if (!allowEdit) konvaTransformer.nodes([]);
        }
        if (konvaLayer) konvaLayer.batchDraw();
    }

    function setKonvaPage(page) {
        konvaRulers.forEach(r => {
            r.group.visible(r.page === page);
        });
        konvaNotes.forEach(n => {
            n.group.visible(n.page === page);
        });
        konvaClouds.forEach(c => {
            c.group.visible(c.page === page);
        });
        konvaStamps.forEach(s => {
            s.group.visible(s.page === page);
        });
        konvaDrawPaths.forEach(p => {
            p.path.visible(p.page === page);
        });
        if (konvaTransformer) {
            konvaTransformer.nodes([]);
        }
        konvaSelectedNode = null;
        if (konvaLayer) konvaLayer.batchDraw();
    }

    function clearSelectionVisual() {
        if (!konvaSelectedNode) return;
        if (konvaSelectedNode.type === 'stamp') {
            const ref = konvaSelectedNode.ref;
            ref?.rect?.strokeWidth(4);
            ref?.rect?.dash([]);
        } else if (konvaSelectedNode.type === 'freedraw') {
            const ref = konvaSelectedNode.ref;
            const data = konvaDrawPaths.find(p => p.path === ref);
            if (data && ref) ref.stroke(data.color);
        } else if (konvaSelectedNode.type === 'ruler') {
            const r = konvaSelectedNode.ref;
            if (r?.line) {
                r.line.dash([]);
                r.line.shadowColor('transparent');
                r.line.shadowBlur(0);
            }
        }
        if (konvaLayer) konvaLayer.batchDraw();
    }

    function createKonvaRuler(p1, p2, targetPage = pageNum) {
        const group = new Konva.Group({ draggable: true, annoType: 'ruler' });
        const line = new Konva.Line({
            points: [p1.x, p1.y, p2.x, p2.y],
            stroke: '#22c55e',
            strokeWidth: 4
        });
        const a1 = new Konva.Circle({
            x: p1.x, y: p1.y,
            radius: 6,
            fill: '#ffffff',
            stroke: '#22c55e',
            strokeWidth: 2,
            draggable: true,
            // FIX: hitbox más grande para mobile
            hitFunc: function(context) {
                context.beginPath();
                context.arc(0, 0, 24, 0, Math.PI * 2, true);
                context.closePath();
                context.fillStrokeShape(this);
            }
        });
        const a2 = new Konva.Circle({
            x: p2.x, y: p2.y,
            radius: 6,
            fill: '#ffffff',
            stroke: '#22c55e',
            strokeWidth: 2,
            draggable: true,
            // FIX: hitbox más grande para mobile
            hitFunc: function(context) {
                context.beginPath();
                context.arc(0, 0, 24, 0, Math.PI * 2, true);
                context.closePath();
                context.fillStrokeShape(this);
            }
        });
        const label = new Konva.Text({
            x: (p1.x + p2.x) / 2,
            y: (p1.y + p2.y) / 2 - 15,
            text: '',
            fontSize: 16,
            fill: '#22c55e',
            padding: 4,
            stroke: '#ffffff',
            strokeWidth: 3,
            fillAfterStrokeEnabled: true
        });

        group.add(line, a1, a2, label);
        konvaLayer.add(group);

        const ruler = { group, line, a1, a2, label, page: targetPage };
        konvaRulers.push(ruler);
        if (!konvaRulersByPage[targetPage]) konvaRulersByPage[targetPage] = [];
        konvaRulersByPage[targetPage].push(ruler);

        const updateLine = () => {
            const p1c = a1.position();
            const p2c = a2.position();
            line.points([p1c.x, p1c.y, p2c.x, p2c.y]);
            updateKonvaLabel(ruler);
            konvaLayer.batchDraw();
        };

        a1.on('dragmove', updateLine);
        a2.on('dragmove', updateLine);
        a1.on('dragend', () => { saveCurrentPageAnnotations(); saveHistory(); });
        a2.on('dragend', () => { saveCurrentPageAnnotations(); saveHistory(); });
        group.on('dragmove', () => {
            updateKonvaLabel(ruler);
            konvaLayer.batchDraw();
        });
        group.on('dragend', () => { saveCurrentPageAnnotations(); saveHistory(); });
        group.on('click tap', () => {
            if (currentMode !== 'smart') return;
            clearSelectionVisual();
            if (konvaTransformer) konvaTransformer.nodes([]);
            konvaSelectedNode = { type: 'ruler', ref: ruler };
            line.dash([10 * (1 / (konvaStage?.scaleX() || 1)), 6 * (1 / (konvaStage?.scaleX() || 1))]);
            line.shadowColor('#22c55e');
            line.shadowBlur(8);
            konvaLayer.batchDraw();
            showPropSection('measure');
        });

        updateKonvaLabel(ruler);
        updateKonvaInteractivity();
        return ruler;
    }

    function isKonvaNoteEmpty(note) {
        if (!note || !note.label) return true;
        const raw = String(note.label.text() || '');
        const trimmed = raw.trim();
        return trimmed === '';
    }

    function removeKonvaNote(note) {
        if (!note) return;
        if (konvaSelectedNote === note) konvaSelectedNote = null;
        if (konvaSelectedNode && konvaSelectedNode.type === 'note' && konvaSelectedNode.ref === note) {
            konvaSelectedNode = null;
        }
        if (konvaTransformer) konvaTransformer.nodes([]);
        note.group.destroy();
        konvaNotes = konvaNotes.filter(n => n !== note);
        if (konvaLayer) konvaLayer.batchDraw();
        saveCurrentPageAnnotations();
    }

    function discardEmptyActiveKonvaNote() {
        if (!konvaSelectedNote) return false;
        if (!isKonvaNoteEmpty(konvaSelectedNote)) return false;
        removeKonvaNote(konvaSelectedNote);
        showToast("Empty note discarded", "warning");
        return true;
    }

    function getResponsiveNotePreset() {
        const width = window.innerWidth || 1280;
        if (width <= 640) return { fontSize: 42, minEditW: 180, minEditH: 52 };
        if (width <= 1024) return { fontSize: 54, minEditW: 220, minEditH: 64 };
        return { fontSize: 64, minEditW: 260, minEditH: 72 };
    }

    // MIGRATED: detectar anotaciones Konva (incluye stamps y free draw)
    function isKonvaAnnotationTarget(target) {
        let node = target;
        while (node) {
            const annoType = node.getAttr && node.getAttr('annoType');
            if (annoType === 'note' || annoType === 'cloud' || annoType === 'ruler' || annoType === 'stamp' || annoType === 'freedraw') return true;
            node = node.getParent ? node.getParent() : null;
        }
        return false;
    }

    // NEW-FEAT1: fondo tipo sticky-note + sync con tamaño de texto
    function updateKonvaNoteBox(note) {
        if (!note || !note.label || !note.bg) return;
        const padX = note.padX || 14;
        const padY = note.padY || 10;
        note.bg.position({ x: -padX, y: -padY });
        note.bg.size({
            width: Math.max(40, note.label.width()) + (padX * 2),
            height: Math.max(20, note.label.height()) + (padY * 2)
        });
    }

    // FIX-BUG5: mantener hitbox en sync tras transformaciones
    function syncKonvaCloudHitBox(cloud) {
        if (!cloud || !cloud.hitBox) return;
        const baseW = 180;
        const baseH = 120;
        const pad = 12;
        cloud.hitBox.position({ x: -(baseW / 2) - pad, y: -(baseH / 2) - pad });
        cloud.hitBox.size({ width: baseW + (pad * 2), height: baseH + (pad * 2) });
    }

    function createKonvaNote(pos, text = 'annotation', targetPage = pageNum) {
        const preset = getResponsiveNotePreset();
        const group = new Konva.Group({ x: pos.x, y: pos.y, draggable: true, annoType: 'note' });
        // NEW-FEAT1: caja tipo sticky note
        const noteBg = new Konva.Rect({
            x: -14,
            y: -10,
            width: 328,
            height: 96,
            fill: 'rgba(255,255,0,0.30)',
            stroke: '#facc15',
            strokeWidth: 2,
            cornerRadius: 6
        });
        const label = new Konva.Text({
            x: 0,
            y: 0,
            text,
            fontSize: preset.fontSize,
            fill: '#ef4444',
            fontFamily: 'Arial',
            wrap: 'word',
            // FIX-BUG3: width inicial para permitir reflow real al redimensionar
            width: 300
        });
        group.add(noteBg);
        group.add(label);
        konvaLayer.add(group);

        const note = { group, label, bg: noteBg, page: targetPage, padX: 14, padY: 10, bgFill: 'rgba(255,255,0,0.30)' };
        updateKonvaNoteBox(note);
        konvaNotes.push(note);

        group.on('mousedown touchstart', (e) => {
            // FIX-BUG1: evitar que el stage capture pan cuando se interactúa con nota
            e.cancelBubble = true;
        });
        group.on('click tap', () => {
            if (currentMode !== 'smart') setMode('smart');
            clearSelectionVisual();
            konvaSelectedNote = note;
            konvaSelectedNode = { type: 'note', ref: note };
            if (konvaTransformer) konvaTransformer.nodes([group]);
            // FIX-2c: evitar que Fabric sobreescriba el panel de propiedades de Konva
            canvas.discardActiveObject();
            canvas.requestRenderAll();
            showPropSection('text');
            syncTextSizeInput(Math.round(note.label.fontSize()));
            const currentFill = note.label.fill();
            document.querySelectorAll('#prop-text .color-dot, #m-prop-text .color-dot').forEach(d => {
                d.classList.remove('active');
                if ((d.getAttribute('data-col') || '').toLowerCase() === (currentFill || '').toLowerCase()) d.classList.add('active');
            });
        });
        group.on('dragend', () => { saveCurrentPageAnnotations(); saveHistory(); });
        label.on('dblclick dbltap', () => {
            if (currentMode !== 'smart') setMode('smart');
            konvaSelectedNote = note;
            if (konvaTransformer) konvaTransformer.nodes([group]);
            konvaSelectedNode = { type: 'note', ref: note };
            startInlineNoteEdit(note);
        });
        group.on('dblclick dbltap', () => {
            if (currentMode !== 'smart') setMode('smart');
            konvaSelectedNote = note;
            if (konvaTransformer) konvaTransformer.nodes([group]);
            konvaSelectedNode = { type: 'note', ref: note };
            startInlineNoteEdit(note);
        });

        updateKonvaInteractivity();
        return note;
    }

    function createKonvaCloud(pos, targetPage = pageNum, strokeWidth = cloudStrokeWidth) {
        const group = new Konva.Group({ x: pos.x, y: pos.y, draggable: true, annoType: 'cloud' });

        const w = 180;
        const h = 120;
        const pad = 12;
        const stroke = '#ef4444';

        const cloudShape = new Konva.Shape({
            sceneFunc: (ctx, shape) => {
                const left = -w / 2;
                const top = -h / 2;
                const right = w / 2;
                const bottom = h / 2;
                const scallop = 9;

                ctx.beginPath();

                // Top
                let x = left;
                ctx.moveTo(x, top);
                while (x < right) {
                    const nx = Math.min(x + scallop, right);
                    const mid = (x + nx) / 2;
                    ctx.quadraticCurveTo(mid, top - 7, nx, top);
                    x = nx;
                }
                // Right
                let y = top;
                while (y < bottom) {
                    const ny = Math.min(y + scallop, bottom);
                    const mid = (y + ny) / 2;
                    ctx.quadraticCurveTo(right + 7, mid, right, ny);
                    y = ny;
                }
                // Bottom
                x = right;
                while (x > left) {
                    const nx = Math.max(x - scallop, left);
                    const mid = (x + nx) / 2;
                    ctx.quadraticCurveTo(mid, bottom + 7, nx, bottom);
                    x = nx;
                }
                // Left
                y = bottom;
                while (y > top) {
                    const ny = Math.max(y - scallop, top);
                    const mid = (y + ny) / 2;
                    ctx.quadraticCurveTo(left - 7, mid, left, ny);
                    y = ny;
                }

                ctx.closePath();
                ctx.fillStrokeShape(shape);
            },
            stroke,
            strokeWidth,
            fill: 'transparent',
            shadowColor: '#ef4444',
            shadowBlur: 8,
            shadowOpacity: 0.18
        });

        const hitBox = new Konva.Rect({
            x: -(w / 2) - pad,
            y: -(h / 2) - pad,
            width: w + (pad * 2),
            height: h + (pad * 2),
            fill: 'rgba(0,0,0,0.001)',
            strokeWidth: 0
        });

        group.add(cloudShape);
        group.add(hitBox);
        konvaLayer.add(group);

        const cloud = { group, shape: cloudShape, hitBox, page: targetPage };
        konvaClouds.push(cloud);

        group.on('mousedown touchstart', (e) => {
            // FIX-BUG5: evitar que pan del stage capture interacciones de nubes
            e.cancelBubble = true;
        });
        group.on('click tap', () => {
            if (currentMode !== 'smart') setMode('smart');
            clearSelectionVisual();
            // FIX-2c: evitar que Fabric sobreescriba el panel de cloud
            canvas.discardActiveObject();
            canvas.requestRenderAll();
            if (konvaTransformer) konvaTransformer.nodes([group]);
            konvaSelectedNode = { type: 'cloud', ref: cloud };
            cloudStrokeWidth = cloud.shape.strokeWidth();
            syncCloudStrokeControl(cloudStrokeWidth);
            showPropSection('cloud');
        });
        group.on('dblclick dbltap', () => {
            if (currentMode !== 'smart') setMode('smart');
            if (konvaTransformer) konvaTransformer.nodes([group]);
            konvaSelectedNode = { type: 'cloud', ref: cloud };
            cloudStrokeWidth = cloud.shape.strokeWidth();
            syncCloudStrokeControl(cloudStrokeWidth);
            showPropSection('cloud');
        });
        group.on('dragend', () => { saveCurrentPageAnnotations(); saveHistory(); });
        syncKonvaCloudHitBox(cloud);

        updateKonvaInteractivity();
        return cloud;
    }


    // MIGRATED: free draw como Konva.Line directo
    function createKonvaFreePath(points, color = '#ef4444', width = 3, targetPage = pageNum) {
        const path = new Konva.Line({
            points,
            stroke: color,
            strokeWidth: width,
            hitStrokeWidth: Math.max(16, width * 6),
            lineCap: 'round',
            lineJoin: 'round',
            tension: 0.4
        });
        path.setAttr('annoType', 'freedraw');
        path.on('click tap', () => {
            if (currentMode !== 'smart') setMode('smart');
            selectFreeDrawPath(path);
        });
        konvaLayer.add(path);
        const item = { path, page: targetPage, color, width, points: [...points] };
        konvaDrawPaths.push(item);
        return item;
    }

    function selectFreeDrawPath(path) {
        if (!path) return;
        clearSelectionVisual();
        konvaSelectedNode = { type: 'freedraw', ref: path };
        path.stroke('#ff0000');
        if (konvaLayer) konvaLayer.batchDraw();
        showPropSection('smart');
    }

    function eraseFreeDrawPath(path, deferHistory = false) {
        if (!path) return false;
        const exists = konvaDrawPaths.some(p => p.path === path);
        if (!exists) return false;
        if (konvaSelectedNode?.type === 'freedraw' && konvaSelectedNode.ref === path) konvaSelectedNode = null;
        path.destroy();
        konvaDrawPaths = konvaDrawPaths.filter(p => p.path !== path);
        saveCurrentPageAnnotations();
        if (!deferHistory) saveHistory();
        if (konvaLayer) konvaLayer.batchDraw();
        return true;
    }

    function eraseFreeDrawAtPointer(pos) {
        if (!konvaLayer || !pos) return false;
        const hit = konvaLayer.getIntersection(pos);
        const annoType = hit?.getAttr ? hit.getAttr('annoType') : null;
        if (annoType === 'freedraw') {
            return eraseFreeDrawPath(hit, true);
        }
        return false;
    }

    // MIGRATED: stamp Konva.Group
    function createKonvaStamp(worldX, worldY, text, color, targetPage = pageNum) {
        const STAMP_W = 180, STAMP_H = 70;
        const rect = new Konva.Rect({
            x: -STAMP_W / 2, y: -STAMP_H / 2,
            width: STAMP_W, height: STAMP_H,
            cornerRadius: 8, fill: 'transparent', stroke: color, strokeWidth: 4, opacity: 1
        });
        const lbl = new Konva.Text({
            x: -STAMP_W / 2, y: -STAMP_H / 2,
            width: STAMP_W, height: STAMP_H,
            text, fontSize: 28, fontFamily: 'Arial', fontStyle: 'bold', fill: color,
            align: 'center', verticalAlign: 'middle'
        });
        const group = new Konva.Group({ x: worldX, y: worldY, draggable: true, opacity: 0.85 });
        group.setAttr('annoType', 'stamp');
        group.setAttr('stampText', text);
        group.setAttr('stampColor', color);
        group.add(rect, lbl);
        group.on('click tap', () => {
            clearSelectionVisual();
            canvas.discardActiveObject();
            if (konvaTransformer) konvaTransformer.nodes([]);
            konvaSelectedNode = { type: 'stamp', ref: { group, rect, lbl } };
            rect.strokeWidth(6);
            rect.dash([8, 4]);
            konvaLayer.batchDraw();
            showPropSection('smart');
        });
        group.on('dragstart', () => {
            konvaSelectedNode = { type: 'stamp', ref: { group, rect, lbl } };
        });
        group.on('dragend', () => {
            saveCurrentPageAnnotations();
            saveHistory();
        });
        konvaLayer.add(group);
        konvaLayer.batchDraw();
        const stamp = { group, rect, lbl, page: targetPage };
        konvaStamps.push(stamp);
        return stamp;
    }

    function ensureKonvaTransformer() {
        if (!konvaLayer) return;
        if (!konvaTransformer) {
            konvaTransformer = new Konva.Transformer({
                enabledAnchors: ['top-left','top-right','bottom-left','bottom-right'],
                rotateEnabled: false,
                keepRatio: false,
                // FIX-2e: anchors más grandes en mobile
                anchorSize: window.innerWidth <= 991 ? 16 : 10,
                anchorStrokeWidth: 2,
                boundBoxFunc: (oldBox, newBox) => {
                    if (newBox.width < 20 || newBox.height < 20) return oldBox;
                    return newBox;
                }
            });
            konvaTransformer.on('transformend', () => {
                // FIX-BUG3: en notas, convertir escala en width para reflow real de texto
                if (konvaSelectedNode?.type === 'note' && konvaSelectedNode.ref?.label) {
                    const note = konvaSelectedNode.ref;
                    const sx = note.group.scaleX() || 1;
                    const sy = note.group.scaleY() || 1;
                    note.label.width(Math.max(40, note.label.width() * sx));
                    note.label.fontSize(Math.max(8, note.label.fontSize() * sy));
                    note.group.scaleX(1);
                    note.group.scaleY(1);
                    updateKonvaNoteBox(note);
                }
                // FIX-BUG5: asegurar hitbox vigente en nubes tras transform
                if (konvaSelectedNode?.type === 'cloud' && konvaSelectedNode.ref) {
                    syncKonvaCloudHitBox(konvaSelectedNode.ref);
                }
                saveCurrentPageAnnotations();
            });
            konvaLayer.add(konvaTransformer);
        }
    }

    function deleteKonvaSelection() {
        if (!konvaSelectedNode) return false;
        const { type, ref } = konvaSelectedNode;
        if (type === 'ruler') {
            ref.group.destroy();
            konvaRulers = konvaRulers.filter(r => r !== ref);
            if (konvaRulersByPage[ref.page]) {
                konvaRulersByPage[ref.page] = konvaRulersByPage[ref.page].filter(r => r !== ref);
            }
        } else if (type === 'note') {
            if (konvaSelectedNote === ref) konvaSelectedNote = null;
            ref.group.destroy();
            konvaNotes = konvaNotes.filter(n => n !== ref);
        } else if (type === 'cloud') {
            ref.group.destroy();
            konvaClouds = konvaClouds.filter(c => c !== ref);
        } else if (type === 'stamp') {
            ref.group.destroy();
            konvaStamps = konvaStamps.filter(s => s.group !== ref.group);
        } else if (type === 'freedraw') {
            ref.destroy();
            konvaDrawPaths = konvaDrawPaths.filter(p => p.path !== ref);
        }
        konvaSelectedNode = null;
        if (konvaTransformer) konvaTransformer.nodes([]);
        if (konvaLayer) konvaLayer.batchDraw();
        saveCurrentPageAnnotations();
        return true;
    }

    function startInlineNoteEdit(note) {
        if (!note || !konvaStage || !konvaLayer) return;
        konvaSelectedNote = note;

        // Auto-zoom para edición de nota (desktop/mobile)
        noteEditViewportBefore = {
            x: konvaStage.x(),
            y: konvaStage.y(),
            scale: konvaStage.scaleX()
        };
        noteEditDidAutoZoom = false;
        {
            const textNode = note.label;
            const containerRect = konvaStage.container().getBoundingClientRect();
            const containerW = containerRect.width;
            const containerH = containerRect.height * 0.55;
            const MIN_ZOOM_FOR_EDIT = (window.innerWidth <= 991) ? 1.8 : 1.35;
            const targetZoom = Math.max(konvaStage.scaleX(), MIN_ZOOM_FOR_EDIT);

            const gsx = note.group.scaleX() || 1;
            const gsy = note.group.scaleY() || 1;
            const worldCenterX = note.group.x() + (textNode.x() + (textNode.width() / 2)) * gsx;
            const worldCenterY = note.group.y() + (textNode.y() + (textNode.height() / 2)) * gsy;

            const newTx = (containerW / 2) - (worldCenterX * targetZoom);
            const newTy = (containerH / 2) - (worldCenterY * targetZoom);

            if (Math.abs(targetZoom - konvaStage.scaleX()) > 0.001 || Math.abs(newTx - konvaStage.x()) > 0.5 || Math.abs(newTy - konvaStage.y()) > 0.5) {
                konvaStage.scale({ x: targetZoom, y: targetZoom });
                konvaStage.position({ x: newTx, y: newTy });
                konvaStage.batchDraw();
                document.getElementById('zoom-disp').innerText = Math.round(targetZoom * 100) + '%';
                updateRulerScales();
                noteEditDidAutoZoom = true;
            }
        }

        const container = konvaStage.container();
        const rect = container.getBoundingClientRect();
        const vpt = getViewport();

        const textNode = note.label;
        const gsx = note.group.scaleX() || 1;
        const gsy = note.group.scaleY() || 1;
        const worldTextX = note.group.x() + textNode.x() * gsx;
        const worldTextY = note.group.y() + textNode.y() * gsy;

        const areaPosition = {
            x: rect.left + worldTextX * vpt.scaleX + vpt.translateX,
            y: rect.top + worldTextY * vpt.scaleY + vpt.translateY
        };

        const fontSize = textNode.fontSize();

        if (!konvaEditingTextarea) {
            konvaEditingTextarea = document.createElement('textarea');
            konvaEditingTextarea.style.position = 'absolute';
            konvaEditingTextarea.style.zIndex = '3000';
            konvaEditingTextarea.style.resize = 'none';
            konvaEditingTextarea.style.border = '1px solid #475569';
            konvaEditingTextarea.style.borderRadius = '6px';
            konvaEditingTextarea.style.padding = '6px 8px';
            konvaEditingTextarea.style.outline = 'none';
            konvaEditingTextarea.style.background = '#0f172a';
            konvaEditingTextarea.style.color = '#ffffff';
            konvaEditingTextarea.style.overflow = 'hidden';
            container.appendChild(konvaEditingTextarea);
        }

        const fontSizePx = fontSize * vpt.scaleX;
        konvaEditingTextarea.style.fontSize = fontSizePx + 'px';
        konvaEditingTextarea.style.lineHeight = '1.2';
        // FIX-BUG6: usar posición calculada con offset real del contenedor
        konvaEditingTextarea.style.left = areaPosition.x + 'px';
        konvaEditingTextarea.style.top = areaPosition.y + 'px';
        const preset = getResponsiveNotePreset();
        konvaEditingTextarea.style.width = Math.max(preset.minEditW, textNode.width() * gsx * vpt.scaleX) + 'px';
        konvaEditingTextarea.style.height = Math.max(preset.minEditH, textNode.height() * gsy * vpt.scaleY) + 'px';
        konvaEditingTextarea.style.background = 'transparent';
        konvaEditingTextarea.style.border = 'none';
        konvaEditingTextarea.style.color = 'transparent';
        konvaEditingTextarea.style.caretColor = '#ffffff';
        konvaEditingTextarea.value = textNode.text();
        konvaEditingTextarea.focus();
        konvaEditingTextarea.select();

        const finish = () => {
            if (!konvaEditingTextarea) return;
            const next = konvaEditingTextarea.value.trim();
            if (next !== '') {
                textNode.text(next);
            }
            updateKonvaNoteBox(note);
            konvaEditingTextarea.remove();
            konvaEditingTextarea = null;
            if (isKonvaNoteEmpty(note)) {
                removeKonvaNote(note);
                showToast("Empty note discarded", "warning");
                saveCurrentPageAnnotations();
            } else {
                konvaLayer.batchDraw();
                saveCurrentPageAnnotations();
            }

            if (noteEditDidAutoZoom && noteEditViewportBefore && konvaStage) {
                konvaStage.scale({ x: noteEditViewportBefore.scale, y: noteEditViewportBefore.scale });
                konvaStage.position({ x: noteEditViewportBefore.x, y: noteEditViewportBefore.y });
                konvaStage.batchDraw();
                document.getElementById('zoom-disp').innerText = Math.round((noteEditViewportBefore.scale || 1) * 100) + '%';
                updateRulerScales();
            }
            noteEditDidAutoZoom = false;
            noteEditViewportBefore = null;
        };

        const onInput = () => {
            textNode.text(konvaEditingTextarea.value);
            // NEW-FEAT1: ajustar fondo sticky al contenido mientras se escribe
            updateKonvaNoteBox(note);
            konvaLayer.batchDraw();
        };
        const onKey = (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                finish();
            }
        };
        const onBlur = () => finish();
        konvaEditingTextarea.addEventListener('input', onInput);
        konvaEditingTextarea.addEventListener('keydown', onKey);
        konvaEditingTextarea.addEventListener('blur', onBlur);
    }

    function initKonvaRuler() {
        if (!useKonvaRuler || konvaStage) return;
        const w = document.getElementById('canvas-wrapper');
        if (!w) return;
        konvaStage = new Konva.Stage({
            container: 'konva-container',
            width: w.clientWidth,
            height: w.clientHeight
        });
        if (konvaStage.container()) {
            konvaStage.container().style.touchAction = 'none';
            konvaStage.container().style.webkitUserSelect = 'none';
            konvaStage.container().style.userSelect = 'none';
            const firstCanvas = konvaStage.container().querySelector('canvas');
            if (firstCanvas) firstCanvas.style.touchAction = 'none';
            const ctx = firstCanvas?.getContext('2d');
            if (ctx) {
                ctx.imageSmoothingEnabled = true;
                if ('imageSmoothingQuality' in ctx) ctx.imageSmoothingQuality = 'high';
            }
        }
        bgLayer = new Konva.Layer({ listening: false });
        konvaStage.add(bgLayer);
        konvaLayer = new Konva.Layer();
        konvaStage.add(konvaLayer);
        ensureKonvaTransformer();

        // Zoom nativo Konva stage
        konvaStage.container().addEventListener('wheel', function(e) {
            e.preventDefault();
            const rect = konvaStage.container().getBoundingClientRect();
            const screenX = e.clientX - rect.left;
            const screenY = e.clientY - rect.top;
            const newScale = konvaStage.scaleX() * (0.999 ** e.deltaY);
            zoomToPoint(screenX, screenY, newScale);
        }, { passive: false });

        // MIGRATED: pan/zoom touch unificado en Konva (sin listeners externos conflictivos)
        let panStart = null;
        let touchPinchDist = 0;
        let touchPinchCX = 0;
        let touchPinchCY = 0;
        let touchIsPinching = false;
        konvaStage.on('mousedown touchstart', (e) => {
            const evt = e.evt;
            const target = e.target;
            const isAnnoTarget = isKonvaAnnotationTarget(target);
            const isEmpty = !target || target === konvaStage;

            // PINCH: 2 dedos -> zoom + pan simultáneo
            if (evt && evt.touches && evt.touches.length === 2) {
                konvaIsPanning = false;
                panStart = null;
                touchIsPinching = true;
                const t0 = evt.touches[0], t1 = evt.touches[1];
                const dx = t0.clientX - t1.clientX;
                const dy = t0.clientY - t1.clientY;
                touchPinchDist = Math.sqrt(dx * dx + dy * dy);
                touchPinchCX = (t0.clientX + t1.clientX) / 2;
                touchPinchCY = (t0.clientY + t1.clientY) / 2;
                if (typeof evt.preventDefault === 'function') evt.preventDefault();
                return;
            }

            if (pendingPlacementTool || isAnnoTarget) return;
            if (currentMode === 'draw' || currentMode === 'cal') return;
            if (touchIsPinching) return;

            const isTouch = !!(evt && evt.touches && evt.touches.length === 1);
            const canPanMouse = evt && ((evt.altKey || evt.button === 2) || (currentMode === 'smart' && isEmpty));
            const canPanTouch = isTouch && currentMode === 'smart' && isEmpty;

            if (canPanMouse || canPanTouch) {
                const cx = isTouch ? evt.touches[0].clientX : evt.clientX;
                const cy = isTouch ? evt.touches[0].clientY : evt.clientY;
                panStart = { x: cx, y: cy, stageX: konvaStage.x(), stageY: konvaStage.y() };
                konvaIsPanning = true;
                if (evt && typeof evt.preventDefault === 'function') evt.preventDefault();
            }
        });
        konvaStage.on('click tap', (e) => {
            const target = e.target;
            const isEmpty = !target || target === konvaStage;

            if (currentMode === 'smart' && konvaSelectedNote && isKonvaNoteEmpty(konvaSelectedNote)) {
                const clickedInsideSelectedNote = target && (
                    target === konvaSelectedNote.group ||
                    target === konvaSelectedNote.label ||
                    target.getParent?.() === konvaSelectedNote.group
                );
                if (!clickedInsideSelectedNote) {
                    removeKonvaNote(konvaSelectedNote);
                    showToast("Empty note discarded", "warning");
                }
            }

            if (currentMode === 'smart' && isEmpty && konvaTransformer) {
                konvaTransformer.nodes([]);
                konvaLayer.batchDraw();
            }
            if (currentMode === 'smart' && isEmpty) {
                clearSelectionVisual();
                konvaSelectedNode = null;
                showPropSection('smart');
            }
        });
        konvaStage.on('mousemove touchmove', (e) => {
            const evt = e.evt;
            if (!evt) return;

            // PINCH MOVE: 2 dedos -> zoom + pan simultáneo
            if (evt.touches && evt.touches.length === 2 && touchIsPinching) {
                const t0 = evt.touches[0], t1 = evt.touches[1];
                const dx = t0.clientX - t1.clientX;
                const dy = t0.clientY - t1.clientY;
                const dist = Math.sqrt(dx * dx + dy * dy);
                const currentCX = (t0.clientX + t1.clientX) / 2;
                const currentCY = (t0.clientY + t1.clientY) / 2;
                const rect = konvaStage.container().getBoundingClientRect();
                const screenX = currentCX - rect.left;
                const screenY = currentCY - rect.top;

                konvaStage.x(konvaStage.x() + (currentCX - touchPinchCX));
                konvaStage.y(konvaStage.y() + (currentCY - touchPinchCY));

                if (touchPinchDist > 0) {
                    zoomToPoint(screenX, screenY, konvaStage.scaleX() * (dist / touchPinchDist));
                }

                touchPinchDist = dist;
                touchPinchCX = currentCX;
                touchPinchCY = currentCY;
                if (typeof evt.preventDefault === 'function') evt.preventDefault();
                return;
            }

            // PAN 1 dedo / mouse
            if (pendingPlacementTool) return;
            if (!konvaIsPanning || !panStart) return;
            const isTouch = !!(evt.touches && evt.touches.length === 1);
            const cx = isTouch ? evt.touches[0].clientX : evt.clientX;
            const cy = isTouch ? evt.touches[0].clientY : evt.clientY;
            const dx2 = cx - panStart.x;
            const dy2 = cy - panStart.y;
            konvaStage.position({ x: panStart.stageX + dx2, y: panStart.stageY + dy2 });
            konvaStage.batchDraw();
            viewport.x = konvaStage.x();
            viewport.y = konvaStage.y();
            canvas.viewportTransform = [konvaStage.scaleX(), 0, 0, konvaStage.scaleY(), viewport.x, viewport.y];
            if (typeof evt.preventDefault === 'function') evt.preventDefault();
        });
        konvaStage.on('mouseup touchend touchcancel', (e) => {
            const evt = e.evt;
            const touchCount = evt && evt.touches ? evt.touches.length : 0;

            if (touchCount < 2) {
                if (touchIsPinching) {
                    touchIsPinching = false;
                    touchPinchDist = 0;
                    if (touchCount === 1 && evt.touches) {
                        const t = evt.touches[0];
                        panStart = { x: t.clientX, y: t.clientY, stageX: konvaStage.x(), stageY: konvaStage.y() };
                        konvaIsPanning = true;
                        return;
                    }
                }
            }

            if (touchCount === 0 || !evt || !evt.touches) {
                konvaIsPanning = false;
                panStart = null;
                touchIsPinching = false;
                touchPinchDist = 0;
            }
        });

        konvaStage.on('mousedown touchstart', (e) => {
            const target = e.target;
            const isAnnoTarget = isKonvaAnnotationTarget(target);
            const isEmpty = !target || target === konvaStage;
            const pos = konvaStage.getPointerPosition();
            // FIX-2d: ignorar multi-touch (pinch) para evitar interferencia con ruler/placement
            if (e.evt && e.evt.touches && e.evt.touches.length > 1) return;
            // FIX-BUG1: placement solo en área vacía, nunca sobre nota/nube
            if (pendingPlacementTool && isEmpty && !isAnnoTarget) {
                if (!pos) return;
                pendingPlacementStart = screenToWorld(pos);
                if (pendingPlacementPreview) pendingPlacementPreview.destroy();
                pendingPlacementPreview = new Konva.Rect({
                    x: pendingPlacementStart.x,
                    y: pendingPlacementStart.y,
                    width: 1,
                    height: 1,
                    stroke: '#22c55e',
                    strokeWidth: 1.5,
                    dash: [6, 4]
                });
                konvaLayer.add(pendingPlacementPreview);
                konvaLayer.batchDraw();
                return;
            }
            if (!pos) return;
            const world = screenToWorld(pos);

            if (currentMode === 'draw') {
                const annoType = target?.getAttr ? target.getAttr('annoType') : null;
                if (drawEraserMode) {
                    konvaIsErasing = true;
                    konvaErasedInGesture = false;
                    if (annoType === 'freedraw' && eraseFreeDrawPath(target, true)) {
                        konvaErasedInGesture = true;
                    } else if (eraseFreeDrawAtPointer(pos)) {
                        konvaErasedInGesture = true;
                    }
                    return;
                }
                isKonvaDrawing = true;
                konvaCurrentPoints = [world.x, world.y];
                konvaCurrentPath = new Konva.Line({
                    points: konvaCurrentPoints,
                    stroke: drawColor,
                    strokeWidth: drawWidth / (getViewport().scaleX),
                    hitStrokeWidth: Math.max(16, (drawWidth / (getViewport().scaleX)) * 6),
                    lineCap: 'round',
                    lineJoin: 'round',
                    tension: 0.4,
                    globalCompositeOperation: 'source-over'
                });
                konvaCurrentPath.setAttr('annoType', 'freedraw');
                konvaLayer.add(konvaCurrentPath);
                return;
            }

            if (currentMode === 'cal') {
                if (calMode === 'preset') return;
                if (!konvaCalLine) {
                    konvaCalPoints = { x1: world.x, y1: world.y, x2: world.x, y2: world.y };
                    konvaCalLine = new Konva.Line({
                        points: [world.x, world.y, world.x, world.y],
                        stroke: '#eab308',
                        strokeWidth: 3 / getViewport().scaleX,
                        lineCap: 'round',
                        dash: [8 / getViewport().scaleX, 4 / getViewport().scaleX]
                    });
                    konvaLayer.add(konvaCalLine);
                } else {
                    konvaCalPoints.x2 = world.x;
                    konvaCalPoints.y2 = world.y;
                    konvaCalLine.points([konvaCalPoints.x1, konvaCalPoints.y1, world.x, world.y]);
                    konvaCalFinished = konvaCalLine;
                    konvaCalLine = null;
                    const dx = konvaCalPoints.x2 - konvaCalPoints.x1;
                    const dy = konvaCalPoints.y2 - konvaCalPoints.y1;
                    canvas.tempDist = Math.sqrt(dx * dx + dy * dy);
                    calLineObject = konvaCalFinished;
                    document.getElementById('cal-actions').style.display = 'flex';
                    document.getElementById('cal-hint').style.display = 'none';
                    document.getElementById('btn-del-cal').style.display = 'inline-block';
                    document.getElementById('cal-val').focus();
                    konvaLayer.batchDraw();
                }
                return;
            }

            if (currentMode !== 'measure') return;
            konvaDrawing = createKonvaRuler(world, world);
            syncKonvaToFabric();
        });

        konvaStage.on('mousemove touchmove', (e) => {
            const pos = konvaStage.getPointerPosition();
            if (pendingPlacementTool && pendingPlacementStart && pendingPlacementPreview && pos) {
                const world = screenToWorld(pos);
                const x = Math.min(pendingPlacementStart.x, world.x);
                const y = Math.min(pendingPlacementStart.y, world.y);
                const w = Math.max(1, Math.abs(world.x - pendingPlacementStart.x));
                const h = Math.max(1, Math.abs(world.y - pendingPlacementStart.y));
                pendingPlacementPreview.position({ x, y });
                pendingPlacementPreview.size({ width: w, height: h });
                konvaLayer.batchDraw();
                return;
            }
            if (currentMode === 'draw' && drawEraserMode && konvaIsErasing) {
                if (!pos) return;
                if (eraseFreeDrawAtPointer(pos)) konvaErasedInGesture = true;
                if (e.evt && typeof e.evt.preventDefault === 'function') e.evt.preventDefault();
                return;
            }

            if (currentMode === 'draw' && isKonvaDrawing && konvaCurrentPath) {
                if (e.evt && e.evt.touches && e.evt.touches.length > 1) {
                    isKonvaDrawing = false;
                    if (konvaCurrentPath) {
                        konvaCurrentPath.destroy();
                        konvaCurrentPath = null;
                    }
                    konvaLayer.batchDraw();
                    return;
                }
                if (e.evt && typeof e.evt.preventDefault === 'function') e.evt.preventDefault();
                if (!pos) return;
                const world = screenToWorld(pos);
                konvaCurrentPoints = konvaCurrentPoints.concat([world.x, world.y]);
                konvaCurrentPath.points(konvaCurrentPoints);
                konvaLayer.batchDraw();
                return;
            }

            if (currentMode === 'cal' && konvaCalLine) {
                if (!pos) return;
                const world = screenToWorld(pos);
                konvaCalLine.points([konvaCalPoints.x1, konvaCalPoints.y1, world.x, world.y]);
                konvaLayer.batchDraw();
                return;
            }

            if (!konvaDrawing) return;
            if (!pos) return;
            const world = screenToWorld(pos);
            konvaDrawing.a2.position(world);
            konvaDrawing.line.points([
                konvaDrawing.a1.x(), konvaDrawing.a1.y(),
                world.x, world.y
            ]);
            updateKonvaLabel(konvaDrawing);
            konvaLayer.batchDraw();
        });

        konvaStage.on('mouseup touchend', () => {
            if (currentMode === 'draw' && drawEraserMode && konvaIsErasing) {
                if (konvaErasedInGesture) saveHistory();
                konvaIsErasing = false;
                konvaErasedInGesture = false;
                return;
            }

            if (pendingPlacementTool && pendingPlacementStart) {
                const pos = konvaStage.getPointerPosition();
                const end = pos ? screenToWorld(pos) : pendingPlacementStart;
                const minX = Math.min(pendingPlacementStart.x, end.x);
                const minY = Math.min(pendingPlacementStart.y, end.y);
                const width = Math.max(10, Math.abs(end.x - pendingPlacementStart.x));
                const height = Math.max(10, Math.abs(end.y - pendingPlacementStart.y));
                const cx = minX + (width / 2);
                const cy = minY + (height / 2);

                if (pendingPlacementPreview) {
                    pendingPlacementPreview.destroy();
                    pendingPlacementPreview = null;
                }

                const toolToPlace = pendingPlacementTool;
                // FIX-BUG1: limpiar placement inmediatamente para evitar estado residual al arrastrar luego
                clearPlacementTool();

                if (toolToPlace === 'note') {
                    const note = createKonvaNote({ x: cx, y: cy }, 'annotation');
                    const baseW = Math.max(1, note.label.width());
                    const baseH = Math.max(1, note.label.height());
                    note.group.scaleX(Math.max(0.35, width / baseW));
                    note.group.scaleY(Math.max(0.35, height / baseH));
                    updateKonvaNoteBox(note);
                    if (konvaTransformer) konvaTransformer.nodes([note.group]);
                    konvaSelectedNode = { type: 'note', ref: note };
                    konvaSelectedNote = note;
                    startInlineNoteEdit(note);
                } else if (toolToPlace === 'cloud') {
                    const cloud = createKonvaCloud({ x: cx, y: cy });
                    cloud.group.scaleX(Math.max(0.35, width / 180));
                    cloud.group.scaleY(Math.max(0.35, height / 120));
                    syncKonvaCloudHitBox(cloud);
                    if (konvaTransformer) konvaTransformer.nodes([cloud.group]);
                    konvaSelectedNode = { type: 'cloud', ref: cloud };
                }

                saveCurrentPageAnnotations();
                saveHistory();
                return;
            }

            if (currentMode === 'draw' && isKonvaDrawing) {
                isKonvaDrawing = false;
                if (!konvaCurrentPath) return;
                if (konvaCurrentPoints.length < 4) {
                    konvaCurrentPath.destroy();
                    konvaCurrentPath = null;
                    konvaLayer.batchDraw();
                    return;
                }
                const completedPath = konvaCurrentPath;
                completedPath.on('click tap', () => {
                    if (currentMode !== 'smart') setMode('smart');
                    selectFreeDrawPath(completedPath);
                });
                konvaDrawPaths.push({
                    path: completedPath,
                    page: pageNum,
                    color: drawColor,
                    width: drawWidth / (getViewport().scaleX),
                    points: [...konvaCurrentPoints]
                });
                konvaCurrentPath = null;
                konvaCurrentPoints = [];
                konvaLayer.batchDraw();
                saveCurrentPageAnnotations();
                saveHistory();
                return;
            }

            if (!konvaDrawing) return;
            const p1 = konvaDrawing.a1.position();
            const p2 = konvaDrawing.a2.position();
            const dist = Math.hypot(p2.x - p1.x, p2.y - p1.y);
            if (dist < 10) {
                konvaDrawing.group.destroy();
                konvaRulers = konvaRulers.filter(r => r !== konvaDrawing);
                if (konvaRulersByPage[pageNum]) {
                    konvaRulersByPage[pageNum] = konvaRulersByPage[pageNum].filter(r => r !== konvaDrawing);
                }
                konvaLayer.draw();
            }
            konvaDrawing = null;
            saveCurrentPageAnnotations();
            saveHistory();
        });

        setKonvaPage(pageNum);
        setKonvaActive(true);
        updateKonvaInteractivity();
    }

    // REMOVED: lógica double-tap/controles personalizados de Fabric

    // REMOVED: lock/unlock legacy de Fabric (anotaciones migradas a Konva)
    function lockObject(obj) { return; }
    function unlockObject(obj) { return; }

    // --- DELETE FUNCTIONALITY ---
    function deleteSelected() {
        if (deleteKonvaSelection()) {
            saveHistory();
            showToast("Selection deleted", "success");
        }
    }

    // REMOVED: mouse:up legacy de Fabric para líneas/calibración

    // --- LOAD LOGIC ---
    if(fileExt === 'pdf') {
        const loadingTask = pdfjsLib.getDocument({
            url: fileUrl,
            rangeChunkSize: 262144,
            disableStream: false,
            disableAutoFetch: true,
            cMapUrl: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/cmaps/',
            cMapPacked: true,
            standardFontDataUrl: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/standard_fonts/'
        });
        loadingTask.promise.then(pdf => {
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
            const img = new window.Image();
            img.onload = () => { setBg(img); loadPageAnnotations(1); };
            img.src = url;
        }).catch(e => { console.error(e); showToast("Error loading HEIC", "error"); });
    } else {
        document.getElementById('p-total').textContent = '1'; renderPageList(1);
        const img = new window.Image();
        img.onload = () => { setBg(img); loadPageAnnotations(1); };
        img.onerror = () => { showToast("Error loading image", "error"); };
        img.src = fileUrl;
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
        tempRenderCanvas.width = Math.max(1, Math.floor(viewport.width));
        tempRenderCanvas.height = Math.max(1, Math.floor(viewport.height));
        await page.render({ canvasContext: tempRenderCtx, viewport }).promise;
        const quality = scale >= pdfScale ? 0.8 : 0.62;
        return tempRenderCanvas.toDataURL('image/jpeg', quality);
    }

    function applyBackground(url, num, token, loadAnnotations) {
        if (token !== renderToken) return;
        const img = new window.Image();
        img.onload = () => {
            if (token !== renderToken) return;
            setBg(img);
            if (loadAnnotations) loadPageAnnotations(num);
        };
        img.src = url;
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

    function queuePrefetch(num) {
        if (prefetchQueue.includes(num)) return;
        prefetchQueue.push(num);
    }

    async function flushPrefetchQueue() {
        if (prefetchBusy || !pdfDoc || prefetchQueue.length === 0) return;
        prefetchBusy = true;
        try {
            while (prefetchQueue.length) {
                const n = prefetchQueue.shift();
                const cached = getCache(n);
                if (cached && (cached.low || cached.high)) continue;
                try {
                    const url = await renderPageToDataUrl(n, LOW_RES_SCALE);
                    setCache(n, 'low', url);
                } catch (_) {}
            }
        } finally {
            prefetchBusy = false;
        }
    }

    function scheduleIdlePrefetch() {
        const runner = () => flushPrefetchQueue();
        if (typeof window.requestIdleCallback === 'function') {
            window.requestIdleCallback(runner, { timeout: 800 });
        } else {
            setTimeout(runner, 120);
        }
    }

    function prefetchNeighbors(num) {
        if(!pdfDoc) return;
        const total = pdfDoc.numPages;
        for (let step = 1; step <= PREFETCH_DISTANCE; step++) {
            const left = num - step;
            const right = num + step;
            if (left >= 1) queuePrefetch(left);
            if (right <= total) queuePrefetch(right);
        }
        scheduleIdlePrefetch();
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

    function setBg(imgElement) {
        if (!bgLayer) return;
        bgLayer.destroyChildren();
        pdfImageSize = {
            width: imgElement.naturalWidth || imgElement.width,
            height: imgElement.naturalHeight || imgElement.height
        };
        bgImage = new Konva.Image({
            image: imgElement,
            x: 0,
            y: 0,
            width: pdfImageSize.width,
            height: pdfImageSize.height,
            listening: false
        });
        bgLayer.add(bgImage);
        bgLayer.batchDraw();
    }

    function jumpToPage(targetPage) {
        saveCurrentPageAnnotations();
        if (bgLayer) bgLayer.destroyChildren();
        undoStack = []; historyIndex = -1;
        pageNum = targetPage; 
        loadCalibrationForPage(false);
        if (useKonvaRuler) setKonvaPage(pageNum);
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
        const state = getSavedPageState(pg);
        loadKonvaForPage(pg, state.konva);
        historyProcessing = false;
        saveHistory();
    }

    // --- HISTORY ---
    function saveHistory() {
        if (historyProcessing) return;
        if (historyIndex < undoStack.length - 1) {
            undoStack = undoStack.slice(0, historyIndex + 1);
        }
        // MIGRATED: historial basado en estado Konva por página
        const state = {
            konva: serializeKonvaForPage(pageNum),
            pageNum
        };
        undoStack.push(JSON.stringify(state));
        historyIndex++;
        if (undoStack.length > MAX_HISTORY) {
            undoStack.shift();
            historyIndex--;
        }
        updateHistoryButtons();
    }

    function undo() {
        if (historyIndex > 0) {
            historyProcessing = true;
            historyIndex--;
            const state = JSON.parse(undoStack[historyIndex]);
            loadKonvaForPage(state.pageNum, state.konva);
            historyProcessing = false;
            updateHistoryButtons();
        }
    }

    function redo() {
        if (historyIndex < undoStack.length - 1) {
            historyProcessing = true;
            historyIndex++;
            const state = JSON.parse(undoStack[historyIndex]);
            loadKonvaForPage(state.pageNum, state.konva);
            historyProcessing = false;
            updateHistoryButtons();
        }
    }

    function updateHistoryButtons() {
        const btnUndo = document.getElementById('btn-undo');
        const btnRedo = document.getElementById('btn-redo');
        if(historyIndex > 0) btnUndo.classList.remove('btn-disabled'); else btnUndo.classList.add('btn-disabled');
        if(historyIndex < undoStack.length - 1) btnRedo.classList.remove('btn-disabled'); else btnRedo.classList.add('btn-disabled');
    }

    // REMOVED: relink de objetos Fabric ya no aplica
    function reLinkObjects() { return; }

    // --- EVENTS ---
    // REMOVED: listeners de anotaciones Fabric (added/modified/removed/path/text/moving)

    // --- TOOL SWITCHING ---
    function setMode(mode) {
        if (calLineObject && mode !== 'cal') clearCalLine();
        discardEmptyActiveKonvaNote();
        if (mode !== 'smart' && pendingPlacementTool) clearPlacementTool();
        resetToolState();
        currentMode = mode;

        document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
        if (mode !== 'smart') {
            const btn = document.getElementById('btn-' + mode);
            if (btn) btn.classList.add('active');
        } else {
            document.getElementById('btn-smart').classList.add('active');
        }

        showPropSection(mode);
        document.getElementById('stamp-menu').style.display = 'none';

        if (mode === 'measure' && pixelsPerFoot <= 0) {
            showToast("Please calibrate first!", "error");
            setMode('cal');
            return;
        }
        if (mode === 'cal') {
            updateCalHint();
            if (window.innerWidth <= 991) openMobileCalModal();
        }

        initKonvaRuler();
        setKonvaActive(true);
        updateKonvaInteractivity();

        if (konvaStage && konvaStage.container()) {
            if (mode === 'draw') {
                konvaStage.container().style.cursor = drawEraserMode ? 'not-allowed' : 'crosshair';
            } else if (mode === 'measure' || mode === 'cal') {
                konvaStage.container().style.cursor = 'crosshair';
            } else {
                konvaStage.container().style.cursor = 'default';
            }
        }
    }

    function resetToolState() {
        // REMOVED: limpieza de líneas temporales Fabric
        if(currentMode !== 'cal') {
             document.getElementById('cal-actions').style.display = 'none';
             document.getElementById('cal-hint').style.display = 'block';
             document.getElementById('cal-val').value = '';
             resetScalePresetSelection();
             updateCalHint();
        }
    }
    
    function showPropSection(idPart) {
        // Desktop & tablet: activate prop-section pill in the header properties bar
        document.querySelectorAll('.prop-section').forEach(p => p.classList.remove('active'));
        const el = document.getElementById('prop-' + idPart);
        if (el) el.classList.add('active');
        keepScaleDisplayVisible();

        if (window.innerWidth <= 599) {
            // Phone only: activate the mobile-props-panel at the bottom
            const panel = document.getElementById('mobile-props-panel');
            if (!panel) return;

            panel.querySelectorAll('.mobile-prop-section').forEach(s => s.classList.remove('active'));

            const noOptionsTools = ['smart'];
            if (noOptionsTools.includes(idPart)) {
                panel.classList.remove('visible');
                document.body.classList.remove('has-mobile-props');
                return;
            }

            const mSection = document.getElementById('m-prop-' + idPart);
            if (mSection) {
                mSection.classList.add('active');
                panel.classList.add('visible');
                document.body.classList.add('has-mobile-props');
            } else {
                panel.classList.remove('visible');
                document.body.classList.remove('has-mobile-props');
            }
        } else {
            // Desktop/tablet: always clean up any leftover mobile state
            const panel = document.getElementById('mobile-props-panel');
            if (panel) {
                panel.classList.remove('visible');
                panel.querySelectorAll('.mobile-prop-section').forEach(s => s.classList.remove('active'));
            }
            document.body.classList.remove('has-mobile-props');
        }
    }

    function syncDesktopDot(propId, color) {
        const normalized = String(color || '').toLowerCase();
        document.querySelectorAll('#' + propId + ' .color-dot').forEach(d => d.classList.remove('active'));
        document.querySelectorAll('#' + propId + ' .color-dot').forEach(d => {
            const bg = String(d.style.background || '').toLowerCase().replace(/\s+/g, '');
            const col = String(d.getAttribute('data-col') || '').toLowerCase();
            if (col === normalized || bg.includes(normalized)) d.classList.add('active');
        });
    }

    function syncPenWidthSlider(val) {
        const mSlider = document.getElementById('m-pen-width');
        if (mSlider) mSlider.value = val;
        const dSlider = document.querySelector('#prop-draw input[type="range"]');
        if (dSlider) dSlider.value = val;
    }

    function syncTextSizeInput(val) {
        const mInput = document.getElementById('m-text-size-input');
        if (mInput) mInput.value = val;
        const dInput = document.getElementById('text-size-input');
        if (dInput) dInput.value = val;
    }

    function syncCloudStrokeSelect(val) {
        const mSelect = document.getElementById('m-cloud-stroke');
        if (mSelect) mSelect.value = val;
        const dSelect = document.getElementById('cloud-stroke');
        if (dSelect) dSelect.value = val;
    }

    function toggleStampMenu() {
        const m = document.getElementById('stamp-menu');
        m.style.display = (m.style.display === 'flex') ? 'none' : 'flex';
    }

    function addStamp(text, color) {
        setMode('smart');
        if (!konvaStage) initKonvaRuler();
        setKonvaActive(true);
        const vpt = getViewport();
        const wrapper = document.getElementById('canvas-wrapper');
        const cx = (wrapper.clientWidth / 2 - vpt.translateX) / vpt.scaleX;
        const cy = (wrapper.clientHeight / 2 - vpt.translateY) / vpt.scaleY;
        const stamp = createKonvaStamp(cx, cy, text, color);
        konvaSelectedNode = { type: 'stamp', ref: stamp };
        document.getElementById('stamp-menu').style.display = 'none';
        saveCurrentPageAnnotations();
        saveHistory();
        showToast(`${text} stamp placed`, 'success');
    }

    // --- CANVAS INPUTS ---
    // REMOVED: interacción de anotaciones en Fabric (migrada a Konva)
    function finishLineLogic() {
        // REMOVED: calibración/medición legacy en Fabric
    }

    function clearCalLine() {
        if (konvaCalLine) { konvaCalLine.destroy(); konvaCalLine = null; }
        if (konvaCalFinished) { konvaCalFinished.destroy(); konvaCalFinished = null; }
        konvaCalPoints = null;
        calLineObject = null;
        canvas.tempDist = 0;
        if (konvaLayer) konvaLayer.batchDraw();
        document.getElementById('cal-actions').style.display = 'none';
        document.getElementById('cal-hint').style.display = 'block';
        document.getElementById('cal-val').value = '';
        resetScalePresetSelection();
        updateCalHint();
        canvas.requestRenderAll();
    }
    
    function finishCal(save) {
        if(save) {
            const val = parseFloat(document.getElementById('cal-val').value);
            if(val > 0) {
                pixelsPerFoot = canvas.tempDist / val;
                calibrationByPage[pageNum] = { data: pixelsPerFoot, label: 'Custom' };
                setScaleDisplay('Custom');
                showToast(`Calibrated! 1 ft = ${pixelsPerFoot.toFixed(2)} px`, "success");
                refreshMeasureLabels();
                clearCalLine();
            } else { showToast("Invalid value", "error"); return; }
        } else clearCalLine();
        resetToolState(); setMode('smart');
    }

    // --- UTILS ---
    function setPenColor(c, el) {
        drawColor = c;
        drawEraserMode = false;
        document.querySelectorAll('#prop-draw .color-dot, #m-prop-draw .color-dot').forEach(d => d.classList.remove('active'));
        if (el) el.classList.add('active');
        syncDesktopDot('prop-draw', c);
    }
    function setPenWidth(w) { drawWidth = parseFloat(w) || 3; syncPenWidthSlider(w); }
    function setDrawColor(color) { drawColor = color; drawEraserMode = false; }
    function setDrawWidth(val) { drawWidth = parseFloat(val) || 3; }
    function setDrawEraser(enabled) {
        drawEraserMode = !!enabled;
        if (!drawEraserMode) {
            konvaIsErasing = false;
            konvaErasedInGesture = false;
        }
        const btn = document.getElementById('btn-draw-eraser');
        if (btn) {
            btn.classList.toggle('btn-danger', drawEraserMode);
            btn.classList.toggle('btn-outline-light', !drawEraserMode);
        }
        const mBtn = document.getElementById('m-btn-draw-eraser');
        if (mBtn) {
            mBtn.classList.toggle('btn-warning', drawEraserMode);
            mBtn.classList.toggle('btn-outline-light', !drawEraserMode);
        }
        if (konvaStage && konvaStage.container()) {
            konvaStage.container().style.cursor = drawEraserMode ? 'not-allowed' : 'crosshair';
        }
    }
    function toggleDrawEraser() {
        setDrawEraser(!drawEraserMode);
        showToast(drawEraserMode ? 'Eraser enabled: tap a stroke to delete' : 'Eraser disabled', 'success');
    }

    function startPlacementTool(tool) {
        pendingPlacementTool = tool;
        setMode('smart');
        if (!useKonvaRuler) return;
        initKonvaRuler();
        setKonvaActive(true);
        updateKonvaInteractivity();
        if (konvaStage && konvaStage.container()) konvaStage.container().style.cursor = 'crosshair';
        showToast(tool === 'note' ? 'Tap where you want to place the note' : 'Tap where you want to place the cloud', 'success');
        // FIX-2e: cerrar toolbar en mobile para visualizar mejor el canvas
        if (window.innerWidth <= 599 && typeof closeTools === 'function') closeTools();
    }

    function clearPlacementTool() {
        pendingPlacementTool = null;
        pendingPlacementStart = null;
        if (pendingPlacementPreview) {
            pendingPlacementPreview.destroy();
            pendingPlacementPreview = null;
            if (konvaLayer) konvaLayer.batchDraw();
        }
        if (konvaStage && konvaStage.container()) konvaStage.container().style.cursor = 'default';
    }

    function addText() {
        setMode('smart');
        startPlacementTool('note');
    }

    function addCloud() {
        setMode('smart');
        showPropSection('cloud');
        syncCloudStrokeControl();
        if (!useKonvaRuler) return;
        startPlacementTool('cloud');
    }

    function setTextFixedColor(color, el) {
        document.querySelectorAll('#prop-text .color-dot, #m-prop-text .color-dot').forEach(d => d.classList.remove('active'));
        if (el) el.classList.add('active');
        document.querySelectorAll('#prop-text .color-dot, #m-prop-text .color-dot').forEach(d => {
            if ((d.getAttribute('data-col') || '').toLowerCase() === String(color).toLowerCase()) d.classList.add('active');
        });
        updateTextProp('fill', color);
    }

    const REPORT_ATTACH_MAX_FILES = 5;
    const REPORT_ATTACH_MAX_BYTES = 10 * 1024 * 1024;
    const REPORT_ATTACH_ALLOWED = [
        /^image\//,
        /^application\/pdf$/,
        /^application\/msword$/,
        /^application\/vnd\.openxmlformats-officedocument\./,
        /^application\/vnd\.ms-excel$/
    ];
    let reportAttachments = [];

    function isAllowedAttachmentType(file) {
        return REPORT_ATTACH_ALLOWED.some(rx => rx.test(file.type || ''));
    }

    function formatBytes(bytes) {
        if (!isFinite(bytes) || bytes <= 0) return '0 B';
        const units = ['B','KB','MB','GB'];
        let i = 0;
        let val = bytes;
        while (val >= 1024 && i < units.length - 1) { val /= 1024; i++; }
        return `${val.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
    }

    function getAttachmentIcon(file) {
        const type = (file.type || '').toLowerCase();
        if (type.startsWith('image/')) return 'fa-file-image';
        if (type === 'application/pdf') return 'fa-file-pdf';
        if (type.includes('word') || type.includes('officedocument.word')) return 'fa-file-word';
        if (type.includes('excel') || type.includes('spreadsheet')) return 'fa-file-excel';
        return 'fa-file';
    }

    function renderAttachmentPreview() {
        const box = document.getElementById('rep-attachments-preview');
        if (!box) return;
        box.innerHTML = '';
        reportAttachments.forEach((entry, idx) => {
            const row = document.createElement('div');
            row.className = 'd-flex align-items-center justify-content-between p-2 rounded';
            row.style.background = '#111827';
            row.style.border = '1px solid #334155';

            const left = document.createElement('div');
            left.className = 'd-flex align-items-center gap-2';

            if (entry.previewUrl) {
                const img = document.createElement('img');
                img.src = entry.previewUrl;
                img.alt = entry.file.name;
                img.style.width = '44px';
                img.style.height = '44px';
                img.style.objectFit = 'cover';
                img.style.borderRadius = '6px';
                left.appendChild(img);
            } else {
                const icon = document.createElement('i');
                icon.className = `fas ${getAttachmentIcon(entry.file)} text-accent`;
                left.appendChild(icon);
            }

            const meta = document.createElement('div');
            meta.className = 'small';
            meta.innerHTML = `<div class="text-white">${entry.file.name}</div><div class="text-muted">${formatBytes(entry.file.size)}</div>`;
            left.appendChild(meta);

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-danger';
            btn.innerHTML = '<i class="fas fa-times"></i>';
            btn.onclick = () => {
                if (entry.previewUrl) URL.revokeObjectURL(entry.previewUrl);
                reportAttachments.splice(idx, 1);
                renderAttachmentPreview();
            };

            row.appendChild(left);
            row.appendChild(btn);
            box.appendChild(row);
        });
    }

    function addReportAttachments(fileList) {
        const files = Array.from(fileList || []);
        for (const file of files) {
            if (reportAttachments.length >= REPORT_ATTACH_MAX_FILES) {
                showToast('Maximum 5 attachments per report', 'warning');
                break;
            }
            if (!isAllowedAttachmentType(file)) {
                showToast(`Unsupported file type: ${file.name}`, 'error');
                continue;
            }
            if (file.size > REPORT_ATTACH_MAX_BYTES) {
                showToast(`File exceeds 10MB: ${file.name}`, 'error');
                continue;
            }
            const previewUrl = (file.type || '').startsWith('image/') ? URL.createObjectURL(file) : null;
            reportAttachments.push({ file, previewUrl });
        }
        renderAttachmentPreview();
    }

    function initReportAttachmentUI() {
        const input = document.getElementById('rep-attachments');
        const drop = document.getElementById('rep-attach-dropzone');
        if (!input || !drop || input.dataset.bound === '1') return;

        input.addEventListener('change', (e) => {
            addReportAttachments(e.target.files);
            input.value = '';
        });

        ['dragenter', 'dragover'].forEach(evt => {
            drop.addEventListener(evt, (e) => {
                e.preventDefault();
                e.stopPropagation();
                drop.style.borderColor = '#22c55e';
            });
        });

        ['dragleave', 'drop'].forEach(evt => {
            drop.addEventListener(evt, (e) => {
                e.preventDefault();
                e.stopPropagation();
                drop.style.borderColor = '#475569';
            });
        });

        drop.addEventListener('drop', (e) => {
            addReportAttachments(e.dataTransfer?.files || []);
        });

        input.dataset.bound = '1';
    }

    function resetReportAttachments() {
        reportAttachments.forEach(a => { if (a.previewUrl) URL.revokeObjectURL(a.previewUrl); });
        reportAttachments = [];
        renderAttachmentPreview();
    }

    function openReportModal() {
        saveCurrentPageAnnotations();
        initReportAttachmentUI();
        resetReportAttachments();
        new bootstrap.Modal(document.getElementById('reportModal')).show();
    }

    async function captureEditorSnapshot() {
        if (!konvaStage) return null;
        try {
            return await new Promise((resolve, reject) => {
                konvaStage.toDataURL({
                    mimeType: 'image/jpeg',
                    quality: 0.92,
                    pixelRatio: 1.5,
                    callback: (dataUrl) => {
                        if (dataUrl && dataUrl.length > 100) resolve(dataUrl);
                        else reject(new Error('empty'));
                    }
                });
            });
        } catch (_) {
            try {
                const wrapper = document.getElementById('canvas-wrapper');
                const offscreen = document.createElement('canvas');
                offscreen.width = wrapper.clientWidth;
                offscreen.height = wrapper.clientHeight;
                const ctx = offscreen.getContext('2d');
                konvaStage.getLayers().forEach(layer => {
                    const lc = layer.getCanvas()?._canvas;
                    if (lc) ctx.drawImage(lc, 0, 0);
                });
                return offscreen.toDataURL('image/jpeg', 0.92);
            } catch (e2) {
                return null;
            }
        }
    }

    async function submitReport() {
        const btn = document.getElementById('btn-generate');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Generating...';
        try {
            const { jsPDF } = window.jspdf;
            const dataUrl = await captureEditorSnapshot();
            const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
            const PW = doc.internal.pageSize.getWidth();
            const PH = doc.internal.pageSize.getHeight();
            const MARGIN = 18;
            const CONTENT_W = PW - MARGIN * 2;

            const techName = document.getElementById('rep-name').value.trim() || 'Unknown';
            const techRole = document.getElementById('rep-role').value.trim() || 'Technician';
            const desc = document.getElementById('rep-desc').value.trim();
            const dateStr = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            const timeStr = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

            doc.setFillColor(251, 90, 58);
            doc.rect(0, 0, PW, 28, 'F');
            doc.setTextColor(255, 255, 255);
            doc.setFontSize(18);
            doc.setFont('helvetica', 'bold');
            doc.text('FIELD ACTIVITY REPORT', MARGIN, 12);
            doc.setFontSize(9);
            doc.setFont('helvetica', 'normal');
            doc.text('Brightronix — Electroplan', MARGIN, 20);
            doc.text(dateStr + ' · ' + timeStr, PW - MARGIN, 20, { align: 'right' });

            let y = 38;
            doc.setFillColor(36, 42, 56);
            doc.roundedRect(MARGIN, y, CONTENT_W, 38, 3, 3, 'F');
            doc.setTextColor(148, 163, 184);
            doc.setFontSize(7);
            doc.setFont('helvetica', 'bold');
            doc.text('PROJECT', MARGIN + 8, y + 8);
            doc.text('FILE', MARGIN + (CONTENT_W / 3) + 8, y + 8);
            doc.text('PAGE', MARGIN + (CONTENT_W * 2 / 3) + 8, y + 8);
            doc.setTextColor(255, 255, 255);
            doc.setFontSize(10);
            doc.setFont('helvetica', 'bold');

            const projName = doc.splitTextToSize('<?= addslashes($projectName) ?>', CONTENT_W / 3 - 16);
            doc.text(projName, MARGIN + 8, y + 16);
            const fname = doc.splitTextToSize('<?= addslashes($file['filename']) ?>', CONTENT_W / 3 - 16);
            doc.text(fname, MARGIN + (CONTENT_W / 3) + 8, y + 16);
            doc.text('Sheet ' + (typeof pageNum !== 'undefined' ? pageNum : '1'), MARGIN + (CONTENT_W * 2 / 3) + 8, y + 16);

            doc.setDrawColor(47, 56, 74);
            doc.line(MARGIN + CONTENT_W / 3, y + 3, MARGIN + CONTENT_W / 3, y + 35);
            doc.line(MARGIN + CONTENT_W * 2 / 3, y + 3, MARGIN + CONTENT_W * 2 / 3, y + 35);

            y += 48;
            doc.setTextColor(251, 90, 58);
            doc.setFontSize(8);
            doc.setFont('helvetica', 'bold');
            doc.text('TECHNICIAN', MARGIN, y);
            doc.setDrawColor(251, 90, 58);
            doc.line(MARGIN, y + 1.5, MARGIN + 30, y + 1.5);
            y += 7;
            doc.setTextColor(255, 255, 255);
            doc.setFontSize(12);
            doc.setFont('helvetica', 'bold');
            doc.text(techName, MARGIN, y);
            doc.setFontSize(9);
            doc.setFont('helvetica', 'normal');
            doc.setTextColor(148, 163, 184);
            doc.text(techRole.charAt(0).toUpperCase() + techRole.slice(1), MARGIN, y + 6);

            y += 20;
            doc.setTextColor(251, 90, 58);
            doc.setFontSize(8);
            doc.setFont('helvetica', 'bold');
            doc.text('ACTIVITY DESCRIPTION', MARGIN, y);
            doc.setDrawColor(251, 90, 58);
            doc.line(MARGIN, y + 1.5, MARGIN + 52, y + 1.5);
            y += 8;
            doc.setTextColor(220, 220, 220);
            doc.setFontSize(10);
            doc.setFont('helvetica', 'normal');
            const splitDesc = doc.splitTextToSize(desc || '(No description provided)', CONTENT_W);
            doc.text(splitDesc, MARGIN, y);
            y += splitDesc.length * 5.5 + 6;

            if (reportAttachments.length > 0) {
                doc.setTextColor(251, 90, 58);
                doc.setFontSize(8);
                doc.setFont('helvetica', 'bold');
                doc.text('ATTACHMENTS', MARGIN, y);
                doc.setDrawColor(251, 90, 58);
                doc.line(MARGIN, y + 1.5, MARGIN + 36, y + 1.5);
                y += 8;
                doc.setTextColor(200, 200, 200);
                doc.setFontSize(9);
                doc.setFont('helvetica', 'normal');
                reportAttachments.forEach(a => {
                    doc.text('· ' + a.file.name, MARGIN + 4, y);
                    y += 5.5;
                });
                y += 4;
            }

            doc.setFillColor(36, 42, 56);
            doc.rect(0, PH - 14, PW, 14, 'F');
            doc.setTextColor(148, 163, 184);
            doc.setFontSize(7);
            doc.setFont('helvetica', 'normal');
            doc.text('Brightronix · Electroplan · Field Activity Report', MARGIN, PH - 5);
            doc.text('Page 1 of 2', PW - MARGIN, PH - 5, { align: 'right' });

            doc.addPage();
            doc.setFillColor(251, 90, 58);
            doc.rect(0, 0, PW, 20, 'F');
            doc.setTextColor(255, 255, 255);
            doc.setFontSize(13);
            doc.setFont('helvetica', 'bold');
            doc.text('PLAN SNAPSHOT — Annotated View', MARGIN, 13);

            if (dataUrl) {
                const imgProps = doc.getImageProperties(dataUrl);
                const maxW = CONTENT_W;
                const maxH = PH - 40;
                let imgW = maxW;
                let imgH = (imgProps.height * imgW) / imgProps.width;
                if (imgH > maxH) {
                    imgH = maxH;
                    imgW = (imgProps.width * imgH) / imgProps.height;
                }
                const imgX = MARGIN + (CONTENT_W - imgW) / 2;
                doc.addImage(dataUrl, 'JPEG', imgX, 26, imgW, imgH);
            } else {
                doc.setTextColor(148, 163, 184);
                doc.setFontSize(10);
                doc.text('(Snapshot not available)', MARGIN, 40);
            }

            doc.setFillColor(36, 42, 56);
            doc.rect(0, PH - 14, PW, 14, 'F');
            doc.setTextColor(148, 163, 184);
            doc.setFontSize(7);
            doc.text('Brightronix · Electroplan · Field Activity Report', MARGIN, PH - 5);
            doc.text('Page 2 of 2', PW - MARGIN, PH - 5, { align: 'right' });

            const pdfBlob = doc.output('blob');
            const annotationsJson = JSON.stringify(allAnnotations);
            const fd = new FormData();
            fd.append('action', 'save_report_flow');
            fd.append('file_id', fileId);
            fd.append('pdf_file', pdfBlob);
            fd.append('annotations_json', annotationsJson);
            fd.append('tech_name', techName);
            fd.append('tech_role', techRole);
            fd.append('description', desc);
            reportAttachments.forEach(a => fd.append('attachments[]', a.file, a.file.name));

            const res = await fetch('../api/api.php', { method: 'POST', body: fd });
            const d = await res.json();
            if (d.status === 'success') {
                showToast("Report saved successfully!", "success");
                setTimeout(() => location.href = "preview.php?id=" + fileId, 1500);
            } else {
                showToast("Error saving report: " + d.msg, "error");
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check"></i> Generate Report';
            }
        } catch (e) {
            console.error(e);
            showToast("Critical Error generating report", "error");
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> Generate Report';
        }
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
        if (!konvaSelectedNote || !konvaSelectedNote.label) return;
        if (prop === 'fill') {
                konvaSelectedNote.label.fill(val);
                // FIX-BUG2 / NEW-FEAT1: sincronizar color de fondo sticky al cambiar color de texto
                const bgMap = {
                    '#ef4444': 'rgba(239,68,68,0.22)',
                    '#3b82f6': 'rgba(59,130,246,0.22)',
                    '#22c55e': 'rgba(34,197,94,0.22)',
                    '#eab308': 'rgba(234,179,8,0.22)',
                    '#ec4899': 'rgba(236,72,153,0.24)',
                    '#f97316': 'rgba(249,115,22,0.24)',
                    '#8b5cf6': 'rgba(139,92,246,0.24)',
                    '#ffffff': 'rgba(255,255,255,0.22)'
                };
                const normalized = String(val || '').toLowerCase();
                const bgFill = bgMap[normalized] || 'rgba(255,255,0,0.30)';
                konvaSelectedNote.bgFill = bgFill;
                if (konvaSelectedNote.bg) {
                    konvaSelectedNote.bg.fill(bgFill);
                    konvaSelectedNote.bg.stroke(val);
                }
            }
        if (prop === 'fontSize') {
            const nextSize = parseInt(val, 10) || konvaSelectedNote.label.fontSize();
            konvaSelectedNote.label.fontSize(nextSize);
            syncTextSizeInput(nextSize);
        }
        updateKonvaNoteBox(konvaSelectedNote);
        if (konvaLayer) konvaLayer.batchDraw();
        saveCurrentPageAnnotations();
    }

    function updateTextScales(zoom) {
        // Las reglas/anotaciones Konva escalan vía syncKonvaToFabric
        syncKonvaToFabric();
    }

    // REMOVED: selección/transformaciones Fabric legacy

    window.addEventListener('keydown', e => {
        if ((e.ctrlKey || e.metaKey) && e.key === 'z') { e.preventDefault(); undo(); }
        if ((e.ctrlKey || e.metaKey) && e.key === 'y') { e.preventDefault(); redo(); }
        if (e.key === 'Delete' || e.key === 'Backspace') {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            e.preventDefault();
            deleteSelected();
        }
    });

    // REMOVED: wheel de Fabric, se usa wheel listener de Konva

    // UI Isaac_work: auto mostrar barra de herramientas en móvil al cargar
    document.addEventListener('DOMContentLoaded', () => {
        initKonvaRuler();
        // En mobile iniciamos con herramientas cerradas para no bloquear el plano.
        if (window.innerWidth <= 599) {
            closeTools();
        }
    });

    // No persistir al refrescar/salir: solo persiste con Save (save_report_flow).

</script>
</body>
</html>
