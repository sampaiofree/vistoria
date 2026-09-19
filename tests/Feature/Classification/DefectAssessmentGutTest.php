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
use App\Models\InspectionLocationMap;
use App\Models\InspectionLocationMarker;
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
            'condition' => 'new', 'gravity' => '2', 'urgency' => '2', 'trend' => '4',
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
        $this->assertSame(1, $assessment->gut_snapshot['catalog_version']);
        $this->assertSame(1, $assessment->classification_snapshot['catalog_version']);
        $this->assertArrayNotHasKey('classification_id', $assessment->classification_snapshot);
        $this->assertArrayNotHasKey('category_id', $assessment->gut_snapshot);

        $this->actingAs($actor)->get(route('defect-assessments.show', $assessment))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('gut_options.gravity', 5)
                ->where('gut_options.gravity.0.score', 1)
                ->where('classification.code', 'IE-3')
                ->where('classification.color', '#FFFF00')
                ->where('classification.label', 'Média')
                ->where('classification.score_band', '16-35')
                ->missing('assessment.defect_classification_id'));
    }

    public function test_invalid_http_notes_do_not_replace_a_saved_result(): void
    {
        [$actor, $assessment] = $this->scenario();
        $saved = app(SaveDefectAssessmentGut::class)->handle($actor, $assessment, ['gravity' => 5, 'urgency' => 5, 'trend' => 5]);
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
        $saved = app(SaveDefectAssessmentGut::class)->handle($actor, $assessment, ['gravity' => 2, 'urgency' => 2, 'trend' => 4]);
        $snapshot = [...$saved->classification_snapshot, 'name' => 'Classificação histórica', 'color' => '#123456', 'lower_limit' => 12, 'upper_limit' => 18, 'catalog_version' => 0];
        $gut = $saved->gut_snapshot;
        $gut['criteria']['gravity']['color'] = '#654321';
        $saved->update(['status' => 'complete', 'classification_snapshot' => $snapshot, 'gut_snapshot' => $gut]);
        $map = InspectionLocationMap::factory()->forInspection($saved->inspection, DefectCategory::StructuralRecovery)->create();
        InspectionLocationMarker::factory()->forMapAndAssessment($map, $saved)->create();

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
        $this->assertSame(['code' => 'IE-3', 'color' => '#123456'], $reportMap['classification_legend'][2]);
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
        foreach ([1, 5] as $note) {
            [$actor, $assessment] = $this->scenario(DefectCategory::AnticorrosiveTreatment);
            $saved = app(SaveDefectAssessmentGut::class)->handle($actor, $assessment, ['gravity' => $note, 'urgency' => $note, 'trend' => $note]);
            $this->assertSame($note ** 3, $saved->gut_score);
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

    public function test_conditions_without_gut_clear_the_current_result_and_keep_previous_snapshots(): void
    {
        foreach ([DefectAssessmentCondition::Repaired, DefectAssessmentCondition::NotLocated, DefectAssessmentCondition::NotInspected] as $condition) {
            [$actor, $assessment] = $this->scenario();
            $saved = app(SaveDefectAssessmentGut::class)->handle($actor, $assessment, ['gravity' => 5, 'urgency' => 5, 'trend' => 5]);
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
            'condition' => 'new', 'gravity' => 5, 'urgency' => 5, 'trend' => 5,
        ])->assertForbidden();
        $this->assertNull($assessment->refresh()->gut_score);
    }

    public function test_native_categories_do_not_require_maps_to_submit_an_inspection(): void
    {
        foreach (DefectCategory::cases() as $category) {
            [$actor, $assessment] = $this->scenario($category);
            $saved = app(SaveDefectAssessmentGut::class)->handle($actor, $assessment, ['gravity' => 5, 'urgency' => 5, 'trend' => 5]);
            $this->satisfyAssessmentPublicationRequirements($saved);
            app(CompleteDefectAssessment::class)->handle($actor, $saved);
            $submitted = app(SubmitInspectionForReview::class)->handle($assessment->inspection, $actor);
            $this->assertSame(InspectionStatus::AwaitingReview, $submitted->status);
            $this->assertSame(0, $submitted->locationMaps()->count());
        }
    }

    /** @return array{User,DefectAssessment} */
    private function scenario(DefectCategory $category = DefectCategory::Civil): array
    {
        $organization = Organization::factory()->create();
        app(TenantContext::class)->set($organization);
        $actor = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);
        $equipment = Equipment::factory()->for($organization)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create(['status' => InspectionStatus::InProgress]);
        InspectionResponsible::factory()->forInspection($inspection, $actor)->create(['responsibility' => InspectionResponsibility::Preparer]);
        $defect = Defect::factory()->forEquipment($equipment, $inspection)->create(['category' => $category]);
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->draft()->create([
            'condition' => DefectAssessmentCondition::New, 'comment' => 'Registro suficiente para publicação.',
        ]);

        return [$actor, $assessment];
    }
}
