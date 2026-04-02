<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Lib\View;
use App\Lib\Csrf;
use App\Repositories\PanelboardRepository;
use App\Repositories\ProjectRepository;
use App\Services\CalculationService;

class PanelsController
{
    private PanelboardRepository $panels;
    private ProjectRepository $projects;

    public function __construct()
    {
        $this->panels = new PanelboardRepository();
        $this->projects = new ProjectRepository();
    }

    public function create(string $projectId): void
    {
        $project = $this->projects->find((int)$projectId);
        if (!$project) {
            http_response_code(404);
            echo 'Project not found';
            return;
        }
        View::render('panels/create', ['project' => $project]);
    }

    public function store(string $projectId): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo 'Invalid CSRF token';
            return;
        }

        $panelName = trim($_POST['panel_name'] ?? '');
        if ($panelName === '') {
            http_response_code(422);
            echo 'Panel Name is required';
            return;
        }

        $now = date('Y-m-d H:i:s');
        $scheduleJson = $this->defaultScheduleJson($_POST['poles_config'] ?? '42');

        $nextOrder = $this->panels->nextOrderForProject((int)$projectId);
        $id = $this->panels->create([
            ':project_id' => (int)$projectId,
            ':item_order' => $nextOrder,
            ':panel_name' => $panelName,
            ':panel_status' => trim($_POST['panel_status'] ?? ''),
            ':voltage' => trim($_POST['voltage'] ?? ''),
            ':phase_wire' => trim($_POST['phase_wire'] ?? ''),
            ':poles_config' => trim($_POST['poles_config'] ?? ''),
            ':panel_type' => trim($_POST['panel_type'] ?? ''),
            ':main_type' => trim($_POST['main_type'] ?? ''),
            ':main_size_type' => '',
            ':mounting' => trim($_POST['mounting'] ?? ''),
            ':connected_kva' => null,
            ':connected_amps' => null,
            ':demand_kva' => null,
            ':demand_amps' => null,
            ':percent_imbalance' => null,
            ':minimum_feeder_size' => '',
            ':schedule_json' => $scheduleJson,
            ':last_update' => date('Y-m-d'),
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $calc = new CalculationService();
        $calc->recalculatePanel($id);
        $calc->recalculateProject((int)$projectId);

        header('Location: ' . base_url('/panels/' . $id . '/edit'));
    }

    public function edit(string $id): void
    {
        $panel = $this->panels->find((int)$id);
        if (!$panel) {
            http_response_code(404);
            echo 'Panel not found';
            return;
        }
        View::render('panels/edit', ['panel' => $panel]);
    }

    public function update(string $id): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo 'Invalid CSRF token';
            return;
        }

        $panelName = trim($_POST['panel_name'] ?? '');
        if ($panelName === '') {
            http_response_code(422);
            echo 'Panel Name is required';
            return;
        }

        $now = date('Y-m-d H:i:s');
        $existing = $this->panels->find((int)$id);
        if (!$existing) {
            http_response_code(404);
            echo 'Panel not found';
            return;
        }

        $newPoles = trim($_POST['poles_config'] ?? '');
        $scheduleJson = $existing['schedule_json'];

        // If poles config changed, update the JSON metadata so frontend syncs correctly
        if ($newPoles !== $existing['poles_config']) {
            $jsonDecoded = json_decode($scheduleJson, true);
            if (is_array($jsonDecoded)) {
                $jsonDecoded['poles_config'] = $newPoles;
                $scheduleJson = json_encode($jsonDecoded);
            }
        }

        $this->panels->update((int)$id, [
            ':panel_name' => $panelName,
            ':panel_status' => trim($_POST['panel_status'] ?? ''),
            ':voltage' => trim($_POST['voltage'] ?? ''),
            ':phase_wire' => trim($_POST['phase_wire'] ?? ''),
            ':poles_config' => $newPoles,
            ':panel_type' => trim($_POST['panel_type'] ?? ''),
            ':main_type' => trim($_POST['main_type'] ?? ''),
            ':main_size_type' => trim($_POST['main_size_type'] ?? ''),
            ':mounting' => trim($_POST['mounting'] ?? ''),
            ':connected_kva' => $existing['connected_kva'],
            ':connected_amps' => $existing['connected_amps'],
            ':demand_kva' => $existing['demand_kva'],
            ':demand_amps' => $existing['demand_amps'],
            ':percent_imbalance' => $existing['percent_imbalance'],
            ':minimum_feeder_size' => trim($_POST['minimum_feeder_size'] ?? ''),
            ':schedule_json' => $scheduleJson,
            ':last_update' => date('Y-m-d'),
            ':updated_at' => $now,
        ]);

        $calc = new CalculationService();
        $calc->recalculatePanel((int)$id);
        $calc->recalculateProject((int)$existing['project_id']);

        header('Location: ' . base_url('/panels/' . $id . '/edit'));
    }

    public function delete(string $id): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo 'Invalid CSRF token';
            return;
        }

        $panel = $this->panels->find((int)$id);
        if ($panel) {
            $this->panels->delete((int)$id);
            
            $calc = new CalculationService();
            $calc->recalculateProject((int)$panel['project_id']);

            header('Location: ' . base_url('/projects/' . $panel['project_id']));
            exit;
        }
    }

    private function defaultScheduleJson(string $polesConfig): string
    {
        $totalPoles = $this->normalizePoles($polesConfig);
        $rows = (int)ceil($totalPoles / 2);
        $left = [];
        $right = [];
        for ($i = 0; $i < $rows; $i++) {
            $left[] = $this->emptySlot('L', $i);
            $right[] = $this->emptySlot('R', $i);
        }
        return json_encode([
            'poles_config' => $polesConfig,
            'left' => $left,
            'right' => $right,
        ]);
    }

    private function emptySlot(string $side, int $index): array
    {
        return [
            'id' => $side . '-' . ($index + 1),
            'span_head_id' => null,
            'span_length' => 1,
            'disabled' => false,
            'breaker_span' => '1',
            'description' => '',
            'load_value' => '',
            'load_unit' => 'VA',
            'load_category' => 'lighting',
            'notes' => '',
        ];
    }

    private function normalizePoles(string $polesConfig): int
    {
        if (preg_match('/\d+/', $polesConfig, $m)) {
            return (int)$m[0];
        }
        return 42;
    }

    private function toInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int)$value;
    }
}
