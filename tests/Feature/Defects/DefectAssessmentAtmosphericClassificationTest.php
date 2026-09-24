<?php

declare(strict_types=1);

namespace Tests\Feature\Defects;

use App\Enums\DefectAssessmentStatus;
use App\Enums\DefectCategory;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class DefectAssessmentAtmosphericClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tac_saves_the_atmosphere_with_gut_and_uses_its_derived_urgency(): void
    {
        [$inspector, $assessment] = $this->tacScenario();

        $this->actingAs($inspector)
            ->put(route('defect-assessments.gut.update', $assessment), [
                'condition' => 'new',
                'atmospheric_classification' => ' c4 ',
                'trend_option_code' => 'grade_1_or_0',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $inspection = $assessment->inspection->refresh();
        $assessment->refresh();

        $this->assertSame('C4', $inspection->atmospheric_classification);
        $this->assertSame($inspector->id, $inspection->updated_by);
        $this->assertSame(3, $assessment->urgency);
        $this->assertSame('C4', $assessment->gut_snapshot['criteria']['urgency']['source']['value']);

        $this->actingAs($inspector)
            ->get(route('defect-assessments.show', $assessment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('gut_definition.sources.urgency.value', 'C4')
                ->where('gut_definition.sources.urgency.score', 3)
                ->missing('capabilities.atmospheric_classification_url'));
    }

    public function test_tac_gut_requires_a_valid_atmosphere_and_keeps_the_inspection_unchanged_on_failure(): void
    {
        [$inspector, $assessment] = $this->tacScenario();

        foreach (['C1', ''] as $value) {
            $this->actingAs($inspector)
                ->put(route('defect-assessments.gut.update', $assessment), [
                    'condition' => 'new',
                    'atmospheric_classification' => $value,
                    'trend_option_code' => 'grade_1_or_0',
                ])
                ->assertSessionHasErrors('atmospheric_classification');

            $this->assertSame('C2', $assessment->inspection->refresh()->atmospheric_classification);
            $this->assertNull($assessment->refresh()->gut_snapshot);
        }
    }

    public function test_non_tac_gut_rejects_an_atmospheric_classification_payload(): void
    {
        [$inspector, $assessment] = $this->tacScenario(category: DefectCategory::Civil);

        $this->actingAs($inspector)
            ->put(route('defect-assessments.gut.update', $assessment), [
                'condition' => 'new',
                'atmospheric_classification' => 'C4',
                'safety_impact_code' => 'primary_above_2m',
                'asset_impact_code' => 'secondary_without_asset_impact',
                'urgency_context_code' => 'function',
                'urgency_option_code' => 'building_support_column',
                'trend_group_code' => 'cracking',
                'trend_option_code' => 'prestressed_or_structural_mechanism',
            ])
            ->assertSessionHasErrors('atmospheric_classification');
    }

    public function test_published_tac_keeps_the_atmosphere_read_only_until_it_returns_to_draft(): void
    {
        [$inspector, $assessment] = $this->tacScenario();
        $assessment->update(['status' => DefectAssessmentStatus::Complete]);

        $this->actingAs($inspector)
            ->get(route('defect-assessments.show', $assessment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('capabilities.gut_url', null)
                ->missing('capabilities.atmospheric_classification_url'));

        $source = file_get_contents(resource_path('js/pages/DefectAssessments/Show.vue'));
        $this->assertStringContainsString('v-if="editing.gut"', $source);
        $this->assertStringNotContainsString('Salvar atmosfera', $source);
    }

    /** @return array{User, DefectAssessment} */
    private function tacScenario(DefectCategory $category = DefectCategory::AnticorrosiveTreatment): array
    {
        $organization = Organization::factory()->create();
        app(TenantContext::class)->set($organization);
        $inspector = User::factory()->for($organization)->create([
            'operational_role' => OperationalRole::Inspector,
        ]);
        $equipment = Equipment::factory()->for($organization)->create(['abc_code' => 'A']);
        $inspection = Inspection::factory()->forEquipment($equipment)->create([
            'status' => InspectionStatus::InProgress,
            'atmospheric_classification' => 'C2',
        ]);
        InspectionResponsible::factory()->forInspection($inspection, $inspector)->create([
            'responsibility' => InspectionResponsibility::Preparer,
        ]);
        $defect = Defect::factory()->forEquipment($equipment, $inspection)->create(['category' => $category]);
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->draft()->create();

        return [$inspector, $assessment];
    }
}
