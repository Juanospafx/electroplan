<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT id, project_name, project_number, basis_of_design, service_voltage, service_amps,
               service_kva, total_panels, last_update
        FROM projects";
$params = [];

if ($search !== '') {
    $sql .= " WHERE project_number LIKE :q OR project_name LIKE :q";
    $params[':q'] = '%' . $search . '%';
}

$sql .= " ORDER BY updated_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

echo json_encode(['data' => $rows]);
