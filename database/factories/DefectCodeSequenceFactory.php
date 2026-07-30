<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DefectCategory;
use App\Models\DefectCodeSequence;
use App\Models\Equipment;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DefectCodeSequence>
 */
final class DefectCodeSequenceFactory extends Factory
{
    protected $model = DefectCodeSequence::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'equipment_id' => fn (array $attributes): int => Equipment::factory()
                ->create([
                    'organization_id' => $this->organizationId($attributes['organization_id']),
                ])
                ->getKey(),
            'category' => DefectCategory::Civil,
            'last_number' => 0,
        ];
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
