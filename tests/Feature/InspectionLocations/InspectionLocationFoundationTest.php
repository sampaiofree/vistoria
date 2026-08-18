<?php

declare(strict_types=1);

namespace Tests\Feature\InspectionLocations;

use App\Models\AssessmentPhoto;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\DefectCategory;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionLocationMap;
use App\Models\InspectionLocationMarker;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InspectionLocationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_can_opt_in_to_location_maps_and_defaults_to_disabled(): void
    {
        $organization = Organization::factory()->create();
        $category = DefectCategory::factory()->create(['organization_id' => $organization->id]);

        $this->assertFalse($category->requires_location_map);

        $category->update(['requires_location_map' => true]);

        $this->assertTrue($category->fresh()->requires_location_map);
    }

    public function test_map_belongs_to_inspection_and_category_and_marker_belongs_to_assessment(): void
    {
        [$inspection, $category, $assessment] = $this->inspectionAssessment();

        $map = InspectionLocationMap::factory()->forInspection($inspection, $category)->create([
            'title' => 'VISTA A-A DO VENTILADOR',
        ]);
        $marker = InspectionLocationMarker::factory()->forMapAndAssessment($map, $assessment)->create([
            'label' => 'CV-001',
        ]);

        $this->assertTrue($map->inspection->is($inspection));
        $this->assertTrue($map->category->is($category));
        $this->assertTrue($map->markers->first()->is($marker));
        $this->assertTrue($marker->assessment->is($assessment));
        $this->assertSame(['version' => 1, 'shapes' => [['type' => 'point', 'x' => 0.5, 'y' => 0.5]]], $marker->geometry);
    }

    public function test_one_assessment_has_one_marker_with_multiple_regions_and_photos(): void
    {
        [$inspection, $category, $assessment] = $this->inspectionAssessment();
        $map = InspectionLocationMap::factory()->forInspection($inspection, $category)->create();
        $marker = InspectionLocationMarker::factory()->forMapAndAssessment($map, $assessment)->create([
            'position' => 1,
            'geometry' => ['version' => 1, 'shapes' => [
                ['type' => 'point', 'x' => 0.25, 'y' => 0.25],
                ['type' => 'point', 'x' => 0.75, 'y' => 0.75],
            ]],
        ]);
        $firstPhoto = AssessmentPhoto::factory()->create([
            'organization_id' => $inspection->organization_id,
            'inspection_id' => $inspection->id,
            'defect_assessment_id' => $assessment->id,
        ]);
        $secondPhoto = AssessmentPhoto::factory()->create([
            'organization_id' => $inspection->organization_id,
            'inspection_id' => $inspection->id,
            'defect_assessment_id' => $assessment->id,
            'position' => 2,
        ]);

        $marker->photos()->attach([
            $firstPhoto->id => ['organization_id' => $inspection->organization_id, 'inspection_id' => $inspection->id, 'position' => 1],
            $secondPhoto->id => ['organization_id' => $inspection->organization_id, 'inspection_id' => $inspection->id, 'position' => 2],
        ]);

        $this->assertCount(1, $assessment->locationMarkers);
        $this->assertCount(2, $marker->geometry['shapes']);
        $this->assertCount(2, $marker->fresh()->photos);
    }

    /** @return array{0:Inspection,1:DefectCategory,2:DefectAssessment} */
    private function inspectionAssessment(): array
    {
        $organization = Organization::factory()->create();
        $category = DefectCategory::factory()->create(['organization_id' => $organization->id, 'code' => 'TA']);
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create();
        $defect = Defect::factory()->forEquipment($equipment, $inspection)->create(['defect_category_id' => $category->id]);
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->create();

        return [$inspection, $category, $assessment];
    }
}
