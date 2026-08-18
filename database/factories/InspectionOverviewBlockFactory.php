<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Inspection;
use App\Models\InspectionOverviewBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InspectionOverviewBlock> */
final class InspectionOverviewBlockFactory extends Factory
{
    protected $model = InspectionOverviewBlock::class;

    public function definition(): array
    {
        return [
            'organization_id' => null,
            'inspection_id' => null,
            'position' => 1,
            'comment' => fake()->sentence(),
            'recommendation' => fake()->sentence(),
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function forInspection(Inspection $inspection, int $position = 1): static
    {
        return $this->state([
            'organization_id' => $inspection->organization_id,
            'inspection_id' => $inspection->id,
            'position' => $position,
        ]);
    }
}
