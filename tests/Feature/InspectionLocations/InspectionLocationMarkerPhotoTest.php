<?php

declare(strict_types=1);

namespace Tests\Feature\InspectionLocations;

use App\Enums\DefectCategory;
use App\Enums\InspectionLocationMapProcessingStatus;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Enums\UserAccountType;
use App\Models\AssessmentPhoto;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionLocationMap;
use App\Models\InspectionLocationMarker;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use App\Services\InspectionLocations\InspectionLocationPhotoNumbering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class InspectionLocationMarkerPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_photos_are_validated_and_explicit_order_is_persisted(): void
    {
        [$user, $inspection, $assessment, $marker, $map] = $this->context();
        $first = AssessmentPhoto::factory()->ready()->create(['organization_id' => $inspection->organization_id, 'inspection_id' => $inspection->id, 'defect_assessment_id' => $assessment->id, 'position' => 1]);
        $second = AssessmentPhoto::factory()->ready()->create(['organization_id' => $inspection->organization_id, 'inspection_id' => $inspection->id, 'defect_assessment_id' => $assessment->id, 'position' => 2]);

        $this->actingAs($user)->put(route('inspection-location-markers.photos.sync', $marker), [
            'photo_ids' => [$second->public_id, $first->public_id],
            'lock_version' => 1,
            'map_lock_version' => 1,
        ])->assertRedirect();

        $this->assertSame([$second->public_id, $first->public_id], $marker->fresh()->photos->pluck('public_id')->all());
        $this->assertSame([1, 2], $marker->fresh()->photos->pluck('pivot.position')->all());

        $otherDefect = Defect::factory()->forEquipment($inspection->equipment, $inspection)->create(['category' => $map->category]);
        $otherAssessment = DefectAssessment::factory()->forDefect($otherDefect, $inspection)->create();
        $foreignPhoto = AssessmentPhoto::factory()->create(['organization_id' => $inspection->organization_id, 'inspection_id' => $inspection->id, 'defect_assessment_id' => $otherAssessment->id]);

        $this->actingAs($user)->put(route('inspection-location-markers.photos.sync', $marker->fresh()), [
            'photo_ids' => [$foreignPhoto->public_id],
            'lock_version' => 2,
            'map_lock_version' => 2,
        ])->assertSessionHasErrors('photo_ids');

        $foreignOrganization = Organization::factory()->create();
        $foreignInspection = Inspection::factory()->forEquipment(Equipment::factory()->for($foreignOrganization)->create())->create();
        $foreignDefect = Defect::factory()->forEquipment($foreignInspection->equipment, $foreignInspection)->create();
        $foreignAssessment = DefectAssessment::factory()->forDefect($foreignDefect, $foreignInspection)->create();
        $foreignOrganizationPhoto = AssessmentPhoto::factory()->create([
            'organization_id' => $foreignOrganization->id,
            'inspection_id' => $foreignInspection->id,
            'defect_assessment_id' => $foreignAssessment->id,
        ]);
        $this->actingAs($user)->put(route('inspection-location-markers.photos.sync', $marker->fresh()), [
            'photo_ids' => [$foreignOrganizationPhoto->public_id],
            'lock_version' => 2,
            'map_lock_version' => 2,
        ])->assertSessionHasErrors('photo_ids');
    }

    public function test_linked_photo_receives_one_canonical_number(): void
    {
        [$user, $inspection, $assessment, $marker, $map] = $this->context();
        $photo = AssessmentPhoto::factory()->ready()->create(['organization_id' => $inspection->organization_id, 'inspection_id' => $inspection->id, 'defect_assessment_id' => $assessment->id]);
        $pending = AssessmentPhoto::factory()->create([
            'organization_id' => $inspection->organization_id,
            'inspection_id' => $inspection->id,
            'defect_assessment_id' => $assessment->id,
            'position' => 2,
        ]);

        $this->actingAs($user)->put(route('inspection-location-markers.photos.sync', $marker), [
            'photo_ids' => [$photo->public_id],
            'lock_version' => 1,
            'map_lock_version' => $map->lock_version,
        ])->assertRedirect();

        $numbering = app(InspectionLocationPhotoNumbering::class)->buildForReport($inspection);
        $this->assertSame([$photo->public_id => 5], $numbering);
        $this->assertArrayNotHasKey($pending->public_id, $numbering);
        $this->assertSame([5], app(InspectionLocationPhotoNumbering::class)->numbersForMarker($marker->fresh(), $numbering));
    }

    public function test_map_can_use_any_photo_from_the_manual_gallery_order(): void
    {
        [$user, $inspection, $assessment, $marker, $map] = $this->context();
        $extra = AssessmentPhoto::factory()->ready()->create([
            'organization_id' => $inspection->organization_id,
            'inspection_id' => $inspection->id,
            'defect_assessment_id' => $assessment->id,
            'position' => 3,
        ]);

        $this->actingAs($user)->put(route('inspection-location-markers.photos.sync', $marker), [
            'photo_ids' => [$extra->public_id],
            'lock_version' => 1,
            'map_lock_version' => 1,
        ])->assertRedirect();

        $this->assertSame([$extra->public_id], $marker->fresh()->photos->pluck('public_id')->all());
    }

    /** @return array{0:User,1:Inspection,2:DefectAssessment,3:InspectionLocationMarker,4:InspectionLocationMap} */
    private function context(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin,
            'operational_role' => OperationalRole::Inspector,
        ]);
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::InProgress]);
        InspectionResponsible::factory()->forInspection($inspection, $user)->create(['responsibility' => InspectionResponsibility::Preparer]);
        $category = DefectCategory::AnticorrosiveTreatment;
        $defect = Defect::factory()->forEquipment($equipment, $inspection)->create(['category' => $category->value]);
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->complete()->create();
        $map = InspectionLocationMap::factory()->forInspection($inspection, $category)->create(['processing_status' => InspectionLocationMapProcessingStatus::Ready]);
        $marker = InspectionLocationMarker::factory()->forMapAndAssessment($map, $assessment)->create();

        return [$user, $inspection, $assessment, $marker, $map];
    }
}
