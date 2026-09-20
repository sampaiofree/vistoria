<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DefectCategory;
use App\Enums\MeasurementUnit;
use App\Enums\QuantityCalculationMode;
use App\Enums\QuantityCalculationType;
use App\Models\DefectAssessment;
use App\Models\DefectAssessmentQuantity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Schema;

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
        if (! Schema::hasColumn('defect_assessment_quantities', 'category')) {
            return $this->state(fn (): array => [
                'organization_id' => $assessment->organization_id,
                'inspection_id' => $assessment->inspection_id,
                'defect_assessment_id' => $assessment->id,
            ]);
        }

        $category = $assessment->defect()->firstOrFail()->category;
        $unit = match ($category) {
            DefectCategory::Civil => MeasurementUnit::CubicMeter,
            DefectCategory::AnticorrosiveTreatment => MeasurementUnit::SquareMeter,
            DefectCategory::StructuralRecovery => MeasurementUnit::Kilogram,
        };

        $native = [
            'category' => $category,
            'calculation_type' => match ($category) {
                DefectCategory::Civil => QuantityCalculationType::CivilVolume,
                DefectCategory::AnticorrosiveTreatment => QuantityCalculationType::TacArea,
                DefectCategory::StructuralRecovery => QuantityCalculationType::StructuralRecoveryWeight,
            },
            'inputs' => [],
            'measurement_unit' => $unit,
            'mode' => QuantityCalculationMode::Manual,
            'formula_version' => 0,
            'formula_snapshot' => [
                'source' => 'factory',
                'formula_version' => 0,
                'category' => $category->value,
                'measurement_unit' => $unit->value,
            ],
        ];

        return $this->state(fn (): array => [
            'organization_id' => $assessment->organization_id,
            'inspection_id' => $assessment->inspection_id,
            'defect_assessment_id' => $assessment->id,
            'measurement_unit' => $unit,
            ...$native,
        ]);
    }
}
