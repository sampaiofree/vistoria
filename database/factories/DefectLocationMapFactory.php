<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Defect;
use App\Models\DefectLocationMap;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DefectLocationMap> */
final class DefectLocationMapFactory extends Factory
{
    protected $model = DefectLocationMap::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'equipment_id' => null,
            'defect_id' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (DefectLocationMap $map): void {
            if ($map->defect_id === null) {
                $defect = Defect::factory()->create(['organization_id' => $map->organization_id]);
                $map->organization_id = $defect->organization_id;
                $map->equipment_id = $defect->equipment_id;
                $map->defect_id = $defect->id;
            }
        });
    }

    public function forDefect(Defect $defect): static
    {
        return $this->state([
            'organization_id' => $defect->organization_id,
            'equipment_id' => $defect->equipment_id,
            'defect_id' => $defect->id,
        ]);
    }
}
