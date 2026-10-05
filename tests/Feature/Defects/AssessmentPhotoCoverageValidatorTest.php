<?php

declare(strict_types=1);

namespace Tests\Feature\Defects;

use App\Enums\DefectAssessmentCondition;
use App\Models\AssessmentPhoto;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Organization;
use App\Services\Defects\AssessmentPhotoCoverageValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AssessmentPhotoCoverageValidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_ignores_remaining_photos_of_canceled_assessments_and_requires_treated_evidence(): void
    {
        $organization = Organization::factory()->create();
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create();

        foreach ([DefectAssessmentCondition::Canceled, DefectAssessmentCondition::CanceledWithoutRepair] as $condition) {
            $defect = Defect::factory()->forEquipment($equipment, $inspection)->create();
            $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->complete()->create([
                'condition' => $condition,
            ]);
            AssessmentPhoto::factory()->for($inspection)->for($assessment, 'assessment')->failed()->create([
                'organization_id' => $organization->id,
            ]);
        }

        $validator = app(AssessmentPhotoCoverageValidator::class);
        $validator->validate($inspection);

        $treatedDefect = Defect::factory()->forEquipment($equipment, $inspection)->create();
        $treated = DefectAssessment::factory()->forDefect($treatedDefect, $inspection)->complete()->create([
            'condition' => DefectAssessmentCondition::Treated,
        ]);

        try {
            $validator->validate($inspection);
            $this->fail('A avaria tratada sem fotografias deveria bloquear a cobertura.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($treatedDefect->code, $exception->getMessage());
        }

        foreach ([1, 2] as $position) {
            AssessmentPhoto::factory()->for($inspection)->for($treated, 'assessment')->ready()->create([
                'organization_id' => $organization->id,
                'position' => $position,
            ]);
        }

        $validator->validate($inspection);

        AssessmentPhoto::factory()->for($inspection)->for($treated, 'assessment')->ready()->create([
            'organization_id' => $organization->id,
            'position' => 3,
        ]);

        try {
            $validator->validate($inspection);
            $this->fail('Três fotografias prontas deveriam bloquear o avanço da inspeção.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($treatedDefect->code.' (fotografias em número ímpar', $exception->getMessage());
        }

        AssessmentPhoto::factory()->for($inspection)->for($treated, 'assessment')->ready()->create([
            'organization_id' => $organization->id,
            'position' => 4,
        ]);

        $validator->validate($inspection);
    }
}
