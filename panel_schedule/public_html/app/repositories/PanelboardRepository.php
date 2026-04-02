<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Lib\Database;

class PanelboardRepository
{
    public function listByProject(int $projectId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM panelboards WHERE project_id = :id ORDER BY item_order ASC');
        $stmt->execute([':id' => $projectId]);
        return $stmt->fetchAll();
    }

    public function nextOrderForProject(int $projectId): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(item_order), 0) AS max_order FROM panelboards WHERE project_id = :id');
        $stmt->execute([':id' => $projectId]);
        $max = (int)$stmt->fetchColumn();
        return $max + 1;
    }

    public function find(int $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM panelboards WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO panelboards
            (project_id, item_order, panel_name, panel_status, voltage, phase_wire, poles_config, panel_type, main_type, main_size_type,
             mounting, connected_kva, connected_amps, demand_kva, demand_amps, percent_imbalance,
             minimum_feeder_size, schedule_json, last_update, created_at, updated_at)
            VALUES
            (:project_id, :item_order, :panel_name, :panel_status, :voltage, :phase_wire, :poles_config, :panel_type, :main_type, :main_size_type,
             :mounting, :connected_kva, :connected_amps, :demand_kva, :demand_amps, :percent_imbalance,
             :minimum_feeder_size, :schedule_json, :last_update, :created_at, :updated_at)');

        $stmt->execute($data);
        return (int)$pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $pdo = Database::connection();
        $data[':id'] = $id;
        $stmt = $pdo->prepare('UPDATE panelboards SET
            panel_name = :panel_name,
            panel_status = :panel_status,
            voltage = :voltage,
            phase_wire = :phase_wire,
            poles_config = :poles_config,
            panel_type = :panel_type,
            main_type = :main_type,
            main_size_type = :main_size_type,
            mounting = :mounting,
            connected_kva = :connected_kva,
            connected_amps = :connected_amps,
            demand_kva = :demand_kva,
            demand_amps = :demand_amps,
            percent_imbalance = :percent_imbalance,
            minimum_feeder_size = :minimum_feeder_size,
            schedule_json = :schedule_json,
            last_update = :last_update,
            updated_at = :updated_at
            WHERE id = :id');

        $stmt->execute($data);
    }

    public function updateSchedule(int $id, string $scheduleJson, string $updatedAt): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE panelboards SET schedule_json = :schedule_json, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            ':schedule_json' => $scheduleJson,
            ':updated_at' => $updatedAt,
            ':id' => $id,
        ]);
    }

    public function updateTotals(int $id, array $data): void
    {
        $pdo = Database::connection();
        $data[':id'] = $id;
        $stmt = $pdo->prepare('UPDATE panelboards SET
            connected_kva = :connected_kva,
            connected_amps = :connected_amps,
            demand_kva = :demand_kva,
            demand_amps = :demand_amps,
            percent_imbalance = :percent_imbalance,
            balance_status = :balance_status,
            balance_message = :balance_message,
            last_update = :last_update,
            updated_at = :updated_at
            WHERE id = :id');

        $stmt->execute($data);
    }

    public function updateCalculations(int $id, array $calcs): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE panelboards SET 
            connected_kva = :ck, 
            connected_amps = :ca, 
            demand_kva = :dk, 
            demand_amps = :da, 
            percent_imbalance = :pi,
            balance_status = :bs,
            balance_message = :bm,
            updated_at = :upd
            WHERE id = :id');
        
        $stmt->execute([
            ':ck' => $calcs['connected_kva'],
            ':ca' => $calcs['connected_amps'],
            ':dk' => $calcs['demand_kva'],
            ':da' => $calcs['demand_amps'],
            ':pi' => $calcs['percent_imbalance'],
            ':bs' => $calcs['balance_status'],
            ':bm' => $calcs['balance_message'],
            ':upd' => date('Y-m-d H:i:s'),
            ':id' => $id
        ]);
    }

    public function delete(int $id): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM panelboards WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
