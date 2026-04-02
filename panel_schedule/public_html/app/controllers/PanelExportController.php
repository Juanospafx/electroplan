<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\ExcelService;

class PanelExportController
{
    public function download(string $id): void
    {
        $excel = new ExcelService();
        $excel->exportPanelSchedule((int)$id);
    }
}
