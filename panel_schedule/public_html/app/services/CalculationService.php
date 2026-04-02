<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\PanelboardRepository;
use App\Repositories\ProjectRepository;

class CalculationService
{
    private PanelboardRepository $panels;
    private ProjectRepository $projects;

    public function __construct()
    {
        $this->panels = new PanelboardRepository();
        $this->projects = new ProjectRepository();
    }

    public function recalculatePanel(int $panelId): array
    {
        $panel = $this->panels->find($panelId);
        if (!$panel) {
            return [];
        }

        $schedule = json_decode($panel['schedule_json'] ?? '{}', true);
        $voltage = $panel['voltage'];
        $phaseWire = $panel['phase_wire'] ?? '';
        
        // Initialize Phase Loads
        $phases = ['A' => 0.0, 'B' => 0.0, 'C' => 0.0];
        
        // Helper to add load to phase
        $addLoad = function($rowIdx, $colSide, $va) use (&$phases, $phaseWire) {
            $isSinglePhase = str_contains((string)$phaseWire, '1PH');
            
            if ($isSinglePhase) {
                // 1PH: A, B, A, B...
                $phaseIdx = $rowIdx % 2;
                $p = match ($phaseIdx) {
                    0 => 'A',
                    1 => 'B',
                };
            } else {
                // 3PH: A, B, C, A, B, C...
                $phaseIdx = $rowIdx % 3;
                $p = match ($phaseIdx) {
                    0 => 'A',
                    1 => 'B',
                    2 => 'C',
                };
            }
            $phases[$p] += (float)$va;
        };

        // Iterate Left Side
        if (isset($schedule['left']) && is_array($schedule['left'])) {
            foreach ($schedule['left'] as $idx => $slot) {
                if (!empty($slot['load_value'])) {
                    $span = (int)($slot['breaker_span'] ?? 1);
                    $va = (float)$slot['load_value'];
                    // Distribute load across span
                    $vaPerPhase = $span > 1 ? $va / $span : $va;
                    for ($i = 0; $i < $span; $i++) {
                        $addLoad($idx + $i, 'left', $vaPerPhase);
                    }
                }
            }
        }

        // Iterate Right Side
        if (isset($schedule['right']) && is_array($schedule['right'])) {
            foreach ($schedule['right'] as $idx => $slot) {
                if (!empty($slot['load_value'])) {
                    $span = (int)($slot['breaker_span'] ?? 1);
                    $va = (float)$slot['load_value'];
                    // Distribute load across span
                    $vaPerPhase = $span > 1 ? $va / $span : $va;
                    for ($i = 0; $i < $span; $i++) {
                        $addLoad($idx + $i, 'right', $vaPerPhase);
                    }
                }
            }
        }

        // Calculate Totals
        $totalVA = $phases['A'] + $phases['B'] + $phases['C'];
        $connectedKVA = $totalVA / 1000;

        // Calculate Amps based on Voltage (Excel Logic)
        $amps = 0.0;
        if ($voltage === '480Y/277V') {
            $amps = $connectedKVA / 0.831;
        } elseif ($voltage === '208Y/120V') {
            $amps = $connectedKVA / 0.360; // sqrt(3) * 208 / 1000 approx
        } elseif ($voltage === '240/120V') {
            $amps = $connectedKVA / 0.240;
        }

        // Calculate Imbalance
        $isSinglePhase = str_contains((string)$phaseWire, '1PH');
        
        if ($isSinglePhase) {
            $avg = ($phases['A'] + $phases['B']) / 2;
            $checkPhases = ['A', 'B'];
        } else {
            $avg = $totalVA / 3;
            $checkPhases = ['A', 'B', 'C'];
        }

        $maxDev = 0;
        $maxPhase = 'A';
        foreach ($checkPhases as $p) {
            $load = $phases[$p];
            $dev = abs($load - $avg);
            if ($dev > $maxDev) {
                $maxDev = $dev;
                $maxPhase = $p;
            }
        }
        
        $percentImbal = 0.0;
        if ($avg > 0) {
            $percentImbal = ($maxDev / $avg) * 100;
        }

        // Determine Status
        $status = 'OK';
        if ($percentImbal > 20) $status = 'FAIL';
        elseif ($percentImbal > 10) $status = 'WARN';

        $message = 'Balanced';
        if ($percentImbal > 0) {
            $message = sprintf('Imbalance: %.2f%% (Max Dev: Phase %s)', $percentImbal, $maxPhase);
        }

        // Update DB
        $this->panels->updateCalculations($panelId, [
            'connected_kva' => $connectedKVA,
            'connected_amps' => $amps,
            'demand_kva' => $connectedKVA, // Simplified: Demand = Connected for now
            'demand_amps' => $amps,
            'percent_imbalance' => $percentImbal,
            'balance_status' => $status,
            'balance_message' => $message
        ]);

        return $this->panels->find($panelId);
    }

    public function recalculateProject(int $projectId): array
    {
        // Sum all panels to update project totals
        $panels = $this->panels->listByProject($projectId);
        
        $totalKva = 0.0;
        
        foreach ($panels as $p) {
            $totalKva += (float)$p['connected_kva'];
        }

        // Get Project Voltage to calc Service Amps
        $project = $this->projects->find($projectId);
        $voltage = $project['service_voltage'] ?? '';
        
        $serviceAmps = 0.0;
        if ($voltage === '480Y/277V') {
            $serviceAmps = $totalKva / 0.831;
        } elseif ($voltage === '208Y/120V') {
            $serviceAmps = $totalKva / 0.360;
        } elseif ($voltage === '240/120V') {
            $serviceAmps = $totalKva / 0.240;
        }

        $this->projects->updateTotals($projectId, $totalKva, $serviceAmps);

        return $this->projects->find($projectId);
    }
}