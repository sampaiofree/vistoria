<?php

declare(strict_types=1);

namespace Tests\Feature\InspectionLocations;

use App\Enums\DefectCategory;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Models\AssessmentPhoto;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InspectionLocationPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_removed_location_index_returns_not_found(): void
    {
        [$user, $inspection] = $this->context();

        $this->actingAs($user)
            ->get('/inspections/'.$inspection->getRouteKey().'/locations')
            ->assertNotFound();
    }

    public function test_editor_is_opened_from_the_assessment_and_exposes_one_location_without_color_controls(): void
    {
        [$user, $inspection] = $this->context();
        $assessment = $this->assessment($inspection, DefectCategory::AnticorrosiveTreatment, 'VT-TA-001', 1);
        $this->locateAssessment($assessment, false);

        $this->actingAs($user)
            ->get(route('defect-assessments.location.editor', $assessment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('DefectAssessments/LocationEditor')
                ->where('assessment.public_id', $assessment->public_id)
                ->where('assessment.color', '#64748B')
                ->where('location.confirmed', false)
                ->has('map.background_url')
                ->has('update_url')
                ->missing('assessments')
                ->missing('store_marker_url'));
    }

    public function test_assessment_exposes_inline_location_editor_data_and_a_draft_ready_photo_count(): void
    {
        [$user, $inspection] = $this->context();
        $assessment = $this->assessment($inspection, DefectCategory::Civil, 'VT-CV-001', 1);
        $this->locateAssessment($assessment);

        $this->actingAs($user)
            ->get(route('defect-assessments.show', $assessment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('location_map.location.confirmed', true)
                ->where('location_map.photo_legend', '0 FOTOS PRONTAS')
                ->where('location_map.background_width', 1600)
                ->where('location_map.background_height', 900)
                ->where('location_map.style.fill', '#64748B')
                ->where('location_map.editor.version', 1)
                ->has('location_map.editor.background_url')
                ->where('location_map.editor.background_width', 1600)
                ->where('location_map.editor.background_height', 900)
                ->where('location_map.editor.update_url', route('defect-assessments.location.update', $assessment))
                ->where('location_map.editor.delete_url', route('defect-assessments.location.destroy', $assessment)));

        AssessmentPhoto::factory()->ready()->create([
            'organization_id' => $assessment->organization_id,
            'inspection_id' => $assessment->inspection_id,
            'defect_assessment_id' => $assessment->id,
        ]);

        $this->actingAs($user)
            ->get(route('defect-assessments.show', $assessment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('location_map.photo_legend', '1 FOTO PRONTA'));

        $source = file_get_contents(resource_path('js/pages/DefectAssessments/Show.vue'));

        $this->assertStringContainsString("import InspectionLocationReportMap", $source);
        $this->assertStringContainsString('const locationPreviewMap = computed', $source);
        $this->assertStringContainsString('<InspectionLocationReportMap v-if="locationPreviewMap" :map="locationPreviewMap"', $source);
    }

    /** @return array{User,Inspection} */
    private function context(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::InProgress]);
        InspectionResponsible::factory()->forInspection($inspection, $user)->create(['responsibility' => InspectionResponsibility::Preparer]);

        return [$user, $inspection];
    }

    private function assessment(Inspection $inspection, DefectCategory $category, string $code, int $sequence): DefectAssessment
    {
        $defect = Defect::factory()->forEquipment($inspection->equipment, $inspection)->create([
            'category' => $category,
            'code' => $code,
            'sequence_number' => $sequence,
        ]);

        return DefectAssessment::factory()->forDefect($defect, $inspection)->draft()->create();
    }
}
