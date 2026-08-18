<?php

declare(strict_types=1);

namespace Tests\Feature\InspectionLocations;

use App\Enums\DefectAssessmentCondition;
use App\Enums\InspectionLocationMapProcessingStatus;
use App\Models\AssessmentPhoto;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\DefectCategory;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionLocationMap;
use App\Models\InspectionLocationMarker;
use App\Models\Organization;
use App\Services\InspectionLocations\InspectionLocationCoverageValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class InspectionLocationCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_without_location_requirement_does_not_block_inspection(): void
    {
        [$inspection] = $this->assessmentContext(false);

        app(InspectionLocationCoverageValidator::class)->validate($inspection);

        $this->addToAssertionCount(1);
    }

    public function test_required_category_reports_missing_location_coverage(): void
    {
        [$inspection] = $this->assessmentContext(true);

        try {
            app(InspectionLocationCoverageValidator::class)->validate($inspection);
            $this->fail('A cobertura incompleta deveria impedir o envio.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('inspection_location', $exception->errors());
            $this->assertStringContainsString('sem marcação', $exception->errors()['inspection_location'][0]);
        }
    }

    public function test_not_located_and_not_inspected_assessments_are_exempt(): void
    {
        [$inspection, $category, $firstAssessment] = $this->assessmentContext(true);
        $firstAssessment->update(['condition' => DefectAssessmentCondition::NotLocated]);
        $secondDefect = Defect::factory()->forEquipment($inspection->equipment, $inspection)->create([
            'defect_category_id' => $category->id,
        ]);
        DefectAssessment::factory()->forDefect($secondDefect, $inspection)->create([
            'condition' => DefectAssessmentCondition::NotInspected,
        ]);

        app(InspectionLocationCoverageValidator::class)->validate($inspection);

        $this->addToAssertionCount(1);
    }

    public function test_ready_map_valid_geometry_and_ready_selected_photo_complete_coverage(): void
    {
        [$inspection, $category, $assessment] = $this->assessmentContext(true);
        $map = InspectionLocationMap::factory()->forInspection($inspection, $category)->create([
            'processing_status' => InspectionLocationMapProcessingStatus::Ready,
        ]);
        $marker = InspectionLocationMarker::factory()->forMapAndAssessment($map, $assessment)->create([
            'geometry' => ['version' => 1, 'shapes' => [
                ['type' => 'rectangle', 'x' => 0.1, 'y' => 0.2, 'width' => 0.2, 'height' => 0.2],
            ]],
        ]);
        $photo = AssessmentPhoto::factory()->ready()->create([
            'organization_id' => $inspection->organization_id,
            'inspection_id' => $inspection->id,
            'defect_assessment_id' => $assessment->id,
        ]);
        $marker->photos()->attach($photo->id, [
            'organization_id' => $inspection->organization_id,
            'inspection_id' => $inspection->id,
            'position' => 1,
        ]);

        app(InspectionLocationCoverageValidator::class)->validate($inspection);

        $this->addToAssertionCount(1);
    }

    /** @return array{0:Inspection,1:DefectCategory,2:DefectAssessment} */
    private function assessmentContext(bool $requiresLocationMap): array
    {
        $organization = Organization::factory()->create();
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create();
        $category = DefectCategory::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'TA',
            'requires_location_map' => $requiresLocationMap,
        ]);
        $defect = Defect::factory()->forEquipment($equipment, $inspection)->create([
            'defect_category_id' => $category->id,
        ]);
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->create([
            'condition' => DefectAssessmentCondition::Unchanged,
        ]);

        return [$inspection, $category, $assessment];
    }
}
