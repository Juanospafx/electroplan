<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$stmt = $pdo->query("SELECT project_number FROM projects WHERE project_number REGEXP '^A[0-9]{6}$' ORDER BY project_number DESC LIMIT 1");
$last = $stmt->fetchColumn();

if ($last) {
    $num = (int)substr($last, 1);
    $next = 'A' . str_pad((string)($num + 1), 6, '0', STR_PAD_LEFT);
} else {
    $next = 'A000001';
}

echo json_encode(['project_number' => $next]);
