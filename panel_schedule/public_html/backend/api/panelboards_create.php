<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$projectName = trim($data['project_name'] ?? '');
$projectNumber = trim($data['project_number'] ?? '');
$basisOfDesign = trim($data['basis_of_design'] ?? '');
$serviceVoltage = trim($data['service_voltage'] ?? '');
$serviceAmps = isset($data['service_amps']) && $data['service_amps'] !== '' ? (float)$data['service_amps'] : null;
$serviceKva = isset($data['service_kva']) && $data['service_kva'] !== '' ? (float)$data['service_kva'] : null;
$totalPanels = isset($data['total_panels']) && $data['total_panels'] !== '' ? (int)$data['total_panels'] : null;
$lastUpdate = trim($data['last_update'] ?? '');
$loadLighting = isset($data['load_lighting']) && $data['load_lighting'] !== '' ? (float)$data['load_lighting'] : null;
$loadRecept = isset($data['load_recept']) && $data['load_recept'] !== '' ? (float)$data['load_recept'] : null;
$loadCooling = isset($data['load_cooling']) && $data['load_cooling'] !== '' ? (float)$data['load_cooling'] : null;
$loadHeating = isset($data['load_heating']) && $data['load_heating'] !== '' ? (float)$data['load_heating'] : null;
$loadMotors = isset($data['load_motors']) && $data['load_motors'] !== '' ? (float)$data['load_motors'] : null;
$loadLgMtr = isset($data['load_lg_mtr']) && $data['load_lg_mtr'] !== '' ? (float)$data['load_lg_mtr'] : null;
$loadEquip = isset($data['load_equip']) && $data['load_equip'] !== '' ? (float)$data['load_equip'] : null;
$panels = is_array($data['panels'] ?? null) ? $data['panels'] : [];

if ($projectName === '' || $projectNumber === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Project Name and Project Number are required']);
    exit;
}

if ($lastUpdate === '') {
    $lastUpdate = date('Y-m-d');
}
$now = date('Y-m-d H:i:s');

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO projects
        (project_name, project_number, basis_of_design, service_voltage, service_amps, service_kva,
         total_panels, last_update, load_lighting, load_recept, load_cooling, load_heating,
         load_motors, load_lg_mtr, load_equip, created_at, updated_at)
        VALUES (:project_name, :project_number, :basis_of_design, :service_voltage, :service_amps, :service_kva,
                :total_panels, :last_update, :load_lighting, :load_recept, :load_cooling, :load_heating,
                :load_motors, :load_lg_mtr, :load_equip, :created_at, :updated_at)");

    $stmt->execute([
        ':project_name' => $projectName,
        ':project_number' => $projectNumber,
        ':basis_of_design' => $basisOfDesign,
        ':service_voltage' => $serviceVoltage,
        ':service_amps' => $serviceAmps,
        ':service_kva' => $serviceKva,
        ':total_panels' => $totalPanels,
        ':last_update' => $lastUpdate,
        ':load_lighting' => $loadLighting,
        ':load_recept' => $loadRecept,
        ':load_cooling' => $loadCooling,
        ':load_heating' => $loadHeating,
        ':load_motors' => $loadMotors,
        ':load_lg_mtr' => $loadLgMtr,
        ':load_equip' => $loadEquip,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);

    $projectId = (int)$pdo->lastInsertId();

    if (!empty($panels)) {
        $panelStmt = $pdo->prepare("INSERT INTO panelboards
            (project_id, item_order, panel_name, voltage, phase, poles, panel_type, main_size_type, mounting,
             connected_kva, connected_amps, demand_kva, demand_amps, percent_imbal, minimum_feeder_size,
             created_at, updated_at)
            VALUES (:project_id, :item_order, :panel_name, :voltage, :phase, :poles, :panel_type, :main_size_type, :mounting,
                    :connected_kva, :connected_amps, :demand_kva, :demand_amps, :percent_imbal, :minimum_feeder_size,
                    :created_at, :updated_at)");

        foreach ($panels as $panel) {
            $panelStmt->execute([
                ':project_id' => $projectId,
                ':item_order' => (int)($panel['item_order'] ?? 0),
                ':panel_name' => trim($panel['panel_name'] ?? ''),
                ':voltage' => trim($panel['voltage'] ?? ''),
                ':phase' => trim($panel['phase'] ?? ''),
                ':poles' => isset($panel['poles']) && $panel['poles'] !== '' ? (int)$panel['poles'] : null,
                ':panel_type' => trim($panel['panel_type'] ?? ''),
                ':main_size_type' => trim($panel['main_size_type'] ?? ''),
                ':mounting' => trim($panel['mounting'] ?? ''),
                ':connected_kva' => isset($panel['connected_kva']) && $panel['connected_kva'] !== '' ? (float)$panel['connected_kva'] : null,
                ':connected_amps' => isset($panel['connected_amps']) && $panel['connected_amps'] !== '' ? (float)$panel['connected_amps'] : null,
                ':demand_kva' => isset($panel['demand_kva']) && $panel['demand_kva'] !== '' ? (float)$panel['demand_kva'] : null,
                ':demand_amps' => isset($panel['demand_amps']) && $panel['demand_amps'] !== '' ? (float)$panel['demand_amps'] : null,
                ':percent_imbal' => isset($panel['percent_imbal']) && $panel['percent_imbal'] !== '' ? (float)$panel['percent_imbal'] : null,
                ':minimum_feeder_size' => trim($panel['minimum_feeder_size'] ?? ''),
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'id' => $projectId]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create']);
}
