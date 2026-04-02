<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Lib\Database;

class ProjectRepository
{
    public function all(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT * FROM projects ORDER BY updated_at DESC');
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM projects WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('INSERT INTO projects
            (project_name, project_number, basis_of_design, last_update, service_voltage, service_amps,
             service_kva, total_panels, load_lighting, load_recept, load_cooling, load_heating,
             load_motors, load_lg_mtr, load_equip, created_at, updated_at)
            VALUES
            (:project_name, :project_number, :basis_of_design, :last_update, :service_voltage, :service_amps,
             :service_kva, :total_panels, :load_lighting, :load_recept, :load_cooling, :load_heating,
             :load_motors, :load_lg_mtr, :load_equip, :created_at, :updated_at)');

        $stmt->execute($data);
        return (int)$pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $pdo = Database::connection();
        $data[':id'] = $id;
        $stmt = $pdo->prepare('UPDATE projects SET
            project_name = :project_name,
            project_number = :project_number,
            basis_of_design = :basis_of_design,
            last_update = :last_update,
            service_voltage = :service_voltage,
            service_amps = :service_amps,
            service_kva = :service_kva,
            total_panels = :total_panels,
            load_lighting = :load_lighting,
            load_recept = :load_recept,
            load_cooling = :load_cooling,
            load_heating = :load_heating,
            load_motors = :load_motors,
            load_lg_mtr = :load_lg_mtr,
            load_equip = :load_equip,
            updated_at = :updated_at
            WHERE id = :id');

        $stmt->execute($data);
    }

    public function updateTotals(int $id, float $totalKva, float $serviceAmps): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE projects SET
            service_kva = :service_kva,
            service_amps = :service_amps,
            updated_at = :updated_at
            WHERE id = :id');

        $stmt->execute([
            ':service_kva' => $totalKva,
            ':service_amps' => $serviceAmps,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':id' => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM projects WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
