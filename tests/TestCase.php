<?php

namespace Tests;

use App\Models\AssessmentPhoto;
use App\Models\DefectAssessment;
use App\Models\DefectAssessmentQuantity;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function satisfyAssessmentPublicationRequirements(DefectAssessment $assessment): void
    {
        DefectAssessmentQuantity::factory()->forAssessment($assessment)->create();

        foreach ([1, 2] as $position) {
            AssessmentPhoto::factory()->ready()->create([
                'organization_id' => $assessment->organization_id,
                'inspection_id' => $assessment->inspection_id,
                'defect_assessment_id' => $assessment->id,
                'position' => $position,
            ]);
        }
    }
}
