<?php
declare(strict_types=1);

namespace App\Repositories;

class PanelScheduleRepository
{
    private PanelboardRepository $panels;

    public function __construct()
    {
        $this->panels = new PanelboardRepository();
    }

    public function getSchedule(int $panelId): array
    {
        $panel = $this->panels->find($panelId);
        if (!$panel) {
            return [];
        }
        $json = json_decode($panel['schedule_json'] ?? '{}', true);
        return is_array($json) ? $json : [];
    }

    public function saveSchedule(int $panelId, array $schedule, string $updatedAt): void
    {
        $this->panels->updateSchedule($panelId, json_encode($schedule), $updatedAt);
    }
}
