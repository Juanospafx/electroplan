<?php
declare(strict_types=1);

session_start();


if (isset($_GET['project_id']) && (int)$_GET['project_id'] > 0) {
    $_SESSION['electroplan_project_id'] = (int)$_GET['project_id'];
}


define('BASE_PATH', dirname(__DIR__));

if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

require_once BASE_PATH . '/app/lib/Autoloader.php';
require_once BASE_PATH . '/app/lib/helpers.php';
require_once BASE_PATH . '/app/config/app.php';

use App\Lib\Router;
use App\Controllers\ProjectsController;
use App\Controllers\PanelsController;
use App\Controllers\ApiController;
use App\Controllers\ProjectExportController;
use App\Controllers\PanelExportController;
use App\Controllers\PanelPdfExportController;

$router = new Router();

$router->get('/', function () {
    header('Location: ' . base_url('/projects'));
    exit;
});

$router->get('/projects', [ProjectsController::class, 'index']);
$router->get('/projects/new', [ProjectsController::class, 'create']);
$router->post('/projects', [ProjectsController::class, 'store']);
$router->get('/projects/{id}', [ProjectsController::class, 'show']);
$router->post('/projects/{id}', [ProjectsController::class, 'update']);
$router->post('/projects/{id}/delete', [ProjectsController::class, 'delete']);

$router->get('/projects/{id}/panels/new', [PanelsController::class, 'create']);
$router->post('/projects/{id}/panels', [PanelsController::class, 'store']);

$router->get('/panels/{id}/edit', [PanelsController::class, 'edit']);
$router->post('/panels/{id}', [PanelsController::class, 'update']);
$router->post('/panels/{id}/delete', [PanelsController::class, 'delete']);

$router->get('/api/panels/{id}/schedule', [ApiController::class, 'getSchedule']);
$router->post('/api/panels/{id}/schedule', [ApiController::class, 'saveSchedule']);
$router->post('/api/panels/{id}/recalc', [ApiController::class, 'recalcPanel']);
$router->post('/api/projects/{id}/recalc', [ApiController::class, 'recalcProject']);

$router->get('/projects/{id}/export.xlsx', [ProjectExportController::class, 'download']);
$router->get('/panels/{id}/export.xlsx', [PanelExportController::class, 'download']);
$router->get('/panels/{id}/export.pdf', [PanelPdfExportController::class, 'download']);

$appConfig = require BASE_PATH . '/app/config/app.php';
if (!empty($appConfig['debug_export'])) {
    $router->get('/debug/projects/{id}/export.xlsx', [ProjectExportController::class, 'download']);
    $router->get('/debug/panels/{id}/export.xlsx', [PanelExportController::class, 'download']);
}

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
