<?php

declare(strict_types=1);

namespace Tests\Feature\InspectionLocations;

use App\Enums\DefectAssessmentCondition;
use App\Enums\InspectionLocationMapProcessingStatus;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\UserAccountType;
use App\Models\AssessmentPhoto;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\DefectCategory;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionLocationMap;
use App\Models\InspectionLocationMarker;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InspectionLocationMarkerRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_one_marker_with_multiple_shapes_and_cannot_link_the_same_assessment_twice(): void
    {
        [, $user, $inspection, $category, $assessment, $map] = $this->context();
        $geometry = ['version' => 1, 'shapes' => [
            ['type' => 'rectangle', 'x' => 0.1, 'y' => 0.2, 'width' => 0.2, 'height' => 0.1],
            ['type' => 'polygon', 'points' => [[0.5, 0.5], [0.7, 0.5], [0.6, 0.7]]],
        ]];

        $this->actingAs($user)->post(route('inspection-location-maps.markers.store', $map), [
            'defect_assessment_id' => $assessment->id,
            'label' => 'TA-001',
            'geometry' => $geometry,
            'map_lock_version' => 1,
        ])->assertRedirect();

        $map->refresh();
        $this->actingAs($user)->post(route('inspection-location-maps.markers.store', $map), [
            'defect_assessment_id' => $assessment->id,
            'geometry' => ['version' => 1, 'shapes' => [['type' => 'point', 'x' => 0.8, 'y' => 0.8]]],
            'map_lock_version' => $map->lock_version,
        ])->assertSessionHasErrors('defect_assessment_id');

        $this->assertCount(1, $assessment->fresh()->locationMarkers);
        $this->assertSame($geometry, InspectionLocationMarker::query()->oldest('id')->firstOrFail()->geometry);
        $this->assertSame(2, $map->fresh()->lock_version);
        $this->assertSame($category->id, $assessment->defect->defect_category_id);
        $this->assertSame($inspection->id, $map->inspection_id);

        $otherMap = InspectionLocationMap::factory()->forInspection($inspection, $category)->create([
            'processing_status' => InspectionLocationMapProcessingStatus::Ready,
            'background_path' => 'testing/other-background.webp',
        ]);
        $this->actingAs($user)->post(route('inspection-location-maps.markers.store', $otherMap), [
            'defect_assessment_id' => $assessment->id,
            'geometry' => ['version' => 1, 'shapes' => [['type' => 'point', 'x' => 0.8, 'y' => 0.8]]],
            'map_lock_version' => $otherMap->lock_version,
        ])->assertSessionHasErrors('defect_assessment_id');

        $this->assertCount(1, $assessment->fresh()->locationMarkers);
    }

    public function test_marker_rejects_incompatible_category_and_stale_updates(): void
    {
        [, $user, $inspection, , $assessment, $map] = $this->context();
        $otherCategory = DefectCategory::query()
            ->where('organization_id', $inspection->organization_id)
            ->where('code', 'REC')
            ->firstOrFail();
        $otherDefect = Defect::factory()->forEquipment($inspection->equipment, $inspection)->create(['defect_category_id' => $otherCategory->id]);
        $otherAssessment = DefectAssessment::factory()->forDefect($otherDefect, $inspection)->create();

        $this->actingAs($user)->post(route('inspection-location-maps.markers.store', $map), [
            'defect_assessment_id' => $otherAssessment->id,
            'geometry' => ['version' => 1, 'shapes' => [['type' => 'point', 'x' => 0.2, 'y' => 0.3]]],
            'map_lock_version' => 1,
        ])->assertSessionHasErrors('defect_assessment_id');

        $marker = InspectionLocationMarker::factory()->forMapAndAssessment($map, $assessment)->create(['label' => null]);
        $map->update(['lock_version' => 2]);
        $this->actingAs($user)->put(route('inspection-location-markers.update', $marker), [
            'defect_assessment_id' => $assessment->id,
            'label' => 'Concorrente',
            'geometry' => $marker->geometry,
            'position' => 1,
            'lock_version' => $marker->lock_version,
            'map_lock_version' => 1,
        ])->assertSessionHasErrors('lock_version');

        $this->assertNull($marker->fresh()->label);
    }

    public function test_marker_cannot_be_changed_to_an_assessment_already_used_by_another_map(): void
    {
        [, $user, $inspection, $category, $assessment, $map] = $this->context();
        $otherDefect = Defect::factory()->forEquipment($inspection->equipment, $inspection)->create([
            'defect_category_id' => $category->id,
        ]);
        $otherAssessment = DefectAssessment::factory()->forDefect($otherDefect, $inspection)->complete()->create();
        $otherMap = InspectionLocationMap::factory()->forInspection($inspection, $category)->create([
            'processing_status' => InspectionLocationMapProcessingStatus::Ready,
            'background_path' => 'testing/other-background.webp',
        ]);
        $marker = InspectionLocationMarker::factory()->forMapAndAssessment($map, $assessment)->create();
        InspectionLocationMarker::factory()->forMapAndAssessment($otherMap, $otherAssessment)->create();

        $this->actingAs($user)->put(route('inspection-location-markers.update', $marker), [
            'defect_assessment_id' => $otherAssessment->id,
            'geometry' => $marker->geometry,
            'position' => $marker->position,
            'lock_version' => $marker->lock_version,
            'map_lock_version' => $map->lock_version,
        ])->assertSessionHasErrors('defect_assessment_id');

        $this->assertSame($assessment->id, $marker->fresh()->defect_assessment_id);
    }

    public function test_editor_and_marker_changes_are_blocked_outside_editable_status(): void
    {
        [, $user, $inspection, , $assessment, $map] = $this->context();
        $inspection->update(['status' => InspectionStatus::AwaitingReview]);

        $this->actingAs($user)->get(route('inspection-location-maps.editor', $map))->assertForbidden();
        $this->actingAs($user)->post(route('inspection-location-maps.markers.store', $map), [
            'defect_assessment_id' => $assessment->id,
            'geometry' => ['version' => 1, 'shapes' => [['type' => 'point', 'x' => 0.2, 'y' => 0.3]]],
            'map_lock_version' => 1,
        ])->assertForbidden();
    }

    public function test_editor_reopens_persisted_normalized_geometry(): void
    {
        [, $user, , , $assessment, $map] = $this->context();
        $geometry = ['version' => 1, 'shapes' => [['type' => 'polyline', 'points' => [[0.1, 0.2], [0.8, 0.7]]]]];
        $marker = InspectionLocationMarker::factory()->forMapAndAssessment($map, $assessment)->create(['geometry' => $geometry]);

        $this->actingAs($user)
            ->get(route('inspection-location-maps.editor', $map))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('InspectionLocationMaps/Editor')
                ->where('map.lock_version', 1)
                ->where('markers.0.public_id', $marker->public_id)
                ->where('markers.0.geometry', $geometry)
                ->has('assessments', 1));
    }

    public function test_marker_design_is_normalized_and_can_be_updated_without_losing_photos(): void
    {
        [$organization, $user, $inspection, , $assessment, $map] = $this->context();
        $geometry = ['version' => 1, 'shapes' => [[
            'type' => 'rectangle', 'x' => 0.1, 'y' => 0.2, 'width' => 0.2, 'height' => 0.1,
        ]]];

        $this->actingAs($user)->post(route('inspection-location-maps.markers.store', $map), [
            'defect_assessment_id' => $assessment->id,
            'geometry' => $geometry,
            'style' => ['stroke' => '#ab12cd', 'fill' => '#ab12cd'],
            'map_lock_version' => $map->lock_version,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $marker = InspectionLocationMarker::query()->firstOrFail();
        $this->assertSame('#AB12CD', $marker->style['stroke']);
        $this->assertSame('#AB12CD', $marker->style['fill']);

        $photo = AssessmentPhoto::factory()->create([
            'organization_id' => $organization->id,
            'inspection_id' => $inspection->id,
            'defect_assessment_id' => $assessment->id,
        ]);
        $marker->photos()->attach($photo->id, [
            'organization_id' => $organization->id,
            'inspection_id' => $inspection->id,
            'position' => 1,
        ]);

        $this->actingAs($user)->put(route('inspection-location-markers.update', $marker), [
            'defect_assessment_id' => $assessment->id,
            'label' => $marker->label,
            'geometry' => $geometry,
            'style' => ['stroke' => 'none', 'fill' => '#00aa11'],
            'position' => $marker->position,
            'lock_version' => $marker->lock_version,
            'map_lock_version' => $map->fresh()->lock_version,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $marker->refresh();
        $this->assertSame('none', $marker->style['stroke']);
        $this->assertSame('#00AA11', $marker->style['fill']);
        $this->assertCount(1, $marker->photos);
    }

    public function test_marker_accepts_240_character_additional_legend_and_rejects_longer_text(): void
    {
        [, $user, , , $assessment, $map] = $this->context();

        $this->actingAs($user)->post(route('inspection-location-maps.markers.store', $map), [
            'defect_assessment_id' => $assessment->id,
            'label' => str_repeat('a', 240),
            'geometry' => ['version' => 1, 'shapes' => [['type' => 'point', 'x' => 0.2, 'y' => 0.3]]],
            'map_lock_version' => $map->lock_version,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(str_repeat('a', 240), InspectionLocationMarker::query()->firstOrFail()->label);

        $map->refresh();
        $this->actingAs($user)->post(route('inspection-location-maps.markers.store', $map), [
            'defect_assessment_id' => $assessment->id,
            'label' => str_repeat('b', 241),
            'geometry' => ['version' => 1, 'shapes' => [['type' => 'point', 'x' => 0.5, 'y' => 0.5]]],
            'map_lock_version' => $map->lock_version,
        ])->assertSessionHasErrors('label');
    }

    public function test_marker_rejects_draft_and_non_locatable_assessments(): void
    {
        [, $user, $inspection, $category, $published, $map] = $this->context();
        $draftDefect = Defect::factory()->forEquipment($inspection->equipment, $inspection)->create(['defect_category_id' => $category->id]);
        $draft = DefectAssessment::factory()->forDefect($draftDefect, $inspection)->draft()->create();
        $notLocatedDefect = Defect::factory()->forEquipment($inspection->equipment, $inspection)->create(['defect_category_id' => $category->id]);
        $notLocated = DefectAssessment::factory()->forDefect($notLocatedDefect, $inspection)->complete()->create([
            'condition' => DefectAssessmentCondition::NotLocated,
        ]);
        $payload = [
            'geometry' => ['version' => 1, 'shapes' => [['type' => 'point', 'x' => 0.2, 'y' => 0.3]]],
            'map_lock_version' => $map->lock_version,
        ];

        $this->actingAs($user)->post(route('inspection-location-maps.markers.store', $map), [
            ...$payload,
            'defect_assessment_id' => $draft->id,
        ])->assertSessionHasErrors('defect_assessment_id');

        $this->actingAs($user)->post(route('inspection-location-maps.markers.store', $map), [
            ...$payload,
            'defect_assessment_id' => $notLocated->id,
        ])->assertSessionHasErrors('defect_assessment_id');

        $marker = InspectionLocationMarker::factory()->forMapAndAssessment($map, $published)->create();

        $this->actingAs($user)->put(route('inspection-location-markers.update', $marker), [
            ...$payload,
            'defect_assessment_id' => $draft->id,
            'position' => $marker->position,
            'lock_version' => $marker->lock_version,
        ])->assertSessionHasErrors('defect_assessment_id');

        $this->assertSame($published->id, $marker->fresh()->defect_assessment_id);

        $this->actingAs($user)
            ->get(route('inspection-location-maps.editor', $map))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('assessments', 1)
                ->where('assessments.0.id', $published->id)
                ->where('assessments.0.is_available', false));
    }

    public function test_marker_deletion_uses_current_versions_and_soft_deletes_the_marker(): void
    {
        [, $user, , , $assessment, $map] = $this->context();
        $marker = InspectionLocationMarker::factory()->forMapAndAssessment($map, $assessment)->create();
        $map->update(['lock_version' => 2]);

        $this->actingAs($user)->delete(route('inspection-location-markers.destroy', $marker), [
            'lock_version' => $marker->lock_version,
            'map_lock_version' => 1,
        ])->assertSessionHasErrors('lock_version');

        $this->assertNotSoftDeleted('inspection_location_markers', ['id' => $marker->id]);

        $this->actingAs($user)->delete(route('inspection-location-markers.destroy', $marker), [
            'lock_version' => $marker->lock_version,
            'map_lock_version' => $map->fresh()->lock_version,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSoftDeleted('inspection_location_markers', ['id' => $marker->id]);
        $this->assertSame(3, $map->fresh()->lock_version);
        $this->assertNull(InspectionLocationMarker::withTrashed()->findOrFail($marker->id)->active_slot);

        $this->actingAs($user)->post(route('inspection-location-maps.markers.store', $map), [
            'defect_assessment_id' => $assessment->id,
            'geometry' => ['version' => 1, 'shapes' => [['type' => 'point', 'x' => 0.4, 'y' => 0.4]]],
            'map_lock_version' => $map->fresh()->lock_version,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertCount(1, $assessment->fresh()->locationMarkers);
    }

    public function test_marker_deletion_requires_inspection_access_and_respects_tenant_isolation(): void
    {
        [$organization, $user, , , $assessment, $map] = $this->context();
        $marker = InspectionLocationMarker::factory()->forMapAndAssessment($map, $assessment)->create();
        $payload = [
            'lock_version' => $marker->lock_version,
            'map_lock_version' => $map->lock_version,
        ];

        $unauthorizedUser = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin,
        ]);

        $this->actingAs($unauthorizedUser)
            ->delete(route('inspection-location-markers.destroy', $marker), $payload)
            ->assertForbidden();

        $otherOrganization = Organization::factory()->create();
        $otherTenantUser = User::factory()->for($otherOrganization)->create([
            'account_type' => UserAccountType::CompanyAdmin,
        ]);

        $this->actingAs($otherTenantUser)
            ->delete(route('inspection-location-markers.destroy', $marker), $payload)
            ->assertNotFound();

        $this->assertNotSoftDeleted('inspection_location_markers', ['id' => $marker->id]);
        $this->assertSame($user->organization_id, $marker->organization_id);
    }

    /** @return array{0:Organization,1:User,2:Inspection,3:DefectCategory,4:DefectAssessment,5:InspectionLocationMap} */
    private function context(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::InProgress]);
        InspectionResponsible::factory()->forInspection($inspection, $user)->create(['responsibility' => InspectionResponsibility::Preparer]);
        $category = DefectCategory::factory()->create(['organization_id' => $organization->id, 'code' => 'TA']);
        $defect = Defect::factory()->forEquipment($equipment, $inspection)->create(['defect_category_id' => $category->id]);
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->complete()->create(['comment' => 'Avaliação publicada.']);
        $map = InspectionLocationMap::factory()->forInspection($inspection, $category)->create([
            'processing_status' => InspectionLocationMapProcessingStatus::Ready,
            'background_path' => 'testing/background.webp',
            'background_width' => 1200,
            'background_height' => 800,
        ]);

        return [$organization, $user, $inspection, $category, $assessment, $map];
    }
}
