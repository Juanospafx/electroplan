<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProjectRepository;
use App\Repositories\PanelboardRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExcelService
{
    private ProjectRepository $projects;
    private PanelboardRepository $panels;

    public function __construct()
    {
        $this->projects = new ProjectRepository();
        $this->panels = new PanelboardRepository();
    }

    public function exportProjectSummary(int $projectId): void
    {
        $project = $this->projects->find($projectId);
        if (!$project) {
            http_response_code(404);
            echo 'Project not found';
            return;
        }
        $panels = $this->panels->listByProject($projectId);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('SUMMARY');

        $row = 1;
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Project Name', $project['project_name'] ?? '');
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Project Number', $project['project_number'] ?? '');
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Basis of Design', $project['basis_of_design'] ?? '');
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Last Update', $project['last_update'] ?? '');
        $sheet->getStyle('B4')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Service Voltage', $project['service_voltage'] ?? '');
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Service Amps', $project['service_amps'] ?? '');
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Service KVA', $project['service_kva'] ?? '');
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Total Panels', $project['total_panels'] ?? '');

        $row++;
        $sheet->setCellValue('A' . $row, 'Connected Loads');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Lighting', $project['load_lighting'] ?? '');
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Recept', $project['load_recept'] ?? '');
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Cooling', $project['load_cooling'] ?? '');
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Heating', $project['load_heating'] ?? '');
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Motors', $project['load_motors'] ?? '');
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Lg. Mtr.', $project['load_lg_mtr'] ?? '');
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Equip', $project['load_equip'] ?? '');

        $row += 2;
        $tableStart = $row;
        $headers = [
            'Panel Name', 'Voltage', 'Phase', 'Poles', 'Panel Type', 'Main (Size/Type)', 'Mounting',
            'Connected KVA', 'Connected Amps', 'Demand KVA', 'Demand Amps', '% Imbal.', 'Minimum Feeder Size',
        ];
        $sheet->fromArray($headers, null, 'A' . $row);
        $sheet->getStyle('A' . $row . ':' . $sheet->getHighestColumn() . $row)->getFont()->setBold(true);

        $row++;
        foreach ($panels as $panel) {
            $sheet->fromArray([
                $panel['panel_name'] ?? '',
                $panel['voltage'] ?? '',
                $panel['phase_wire'] ?? '',
                $panel['poles_config'] ?? '',
                $panel['panel_type'] ?? '',
                $panel['main_size_type'] ?? '',
                $panel['mounting'] ?? '',
                $panel['connected_kva'] ?? '',
                $panel['connected_amps'] ?? '',
                $panel['demand_kva'] ?? '',
                $panel['demand_amps'] ?? '',
                $panel['percent_imbalance'] ?? '',
                $panel['minimum_feeder_size'] ?? '',
            ], null, 'A' . $row);
            $row++;
        }

        $sheet->freezePane('A' . ($tableStart + 1));
        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $this->output($spreadsheet, $this->exportFilename((string)($project['project_name'] ?? ''), 'xlsx'));
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
        if (!$project) {
            http_response_code(404);
            echo 'Project not found';
            return;
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Panel ' . substr($panel['panel_name'] ?? '', 0, 25));

        $row = 1;
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Project Name', $project['project_name'] ?? '');
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Project Number', $project['project_number'] ?? '');
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Basis of Design', $project['basis_of_design'] ?? '');
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Last Update', $project['last_update'] ?? '');
        $sheet->getStyle('B4')->getNumberFormat()->setFormatCode('yyyy-mm-dd');

        $row++;
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Panel Name', $panel['panel_name'] ?? '');
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Voltage', $panel['voltage'] ?? '');
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Phase', $panel['phase_wire'] ?? '');
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Poles', $panel['poles_config'] ?? '');
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Panel Type', $panel['panel_type'] ?? '');
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Main (Size/Type)', $panel['main_size_type'] ?? '');
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Mounting', $panel['mounting'] ?? '');
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Connected KVA', $panel['connected_kva'] ?? '');
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Connected Amps', $panel['connected_amps'] ?? '');
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Demand KVA', $panel['demand_kva'] ?? '');
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Demand Amps', $panel['demand_amps'] ?? '');
        $row = $this->writeHeaderLabelValue($sheet, $row, '% Imbalance', $panel['percent_imbalance'] ?? '');
        $row = $this->writeHeaderLabelValue($sheet, $row, 'Balance Status', $panel['balance_status'] ?? '');

        $row += 2;
        $tableStart = $row;
        $headers = ['Position', 'Side', 'Breaker Span', 'Description', 'Category', 'kVA', 'Phase', 'Notes'];
        $sheet->fromArray($headers, null, 'A' . $row);
        $sheet->getStyle('A' . $row . ':H' . $row)->getFont()->setBold(true);

        $row++;
        $schedule = json_decode($panel['schedule_json'] ?? '{}', true);
        if (!is_array($schedule)) {
            $schedule = [];
        }

        $positions = $this->extractScheduleRows($schedule, $panel['phase_wire'] ?? '');
        foreach ($positions as $item) {
            $sheet->fromArray([
                $item['position'],
                $item['side'],
                $item['breaker_span'],
                $item['description'],
                $item['category'],
                $item['kva'],
                $item['phase'],
                $item['notes'],
            ], null, 'A' . $row);
            $row++;
        }

        $sheet->freezePane('A' . ($tableStart + 1));
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $this->output($spreadsheet, $this->exportFilename((string)($project['project_name'] ?? ''), 'xlsx'));
    }

    private function writeHeaderLabelValue(Worksheet $sheet, int $row, string $label, string $value): int
    {
        $sheet->setCellValue('A' . $row, $label);
        $sheet->setCellValue('B' . $row, $value);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        return $row + 1;
    }

    private function extractScheduleRows(array $schedule, string $phaseWire): array
    {
        $rows = [];
        foreach (['left' => 'L', 'right' => 'R'] as $key => $side) {
            $slots = $schedule[$key] ?? [];
            foreach ($slots as $index => $slot) {
                if (!is_array($slot)) continue;
                if (!empty($slot['disabled']) || (!empty($slot['span_head_id']))) continue;

                $kva = $this->slotToKva($slot);
                $rows[] = [
                    'position' => $index + 1,
                    'side' => $side,
                    'breaker_span' => $slot['breaker_span'] ?? '1',
                    'description' => $slot['description'] ?? '',
                    'category' => $slot['load_category'] ?? '',
                    'kva' => $kva,
                    'phase' => $this->assignPhase($side, $index, $phaseWire),
                    'notes' => $slot['notes'] ?? '',
                ];
            }
        }
        return $rows;
    }

    private function slotToKva(array $slot): float
    {
        $value = $slot['load_value'] ?? ($slot['load_va'] ?? null);
        if ($value === null || $value === '') {
            return 0.0;
        }
        $val = (float)$value;
        $unit = strtoupper((string)($slot['load_unit'] ?? 'VA'));
        return $unit === 'KVA' ? $val : $val / 1000;
    }

    private function assignPhase(string $side, int $rowIndex, string $phaseWire): string
    {
        $sequence = ['A', 'B', 'C'];
        $offset = $side === 'R' ? 1 : 0;
        $idx = ($rowIndex + $offset) % 3;
        if (stripos($phaseWire, '3PH') === false) {
            return 'A';
        }
        return $sequence[$idx];
    }

    private function output(Spreadsheet $spreadsheet, string $filename): void
    {
        if (ob_get_length()) {
            ob_end_clean();
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    private function exportFilename(string $projectName, string $extension): string
    {
        $userName = (string)($_SESSION['username'] ?? 'User');
        $clean = static fn(string $value, string $fallback): string =>
            preg_replace('/[^A-Za-z0-9]+/', '', $value) ?: $fallback;

        return $clean($userName, 'User') . '_' . $clean($projectName, 'Project') . '_' . date('dm') . '.' . $extension;
    }
}
