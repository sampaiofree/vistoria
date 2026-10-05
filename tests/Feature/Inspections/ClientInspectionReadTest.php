<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Enums\DefectStatus;
use App\Enums\InspectionStatus;
use App\Enums\UserAccountType;
use App\Models\AssessmentPhoto;
use App\Models\Client;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\DefectLocationMap;
use App\Models\DefectLocationMapVersion;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionOverviewBlock;
use App\Models\InspectionOverviewPhoto;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ClientInspectionReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_list_is_limited_to_released_inspections_and_ignores_internal_filters(): void
    {
        [$user, $equipment] = $this->clientScenario();
        $released = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::Released, 'number' => 'LIB-001']);
        $open = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::InProgress, 'number' => 'OPEN-001']);
        $otherEquipment = Equipment::factory()->create();
        $foreign = Inspection::factory()->forEquipment($otherEquipment)->create(['status' => InspectionStatus::Released]);

        $this->actingAs($user)->get(route('inspections.index', ['status' => 'in_progress', 'scope' => 'available']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('client_view', true)
                ->has('navigation', 1)
                ->where('navigation.0.label', 'Inspeções')
                ->where('notifications', null)
                ->has('inspections.data', 1)
                ->where('inspections.data.0.public_id', $released->public_id)
                ->where('inspections.data.0.show_url', route('inspections.report-preview', $released))
                ->missing('inspections.data.0.stage_responsibles'));
        $this->get(route('inspections.index', ['search' => 'OPEN-001']))
            ->assertInertia(fn (Assert $page) => $page->has('inspections.data', 0));
        $this->get(route('inspections.index', ['search' => 'LIB-001']))
            ->assertInertia(fn (Assert $page) => $page->has('inspections.data', 1));
        $this->get(route('inspections.show', $open))->assertForbidden();
        $this->get(route('inspections.show', $foreign))->assertNotFound();
        $this->get(route('inspections.show', $released))->assertRedirect(route('inspections.report-preview', $released));
        $this->get(route('inspections.report-preview', $open))->assertForbidden();
        $this->get(route('inspections.report-preview', $foreign))->assertNotFound();
    }

    public function test_client_can_read_only_report_and_quantitative_without_internal_navigation(): void
    {
        [$user, $equipment] = $this->clientScenario();
        $released = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::Released]);
        $defect = Defect::factory()->forEquipment($equipment, $released)->create();
        $assessment = DefectAssessment::factory()->forDefect($defect, $released)->complete()->create(['internal_notes' => 'SEGREDO INTERNO']);
        $open = Inspection::factory()->reinspection($released)->create([
            'status' => InspectionStatus::InProgress,
            'number' => 'OPEN-SECRET',
        ]);
        $draft = DefectAssessment::factory()->forDefect($defect, $open)->draft()->create(['previous_assessment_id' => $assessment->id]);
        $defect->update(['status' => DefectStatus::Repaired]);

        $this->actingAs($user);
        foreach (['inspections.report-overview', 'inspections.defects', 'inspections.classifications', 'inspections.photos', 'inspections.team', 'inspections.history'] as $route) {
            $this->get(route($route, $released))->assertForbidden();
        }
        $this->get(route('inspections.report-preview', $released))
            ->assertDontSee('OPEN-SECRET')
            ->assertDontSee('SEGREDO INTERNO')
            ->assertInertia(fn (Assert $page) => $page
                ->where('inspection.defects.0.status', 'active')
                ->missing('inspection.defects.0.correction_requests')
                ->missing('inspection.defects.0.assessment.internal_notes')
                ->missing('inspection.next_inspections.0')
                ->where('inspection_navigation', null)
                ->where('tabs', [])
                ->where('inspection.quantitative_url', route('inspections.quantitative', $released))
                ->missing('inspection.defects_url')
                ->missing('inspection.photos_url')
                ->missing('content.findings.0.assessment_url')
                ->missing('content.findings.0.assessment.show_url'));
        $this->get(route('inspections.quantitative', $released))
            ->assertInertia(fn (Assert $page) => $page
                ->where('inspection_navigation', null)
                ->where('tabs', [])
                ->where('inspection.overview_url', route('inspections.report-preview', $released))
                ->where('export_url', route('inspections.quantitative.export', $released)));
        $this->get(route('defect-assessments.show', $assessment))->assertForbidden();
        $this->get(route('defect-assessments.show', $draft))->assertForbidden();
        $this->get(route('inspections.defects.show', [$open, $defect]))->assertForbidden();
        $this->get(route('inspections.defects.show', [$released, $defect]))->assertForbidden();
        $this->get(route('inspections.defects.historical', [$released, $defect]))->assertForbidden();
        $this->patch(route('defect-assessments.update', $assessment), [])->assertForbidden();
        $this->get(route('inspections.quantitative.export', $released))->assertOk();
        $this->get(route('inspections.quantitative', $open))->assertForbidden();
        $this->get(route('inspections.quantitative.export', $open))->assertForbidden();
    }

    public function test_internal_users_keep_inspection_navigation_and_full_assessment_link(): void
    {
        [, $equipment] = $this->clientScenario();
        $admin = User::factory()->for($equipment->organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $inspection = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::Released]);
        $defect = Defect::factory()->forEquipment($equipment, $inspection)->create();
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->complete()->create();

        $this->actingAs($admin)->get(route('inspections.show', $inspection))->assertOk();
        $this->get(route('inspections.report-preview', $inspection))
            ->assertInertia(fn (Assert $page) => $page
                ->where('inspection_navigation.mode', 'inspection')
                ->where('tabs.0.key', 'overview')
                ->where('content.findings.0.assessment_url', route('defect-assessments.show', $assessment)));
        $this->get(route('defect-assessments.show', $assessment))->assertOk();
        $this->get(route('inspections.quantitative', $inspection))
            ->assertInertia(fn (Assert $page) => $page
                ->where('inspection.overview_url', route('inspections.show', $inspection))
                ->where('inspection_navigation.mode', 'inspection'));
    }

    public function test_client_cannot_open_photo_of_unreleased_assessment(): void
    {
        [$user, $equipment] = $this->clientScenario();
        $released = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::Released]);
        $open = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::InProgress]);
        $defect = Defect::factory()->forEquipment($equipment, $released)->create();
        $assessment = DefectAssessment::factory()->forDefect($defect, $open)->complete()->create();
        $photo = AssessmentPhoto::factory()->create([
            'organization_id' => $equipment->organization_id,
            'inspection_id' => $open->id,
            'defect_assessment_id' => $assessment->id,
        ]);

        $this->actingAs($user)->get(route('defect-assessments.show', $assessment))->assertForbidden();
        $this->get(route('assessment-photos.show', $photo))->assertForbidden();
    }

    public function test_client_assets_are_bound_to_released_assessments_and_overview(): void
    {
        [$user, $equipment] = $this->clientScenario();
        $released = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::Released]);
        $open = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::InProgress]);
        $defect = Defect::factory()->forEquipment($equipment, $released)->create();
        $assessment = DefectAssessment::factory()->forDefect($defect, $released)->complete()->create();
        $draft = DefectAssessment::factory()->forDefect($defect, $open)->draft()->create();
        $map = DefectLocationMap::factory()->forDefect($defect)->create();
        $mapVersion = DefectLocationMapVersion::factory()->forMapAndAssessment($map, $draft)->ready()->create();
        $draft->update(['defect_location_map_version_id' => $mapVersion->id]);
        $releasedMapVersion = DefectLocationMapVersion::factory()->forMapAndAssessment($map, $assessment)
            ->ready()->create(['version' => 2]);
        $mapPath = sprintf('organizations/%d/defects/%s/maps/%s/versions/%s/derivatives/background.webp',
            $equipment->organization_id, $defect->public_id, $map->public_id, $releasedMapVersion->public_id);
        $releasedMapVersion->update(['background_path' => $mapPath]);
        $assessment->update(['defect_location_map_version_id' => $releasedMapVersion->id]);

        Storage::fake('inspection_photos');
        Storage::fake('inspection_maps');
        Storage::disk('inspection_maps')->put($mapPath, 'map');
        Storage::disk('inspection_photos')->put('client-photo.webp', 'photo');
        $visiblePhoto = AssessmentPhoto::factory()->ready()->create([
            'organization_id' => $equipment->organization_id,
            'inspection_id' => $released->id,
            'defect_assessment_id' => $assessment->id,
            'optimized_path' => 'client-photo.webp',
        ]);
        $overviewBlock = InspectionOverviewBlock::factory()->forInspection($open)->create();
        $hiddenOverviewPhoto = InspectionOverviewPhoto::factory()->forBlock($overviewBlock)->ready()->create();
        $visibleOverviewBlock = InspectionOverviewBlock::factory()->forInspection($released)->create();
        $visibleOverviewPhoto = InspectionOverviewPhoto::factory()->forBlock($visibleOverviewBlock)->ready()->create([
            'optimized_path' => 'client-photo.webp',
        ]);

        $this->actingAs($user)->get(route('assessment-photos.show', $visiblePhoto))->assertOk();
        $this->get(route('inspection-overview-photos.show', $visibleOverviewPhoto))->assertOk();
        $this->get(route('inspection-overview-photos.show', $hiddenOverviewPhoto))->assertForbidden();
        $this->get(route('defect-location-map-versions.background', $mapVersion))->assertForbidden();
        $this->get(route('defect-location-map-versions.background', $releasedMapVersion))->assertOk();
    }

    /** @return array{User, Equipment} */
    private function clientScenario(): array
    {
        $organization = Organization::factory()->create();
        $client = Client::factory()->for($organization)->create();
        $equipment = Equipment::factory()->inStructure($client)->create();
        $user = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::Client,
            'operational_role' => null,
        ]);

        return [$user, $equipment];
    }
}
