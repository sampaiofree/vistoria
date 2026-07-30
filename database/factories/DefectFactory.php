<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DefectCategory;
use App\Enums\DefectStatus;
use App\Models\Defect;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Defect>
 */
final class DefectFactory extends Factory
{
    protected $model = Defect::class;

    public function definition(): array
    {
        return [
            'public_id' => null,
            'organization_id' => Organization::factory(),
            'equipment_id' => null,
            'first_inspection_id' => null,
            'code' => fake()->unique()->numerify('VT009-CV-###'),
            'category' => DefectCategory::Civil,
            'sequence_number' => fake()->numberBetween(1, 999),
            'title' => fake()->sentence(4),
            'origin_description' => null,
            'status' => DefectStatus::Active,
            'repaired_at' => null,
            'archived_at' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Defect $defect): void {
            if ($defect->equipment_id === null) {
                $equipment = Equipment::factory()->create([
                    'organization_id' => $this->organizationId($defect->organization_id),
                ]);

                $defect->equipment_id = $equipment->getKey();
            }

            if ($defect->first_inspection_id === null) {
                $inspection = Inspection::factory()
                    ->forEquipment(Equipment::query()->findOrFail($defect->equipment_id))
                    ->create();

                $defect->first_inspection_id = $inspection->getKey();
            }
        });
    }

    public function forEquipment(Equipment $equipment, ?Inspection $inspection = null): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $equipment->organization_id,
            'equipment_id' => $equipment->getKey(),
            'first_inspection_id' => $inspection?->getKey(),
        ]);
    }

    public function repaired(): static
    {
        return $this->state(fn (): array => [
            'status' => DefectStatus::Repaired,
            'repaired_at' => now(),
        ]);
    }

    private function organizationId(mixed $organization): int
    {
        if ($organization instanceof Organization) {
            return (int) $organization->getKey();
        }

        if ($organization instanceof Factory) {
            return (int) $organization->create()->getKey();
        }

        return (int) $organization;
    }
}
