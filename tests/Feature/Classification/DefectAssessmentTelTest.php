<?php

declare(strict_types=1);

namespace Tests\Feature\Classification;

use App\Actions\Classification\SaveDefectAssessmentTelClassification;
use App\Actions\Defects\CompleteDefectAssessment;
use App\Actions\Defects\UpdateDefectAssessment;
use App\Enums\DefectAssessmentCondition;
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
use App\Services\Classification\NativeDefectCatalog;
use App\Services\Classification\TelClassificationResolver;
use App\Services\InspectionLocations\InspectionLocationReportComposer;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class DefectAssessmentTelTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_the_complete_tel_snapshot_and_classification(): void
    {
        [$actor, $assessment] = $this->scenario();

        $this->actingAs($actor)->put(route('defect-assessments.tel.update', $assessment), $this->payload())
            ->assertRedirect()->assertSessionHasNoErrors();

        $assessment->refresh();
        $this->assertSame(12, $assessment->tel_score);
        $this->assertSame('TE-1', $assessment->classification_code);
        $this->assertSame(1, $assessment->classification_priority);
        $this->assertSame('native_tel_catalog', $assessment->tel_snapshot['source']);
        $this->assertSame(NativeDefectCatalog::TEL_CATALOG_VERSION, $assessment->tel_snapshot['catalog_version']);
        $this->assertSame('12', $assessment->tel_snapshot['height_m']);
        $this->assertSame(4, $assessment->tel_snapshot['impact']['score']);
        $this->assertSame(3, $assessment->tel_snapshot['fall_risk']['score']);
        $this->assertSame('roof_corrosion', $assessment->tel_snapshot['damage_group']['code']);
        $this->assertSame('generalized_compromises_fixing', $assessment->tel_snapshot['damage_option']['code']);
        $this->assertSame('TE-1', $assessment->classification_snapshot['code']);
        $this->assertSame('Tratar em até 1 ano', $assessment->tel_snapshot['recommendation']);

        $this->actingAs($actor)->get(route('defect-assessments.show', $assessment))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tel_definition.catalog_version', 1)
                ->where('tel_definition.category.code', 'TEL')
                ->has('tel_definition.damage_groups', 6)
                ->where('tel_classification_ranges.0.code', 'TE-1')
                ->where('capabilities.tel_url', route('defect-assessments.tel.update', $assessment))
                ->where('capabilities.quantity_store_url', null));
    }

    public function test_height_boundaries_and_all_tel_bands_are_resolved_inclusively(): void
    {
        [, $assessment] = $this->scenario();
        $resolver = app(TelClassificationResolver::class);

        foreach ([
            ['height' => 0, 'option' => 'up_to_10_percent', 'impact' => 3, 'score' => 3, 'code' => 'TE-4'],
            ['height' => 10, 'option' => 'above_10_to_20_percent', 'impact' => 3, 'score' => 6, 'code' => 'TE-3'],
            ['height' => 10.01, 'option' => 'above_20_percent', 'impact' => 4, 'score' => 12, 'code' => 'TE-1'],
            ['height' => 15, 'option' => 'above_10_to_20_percent', 'impact' => 4, 'score' => 8, 'code' => 'TE-3'],
            ['height' => 15.01, 'option' => 'above_10_to_20_percent', 'impact' => 5, 'score' => 10, 'code' => 'TE-2'],
            ['height' => 15.01, 'option' => 'up_to_10_percent', 'impact' => 5, 'score' => 5, 'code' => 'TE-4'],
        ] as $case) {
            $resolved = $resolver->resolveTechnical($assessment, [
                'height_m' => $case['height'],
                'damage_group_code' => 'missing_fixing_set',
                'damage_option_code' => $case['option'],
            ]);
            $this->assertSame($case['impact'], $resolved['impact']['score']);
            $this->assertSame($case['score'], $resolved['tel_score']);
            $this->assertSame($case['code'], $resolved['classification']?->code);
        }
    }

    public function test_damage_groups_only_accept_their_own_defined_conditions_and_manual_scores(): void
    {
        [$actor, $assessment] = $this->scenario();

        $this->actingAs($actor)->put(route('defect-assessments.tel.update', $assessment), [
            ...$this->payload(),
            'damage_group_code' => 'lifeline_support',
            'damage_option_code' => 'up_to_10_percent',
        ])->assertSessionHasErrors('damage_option_code');

        $this->actingAs($actor)->put(route('defect-assessments.tel.update', $assessment), [
            ...$this->payload(),
            'impact_score' => 5,
            'risk_score' => 1,
            'fall_risk_score' => 1,
            'tel_score' => 5,
            'classification_score' => 5,
            'classification_code' => 'TE-4',
        ])->assertSessionHasErrors(['impact_score', 'risk_score', 'fall_risk_score', 'tel_score', 'classification_score', 'classification_code']);

        $this->actingAs($actor)->put(route('defect-assessments.tel.update', $assessment), [
            'condition' => DefectAssessmentCondition::New->value,
            'height_m' => 2,
            'damage_group_code' => 'standards_compliance',
            'damage_option_code' => 'no',
        ])->assertSessionHasNoErrors();
        $this->assertSame(3, $assessment->refresh()->tel_score);
        $this->assertSame('TE-4', $assessment->classification_code);
    }

    public function test_every_documented_tel_damage_condition_resolves_only_from_its_own_group(): void
    {
        [, $assessment] = $this->scenario();
        $resolver = app(TelClassificationResolver::class);

        foreach (NativeDefectCatalog::telTechnicalDefinition()['damage_groups'] as $group) {
            foreach ($group['options'] as $option) {
                $resolved = $resolver->resolveTechnical($assessment, [
                    'height_m' => 12,
                    'damage_group_code' => $group['code'],
                    'damage_option_code' => $option['code'],
                ]);

                $this->assertSame($group['code'], $resolved['damage_group']['code']);
                $this->assertSame($option['code'], $resolved['damage_option']['code']);
                $this->assertSame($option['score'], $resolved['risk']['score']);
            }
        }
    }

    public function test_tel_publishes_without_quantity_and_report_uses_its_snapshot_without_gut(): void
    {
        [$actor, $assessment] = $this->scenario();
        app(SaveDefectAssessmentTelClassification::class)->handle($actor, $assessment, $this->payload());
        $this->satisfyAssessmentPublicationRequirements($assessment);

        $published = app(CompleteDefectAssessment::class)->handle($actor, $assessment);
        $this->assertTrue($published->isComplete());
        $this->assertSame(0, $published->quantities()->count());
        $this->assertNull($published->quantity_snapshot);

        $report = app(InspectionLocationReportComposer::class)->compose($assessment->inspection);
        $row = $report['sheets'][0]['maps'][0]['damage_rows'][0];
        $this->assertNull($row['quantity']);
        $this->assertNull($row['gut']);
        $this->assertSame(12, $row['tel']['score']);
        $this->assertSame('roof_corrosion', $row['tel']['damage_group']['code']);
    }

    public function test_tel_is_cleared_for_conditions_that_do_not_require_a_classification_and_is_tenant_scoped(): void
    {
        [$actor, $assessment] = $this->scenario();
        $saved = app(SaveDefectAssessmentTelClassification::class)->handle($actor, $assessment, $this->payload());
        $nextInspection = Inspection::factory()->reinspection($assessment->inspection)->create(['status' => InspectionStatus::InProgress]);
        InspectionResponsible::factory()->forInspection($nextInspection, $actor)->create(['responsibility' => InspectionResponsibility::Preparer]);
        $next = DefectAssessment::factory()->forDefect($assessment->defect, $nextInspection)->create([
            'previous_assessment_id' => $saved->id,
            'condition' => DefectAssessmentCondition::Treated,
            'tel_score' => 12,
            'tel_snapshot' => $saved->tel_snapshot,
            'classification_code' => 'TE-1',
            'classification_snapshot' => $saved->classification_snapshot,
        ]);

        $cleared = app(UpdateDefectAssessment::class)->handle($actor, $next, [
            'condition' => DefectAssessmentCondition::Treated,
        ]);
        $this->assertNull($cleared->tel_score);
        $this->assertNull($cleared->tel_snapshot);
        $this->assertNull($cleared->classification_code);

        $outsider = User::factory()->for(Organization::factory())->create(['operational_role' => OperationalRole::Inspector]);
        $this->actingAs($outsider)->put(route('defect-assessments.tel.update', $assessment), $this->payload())
            ->assertForbidden();
    }

    /** @return array{User, DefectAssessment} */
    private function scenario(): array
    {
        $organization = Organization::factory()->create();
        app(TenantContext::class)->set($organization);
        $actor = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);
        $equipment = Equipment::factory()->for($organization)->create(['abc_code' => 'A']);
        $inspection = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::InProgress]);
        InspectionResponsible::factory()->forInspection($inspection, $actor)->create(['responsibility' => InspectionResponsibility::Preparer]);
        $defect = Defect::factory()->forEquipment($equipment, $inspection)->create(['category' => DefectCategory::RoofCladding]);
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->draft()->create([
            'condition' => DefectAssessmentCondition::New,
            'comment' => 'Registro técnico suficiente.',
            'recommendation' => 'Corrigir a avaria identificada.',
        ]);

        return [$actor, $assessment];
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'condition' => DefectAssessmentCondition::New->value,
            'height_m' => 12,
            'damage_group_code' => 'roof_corrosion',
            'damage_option_code' => 'generalized_compromises_fixing',
        ];
    }
}
