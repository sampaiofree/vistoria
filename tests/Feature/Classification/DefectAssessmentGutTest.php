<?php

declare(strict_types=1);

namespace Tests\Feature\Classification;

use App\Actions\Classification\SaveDefectAssessmentGut;
use App\Actions\Classification\CreateDefectAssessmentQuantity;
use App\Actions\Defects\CompleteDefectAssessment;
use App\Actions\Defects\UpdateDefectAssessment;
use App\Actions\Inspections\SubmitInspectionForPlanning;
use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectCategory;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\DefectAssessmentQuantity;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionOverviewBlock;
use App\Models\InspectionOverviewPhoto;
use App\Models\InspectionResponsible;
use App\Models\InspectionSpecialAssessmentNote;
use App\Models\Organization;
use App\Models\User;
use App\Services\Classification\NativeDefectCatalog;
use App\Services\Demo\ViewFirstDemoPresenter;
use App\Services\InspectionLocations\InspectionLocationReportComposer;
use App\Services\Inspections\InspectionClassificationM2CoverageValidator;
use App\Services\Reports\BuildInspectionClassificationSummary;
use App\Services\Reports\BuildInspectionQuantitativeWorksheet;
use App\Services\Reports\ExportInspectionQuantitativeWorksheet;
use App\Services\Reports\GutGravityLegend;
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
        $this->assertSame(5, $assessment->gut_snapshot['catalog_version']);
        $this->assertSame(5, $assessment->classification_snapshot['catalog_version']);
        $this->assertSame('calculated', $assessment->gut_snapshot['criteria']['gravity']['mode']);
        $this->assertSame('catalog', $assessment->gut_snapshot['criteria']['urgency']['mode']);
        $this->assertSame('structural_function', $assessment->gut_snapshot['criteria']['urgency']['matrix']['code']);
        $this->assertNull($assessment->gut_snapshot['criteria']['urgency']['transporter_type']);
        $this->assertSame('catalog', $assessment->gut_snapshot['criteria']['trend']['mode']);
        $this->assertArrayNotHasKey('classification_id', $assessment->classification_snapshot);
        $this->assertArrayNotHasKey('category_id', $assessment->gut_snapshot);

        $this->actingAs($actor)->get(route('defect-assessments.show', $assessment))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('gut_options.gravity', 5)
                ->where('gut_options.gravity.0.score', 1)
                ->where('gut_options.gravity.0.color', '#00AEEF')
                ->where('gut_definition.catalog_version', 5)
                ->where('gut_definition.category.code', 'REC')
                ->where('gut_definition.safety_impact_options.0.score', 1)
                ->where('gut_definition.safety_impact_options.0.color', '#00AEEF')
                ->where('gut_definition.urgency_matrices.0.code', 'structural_function')
                ->where('gut_definition.urgency_matrices.1.transporter_types.0.code', 'elevated_gallery')
                ->where('classification.code', 'IE-3')
                ->where('classification.color', '#FFFF00')
                ->where('classification.label', 'Média')
                ->where('classification.score_band', '16-35')
                ->missing('assessment.defect_classification_id'));
    }

    public function test_saved_gut_classification_color_is_used_by_the_confirmed_location_preview(): void
    {
        [$actor, $assessment] = $this->scenario(DefectCategory::Civil);
        $saved = app(SaveDefectAssessmentGut::class)->handle($actor, $assessment, $this->technicalGutPayload(DefectCategory::Civil));
        $this->locateAssessment($saved);

        $this->actingAs($actor)
            ->get(route('defect-assessments.show', $saved))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('location_map.location.confirmed', true)
                ->where('location_map.color', $saved->classification_snapshot['color'])
                ->where('location_map.style.fill', $saved->classification_snapshot['color']));
    }

    public function test_report_map_preserves_the_selected_trend_group_for_civil_and_rec(): void
    {
        foreach ([
            [DefectCategory::Civil, 'cracking', 'Fissuração'],
            [DefectCategory::StructuralRecovery, 'discontinuity', 'DESCONTINUIDADE'],
        ] as [$category, $code, $label]) {
            [$actor, $assessment] = $this->scenario($category);
            $saved = app(SaveDefectAssessmentGut::class)->handle($actor, $assessment, $this->technicalGutPayload($category));
            $this->satisfyAssessmentPublicationRequirements($saved);
            $published = app(CompleteDefectAssessment::class)->handle($actor, $saved);

            $map = app(InspectionLocationReportComposer::class)->compose($published->inspection)['sheets'][0]['maps'][0];

            $this->assertSame(
                ['code' => $code, 'label' => $label],
                $map['damage_rows'][0]['gut']['trend']['group'],
            );
            $this->assertSame(
                $published->gut_snapshot['criteria']['trend']['color'],
                $map['damage_rows'][0]['gut']['trend']['color'],
            );
        }
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
        $this->assertSame(
            ['code' => 'discontinuity', 'label' => 'DESCONTINUIDADE'],
            $reportMap['damage_rows'][0]['gut']['trend']['group'],
        );
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

    public function test_rec_urgency_requires_its_technical_matrix_and_rejects_manual_scores(): void
    {
        [$actor, $recovery] = $this->scenario(DefectCategory::StructuralRecovery);
        $payload = $this->technicalGutPayload(DefectCategory::StructuralRecovery);
        $payload['urgency_manual_score'] = 3;

        try {
            app(SaveDefectAssessmentGut::class)->handle($actor, $recovery, $payload);
            $this->fail('A urgência manual REC deveria ser rejeitada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('urgency_manual_score', $exception->errors());
        }

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

    public function test_every_rec_urgency_matrix_option_resolves_its_documented_score(): void
    {
        [, $assessment] = $this->scenario(DefectCategory::StructuralRecovery);
        $resolver = app(\App\Services\Classification\GutClassificationResolver::class);
        $base = $this->technicalGutPayload(DefectCategory::StructuralRecovery);

        foreach (\App\Services\Classification\NativeDefectCatalog::recUrgencyMatrices() as $matrix) {
            $sources = $matrix['code'] === 'structural_function'
                ? [['type' => null, 'options' => $matrix['options']]]
                : array_map(fn (array $type): array => ['type' => $type['code'], 'options' => $type['options']], $matrix['transporter_types']);

            foreach ($sources as $source) {
                foreach ($source['options'] as $option) {
                    $resolved = $resolver->resolveTechnical($assessment, [
                        ...$base,
                        'urgency_matrix_code' => $matrix['code'],
                        'transporter_type_code' => $source['type'],
                        'urgency_option_code' => $option['code'],
                    ]);
                    $urgency = $resolved['criteria']['urgency'];

                    $this->assertSame($option['score'], $urgency['score']);
                    $this->assertSame($matrix['code'], $urgency['matrix']['code']);
                    $this->assertSame($source['type'], $urgency['transporter_type']['code'] ?? null);
                    $this->assertSame($option['code'], $urgency['option']['code']);
                }
            }
        }
    }

    public function test_rec_transporter_snapshot_preserves_matrix_type_option_and_score(): void
    {
        [$actor, $assessment] = $this->scenario(DefectCategory::StructuralRecovery);
        $saved = app(SaveDefectAssessmentGut::class)->handle($actor, $assessment, [
            ...$this->technicalGutPayload(DefectCategory::StructuralRecovery),
            'urgency_matrix_code' => 'patio_port_transporter',
            'transporter_type_code' => 'elevated_truss_bridge',
            'urgency_option_code' => 'diagonal_or_post',
        ]);

        $urgency = $saved->gut_snapshot['criteria']['urgency'];
        $this->assertSame(3, $urgency['score']);
        $this->assertSame('patio_port_transporter', $urgency['matrix']['code']);
        $this->assertSame('elevated_truss_bridge', $urgency['transporter_type']['code']);
        $this->assertSame('Diagonal / Montante', $urgency['option']['label']);
        $this->assertSame('#FFFF00', $urgency['color']);
    }

    public function test_rec_urgency_rejects_crossed_matrix_and_transporter_combinations(): void
    {
        [$actor, $assessment] = $this->scenario(DefectCategory::StructuralRecovery);
        $base = $this->technicalGutPayload(DefectCategory::StructuralRecovery);

        foreach ([
            [...$base, 'urgency_matrix_code' => 'patio_port_transporter', 'transporter_type_code' => null],
            [...$base, 'urgency_matrix_code' => 'structural_function', 'transporter_type_code' => 'elevated_gallery'],
            [...$base, 'urgency_matrix_code' => 'patio_port_transporter', 'transporter_type_code' => 'elevated_gallery', 'urgency_option_code' => 'walkway_profile'],
        ] as $payload) {
            try {
                app(SaveDefectAssessmentGut::class)->handle($actor, $assessment, $payload);
                $this->fail('Combinações entre matrizes REC deveriam ser rejeitadas.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }

        $this->actingAs($actor)->put(route('defect-assessments.gut.update', $assessment), [
            'condition' => 'new', ...$base, 'urgency_manual_score' => 4,
        ])->assertSessionHasErrors('urgency_manual_score');
    }

    public function test_every_civil_urgency_context_and_trend_condition_resolves_its_documented_score(): void
    {
        [, $assessment] = $this->scenario(DefectCategory::Civil);
        $resolver = app(\App\Services\Classification\GutClassificationResolver::class);
        $base = $this->technicalGutPayload(DefectCategory::Civil);

        foreach (\App\Services\Classification\NativeDefectCatalog::civilUrgencyContexts() as $context) {
            foreach ($context['options'] as $option) {
                $resolved = $resolver->resolveTechnical($assessment, [
                    ...$base,
                    'urgency_context_code' => $context['code'],
                    'urgency_option_code' => $option['code'],
                    ...($context['code'] === 'machine_running_path' ? [
                        'trend_group_code' => 'rail_joint', 'trend_option_code' => 'joint_gap',
                    ] : []),
                ]);

                $urgency = $resolved['criteria']['urgency'];
                $this->assertSame($option['score'], $urgency['score']);
                $this->assertSame($context['code'], $urgency['context']['code']);
                $this->assertSame($option['code'], $urgency['option']['code']);
            }
        }

        $definition = \App\Services\Classification\NativeDefectCatalog::technicalDefinition(DefectCategory::Civil);
        $this->assertCount(16, $definition['trend_groups']);
        foreach ($definition['trend_groups'] as $group) {
            foreach ($group['options'] as $option) {
                $resolved = $resolver->resolveTechnical($assessment, [
                    ...$base,
                    'trend_group_code' => $group['code'],
                    'trend_option_code' => $option['code'],
                    'urgency_context_code' => $group['urgency_context_code'],
                    'urgency_option_code' => $group['urgency_context_code'] === 'function' ? 'building_support_column' : 'running_rail',
                ]);

                $trend = $resolved['criteria']['trend'];
                $this->assertSame($option['score'], $trend['score']);
                $this->assertSame($group['code'], $trend['group']['code']);
                $this->assertSame($option['code'], $trend['option']['code']);
            }
        }

        foreach (['chemical_effects', 'rail_wear', 'rail_fixing', 'sleepers'] as $groupCode) {
            $group = collect($definition['trend_groups'])->firstWhere('code', $groupCode);
            $this->assertSame([1, 2, 3], array_column($group['options'], 'score'));
        }
        $railJoint = collect($definition['trend_groups'])->firstWhere('code', 'rail_joint');
        $this->assertSame([1, 2], array_column($railJoint['options'], 'score'));
    }

    public function test_civil_rejects_trend_groups_from_the_other_urgency_context_without_changing_saved_data(): void
    {
        [$actor, $assessment] = $this->scenario(DefectCategory::Civil);
        $base = $this->technicalGutPayload(DefectCategory::Civil);
        $assessment = app(SaveDefectAssessmentGut::class)->handle($actor, $assessment, $base);
        $before = $assessment->getRawOriginal();

        foreach ([
            [...$base, 'trend_group_code' => 'rail_joint', 'trend_option_code' => 'joint_gap'],
            [...$base, 'urgency_context_code' => 'machine_running_path', 'urgency_option_code' => 'running_rail'],
        ] as $payload) {
            $this->actingAs($actor)->put(route('defect-assessments.gut.update', $assessment), ['condition' => 'new', ...$payload])
                ->assertSessionHasErrors(['trend_group_code' => 'Escolha um tipo de degradação compatível com o contexto de urgência informado.']);
            $this->assertSame($before, $assessment->refresh()->getRawOriginal());
        }
    }

    public function test_reading_a_legacy_crossed_civil_snapshot_preserves_the_saved_choices(): void
    {
        [$actor, $assessment] = $this->scenario(DefectCategory::Civil);
        $assessment = app(SaveDefectAssessmentGut::class)->handle($actor, $assessment, $this->technicalGutPayload(DefectCategory::Civil));
        $snapshot = $assessment->gut_snapshot;
        $snapshot['criteria']['trend']['group'] = ['code' => 'rail_joint', 'label' => 'Emenda dos trilhos'];
        $snapshot['criteria']['trend']['option'] = NativeDefectCatalog::trend(DefectCategory::Civil, 'rail_joint', 'joint_gap');
        $snapshot['criteria']['trend']['score'] = 1;
        $snapshot['criteria']['trend']['color'] = NativeDefectCatalog::colorForScore(1);
        $assessment->update(['gut_snapshot' => $snapshot]);
        $before = $assessment->getRawOriginal();

        $this->actingAs($actor)->get(route('defect-assessments.show', $assessment))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('gut_snapshot.criteria.urgency.context.code', 'function')
                ->where('gut_snapshot.criteria.trend.group.code', 'rail_joint'));
        $this->assertSame($before, $assessment->refresh()->getRawOriginal());
    }

    public function test_civil_requires_compatible_contextual_choices_and_rejects_manual_notes(): void
    {
        [$actor, $assessment] = $this->scenario(DefectCategory::Civil);
        $base = $this->technicalGutPayload(DefectCategory::Civil);

        foreach ([
            [...$base, 'urgency_context_code' => null],
            [...$base, 'urgency_context_code' => 'function', 'urgency_option_code' => 'running_rail'],
            [...$base, 'urgency_context_code' => 'machine_running_path', 'urgency_option_code' => 'building_support_column'],
            [...$base, 'urgency_matrix_code' => 'structural_function'],
            [...$base, 'trend_group_code' => 'chemical_effects', 'trend_option_code' => 'prestressed_or_structural_mechanism'],
        ] as $payload) {
            try {
                app(SaveDefectAssessmentGut::class)->handle($actor, $assessment, $payload);
                $this->fail('Combinações CIVIL incompatíveis deveriam ser rejeitadas.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }

        $this->actingAs($actor)->put(route('defect-assessments.gut.update', $assessment), [
            'condition' => 'new', ...$base,
            'gravity' => 5,
            'urgency_manual_score' => 4,
            'trend_manual_score' => 3,
        ])->assertSessionHasErrors(['gravity', 'urgency_manual_score', 'trend_manual_score']);
    }

    public function test_civil_snapshot_preserves_context_and_technical_trend_without_manual_notes(): void
    {
        [$actor, $assessment] = $this->scenario(DefectCategory::Civil);
        $saved = app(SaveDefectAssessmentGut::class)->handle($actor, $assessment, [
            ...$this->technicalGutPayload(DefectCategory::Civil),
            'urgency_context_code' => 'machine_running_path',
            'urgency_option_code' => 'rail_joint',
            'trend_group_code' => 'rail_joint',
            'trend_option_code' => 'joint_alignment',
        ]);

        $this->assertSame(5, $saved->gut_snapshot['catalog_version']);
        $this->assertSame('machine_running_path', $saved->gut_snapshot['criteria']['urgency']['context']['code']);
        $this->assertSame('rail_joint', $saved->gut_snapshot['criteria']['urgency']['option']['code']);
        $this->assertSame('rail_joint', $saved->gut_snapshot['criteria']['trend']['group']['code']);
        $this->assertSame('joint_alignment', $saved->gut_snapshot['criteria']['trend']['option']['code']);
        $this->assertArrayNotHasKey('manual', $saved->gut_snapshot['criteria']['trend']);
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
        foreach ([DefectCategory::Civil, DefectCategory::AnticorrosiveTreatment, DefectCategory::StructuralRecovery] as $category) {
            [$actor, $assessment] = $this->scenario($category);
            $saved = app(SaveDefectAssessmentGut::class)->handle($actor, $assessment, $this->technicalGutPayload($category));
            $this->satisfyAssessmentPublicationRequirements($saved);
            app(CompleteDefectAssessment::class)->handle($actor, $saved);
            $this->completeOverview($assessment->inspection);
            $planner = User::factory()->create(['organization_id' => $assessment->organization_id, 'operational_role' => OperationalRole::Planner]);
            $assessment->inspection->responsibles()->where('responsibility', 'preparer')->update(['responsibility' => 'reviewer']);
            InspectionResponsible::factory()->forInspection($assessment->inspection, $planner)->create(['responsibility' => InspectionResponsibility::Preparer]);
            $submitted = app(SubmitInspectionForPlanning::class)->handle($assessment->inspection, $actor);
            $this->assertSame(InspectionStatus::AwaitingM2, $submitted->status);
            $this->assertSame(1, $submitted->defectAssessments()->whereNotNull('defect_location_map_version_id')->count());
        }
    }

    public function test_solidary_structures_publish_without_quantity_gut_or_location_map(): void
    {
        [$actor, $assessment] = $this->scenario(DefectCategory::SolidaryStructures);
        $this->satisfyAssessmentPublicationRequirements($assessment);

        $published = app(CompleteDefectAssessment::class)->handle($actor, $assessment);

        $this->assertTrue($published->isComplete());
        $this->assertNull($published->quantity_snapshot);
        $this->assertNull($published->gut_score);
        $this->assertNull($published->defect_location_map_version_id);

        $this->actingAs($actor)
            ->get(route('defect-assessments.show', $published))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('assessment.defect.category', 'ES')
                ->where('gut_definition', null)
                ->where('quantity_definition', null)
                ->where('location_map', null)
                ->where('capabilities.gut_url', null)
                ->where('capabilities.quantity_store_url', null)
                ->where('capabilities.location_map_upload_url', null));
    }

    public function test_engineering_note_preserves_gut_and_quantity_through_publication(): void
    {
        [$actor, $assessment] = $this->scenario(DefectCategory::Civil);
        $saved = app(SaveDefectAssessmentGut::class)->handle(
            $actor,
            $assessment,
            $this->technicalGutPayload(DefectCategory::Civil),
        );
        DefectAssessmentQuantity::factory()->forAssessment($assessment)->create();
        $this->assertSame(1, $assessment->quantities()->count());

        $updated = app(UpdateDefectAssessment::class)->handle($actor, $saved, [
            'condition' => DefectAssessmentCondition::New,
            'classification_method' => 'engineering_note',
        ]);

        $this->assertSame('engineering_note', $updated->classification_method->value);
        foreach (['gravity', 'urgency', 'trend', 'gut_score', 'gut_snapshot', 'classification_code', 'classification_snapshot'] as $field) {
            $this->assertEquals($saved->$field, $updated->$field);
        }
        $this->assertSame(1, $updated->quantities()->count());
        $this->assertNull($updated->quantity_snapshot);

        $this->actingAs($actor)->get(route('defect-assessments.show', $updated))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('capabilities.gut_url', route('defect-assessments.gut.update', $updated))
                ->where('capabilities.quantity_store_url', route('defect-assessments.quantities.store', $updated)));
        $this->actingAs($actor)->put(route('defect-assessments.gut.update', $updated), [
            'condition' => 'new', ...$this->technicalGutPayload(DefectCategory::Civil),
        ])->assertRedirect()->assertSessionHasNoErrors();
        app(TenantContext::class)->set($assessment->defect->organization);
        $updated->refresh();

        $removed = app(UpdateDefectAssessment::class)->handle($actor, $updated, [
            'classification_method' => 'gut',
        ]);
        $this->assertSame('gut', $removed->classification_method->value);
        $this->assertSame($saved->gut_score, $removed->gut_score);
        $this->assertSame(1, $removed->quantities()->count());
        $updated = app(UpdateDefectAssessment::class)->handle($actor, $removed, [
            'classification_method' => 'engineering_note',
        ]);

        $this->satisfyAssessmentPublicationRequirements($updated);
        $this->assertSame(1, $updated->quantities()->count());
        $published = app(CompleteDefectAssessment::class)->handle($actor, $updated);
        $this->assertTrue($published->isComplete());
        $this->assertSame(1, $published->quantity_snapshot['item_count']);
        $this->assertSame($saved->gut_score, $published->gut_score);

        $this->actingAs($actor)
            ->get(route('defect-assessments.show', $published))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('assessment.classification_method', 'engineering_note')
                ->where('assessment.gut_score', $saved->gut_score)
                ->where('classification.code', $saved->classification_code)
                ->has('quantities', 1)
                ->where('quantity_snapshot.item_count', 1)
                ->has('classification_method_options', 2));

        $map = app(InspectionLocationReportComposer::class)->compose($published->inspection)['sheets'][0]['maps'][0];
        $this->assertFalse($map['engineering_note_without_quantity']);
        $this->assertSame($published->classification_snapshot['color'], $map['markers'][0]['style']['fill']);
        $quantityRowsKey = $published->defect->category === DefectCategory::Civil ? 'civil_quantity_rows' : 'rec_quantity_rows';
        $this->actingAs($actor)->get(route('inspections.report-preview', $published->inspection))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('content.'.$quantityRowsKey, 1));
    }

    public function test_removing_the_last_engineering_note_quantity_clears_gut_and_adding_one_requires_it_again(): void
    {
        [$actor, $assessment] = $this->scenario(DefectCategory::Civil);
        $assessment = app(SaveDefectAssessmentGut::class)->handle($actor, $assessment, $this->technicalGutPayload(DefectCategory::Civil));
        $assessment = app(UpdateDefectAssessment::class)->handle($actor, $assessment, [
            'classification_method' => 'engineering_note',
        ]);
        $this->assertNull($assessment->gut_score);
        $this->assertNull($assessment->classification_code);
        $item = DefectAssessmentQuantity::factory()->forAssessment($assessment)->create();
        $assessment = app(SaveDefectAssessmentGut::class)->handle($actor, $assessment, $this->technicalGutPayload(DefectCategory::Civil));
        $this->assertNotNull($assessment->gut_score);

        $this->actingAs($actor)->delete(route('defect-assessment-quantities.destroy', $item))
            ->assertRedirect()->assertSessionHasNoErrors();
        $assessment->refresh();
        $this->assertNull($assessment->gut_score);
        $this->assertNull($assessment->gut_snapshot);
        $this->assertNull($assessment->classification_code);

        $this->actingAs($actor)->post(route('defect-assessments.quantities.store', $assessment), [
            'quantity' => ['length' => 1, 'height' => 1, 'width' => 1, 'quantity' => 1],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, $assessment->quantities()->count());
        $this->assertNull($assessment->refresh()->gut_score);

        $this->satisfyAssessmentPublicationRequirements($assessment);
        app(TenantContext::class)->set($assessment->defect->organization);
        try {
            app(CompleteDefectAssessment::class)->handle($actor, $assessment->fresh());
            $this->fail('GUT deveria ser exigido após adicionar quantitativo.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('gravity', $exception->errors());
        }
        $assessment = app(SaveDefectAssessmentGut::class)->handle($actor, $assessment, $this->technicalGutPayload(DefectCategory::Civil));
        $published = app(CompleteDefectAssessment::class)->handle($actor, $assessment);
        $this->assertTrue($published->isComplete());
        $this->assertSame(1, $published->quantity_snapshot['item_count']);
    }

    public function test_rec_engineering_note_with_quantity_uses_gut_color_and_enters_the_report_annex(): void
    {
        [$actor, $assessment] = $this->scenario(DefectCategory::StructuralRecovery);
        $assessment = app(UpdateDefectAssessment::class)->handle($actor, $assessment, [
            'classification_method' => 'engineering_note',
        ]);
        $this->satisfyAssessmentPublicationRequirements($assessment);
        $assessment = app(SaveDefectAssessmentGut::class)->handle($actor, $assessment, $this->technicalGutPayload(DefectCategory::StructuralRecovery));
        $published = app(CompleteDefectAssessment::class)->handle($actor, $assessment);

        $map = app(InspectionLocationReportComposer::class)->compose($published->inspection)['sheets'][0]['maps'][0];
        $this->assertFalse($map['engineering_note_without_quantity']);
        $this->assertSame($published->classification_snapshot['color'], $map['markers'][0]['style']['fill']);
        $this->assertNotNull(app(BuildInspectionQuantitativeWorksheet::class)->build($published->inspection)['rows'][0]['cells']['quantity']['value']);
        $this->actingAs($actor)->get(route('inspections.report-preview', $published->inspection))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('content.rec_quantity_rows', 1));
    }

    public function test_adding_the_first_quantity_to_a_published_engineering_note_requires_republication_with_gut(): void
    {
        foreach ([DefectCategory::Civil, DefectCategory::StructuralRecovery] as $category) {
            [$actor, $assessment] = $this->scenario($category);
            $assessment = app(UpdateDefectAssessment::class)->handle($actor, $assessment, [
                'classification_method' => 'engineering_note',
            ]);
            $this->satisfyAssessmentPublicationRequirements($assessment);
            $assessment->quantities()->delete();
            app(TenantContext::class)->set($assessment->defect->organization);
            $published = app(CompleteDefectAssessment::class)->handle($actor, $assessment->fresh());

            $updated = app(CreateDefectAssessmentQuantity::class)->handle($actor, $published, [
                'quantity' => $category === DefectCategory::Civil
                    ? ['length' => 1, 'height' => 1, 'width' => 1, 'quantity' => 1]
                    : ['element' => 'bolted_connection', 'total_weight' => 1],
            ]);

            $this->assertTrue($updated->isDraft());
            $this->assertCount(1, $updated->quantities);
            $this->assertNull($updated->quantity_snapshot);
            try {
                app(CompleteDefectAssessment::class)->handle($actor, $updated);
                $this->fail('A Nota com quantitativo não pode ser republicada sem GUT.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('gravity', $exception->errors());
            }
        }
    }

    public function test_civil_and_rec_engineering_notes_publish_without_quantities_or_gut(): void
    {
        foreach ([DefectCategory::Civil, DefectCategory::StructuralRecovery] as $category) {
            [$actor, $assessment] = $this->scenario($category);
            $engineering = app(UpdateDefectAssessment::class)->handle($actor, $assessment, [
                'condition' => DefectAssessmentCondition::New,
                'classification_method' => 'engineering_note',
            ]);
            $this->actingAs($actor)->put(route('defect-assessments.gut.update', $engineering), [
                'condition' => 'new', ...$this->technicalGutPayload($category),
            ])->assertSessionHasErrors('gut');

            $this->satisfyAssessmentPublicationRequirements($engineering);
            $engineering->quantities()->delete();
            app(TenantContext::class)->set($engineering->defect->organization);
            $published = app(CompleteDefectAssessment::class)->handle($actor, $engineering->fresh());

            $this->assertTrue($published->isComplete());
            $this->assertNull($published->quantity_snapshot);
            $this->assertNull($published->gut_score);
            $this->assertNull($published->gut_snapshot);
            $this->assertNull($published->classification_code);
            $this->assertTrue(app(ViewFirstDemoPresenter::class)->reportDefectTechnicalDetails($published)['engineering_note_without_quantity']);
            $map = app(InspectionLocationReportComposer::class)->compose($published->inspection)['sheets'][0]['maps'][0];
            $this->assertTrue($map['engineering_note_without_quantity']);
            $this->assertNull($map['damage_rows'][0]['gut']);
            $this->assertSame('#7C3AED', $map['markers'][0]['style']['fill']);
            $this->assertContains(['code' => 'Nota de Engenharia', 'color' => '#7C3AED'], $map['classification_legend']);

            $worksheet = app(BuildInspectionQuantitativeWorksheet::class)->build($published->inspection);
            $this->assertCount(1, $worksheet['rows']);
            $this->assertNull($worksheet['rows'][0]['cells']['quantity']['value']);
            $this->assertNull($worksheet['rows'][0]['cells']['gut_score']['value']);
            $this->assertNull($worksheet['rows'][0]['cells']['classification']['value']);
            $book = app(ExportInspectionQuantitativeWorksheet::class)->spreadsheet($worksheet);
            $this->assertSame($published->defect->code, $book->getActiveSheet()->getCell('D8')->getValue());
            $this->assertNull($book->getActiveSheet()->getCell('E8')->getValue());
            $book->disconnectWorksheets();

            $summary = app(BuildInspectionClassificationSummary::class)->build($published->inspection);
            $section = collect($summary['categories'])->firstWhere('code', $category->value);
            $this->assertSame(0, collect($section['rows'])->sum('defect_count'));
            $this->assertSame($published->public_id, $summary['special_assessment_rows'][0]['assessment_public_id']);
            InspectionSpecialAssessmentNote::query()->create([
                'organization_id' => $published->organization_id,
                'inspection_id' => $published->inspection_id,
                'defect_assessment_id' => $published->id,
                'service' => 'Avaliar em engenharia',
                'priority' => 'Alta',
                'note' => 'Definir solução',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            app(InspectionClassificationM2CoverageValidator::class)->validate($published->inspection);

            $quantityRowsKey = $category === DefectCategory::Civil ? 'civil_quantity_rows' : 'rec_quantity_rows';
            $this->actingAs($actor)->get(route('inspections.report-preview', $published->inspection))
                ->assertOk()->assertInertia(fn (Assert $page) => $page
                    ->has('content.'.$quantityRowsKey, 0)
                    ->where('content.location_documentation.sheets.0.maps.0.engineering_note_without_quantity', true)
                    ->where('content.findings.0.technical_details.engineering_note_without_quantity', true));
        }
    }

    public function test_engineering_note_with_quantities_still_requires_gut_and_tac_keeps_both_requirements(): void
    {
        foreach ([DefectCategory::Civil, DefectCategory::StructuralRecovery, DefectCategory::AnticorrosiveTreatment] as $category) {
            [$actor, $assessment] = $this->scenario($category);
            $engineering = app(UpdateDefectAssessment::class)->handle($actor, $assessment, [
                'classification_method' => 'engineering_note',
            ]);
            if ($category === DefectCategory::AnticorrosiveTreatment) {
                try {
                    app(CompleteDefectAssessment::class)->handle($actor, $engineering);
                    $this->fail('TAC sem quantitativo deveria ser rejeitado.');
                } catch (ValidationException $exception) {
                    $this->assertArrayHasKey('quantity', $exception->errors());
                }
            }
            $this->satisfyAssessmentPublicationRequirements($engineering);

            try {
                app(CompleteDefectAssessment::class)->handle($actor, $engineering->fresh());
                $this->fail('Publicação com quantitativo e sem GUT deveria ser rejeitada.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('gravity', $exception->errors());
            }
        }
    }

    public function test_unsafe_condition_is_saved_per_assessment_without_changing_publication_requirements(): void
    {
        [$actor, $assessment] = $this->scenario(DefectCategory::Civil);

        $this->assertFalse($assessment->is_unsafe_condition);

        $this->actingAs($actor)
            ->patch(route('defect-assessments.update', $assessment), [
                'condition' => DefectAssessmentCondition::New->value,
                'is_unsafe_condition' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $assessment->refresh();
        $this->assertTrue($assessment->is_unsafe_condition);

        $this->actingAs($actor)
            ->get(route('defect-assessments.show', $assessment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('assessment.is_unsafe_condition', true));

        app(TenantContext::class)->set($assessment->organization);
        $assessment = app(SaveDefectAssessmentGut::class)->handle(
            $actor, $assessment, $this->technicalGutPayload(DefectCategory::Civil),
        );
        $this->satisfyAssessmentPublicationRequirements($assessment);
        $published = app(CompleteDefectAssessment::class)->handle($actor, $assessment);
        $this->assertTrue($published->isComplete());
        $this->assertTrue($published->is_unsafe_condition);

        $this->actingAs($actor)
            ->patch(route('defect-assessments.update', $published), [
                'condition' => DefectAssessmentCondition::New->value,
                'is_unsafe_condition' => false,
            ])
            ->assertForbidden();

        $this->actingAs($actor)
            ->patch(route('defect-assessments.status.update', $published), ['status' => 'draft'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($actor)
            ->patch(route('defect-assessments.update', $published), [
                'condition' => DefectAssessmentCondition::New->value,
                'is_unsafe_condition' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertFalse($published->refresh()->is_unsafe_condition);
    }

    public function test_one_not_applicable_impact_uses_the_other_score_and_preserves_the_choice(): void
    {
        foreach ([DefectCategory::Civil, DefectCategory::StructuralRecovery] as $category) {
            [$actor, $assessment] = $this->scenario($category);
            $definition = NativeDefectCatalog::technicalDefinition($category);

            foreach (['safety_impact', 'asset_impact'] as $applicable) {
                $notApplicable = $applicable === 'safety_impact' ? 'asset_impact' : 'safety_impact';
                $options = $definition[$applicable.'_options'];
                $this->assertSame([1, 2, 3, 4, 5, null], array_column($options, 'score'));
                $this->assertSame([
                    'code' => 'not_applicable', 'label' => 'Não se aplica', 'score' => null, 'color' => null,
                ], $options[5]);

                foreach (array_slice($options, 0, 5) as $option) {
                    $this->actingAs($actor)->put(route('defect-assessments.gut.update', $assessment), [
                        'condition' => 'new', ...$this->technicalGutPayload($category),
                        $applicable.'_code' => $option['code'],
                        $notApplicable.'_code' => 'not_applicable',
                    ])->assertRedirect()->assertSessionHasNoErrors();

                    $assessment->refresh();
                    $gravity = $assessment->gut_snapshot['criteria']['gravity'];
                    $this->assertSame($option['score'], $assessment->gravity);
                    $this->assertSame($option['score'] * $assessment->urgency * $assessment->trend, $assessment->gut_score);
                    $this->assertSame($options[5], $gravity[$notApplicable]);
                    $this->assertSame($option, $gravity[$applicable]);
                    $this->assertSame($applicable === 'safety_impact' ? 'IMP. SEG.' : 'IMP. ATIV.', GutGravityLegend::fromSnapshot($gravity));
                    $this->actingAs($actor)->get(route('defect-assessments.show', $assessment))
                        ->assertOk()->assertInertia(fn (Assert $page) => $page
                            ->where('gut_snapshot.criteria.gravity.'.$notApplicable.'.code', 'not_applicable')
                            ->where('gut_snapshot.criteria.gravity.'.$notApplicable.'.score', null)
                            ->where('gut_snapshot.criteria.gravity.score', $option['score']));
                }
            }
        }
    }

    public function test_invalid_impact_combinations_do_not_change_the_saved_assessment(): void
    {
        foreach ([DefectCategory::Civil, DefectCategory::StructuralRecovery] as $category) {
            [$actor, $assessment] = $this->scenario($category);
            $assessment = app(SaveDefectAssessmentGut::class)->handle($actor, $assessment, $this->technicalGutPayload($category));
            $before = $assessment->getRawOriginal();
            $base = $this->technicalGutPayload($category);

            $this->actingAs($actor)->put(route('defect-assessments.gut.update', $assessment), [
                'condition' => 'new', ...$base,
                'safety_impact_code' => 'not_applicable', 'asset_impact_code' => 'not_applicable',
            ])->assertSessionHasErrors([
                'safety_impact_code' => 'Não se aplica não pode ser selecionado nos dois impactos.',
                'asset_impact_code' => 'Não se aplica não pode ser selecionado nos dois impactos.',
            ]);
            $this->assertSame($before, $assessment->refresh()->getRawOriginal());

            foreach (['safety_impact_code', 'asset_impact_code'] as $field) {
                $otherField = $field === 'safety_impact_code' ? 'asset_impact_code' : 'safety_impact_code';
                foreach (['', null, 'unknown', 'missing'] as $invalid) {
                    $payload = ['condition' => 'new', ...$base, $otherField => 'not_applicable', $field => $invalid];
                    if ($invalid === 'missing') {
                        unset($payload[$field]);
                    }
                    $this->actingAs($actor)->put(route('defect-assessments.gut.update', $assessment), $payload)
                        ->assertSessionHasErrors($field);
                    $this->assertSame($before, $assessment->refresh()->getRawOriginal());
                }
            }
        }
    }

    public function test_reading_a_previous_catalog_snapshot_does_not_upgrade_it(): void
    {
        [$actor, $assessment] = $this->scenario();
        $assessment = app(SaveDefectAssessmentGut::class)->handle($actor, $assessment, $this->technicalGutPayload(DefectCategory::Civil));
        $assessment->update([
            'gut_snapshot' => [...$assessment->gut_snapshot, 'catalog_version' => 4],
            'classification_snapshot' => [...$assessment->classification_snapshot, 'catalog_version' => 4],
        ]);
        $before = $assessment->getRawOriginal();
        $this->actingAs($actor)->get(route('defect-assessments.show', $assessment))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('gut_snapshot.catalog_version', 4)
                ->where('gut_definition.catalog_version', 5));
        $this->assertSame($before, $assessment->refresh()->getRawOriginal());
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
            'report_equipment_name' => 'Equipamento do relatório',
        ]);
        InspectionResponsible::factory()->forInspection($inspection, $actor)->create(['responsibility' => InspectionResponsibility::Preparer]);
        $defect = Defect::factory()->forEquipment($equipment, $inspection)->create(['category' => $category]);
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->draft()->create([
            'condition' => DefectAssessmentCondition::New, 'comment' => 'Registro suficiente para publicação.',
        ]);

        return [$actor, $assessment];
    }

    private function completeOverview(Inspection $inspection): void
    {
        foreach ([1, 2] as $position) {
            $block = InspectionOverviewBlock::factory()->forInspection($inspection, $position)->create();

            foreach ([1, 2] as $slot) {
                InspectionOverviewPhoto::factory()->forBlock($block, $slot)->ready()->create();
            }
        }

        $inspection->update([
            'general_notes' => 'Aspectos gerais do equipamento preenchidos.',
            'general_drawing' => 'D-TESTE',
            'procedure_number' => 'P-TESTE',
        ]);
    }

    /** @return array<string, mixed> */
    private function technicalGutPayload(DefectCategory $category): array
    {
        return match ($category) {
            DefectCategory::Civil => [
                'safety_impact_code' => 'primary_above_2m',
                'asset_impact_code' => 'secondary_without_asset_impact',
                'urgency_context_code' => 'function',
                'urgency_option_code' => 'building_support_column',
                'trend_group_code' => 'cracking',
                'trend_option_code' => 'prestressed_or_structural_mechanism',
            ],
            DefectCategory::StructuralRecovery => [
                'safety_impact_code' => 'secondary_up_to_2m',
                'asset_impact_code' => 'secondary_without_asset_impact',
                'urgency_matrix_code' => 'structural_function',
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
