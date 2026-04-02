<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Lib\Csrf;
use App\Repositories\PanelboardRepository;
use App\Services\CalculationService;

class ApiController
{
    private PanelboardRepository $panels;

    public function __construct()
    {
        $this->panels = new PanelboardRepository();
    }

    public function getSchedule(string $id): void
    {
        header('Content-Type: application/json');
        $panel = $this->panels->find((int)$id);
        if (!$panel) {
            http_response_code(404);
            echo json_encode(['error' => 'Panel not found']);
            return;
        }
        $schedule = $panel['schedule_json'] ?: '{}';
        echo $schedule;
    }

    public function saveSchedule(string $id): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? null);
        if (!Csrf::validate($token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            return;
        }

        $panel = $this->panels->find((int)$id);
        if (!$panel) {
            http_response_code(404);
            echo json_encode(['error' => 'Panel not found']);
            return;
        }

        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid JSON']);
            return;
        }

        $updatedAt = date('Y-m-d H:i:s');
        $this->panels->updateSchedule((int)$id, json_encode($json), $updatedAt);

        $calc = new CalculationService();
        $panel = $calc->recalculatePanel((int)$id);
        if (!empty($panel['project_id'])) {
            $calc->recalculateProject((int)$panel['project_id']);
        }

        echo json_encode(['success' => true, 'panel' => $panel]);
    }

    public function recalcPanel(string $id): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? null);
        if (!Csrf::validate($token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            return;
        }

        $calc = new CalculationService();
        $result = $calc->recalculatePanel((int)$id);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'panel' => $result]);
    }

    public function recalcProject(string $id): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? null);
        if (!Csrf::validate($token)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            return;
        }

        $calc = new CalculationService();
        $result = $calc->recalculateProject((int)$id);
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'project' => $result]);
    }
}
