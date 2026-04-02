<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid id']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$project = $stmt->fetch();

if (!$project) {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

$stmtPanels = $pdo->prepare("SELECT id, item_order, panel_name, voltage, phase, poles, panel_type,
                                    main_size_type, mounting, connected_kva, connected_amps,
                                    demand_kva, demand_amps, percent_imbal, minimum_feeder_size
                             FROM panelboards WHERE project_id = :id ORDER BY item_order ASC");
$stmtPanels->execute([':id' => $id]);
$panels = $stmtPanels->fetchAll();

echo json_encode(['project' => $project, 'panels' => $panels]);
