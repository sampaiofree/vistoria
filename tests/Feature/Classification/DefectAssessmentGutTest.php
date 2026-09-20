<?php

declare(strict_types=1);

namespace Tests\Feature\Classification;

use App\Actions\Classification\SaveDefectAssessmentGut;
use App\Actions\Defects\CompleteDefectAssessment;
use App\Actions\Inspections\SubmitInspectionForReview;
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
use App\Services\InspectionLocations\InspectionLocationReportComposer;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class DefectAssessmentGutTest extends TestCase
{
    use RefreshDatabase;

    public function test_inspector_can_save_native_gut_and_read_a_complete_snapshot(): void
    {
        [$actor, $assessment] = $this->scenario(DefectCategory::StructuralRecovery);
        $this->actingAs($actor)->put(route('defect-assessments.gut.update', $assessment), [
            'condition' => 'new', ...$this->technicalGutPayload(DefectCategory::StructuralRecovery),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $assessment->refresh();
        $this->assertSame(16, $assessment->gut_score);
        $this->assertSame('IE-3', $assessment->classification_code);
        $this->assertSame(3, $assessment->classification_priority);
        $this->assertSame($actor->id, $assessment->classified_by);
        $this->assertNotNull($assessment->classified_at);
        $this->assertSame('REC', $assessment->classification_snapshot['category_code']);
        $this->assertSame('Média', $assessment->classification_snapshot['name']);
        $this->assertSame('#FFFF00', $assessment->classification_snapshot['color']);
        $this->assertSame([16, 35], [$assessment->classification_snapshot['lower_limit'], $assessment->classification_snapshot['upper_limit']]);
        $this->assertSame('native_catalog', $assessment->gut_snapshot['source']);
        $this->assertSame(2, $assessment->gut_snapshot['catalog_version']);
        $this->assertSame(2, $assessment->classification_snapshot['catalog_version']);
        $this->assertSame('calculated', $assessment->gut_snapshot['criteria']['gravity']['mode']);
        $this->assertSame('catalog', $assessment->gut_snapshot['criteria']['urgency']['mode']);
        $this->assertSame('catalog', $assessment->gut_snapshot['criteria']['trend']['mode']);
        $this->assertArrayNotHasKey('classification_id', $assessment->classification_snapshot);
        $this->assertArrayNotHasKey('category_id', $assessment->gut_snapshot);

        $this->actingAs($actor)->get(route('defect-assessments.show', $assessment))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('gut_options.gravity', 5)
                ->where('gut_options.gravity.0.score', 1)
                ->where('gut_options.gravity.0.color', '#00AEEF')
                ->where('gut_definition.catalog_version', 2)
                ->where('gut_definition.category.code', 'REC')
                ->where('gut_definition.safety_impact_options.0.score', 1)
                ->where('gut_definition.safety_impact_options.0.color', '#00AEEF')
                ->where('gut_definition.urgency_options.0.score', 1)
                ->where('gut_definition.urgency_options.0.color', '#00AEEF')
                ->where('classification.code', 'IE-3')
                ->where('classification.color', '#FFFF00')
                ->where('classification.label', 'Média')
                ->where('classification.score_band', '16-35')
                ->missing('assessment.defect_classification_id'));
    }

    public function test_invalid_http_notes_do_not_replace_a_saved_result(): void
    {
        [$actor, $assessment] = $this->scenario();
        $saved = app(SaveDefectAssessmentGut::class)->handle($actor, $assessment, $this->technicalGutPayload(DefectCategory::Civil));
        $snapshot = $saved->classification_snapshot;
        $this->actingAs($actor)->put(route('defect-assessments.gut.update', $assessment), [
            'condition' => 'new', 'gravity' => 0, 'urgency' => 6, 'trend' => 1.5,
        ])->assertSessionHasErrors(['gravity', 'urgency', 'trend']);
        $this->assertSame($snapshot, $assessment->refresh()->classification_snapshot);
        $this->assertSame(125, $assessment->gut_score);
    }

    public function test_published_results_display_saved_metadata_even_when_it_differs_from_the_catalog(): void
    {
        [$actor, $assessment] = $this->scenario(DefectCategory::StructuralRecovery);
        $saved = app(SaveDefectAssessmentGut::class)->handle($actor, $assessment, $this->technicalGutPayload(DefectCategory::StructuralRecovery));
        $snapshot = [...$saved->classification_snapshot, 'name' => 'Classificação histórica', 'color' => '#123456', 'lower_limit' => 12, 'upper_limit' => 18, 'catalog_version' => 0];
        $gut = $saved->gut_snapshot;
        $gut['criteria']['gravity']['color'] = '#654321';
        $saved->update(['status' => 'complete', 'classification_snapshot' => $snapshot, 'gut_snapshot' => $gut]);
        $this->satisfyAssessmentPublicationRequirements($saved);

        $this->actingAs($actor)->get(route('defect-assessments.show', $saved))->assertInertia(fn (Assert $page) => $page
            ->where('classification.label', 'Classificação histórica')
            ->where('classification.color', '#123456')
            ->where('classification.score_band', '12-18')
            ->where('classification.catalog_version', 0)
            ->where('gut_snapshot.criteria.gravity.color', '#654321'));
        $composition = app(InspectionLocationReportComposer::class)->compose($saved->inspection);
        $reportMap = $composition['sheets'][0]['maps'][0];
        $this->assertSame(['code' => 'IE-3', 'color' => '#123456'], $reportMap['damage_rows'][0]['classification']);
        $this->assertSame('#654321', $reportMap['damage_rows'][0]['gut']['gravity']['color']);
        $this->assertSame('#123456', $reportMap['markers'][0]['style']['fill']);
        $this->assertSame($snapshot, $saved->refresh()->classification_snapshot);
    }

    public function test_missing_notes_prevent_publication(): void
    {
        [$actor, $assessment] = $this->scenario();
        $this->satisfyAssessmentPublicationRequirements($assessment);
        try {
            app(CompleteDefectAssessment::class)->handle($actor, $assessment);
            $this->fail('Publicação sem GUT deveria ser rejeitada.');
        } catch (ValidationException $exception) {
            $this->assertSame(['gravity', 'urgency', 'trend'], array_keys($exception->errors()));
        }
    }

    public function test_tac_results_outside_the_native_ranges_can_be_saved_and_published(): void
    {
        foreach (['above_7' => 1, 'grade_6_or_7' => 2] as $trendCode => $expectedScore) {
            [$actor, $assessment] = $this->scenario(DefectCategory::AnticorrosiveTreatment);
            $assessment->inspection->equipment->update(['abc_code' => 'C']);
            $assessment->inspection->update(['atmospheric_classification' => 'C2']);
            $saved = app(SaveDefectAssessmentGut::class)->handle($actor, $assessment, ['trend_option_code' => $trendCode]);
            $this->assertSame($expectedScore, $saved->gut_score);
            $this->assertNull($saved->classification_code);
            $this->assertNull($saved->classification_snapshot);
            $this->assertNull($saved->classification_priority);
            $this->assertSame('TAC', $saved->gut_snapshot['category_code']);
            $this->satisfyAssessmentPublicationRequirements($saved);
            $published = app(CompleteDefectAssessment::class)->handle($actor, $saved);
            $this->assertTrue($published->isComplete());
            $this->assertSame('TAC', $published->defect_snapshot['defect']['category']);
            $this->assertArrayNotHasKey('public_id', $published->defect_snapshot['defect']['category_definition']);
        }
    }

    public function test_tac_requires_valid_equipment_and_inspection_sources(): void
    {
        foreach ([
            ['abc_code', null, 'equipment.abc_code'],
            ['abc_code', 'Z', 'equipment.abc_code'],
            ['atmospheric_classification', null, 'inspection.atmospheric_classification'],
            ['atmospheric_classification', 'C1', 'inspection.atmospheric_classification'],
        ] as [$field, $value, $expectedError]) {
            [$actor, $assessment] = $this->scenario(DefectCategory::AnticorrosiveTreatment);
            $source = $field === 'abc_code' ? $assessment->inspection->equipment : $assessment->inspection;
            $source->update([$field => $value]);

            try {
                app(SaveDefectAssessmentGut::class)->handle($actor, $assessment, ['trend_option_code' => 'grade_4_or_5']);
                $this->fail('Uma fonte TAC ausente ou inválida deveria bloquear o cálculo.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($expectedError, $exception->errors());
            }
        }
    }

    public function test_manual_and_dependent_technical_inputs_are_validated(): void
    {
        [$actor, $recovery] = $this->scenario(DefectCategory::StructuralRecovery);
        $payload = $this->technicalGutPayload(DefectCategory::StructuralRecovery);
        unset($payload['urgency_option_code']);
        $payload['urgency_manual_score'] = 3;

        try {
            app(SaveDefectAssessmentGut::class)->handle($actor, $recovery, $payload);
            $this->fail('A urgência manual sem descrição deveria ser rejeitada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('urgency_manual_description', $exception->errors());
        }

        $payload['urgency_manual_description'] = 'Elemento sem correspondência na matriz nativa.';
        $saved = app(SaveDefectAssessmentGut::class)->handle($actor, $recovery, $payload);
        $this->assertSame('manual', $saved->gut_snapshot['criteria']['urgency']['mode']);
        $this->assertSame(3, $saved->urgency);

        [$recActor, $recoveryWithInvalidTrend] = $this->scenario(DefectCategory::StructuralRecovery);
        $invalidDependentOption = $this->technicalGutPayload(DefectCategory::StructuralRecovery);
        $invalidDependentOption['trend_group_code'] = 'deformation';

        try {
            app(SaveDefectAssessmentGut::class)->handle($recActor, $recoveryWithInvalidTrend, $invalidDependentOption);
            $this->fail('Uma condição de outro dano REC deveria ser rejeitada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('trend_option_code', $exception->errors());
        }
    }

    public function test_conditions_without_gut_clear_the_current_result_and_keep_previous_snapshots(): void
    {
        foreach ([DefectAssessmentCondition::Treated, DefectAssessmentCondition::Canceled, DefectAssessmentCondition::CanceledWithoutRepair] as $condition) {
            [$actor, $assessment] = $this->scenario();
            $saved = app(SaveDefectAssessmentGut::class)->handle($actor, $assessment, $this->technicalGutPayload(DefectCategory::Civil));
            $previous = $saved->classification_snapshot;
            $nextInspection = Inspection::factory()->reinspection($assessment->inspection)->create(['status' => InspectionStatus::InProgress]);
            $next = DefectAssessment::factory()->forDefect($assessment->defect, $nextInspection)->create([
                'previous_assessment_id' => $assessment->id, 'condition' => $condition,
                'gravity' => 5, 'urgency' => 5, 'trend' => 5, 'gut_score' => 125,
                'classification_code' => 'CV-1', 'classification_snapshot' => $previous,
                'gut_snapshot' => $saved->gut_snapshot,
            ]);
            $cleared = app(SaveDefectAssessmentGut::class)->handle($actor, $next, []);
            foreach (['gravity', 'urgency', 'trend', 'gut_score', 'gut_snapshot', 'classification_code', 'classification_snapshot', 'classified_at', 'classified_by'] as $field) {
                $this->assertNull($cleared->$field);
            }
            $this->assertSame($previous, $saved->refresh()->classification_snapshot);
        }
    }

    public function test_another_organization_cannot_change_an_assessment_gut(): void
    {
        [$actor, $assessment] = $this->scenario();
        $other = User::factory()->create(['operational_role' => OperationalRole::Inspector]);
        $this->actingAs($other)->put(route('defect-assessments.gut.update', $assessment), [
            'condition' => 'new', ...$this->technicalGutPayload(DefectCategory::Civil),
        ])->assertForbidden();
        $this->assertNull($assessment->refresh()->gut_score);
    }

    public function test_native_categories_require_location_evidence_before_submitting_an_inspection(): void
    {
        foreach (DefectCategory::cases() as $category) {
            [$actor, $assessment] = $this->scenario($category);
            $saved = app(SaveDefectAssessmentGut::class)->handle($actor, $assessment, $this->technicalGutPayload($category));
            $this->satisfyAssessmentPublicationRequirements($saved);
            app(CompleteDefectAssessment::class)->handle($actor, $saved);
            $submitted = app(SubmitInspectionForReview::class)->handle($assessment->inspection, $actor);
            $this->assertSame(InspectionStatus::AwaitingReview, $submitted->status);
            $this->assertSame(1, $submitted->defectAssessments()->whereNotNull('defect_location_map_version_id')->count());
        }
    }

    /** @return array{User,DefectAssessment} */
    private function scenario(DefectCategory $category = DefectCategory::Civil): array
    {
        $organization = Organization::factory()->create();
        app(TenantContext::class)->set($organization);
        $actor = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);
        $equipment = Equipment::factory()->for($organization)->create(['abc_code' => 'A']);
        $inspection = Inspection::factory()->forEquipment($equipment)->create([
            'status' => InspectionStatus::InProgress,
            'atmospheric_classification' => 'C5',
        ]);
        InspectionResponsible::factory()->forInspection($inspection, $actor)->create(['responsibility' => InspectionResponsibility::Preparer]);
        $defect = Defect::factory()->forEquipment($equipment, $inspection)->create(['category' => $category]);
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->draft()->create([
            'condition' => DefectAssessmentCondition::New, 'comment' => 'Registro suficiente para publicação.',
        ]);

        return [$actor, $assessment];
    }

    /** @return array<string, mixed> */
    private function technicalGutPayload(DefectCategory $category): array
    {
        return match ($category) {
            DefectCategory::Civil => [
                'safety_impact_code' => 'primary_above_2m',
                'asset_impact_code' => 'secondary_without_asset_impact',
                'urgency_option_code' => 'building_support_column',
                'trend_group_code' => 'cracking',
                'trend_manual_description' => 'Fissuração ativa observada em campo.',
                'trend_manual_score' => 5,
            ],
            DefectCategory::StructuralRecovery => [
                'safety_impact_code' => 'secondary_up_to_2m',
                'asset_impact_code' => 'secondary_without_asset_impact',
                'urgency_option_code' => 'guardrail',
                'trend_group_code' => 'discontinuity',
                'trend_option_code' => 'visible_crack_or_insufficient_weld',
            ],
            DefectCategory::AnticorrosiveTreatment => [
                'trend_option_code' => 'grade_1_or_0',
            ],
        };
    }
}
