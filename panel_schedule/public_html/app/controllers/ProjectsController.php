<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Lib\View;
use App\Lib\Csrf;
use App\Repositories\ProjectRepository;
use App\Repositories\PanelboardRepository;
use App\Services\CalculationService;

class ProjectsController
{
    private ProjectRepository $projects;
    private PanelboardRepository $panels;

    public function __construct()
    {
        $this->projects = new ProjectRepository();
        $this->panels = new PanelboardRepository();
    }

    public function index(): void
    {
        $projects = $this->projects->all();
        View::render('projects/index', ['projects' => $projects]);
    }

    public function create(): void
    {
        View::render('projects/create', []);
    }

    public function store(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo 'Invalid CSRF token';
            return;
        }

        $projectName = trim($_POST['project_name'] ?? '');
        $projectNumber = trim($_POST['project_number'] ?? '');
        if ($projectName === '' || $projectNumber === '') {
            http_response_code(422);
            echo 'Project Name and Project Number are required';
            return;
        }

        $now = date('Y-m-d H:i:s');
        $lastUpdate = $_POST['last_update'] ?? date('Y-m-d');

        $id = $this->projects->create([
            ':project_name' => $projectName,
            ':project_number' => $projectNumber,
            ':basis_of_design' => trim($_POST['basis_of_design'] ?? ''),
            ':last_update' => $lastUpdate,
            ':service_voltage' => trim($_POST['service_voltage'] ?? ''),
            ':service_amps' => $this->toFloat($_POST['service_amps'] ?? null),
            ':service_kva' => $this->toFloat($_POST['service_kva'] ?? null),
            ':total_panels' => $this->toInt($_POST['total_panels'] ?? null),
            ':load_lighting' => $this->toFloat($_POST['load_lighting'] ?? null),
            ':load_recept' => $this->toFloat($_POST['load_recept'] ?? null),
            ':load_cooling' => $this->toFloat($_POST['load_cooling'] ?? null),
            ':load_heating' => $this->toFloat($_POST['load_heating'] ?? null),
            ':load_motors' => $this->toFloat($_POST['load_motors'] ?? null),
            ':load_lg_mtr' => $this->toFloat($_POST['load_lg_mtr'] ?? null),
            ':load_equip' => $this->toFloat($_POST['load_equip'] ?? null),
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        header('Location: ' . base_url('/projects/' . $id));
    }

    public function show(string $id): void
    {
        $project = $this->projects->find((int)$id);
        if (!$project) {
            http_response_code(404);
            echo 'Project not found';
            return;
        }
        $panels = $this->panels->listByProject((int)$id);
        View::render('projects/show', ['project' => $project, 'panels' => $panels]);
    }

    public function update(string $id): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo 'Invalid CSRF token';
            return;
        }
        $projectName = trim($_POST['project_name'] ?? '');
        $projectNumber = trim($_POST['project_number'] ?? '');
        if ($projectName === '' || $projectNumber === '') {
            http_response_code(422);
            echo 'Project Name and Project Number are required';
            return;
        }

        $now = date('Y-m-d H:i:s');
        $lastUpdate = $_POST['last_update'] ?? date('Y-m-d');

        $this->projects->update((int)$id, [
            ':project_name' => $projectName,
            ':project_number' => $projectNumber,
            ':basis_of_design' => trim($_POST['basis_of_design'] ?? ''),
            ':last_update' => $lastUpdate,
            ':service_voltage' => trim($_POST['service_voltage'] ?? ''),
            ':service_amps' => $this->toFloat($_POST['service_amps'] ?? null),
            ':service_kva' => $this->toFloat($_POST['service_kva'] ?? null),
            ':total_panels' => $this->toInt($_POST['total_panels'] ?? null),
            ':load_lighting' => $this->toFloat($_POST['load_lighting'] ?? null),
            ':load_recept' => $this->toFloat($_POST['load_recept'] ?? null),
            ':load_cooling' => $this->toFloat($_POST['load_cooling'] ?? null),
            ':load_heating' => $this->toFloat($_POST['load_heating'] ?? null),
            ':load_motors' => $this->toFloat($_POST['load_motors'] ?? null),
            ':load_lg_mtr' => $this->toFloat($_POST['load_lg_mtr'] ?? null),
            ':load_equip' => $this->toFloat($_POST['load_equip'] ?? null),
            ':updated_at' => $now,
        ]);

        $calc = new CalculationService();
        $calc->recalculateProject((int)$id);

        header('Location: ' . base_url('/projects/' . $id));
    }

    public function delete(string $id): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo 'Invalid CSRF token';
            return;
        }

        $this->projects->delete((int)$id);

        header('Location: ' . base_url('/'));
        exit;
    }

    private function toFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (float)$value;
    }

    private function toInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int)$value;
    }
}
