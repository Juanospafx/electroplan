<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\PdfService;

class PanelPdfExportController
{
    public function download(string $id): void
    {
        $service = new PdfService();
        $service->exportPanelSchedule((int)$id);
    }
}