<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\PanelboardRepository;
use App\Repositories\ProjectRepository;
use TCPDF;

class PdfService
{
    private PanelboardRepository $panels;
    private ProjectRepository $projects;

    public function __construct()
    {
        $this->panels = new PanelboardRepository();
        $this->projects = new ProjectRepository();
    }

    public function exportPanelSchedule(int $panelId): void
    {
        $panel = $this->panels->find($panelId);
        if (!$panel) {
            http_response_code(404);
            echo 'Panel not found';
            return;
        }
        $project = $this->projects->find((int)$panel['project_id']);

        // Ensure TCPDF is available
        if (!class_exists('TCPDF')) {
            die('TCPDF library is not installed. Please run: composer require tecnickcom/tcpdf');
        }

        // Initialize PDF
        $pdf = new TCPDF('L', 'mm', 'LETTER', true, 'UTF-8', false);
        $pdf->SetCreator('PanelMaster');
        $pdf->SetAuthor('PanelMaster');
        $pdf->SetTitle('Panel Schedule - ' . ($panel['panel_name'] ?? 'Unknown'));
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->AddPage();

        // Title
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'PANELBOARD SCHEDULE', 0, 1, 'C');
        $pdf->Ln(2);

        // Header Info
        $pdf->SetFont('helvetica', '', 10);
        $y = $pdf->GetY();
        $col1 = 15; $col2 = 100; $col3 = 180;
        
        $pdf->Text($col1, $y, 'Project: ' . ($project['project_name'] ?? ''));
        $pdf->Text($col3, $y, 'Panel Name: ' . ($panel['panel_name'] ?? ''));
        $y += 6;
        $pdf->Text($col1, $y, 'Location: ' . ($panel['location'] ?? 'N/A'));
        $pdf->Text($col3, $y, 'Voltage: ' . ($panel['voltage'] ?? ''));
        $y += 6;
        $pdf->Text($col1, $y, 'Supply From: ' . ($panel['supply_from'] ?? 'N/A'));
        $pdf->Text($col3, $y, 'Mains: ' . ($panel['main_size_type'] ?? ''));
        
        $pdf->SetY($y + 10);

        // Table Header
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetLineWidth(0.1);

        // Column Widths: Total ~260mm
        // Ckt(10), Desc(70), Brk(15), VA(20), Ph(10), VA(20), Brk(15), Desc(70), Ckt(10)
        $w = [12, 70, 15, 20, 12, 20, 15, 70, 12];
        $h = 7;

        $headers = ['CKT', 'DESCRIPTION', 'BK/P', 'VA', 'PH', 'VA', 'BK/P', 'DESCRIPTION', 'CKT'];
        foreach ($headers as $i => $header) {
            $pdf->Cell($w[$i], $h, $header, 1, 0, 'C', 1);
        }
        $pdf->Ln();

        // Table Body
        $pdf->SetFont('helvetica', '', 9);
        $schedule = json_decode($panel['schedule_json'] ?? '{}', true);
        $left = $schedule['left'] ?? [];
        $right = $schedule['right'] ?? [];
        $count = max(count($left), count($right));

        for ($i = 0; $i < $count; $i++) {
            $l = $left[$i] ?? [];
            $r = $right[$i] ?? [];
            $phase = ['A', 'B', 'C'][$i % 3];

            // Left Data
            $lCkt = ($i * 2) + 1;
            $lDesc = $l['description'] ?? '';
            $lBrk = $l['breaker_span'] ?? '';
            $lVa = $l['load_value'] ?? '';

            // Right Data
            $rCkt = ($i * 2) + 2;
            $rDesc = $r['description'] ?? '';
            $rBrk = $r['breaker_span'] ?? '';
            $rVa = $r['load_value'] ?? '';

            // Draw Row
            // Check for page break
            if ($pdf->GetY() > 190) {
                $pdf->AddPage();
                // Re-draw header
                $pdf->SetFont('helvetica', 'B', 9);
                foreach ($headers as $k => $header) {
                    $pdf->Cell($w[$k], $h, $header, 1, 0, 'C', 1);
                }
                $pdf->Ln();
                $pdf->SetFont('helvetica', '', 9);
            }

            $pdf->Cell($w[0], $h, (string)$lCkt, 1, 0, 'C');
            $pdf->Cell($w[1], $h, $lDesc, 1, 0, 'L');
            $pdf->Cell($w[2], $h, (string)$lBrk, 1, 0, 'C');
            $pdf->Cell($w[3], $h, (string)$lVa, 1, 0, 'R');
            
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell($w[4], $h, $phase, 1, 0, 'C', 1);
            $pdf->SetFont('helvetica', '', 9);

            $pdf->Cell($w[5], $h, (string)$rVa, 1, 0, 'R');
            $pdf->Cell($w[6], $h, (string)$rBrk, 1, 0, 'C');
            $pdf->Cell($w[7], $h, $rDesc, 1, 0, 'L');
            $pdf->Cell($w[8], $h, (string)$rCkt, 1, 1, 'C');
        }

        // Footer / Totals
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(60, 7, 'Total Connected KVA: ' . ($panel['connected_kva'] ?? '0.00'), 0, 1);
        $pdf->Cell(60, 7, 'Total Connected Amps: ' . ($panel['connected_amps'] ?? '0.00'), 0, 1);


        // Output + save copy into Electroplan project Tools folder
        $filename = 'Panel_' . preg_replace('/[^a-zA-Z0-9]/', '_', $panel['panel_name'] ?? 'Schedule') . '.pdf';
        $pdfBinary = $pdf->Output($filename, 'S');

        $electroplanProjectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : (int)($_SESSION['electroplan_project_id'] ?? 0);
        if ($electroplanProjectId > 0 && is_string($pdfBinary) && $pdfBinary !== '') {
            $this->saveExportToElectroplan($electroplanProjectId, 'panel_schedule', $filename, $pdfBinary);
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfBinary));
        echo $pdfBinary;
        exit;
    }

    private function saveExportToElectroplan(int $projectId, string $toolSlug, string $downloadFilename, string $pdfBinary): void
    {
        try {
            $electroplanDb = dirname(__DIR__, 4) . '/core/db/connection.php';
            if (!file_exists($electroplanDb)) return;
            require $electroplanDb;
            if (!isset($pdo) || !($pdo instanceof \PDO)) return;

            $stmtFolder = $pdo->prepare("SELECT id FROM folders WHERE project_id = ? AND name = 'Tools' AND deleted_at IS NULL LIMIT 1");
            $stmtFolder->execute([$projectId]);
            $toolsFolderId = (int)$stmtFolder->fetchColumn();
            if ($toolsFolderId <= 0) {
                $pdo->prepare("INSERT INTO folders (project_id, name) VALUES (?, 'Tools')")->execute([$projectId]);
                $toolsFolderId = (int)$pdo->lastInsertId();
            }

            $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $downloadFilename) ?: ('export_' . gmdate('Y-m-d') . '.pdf');
            $diskDir = dirname(__DIR__, 4) . '/uploads/tool/' . $toolSlug . '/' . $projectId . '/';
            if (!is_dir($diskDir) && !mkdir($diskDir, 0775, true) && !is_dir($diskDir)) return;

            $diskName = time() . '_' . $safeName;
            $diskPath = $diskDir . $diskName;
            if (file_put_contents($diskPath, $pdfBinary) === false) return;

            $publicPath = 'uploads/tool/' . $toolSlug . '/' . $projectId . '/' . $diskName;

            $stmtCheck = $pdo->prepare("SELECT id, version_group_id, version_number FROM files
                WHERE project_id = ? AND folder_id = ? AND sub_folder_id IS NULL AND filename = ? AND deleted_at IS NULL
                ORDER BY version_number DESC LIMIT 1");
            $stmtCheck->execute([$projectId, $toolsFolderId, $safeName]);
            $existing = $stmtCheck->fetch(\PDO::FETCH_ASSOC);

            $versionGroup = uniqid('vgroup_');
            $versionNum = 1;
            if ($existing) {
                $versionGroup = $existing['version_group_id'] ?: uniqid('vgroup_');
                $versionNum = (int)$existing['version_number'] + 1;
                if (empty($existing['version_group_id'])) {
                    $pdo->prepare("UPDATE files SET version_group_id = ? WHERE id = ?")->execute([$versionGroup, (int)$existing['id']]);
                }
            }

            $uploadedBy = 1;
            $stmtIns = $pdo->prepare("INSERT INTO files (project_id, folder_id, sub_folder_id, filename, filepath, file_type, uploaded_by, version_group_id, version_number)
                                      VALUES (?, ?, NULL, ?, ?, 'pdf', ?, ?, ?)");
            $stmtIns->execute([$projectId, $toolsFolderId, $safeName, $publicPath, $uploadedBy, $versionGroup, $versionNum]);
        } catch (\Throwable $e) {
            // Silent fail: download still succeeds.
        }
    }


}
