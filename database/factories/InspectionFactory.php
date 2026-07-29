<?php

namespace Database\Factories;

use App\Enums\InspectionStatus;
use App\Models\Equipment;
use App\Models\Inspection;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Inspection> */
final class InspectionFactory extends Factory
{
    protected $model = Inspection::class;

    public function definition(): array
    {
        return [
            'equipment_id' => Equipment::factory(),
            'organization_id' => fn (array $attributes) => Equipment::query()->findOrFail($attributes['equipment_id'])->organization_id,
            'status' => InspectionStatus::Planned,
            'context_snapshot' => [],
        ];
    }

    public function forEquipment(Equipment $equipment): static
    {
        return $this->state(['organization_id' => $equipment->organization_id, 'equipment_id' => $equipment->id]);
    }
}
