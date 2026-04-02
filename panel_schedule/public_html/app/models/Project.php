<?php
declare(strict_types=1);

namespace App\Models;

class Project
{
    public int $id;
    public string $project_name;
    public string $project_number;
    public ?string $basis_of_design;
    public ?string $service_voltage;
    public ?float $service_amps;
    public ?float $service_kva;
    public ?int $total_panels;
    public ?string $last_update;
    public ?float $load_lighting;
    public ?float $load_recept;
    public ?float $load_cooling;
    public ?float $load_heating;
    public ?float $load_motors;
    public ?float $load_lg_mtr;
    public ?float $load_equip;
}
