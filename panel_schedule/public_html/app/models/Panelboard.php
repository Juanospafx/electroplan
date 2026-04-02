<?php
declare(strict_types=1);

namespace App\Models;

class Panelboard
{
    public int $id;
    public int $project_id;
    public int $item_order;
    public string $panel_name;
    public ?string $panel_status;
    public ?string $voltage;
    public ?string $phase_wire;
    public ?string $poles_config;
    public ?string $panel_type;
    public ?string $main_type;
    public ?string $main_size_type;
    public ?string $mounting;
    public ?float $connected_kva;
    public ?float $connected_amps;
    public ?float $demand_kva;
    public ?float $demand_amps;
    public ?float $percent_imbalance;
    public ?string $balance_status;
    public ?string $balance_message;
    public ?string $minimum_feeder_size;
    public ?string $schedule_json;
    public ?string $last_update;
}
