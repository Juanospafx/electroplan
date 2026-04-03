<?php
// timeline.php - Historial Global V1.6 (Breadcrumbs + Estructura Fixed)
require_once __DIR__ . '/../core/auth/session.php';
require_once __DIR__ . '/../core/db/connection.php';
require_once __DIR__ . '/../core/time.php'; 

// =========================================================
// 1. DEFINICIÓN DE USUARIO Y ROLES
// =========================================================
$userId = $_SESSION['user_id'];
$userName = $_SESSION['username'];
$userRoleRaw = $_SESSION['role'] ?? 'viewer'; 
$userRole = strtolower($userRoleRaw); 

// Definir permisos
$isAdmin = ($userRole === 'admin');
$canCreate = $isAdmin;
$canDelete = $isAdmin; 
$canUpload = $isAdmin;

// --- LOGICA DE FILTRO DE FECHA ---
$filterDate = $_GET['filter_date'] ?? '';
$params = [];

// Consulta Base
$baseSql = "
    SELECT * FROM (
        (SELECT 
            'project' as type, 
            p.id as ref_id, 
            p.name as title, 
            p.description as subtitle, 
            p.created_at as activity_date, 
            u.username as user_name,
            u.role as user_role
         FROM projects p 
         LEFT JOIN users u ON p.created_by = u.id)

        UNION

        (SELECT 
            'file' as type, 
            f.id as ref_id, 
            f.filename as title, 
            prj.name as subtitle, 
            f.uploaded_at as activity_date, 
            u.username as user_name,
            u.role as user_role
         FROM files f 
         LEFT JOIN users u ON f.uploaded_by = u.id
         LEFT JOIN projects prj ON f.project_id = prj.id)

        UNION

        (SELECT 
            'report' as type, 
            fr.file_id as ref_id, 
            'Field Report Generated' as title, 
            fr.description as subtitle, 
            fr.created_at as activity_date, 
            fr.technician_name as user_name,
            fr.technician_role as user_role
         FROM file_reports fr)
    ) AS history
";

// Aplicar Filtro si existe
if (!empty($filterDate)) {
    $baseSql .= " WHERE DATE(activity_date) = ? ";
    $params[] = $filterDate;
}

$baseSql .= " ORDER BY activity_date DESC LIMIT 50";

$stmt = $pdo->prepare($baseSql);
$stmt->execute($params);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// INCLUIR CABEZAL
$pageTitle = "Timeline | Brightronix";
include __DIR__ . '/../views/header.php'; 
?>

    <main class="main-content">
        <style>
            :root {
                /* Paleta Dark Mode (Deep Matte) */
                --bg-body: #1b212d;
                --bg-card: #242a38;
                --bg-input: #151a23;
                --primary: #fb5a3a;
                --primary-hover: #e14e32;
                --text-white: #ffffff;
                --text-gray: #94a3b8;
                --text-muted: #58657a;
                --border-subtle: #2f384a;
                --radius-box: 20px;
            }

             /* ESTILOS TIMELINE */
            .timeline-container { position: relative; max-width: 800px; margin-left: 10px; }
            .timeline-container::before { content: ''; position: absolute; top: 0; bottom: 0; left: 24px; width: 2px; background: var(--border-subtle); border-radius: 2px; }
            .timeline-item { position: relative; padding-left: 60px; margin-bottom: 30px; }
            .timeline-icon { position: absolute; left: 0; top: 0; width: 50px; height: 50px; border-radius: 50%; background: var(--bg-body); border: 2px solid var(--border-subtle); display: flex; align-items: center; justify-content: center; z-index: 2; color: var(--text-gray); font-size: 1.1rem; transition: 0.3s; }
            .timeline-item:hover .timeline-icon { border-color: var(--primary); color: white; background: var(--primary); box-shadow: 0 0 15px rgba(251, 90, 58, 0.4); }
            .timeline-card { background: var(--bg-card); border-radius: var(--radius-box); padding: 20px 25px; border: 1px solid var(--border-subtle); transition: 0.3s; }
            .timeline-card:hover { transform: translateY(-3px); border-color: var(--primary); background: var(--bg-input); }
            .time-badge { font-size: 0.75rem; color: var(--text-gray); margin-bottom: 8px; display: block; text-transform: uppercase; letter-spacing: 0.5px; }
            .activity-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 5px; color: var(--text-white); }
            .activity-desc { color: var(--text-gray); font-size: 0.9rem; }
            .user-mini { display: flex; align-items: center; gap: 8px; margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border-subtle); }
            .user-mini div.av { width: 24px; height: 24px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; font-weight: bold; }
            .text-muted { color: var(--text-gray) !important; }
            .user-role-badge { background: var(--bg-body); padding: 2px 8px; border-radius: 10px; font-size: 0.65rem; margin-left: auto; color: var(--text-gray); border: 1px solid var(--border-subtle); }
            /* ESTILO PARA SEPARADOR DE FECHAS */
            .date-separator { position: relative; margin: 40px 0 30px 0; padding-left: 60px; }
            .date-separator::before { content: ''; position: absolute; left: 19px; top: 50%; width: 12px; height: 12px; background: var(--primary); border-radius: 50%; border: 3px solid var(--bg-body); z-index: 3; transform: translateY(-50%); }
            .date-label { display: inline-block; background: rgba(251, 90, 58, 0.1); color: var(--primary); padding: 5px 15px; border-radius: 20px; font-weight: 700; font-size: 0.85rem; border: 1px solid rgba(251, 90, 58, 0.2); }

            .form-control {
                background: var(--bg-input) !important;
                border: 1px solid var(--border-subtle) !important;
                color: var(--text-white) !important;
                border-radius: 10px;
            }
            .form-control::placeholder {
                color: var(--text-gray) !important;
                opacity: 1; /* Soporte para Firefox */
            }
            .form-control:focus {
                border-color: var(--primary) !important;
                box-shadow: 0 0 0 3px rgba(251, 90, 58, 0.2) !important;
            }
            /* Estilo para el icono del calendario nativo */
            .form-control::-webkit-calendar-picker-indicator {
                filter: invert(0.8);
                cursor: pointer;
            }

            .btn-primary {
                background-color: var(--primary) !important;
                border-color: var(--primary) !important;
            }
            .btn-primary:hover {
                background-color: var(--primary-hover) !important;
                border-color: var(--primary-hover) !important;
            }

            body.theme-light {
                --bg-body: #e2e8f0;
                --bg-card: #ffffff;
                --bg-input: #f8fafc;
                --text-white: #0f172a;
                --text-gray: #64748b;
                --text-muted: #94a3b8;
                --border-subtle: #ffffff;
            }
            body.theme-light .form-control::-webkit-calendar-picker-indicator { filter: none; }
            body.theme-light .bg-dark { background-color: var(--bg-card) !important; color: var(--text-white) !important; border-color: var(--border-subtle) !important; }

            /* ESTILOS COMPACTOS PARA EL CALENDARIO (FLATPICKR) */
            .flatpickr-calendar {
                width: 280px !important;
                background: var(--bg-card) !important;
                border: 1px solid var(--border-subtle) !important;
                box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.5) !important;
                border-radius: 12px !important;
                font-family: 'Poppins', sans-serif !important;
                padding: 5px;
            }
            .flatpickr-months .flatpickr-month, 
            .flatpickr-current-month .flatpickr-monthDropdown-months,
            .flatpickr-current-month input.cur-year,
            .flatpickr-months .flatpickr-prev-month, .flatpickr-months .flatpickr-next-month {
                color: var(--text-white) !important;
                fill: var(--text-white) !important;
            }
            /* Ajuste dinámico de la lista de meses */
            .flatpickr-monthDropdown-months {
                background: var(--bg-card) !important;
                color: var(--text-white) !important;
                font-size: 0.85rem !important;
                font-weight: 600 !important;
            }
            .flatpickr-monthDropdown-month {
                background: var(--bg-card);
                color: var(--text-white);
            }
            .flatpickr-current-month {
                display: flex !important;
                align-items: center;
                justify-content: center;
                gap: 4px;
            }
            .flatpickr-current-month input.cur-year {
                font-size: 0.85rem !important;
                font-weight: 600 !important;
                text-align: center;
            }
            .flatpickr-weekdays, span.flatpickr-weekday {
                color: var(--text-gray) !important;
                font-size: 0.75rem !important;
            }
            .dayContainer { width: 270px !important; min-width: 270px !important; max-width: 270px !important; }
            .flatpickr-day {
                color: var(--text-white) !important;
                font-size: 0.85rem !important; /* Ajustado para que coincida con el tamaño del mes */
                border-radius: 6px !important;
                max-width: 36px !important;
                height: 36px !important;
                line-height: 36px !important;
            }
            .flatpickr-day:hover {
                background: var(--bg-input) !important;
                border-color: var(--border-subtle) !important;
            }
            .flatpickr-day.selected, 
            .flatpickr-day.selected:hover, 
            .flatpickr-day.selected:focus {
                background: var(--primary) !important;
                border-color: var(--primary) !important;
                color: white !important;
            }
            .flatpickr-day.today { border-color: var(--primary) !important; }
            .flatpickr-day.flatpickr-disabled { color: var(--text-muted) !important; }

            /* Opacidad para días que no corresponden al mes actual */
            .flatpickr-day.prevMonthDay,
            .flatpickr-day.nextMonthDay {
                opacity: 0.3 !important;
            }
        </style>
        <!-- Flatpickr CSS -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

        <header class="header">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="breadcrumbs">
                    <a href="index.php">Home</a>
                    <i class="fas fa-chevron-right mx-2" style="font-size:0.7rem"></i>
                    <span>Timeline</span>
                </div>
            </div>

            <div class="user-pill">
                <div class="avatar"><?= strtoupper(substr($userName,0,1)) ?></div>
                <span class="small fw-bold"><?= htmlspecialchars($userName) ?></span>
            </div>
        </header>

        <div class="d-flex justify-content-between align-items-end mb-5">
            <div>
                 <h2 class="fw-bold m-0">Activity Timeline</h2>
                 <p class="text-gray mb-0">Track all actions performed in the system.</p>
            </div>
            
            <form method="GET" class="d-flex align-items-center gap-2">
                <input type="text" name="filter_date" id="activityDatePicker" class="form-control form-control-sm" placeholder="Select date..." value="<?= htmlspecialchars($filterDate) ?>">
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i></button>
                <?php if(!empty($filterDate)): ?>
                    <a href="timeline.php" class="btn btn-sm btn-outline-secondary" title="Clear"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </form>
        </div>

        <div class="timeline-container">
            <?php 
            $currentDateGroup = ''; 
            
            foreach($activities as $act): 
                $icon = 'fa-circle'; $link = '#';
                if($act['type'] == 'project') { $icon = 'fa-layer-group'; $colorClass = 'text-primary'; $actionText = 'New Project Created'; $link = "index.php?project_id=" . $act['ref_id']; } 
                elseif($act['type'] == 'file') { $icon = 'fa-file-upload'; $colorClass = 'text-info'; $actionText = 'File Uploaded'; $link = "preview.php?id=" . $act['ref_id']; } 
                elseif($act['type'] == 'report') { $icon = 'fa-clipboard-check'; $colorClass = 'text-success'; $actionText = 'Field Report Submitted'; $link = "preview.php?id=" . $act['ref_id']; }
                
                // LOGICA DE AGRUPACIÓN POR DIA
                $actDateObj = new DateTime($act['activity_date']); // PHP usará el $appTimeZone definido en functions.php
                $actDateStr = $actDateObj->format('Y-m-d');
                
                if($actDateStr !== $currentDateGroup):
                    $today = date('Y-m-d');
                    $yesterday = date('Y-m-d', strtotime('-1 day'));
                    
                    if($actDateStr === $today) $label = "Today";
                    elseif($actDateStr === $yesterday) $label = "Yesterday";
                    else $label = $actDateObj->format('l, F j, Y');
                    
                    echo '<div class="date-separator"><span class="date-label">'. $label .'</span></div>';
                    $currentDateGroup = $actDateStr;
                endif;
            ?>
            
            <div class="timeline-item">
                <div class="timeline-icon"><i class="fas <?= $icon ?>"></i></div>
                <a href="<?= $link ?>" class="timeline-card d-block text-decoration-none">
                    <span class="time-badge"><i class="far fa-clock me-1"></i> <?= time_elapsed_string($act['activity_date']) ?> <span class="opacity-50 mx-1">|</span> <?= $actDateObj->format('h:i A') ?></span>
                    <div class="activity-title"><?= htmlspecialchars($act['title']) ?></div>
                    <div class="activity-desc"><?= $act['type'] == 'file' ? 'Uploaded to: ' : '' ?><?= htmlspecialchars($act['subtitle'] ?: 'No additional details.') ?></div>
                    <div class="user-mini">
                        <div class="av"><?= strtoupper(substr($act['user_name'] ?? 'U', 0, 1)) ?></div>
                        <div class="small fw-bold text-white"><?= htmlspecialchars($act['user_name'] ?? 'System') ?></div>
                        <div class="small text-muted ms-1">performed action: <span class="<?= $colorClass ?>"><?= $actionText ?></span></div>
                        <div class="user-role-badge"><?= strtoupper($act['user_role'] ?? '') ?></div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>

            <?php if(empty($activities)): ?>
                <div class="text-center py-5 text-gray">
                    <i class="fas fa-search fa-3x mb-3 opacity-25"></i>
                    <p>No activity found for this criteria.</p>
                    <?php if(!empty($filterDate)): ?><a href="timeline.php" class="text-primary small">Clear filters</a><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            flatpickr("#activityDatePicker", {
                dateFormat: "Y-m-d",
                altInput: true,
                altFormat: "F j, Y",
                disableMobile: true, // Forza el uso del calendario custom en móviles
                minDate: "2025-01-01", // Solo permite seleccionar fechas desde 2025 en adelante
                position: "below center" // Centra el calendario horizontalmente bajo el input
            });
        });
    </script>

<?php include __DIR__ . '/../views/footer.php'; ?>