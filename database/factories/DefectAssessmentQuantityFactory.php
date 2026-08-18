<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MeasurementUnit;
use App\Models\DefectAssessment;
use App\Models\DefectAssessmentQuantity;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DefectAssessmentQuantity> */
final class DefectAssessmentQuantityFactory extends Factory
{
    protected $model = DefectAssessmentQuantity::class;

    public function definition(): array
    {
        return [
            'defect_assessment_id' => DefectAssessment::factory(),
            'organization_id' => fn (array $attributes): int => DefectAssessment::query()->findOrFail($attributes['defect_assessment_id'])->organization_id,
            'inspection_id' => fn (array $attributes): int => DefectAssessment::query()->findOrFail($attributes['defect_assessment_id'])->inspection_id,
            'description' => null,
            'quantity' => 1,
            'measurement_value' => fake()->randomFloat(2, 0.1, 20),
            'measurement_unit' => MeasurementUnit::SquareMeter,
            'position' => 1,
            'notes' => null,
        ];
    }

    public function forAssessment(DefectAssessment $assessment): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $assessment->organization_id,
            'inspection_id' => $assessment->inspection_id,
            'defect_assessment_id' => $assessment->id,
        ]);
    }
}
