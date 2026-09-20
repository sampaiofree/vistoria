<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DefectAssessment;
use App\Models\DefectAssessmentLocation;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DefectAssessmentLocation> */
final class DefectAssessmentLocationFactory extends Factory
{
    protected $model = DefectAssessmentLocation::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'equipment_id' => null,
            'inspection_id' => null,
            'defect_assessment_id' => null,
            'geometry' => ['version' => 1, 'shapes' => [['type' => 'point', 'x' => 0.5, 'y' => 0.5]]],
            'label' => null,
            'confirmed_at' => null,
            'confirmed_by' => null,
            'lock_version' => 1,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function forAssessment(DefectAssessment $assessment): static
    {
        return $this->state([
            'organization_id' => $assessment->organization_id,
            'equipment_id' => $assessment->equipment_id,
            'inspection_id' => $assessment->inspection_id,
            'defect_assessment_id' => $assessment->id,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(['confirmed_at' => now()]);
    }
}
