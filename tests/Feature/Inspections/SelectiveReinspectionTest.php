<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Actions\Classification\SaveDefectAssessmentGut;
use App\Actions\Classification\SaveDefectAssessmentTelClassification;
use App\Actions\Defects\AssessExistingDefect;
use App\Actions\Inspections\UpdatePlannedInspection;
use App\Actions\Photos\StoreAssessmentPhoto;
use App\Enums\DefectAssessmentClassificationMethod;
use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectCategory;
use App\Enums\DefectStatus;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Enums\UserAccountType;
use App\Models\AssessmentPhoto;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionClassificationM2Link;
use App\Models\InspectionOverviewPhoto;
use App\Models\InspectionResponsible;
use App\Models\InspectionSpecialAssessmentNote;
use App\Models\Organization;
use App\Models\SapM2Note;
use App\Models\User;
use App\Services\Defects\DefectAssessmentQuantitySnapshot;
use App\Services\Defects\InspectionAssessmentResolver;
use App\Services\Defects\InspectionDefectScope;
use App\Services\Defects\ReinspectionCoverageValidator;
use App\Services\Defects\ResolvePreviousDefectAssessment;
use App\Services\InspectionLocations\InspectionLocationPhotoNumbering;
use App\Services\InspectionLocations\InspectionLocationReportComposer;
use App\Services\Inspections\InspectionReadModelPresenter;
use App\Services\Reports\BuildInspectionClassificationSummary;
use App\Services\Reports\BuildInspectionQuantitativeWorksheet;
use App\Services\Reports\ExportInspectionQuantitativeWorksheet;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class SelectiveReinspectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_options_select_all_active_defects_and_use_saved_classification_colors(): void
    {
        $c = $this->context();
        Defect::factory()->forEquipment($c['equipment'], $c['previous'])->repaired()->create();
        $canceled = Inspection::factory()->forEquipment($c['equipment'])->create(['status' => InspectionStatus::Canceled]);
        Defect::factory()->forEquipment($c['equipment'], $canceled)->create();

        $this->actingAs($c['planner'])->getJson(route('inspections.reinspection-options', ['equipment_id' => $c['equipment']->id]))
            ->assertOk()->assertJsonCount(2, 'defects')
            ->assertJsonPath('selected_ids', [$c['first']->defect_id, $c['second']->defect_id])
            ->assertJsonPath('defects.0.code', $c['first']->defect->code)
            ->assertJsonPath('defects.0.classification_color', '#CC1122')
            ->assertJsonPath('defects.0.score_label', 'GUT 125')
            ->assertJsonPath('previous_inspection_id', $c['previous']->id);

        $this->actingAs($c['inspector'])->getJson(route('inspections.reinspection-options', ['equipment_id' => $c['equipment']->id]))->assertForbidden();
        $foreign = Equipment::factory()->create();
        $this->actingAs($c['planner'])->getJson(route('inspections.reinspection-options', ['equipment_id' => $foreign->id]))->assertNotFound();
    }

    public function test_options_distinguish_tel_engineering_and_categories_without_gut(): void
    {
        $c = $this->context();
        $c['first']->update(['classification_method' => DefectAssessmentClassificationMethod::EngineeringNote, 'gut_score' => null]);
        $c['second']->defect->update(['category' => DefectCategory::RoofCladding]);
        $c['second']->update(['tel_score' => 12, 'gut_score' => null]);
        $solidary = Defect::factory()->forEquipment($c['equipment'], $c['previous'])->create(['category' => DefectCategory::SolidaryStructures, 'sequence_number' => 3]);
        DefectAssessment::factory()->forDefect($solidary, $c['previous'])->complete()->create();

        $this->actingAs($c['planner'])->getJson(route('inspections.reinspection-options', ['equipment_id' => $c['equipment']->id]))
            ->assertOk()->assertJsonPath('defects.0.score_label', 'Nota de engenharia')
            ->assertJsonPath('defects.1.score_label', 'TEL 12')
            ->assertJsonPath('defects.2.score_label', 'Não se aplica')
            ->assertJsonPath('defects.2.score', null);
    }

    public function test_partial_selection_persists_sources_without_creating_assessments_or_copying_photos(): void
    {
        $c = $this->context();
        $photoCount = AssessmentPhoto::count();
        $inspection = $this->plan($c, [$c['first']->defect_id]);

        $this->assertSame(1, $inspection->reinspection_scope_version);
        $this->assertCount(2, $inspection->defectScopes);
        $this->assertSame(0, $inspection->defectAssessments()->count());
        $this->assertSame($photoCount, AssessmentPhoto::count());
        $this->assertDatabaseHas('inspection_defect_scopes', [
            'inspection_id' => $inspection->id, 'defect_id' => $c['second']->defect_id,
            'source_assessment_id' => $c['second']->id, 'requires_reinspection' => false,
        ]);
        $this->assertSame('2025-01-10', $inspection->defectScopes->firstWhere('defect_id', $c['second']->defect_id)->historical_due_date->toDateString());
        $history = $inspection->statusHistories()->where('reason', 'Escopo de reinspeção atualizado.')->firstOrFail();
        $this->assertSame($c['planner']->id, $history->changed_by);
        $this->assertCount(2, $history->metadata['after']);
    }

    public function test_invalid_selections_are_rejected_and_batch_creation_is_atomic(): void
    {
        $c = $this->context();
        $foreign = Defect::factory()->create();
        $otherEquipment = Equipment::factory()->for($c['organization'])->create();
        $otherDefect = Defect::factory()->forEquipment($otherEquipment)->create();
        $canceled = Inspection::factory()->forEquipment($c['equipment'])->create(['status' => InspectionStatus::Canceled]);
        $unrelated = Defect::factory()->forEquipment($c['equipment'], $canceled)->create();
        $count = Inspection::count();
        foreach ([[], [$foreign->id], [$otherDefect->id], [$unrelated->id], [$c['first']->defect_id, $c['first']->defect_id]] as $selected) {
            $this->actingAs($c['planner'])->post(route('inspections.store'), ['inspections' => [
                $this->record($c, ['reinspection_defect_ids' => $selected]),
            ]])->assertSessionHasErrors('inspections.0.reinspection_defect_ids');
            $this->assertSame($count, Inspection::count());
        }

        $initial = Equipment::factory()->for($c['organization'])->create();
        $this->actingAs($c['planner'])->post(route('inspections.store'), ['inspections' => [
            $this->record($c, ['equipment_id' => $initial->id]),
            $this->record($c, ['reinspection_defect_ids' => []]),
        ]])->assertSessionHasErrors('inspections.1.reinspection_defect_ids');
        $this->assertSame($count, Inspection::count());
        $this->assertDatabaseCount('inspection_defect_scopes', 0);
    }

    public function test_missing_published_history_cannot_be_deselected_and_stale_base_is_rejected(): void
    {
        $c = $this->context();
        $missing = Defect::factory()->forEquipment($c['equipment'], $c['previous'])->create();
        $this->actingAs($c['planner'])->getJson(route('inspections.reinspection-options', ['equipment_id' => $c['equipment']->id]))
            ->assertOk()->assertJsonPath('defects.2.must_reinspect', true);
        $this->actingAs($c['planner'])->post(route('inspections.store'), ['inspections' => [
            $this->record($c, ['reinspection_defect_ids' => [$c['first']->defect_id]]),
        ]])->assertSessionHasErrors('inspections.0.reinspection_defect_ids');
        $this->actingAs($c['planner'])->post(route('inspections.store'), ['inspections' => [
            $this->record($c, ['reinspection_base_id' => null]),
        ]])->assertSessionHasErrors('inspections.0.reinspection_defect_ids');
        $inspection = $this->plan($c, [$missing->id]);
        $this->assertTrue($inspection->defectScopes->firstWhere('defect_id', $missing->id)->requires_reinspection);
    }

    public function test_planner_can_change_scope_before_start_and_omitting_selection_preserves_it(): void
    {
        $c = $this->context();
        $inspection = $this->plan($c, [$c['first']->defect_id]);
        $this->actingAs($c['planner'])->get(route('inspections.edit', $inspection))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('reinspection_options.selected_ids', [$c['first']->defect_id]));
        $this->put(route('inspections.update', $inspection), $this->record($c, ['reinspection_defect_ids' => [$c['second']->defect_id]]))
            ->assertSessionHasNoErrors();
        $this->put(route('inspections.update', $inspection), $this->record($c))->assertSessionHasNoErrors();
        $this->assertSame([$c['second']->defect_id], $inspection->defectScopes()->where('requires_reinspection', true)->pluck('defect_id')->all());

        // A stale model and a stale form must both recheck the locked status.
        Inspection::whereKey($inspection->id)->update(['status' => InspectionStatus::InProgress]);
        $this->put(route('inspections.update', $inspection), $this->record($c, ['reinspection_defect_ids' => [$c['first']->defect_id]]))->assertForbidden();
        app(TenantContext::class)->set($c['organization']);
        try {
            app(UpdatePlannedInspection::class)->handle($inspection, $c['planner'], $this->record($c, ['reinspection_defect_ids' => [$c['first']->defect_id]]));
            $this->fail('Stale action must reject the scope change.');
        } catch (ValidationException) {
            $this->assertSame([$c['second']->defect_id], $inspection->defectScopes()->where('requires_reinspection', true)->pluck('defect_id')->all());
        }
    }

    public function test_changing_equipment_rebuilds_scope_and_initial_and_empty_reinspections_are_allowed(): void
    {
        $c = $this->context();
        $inspection = $this->plan($c, [$c['first']->defect_id]);
        $initial = Equipment::factory()->for($c['organization'])->create();
        $this->actingAs($c['planner'])->put(route('inspections.update', $inspection), $this->record($c, ['equipment_id' => $initial->id]))->assertSessionHasNoErrors();
        $this->assertSame(0, $inspection->defectScopes()->count());
        $this->assertNull($inspection->fresh()->previous_inspection_id);

        $empty = Equipment::factory()->for($c['organization'])->create();
        Inspection::factory()->forEquipment($empty)->create(['status' => InspectionStatus::Released]);
        $this->actingAs($c['planner'])->post(route('inspections.store'), ['inspections' => [
            $this->record($c, ['equipment_id' => $empty->id, 'reinspection_defect_ids' => []]),
        ]])->assertSessionHasNoErrors();
    }

    public function test_unselected_history_is_read_only_and_navigation_stays_in_current_inspection(): void
    {
        $c = $this->context();
        $inspection = $this->plan($c, [$c['first']->defect_id]);
        $inspection->update(['status' => InspectionStatus::InProgress]);
        $historicalUrl = route('inspections.defects.historical', [$inspection, $c['second']->defect]);
        $this->actingAs($c['inspector'])->get($historicalUrl)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('DefectAssessments/Show')->where('assessment.id', $c['second']->id)
            ->where('assessment.comment', 'Comentário histórico 2')
            ->where('historical_source.inspection.number', $c['previous']->number)
            ->where('assessment_navigation.defects_url', route('inspections.defects', $inspection))
            ->where('inspection_navigation.inspection.public_id', $inspection->public_id)
            ->where('capabilities.update_url', null)->where('capabilities.status_url', null)
            ->where('capabilities.photo_upload_url', null)->where('capabilities.gut_url', null)
            ->where('capabilities.quantity_store_url', null)->where('capabilities.location_map_upload_url', null)
            ->where('reinspection_action', null)->where('correction_requests', null)
            ->has('photos', 2));
        $this->get(route('inspections.defects.historical', [$inspection, $c['first']->defect]))->assertNotFound();

        $this->post(route('inspections.defects.assessments.store', [$inspection, $c['second']->defect]), ['condition' => 'reinspected', 'assessment_action' => 'draft'])->assertForbidden();
        foreach (['draft', 'complete'] as $status) {
            $this->patch(route('defect-assessments.status.update', $c['second']), ['status' => $status])->assertForbidden();
        }
        $this->patch(route('defect-assessments.update', $c['second']), ['condition' => 'reinspected', 'comment' => 'Tentativa'])->assertForbidden();
        $this->put(route('defect-assessments.gut.update', $c['second']), [])->assertForbidden();
        $this->post(route('defect-assessments.quantities.store', $c['second']), [])->assertForbidden();
        $this->delete(route('assessment-photos.destroy', $c['second']->photos->first()))->assertForbidden();
        $this->put(route('defect-assessments.location.update', $c['second']), [])->assertForbidden();

        app(TenantContext::class)->set($c['organization']);
        try {
            app(AssessExistingDefect::class)->handle($c['inspector'], $inspection, $c['second']->defect, ['condition' => 'reinspected']);
            $this->fail('Direct action must reject a deselected defect.');
        } catch (ValidationException) {
            $this->assertSame(0, $inspection->defectAssessments()->count());
        }
    }

    public function test_progress_and_coverage_only_require_selected_and_new_defects(): void
    {
        $c = $this->context();
        $inspection = $this->plan($c, [$c['first']->defect_id]);
        $inspection->update(['status' => InspectionStatus::InProgress]);
        $this->assertSame(['completed' => 0, 'total' => 1, 'percentage' => 0], app(InspectionReadModelPresenter::class)->progress($inspection));
        try {
            app(ReinspectionCoverageValidator::class)->validate($inspection);
            $this->fail('Selected defect remains pending.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('1 avaria', $e->getMessage());
        }
        $current = DefectAssessment::factory()->forDefect($c['first']->defect, $inspection)->complete()->create(['condition' => DefectAssessmentCondition::Reinspected]);
        app(ReinspectionCoverageValidator::class)->validate($inspection->fresh());
        $this->assertSame(['completed' => 1, 'total' => 1, 'percentage' => 100], app(InspectionReadModelPresenter::class)->progress($inspection->fresh()));
        $new = Defect::factory()->forEquipment($c['equipment'], $inspection)->create();
        $this->assertSame(['completed' => 1, 'total' => 2, 'percentage' => 50], app(InspectionReadModelPresenter::class)->progress($inspection->fresh()));
        $this->actingAs($c['inspector'])->get(route('inspections.defects', $inspection))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.total', 3)->where('summary.required_total', 2)->where('summary.historical_count', 1)
            ->where('summary.pending', 1)->where('summary.completed', 1)
            ->where('content.items.1.historical_carried_forward', true)->where('content.items.1.assessment_store_url', null));
    }

    public function test_direct_classification_and_photo_actions_cannot_mutate_published_historical_sources(): void
    {
        $c = $this->context();
        $inspection = $this->plan($c, [$c['first']->defect_id]);
        $inspection->update(['status' => InspectionStatus::InProgress]);
        app(TenantContext::class)->set($c['organization']);
        $source = $c['second'];
        $original = $source->getAttributes();
        $photoCount = AssessmentPhoto::count();
        foreach ([
            fn () => app(SaveDefectAssessmentGut::class)->handle($c['inspector'], $source, []),
            fn () => app(SaveDefectAssessmentTelClassification::class)->handle($c['inspector'], $source, []),
            fn () => app(StoreAssessmentPhoto::class)->handle($c['inspector'], $source, UploadedFile::fake()->create('foto.jpg'), []),
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('The historical source must be immutable even for direct action calls.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('assessment', $e->errors());
            }
        }
        $this->assertSame($original, $source->fresh()->getAttributes());
        $this->assertSame($photoCount, AssessmentPhoto::count());
    }

    public function test_reports_include_history_once_preserve_due_dates_and_inherit_m2(): void
    {
        $c = $this->context();
        $oldNote = SapM2Note::query()->create(['organization_id' => $c['organization']->id, 'equipment_id' => $c['equipment']->id, 'sap_number' => '12345678']);
        InspectionClassificationM2Link::query()->create(['organization_id' => $c['organization']->id, 'inspection_id' => $c['previous']->id, 'category' => 'CV', 'classification_code' => 'CV-1', 'sap_m2_note_id' => $oldNote->id]);
        $inspection = $this->plan($c, [$c['first']->defect_id]);
        $inspection->update(['status' => InspectionStatus::InProgress, 'inspected_on' => '2026-10-10']);
        $current = $this->published($c['first']->defect, $inspection, 3);
        $rows = app(BuildInspectionQuantitativeWorksheet::class)->build($inspection)['rows'];
        $this->assertCount(2, $rows);
        $this->assertSame('2027-10-10', $rows[0]['cells']['due_date']['value']);
        $this->assertSame('2025-01-10', $rows[1]['cells']['due_date']['value']);
        $this->assertStringContainsString('Histórico mantido', $rows[1]['cells']['condition']['value']);
        $this->assertSame($c['second']->public_id, $rows[1]['assessment_public_id']);
        $sheet = app(ExportInspectionQuantitativeWorksheet::class)->spreadsheet(app(BuildInspectionQuantitativeWorksheet::class)->build($inspection));
        $this->assertStringContainsString('Histórico mantido', (string) $sheet->getActiveSheet()->getCell('N9')->getValue());
        $sheet->disconnectWorksheets();

        $summary = app(BuildInspectionClassificationSummary::class)->build($inspection);
        $civil = collect($summary['categories'])->firstWhere('code', 'CV');
        $group = collect($civil['rows'])->firstWhere('classification_code', 'CV-1');
        $this->assertSame(2, $group['defect_count']);
        $this->assertSame('10/01/2025', $group['m2_due_date']);
        $this->assertSame('12345678', $group['sap_m2_number']);
        $this->assertSame(1, $inspection->classificationM2Links()->count());
        $maps = app(InspectionLocationReportComposer::class)->compose($inspection);
        $this->assertSame(2, $maps['map_count']);
        $this->assertSame(4, $maps['photo_count']);
        $this->assertCount(4, app(InspectionLocationPhotoNumbering::class)->buildForReport($inspection));
        $this->actingAs($c['inspector'])->get(route('inspections.report-preview', $inspection))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('content.findings', 2)->has('content.photographic_documentation.blocks', 2)
            ->where('content.findings.1.assessment.id', $c['second']->id)
            ->where('content.findings.1.historical_label', fn ($value): bool => str_contains($value, 'Histórico mantido')));
    }

    public function test_m2_from_a_different_classification_is_not_shown_on_the_new_group(): void
    {
        $c = $this->context();
        $oldNote = SapM2Note::query()->create([
            'organization_id' => $c['organization']->id,
            'equipment_id' => $c['equipment']->id,
            'sap_number' => '12345678',
        ]);
        InspectionClassificationM2Link::query()->create([
            'organization_id' => $c['organization']->id,
            'inspection_id' => $c['previous']->id,
            'category' => 'CV',
            'classification_code' => 'CV-1',
            'sap_m2_note_id' => $oldNote->id,
        ]);
        $inspection = $this->plan($c, [$c['first']->defect_id]);
        $current = $this->published($c['first']->defect, $inspection, 3);
        $current->update(['classification_code' => 'CV-2']);

        $civil = collect(app(BuildInspectionClassificationSummary::class)->build($inspection)['categories'])->firstWhere('code', 'CV');

        $this->assertNull(collect($civil['rows'])->firstWhere('classification_code', 'CV-2')['sap_m2_number']);
        $this->assertSame('12345678', collect($civil['rows'])->firstWhere('classification_code', 'CV-1')['sap_m2_number']);
    }

    public function test_consecutive_partial_reinspections_keep_original_sources_and_later_reassessment_uses_them(): void
    {
        $c = $this->context();
        $first = $this->plan($c, [$c['first']->defect_id]);
        $this->published($c['first']->defect, $first, 3);
        $first->update(['status' => InspectionStatus::Released, 'released_at' => '2026-10-12', 'inspected_on' => '2026-10-10']);
        $second = $this->plan($c, [$c['first']->defect_id]);
        $entry = $second->defectScopes->firstWhere('defect_id', $c['second']->defect_id);
        $this->assertSame($c['second']->id, $entry->source_assessment_id);
        $this->assertSame('2025-01-10', $entry->historical_due_date->toDateString());
        $second->update(['status' => InspectionStatus::Canceled]);
        $third = $this->plan($c, [$c['second']->defect_id]);
        $this->assertSame($first->id, $third->previous_inspection_id);
        $third->update(['status' => InspectionStatus::InProgress]);
        app(TenantContext::class)->set($c['organization']);
        $newAssessment = app(AssessExistingDefect::class)->handle($c['inspector'], $third, $c['second']->defect, ['condition' => 'reinspected']);
        $this->assertSame($c['second']->id, $newAssessment->previous_assessment_id);
        $this->assertSame($c['second']->id, app(ResolvePreviousDefectAssessment::class)->handle($c['second']->defect, $third)->id);
        $this->assertNull($newAssessment->location->confirmed_at);

        $c['second']->defect->update(['status' => DefectStatus::Repaired]);
        $this->assertCount(2, app(InspectionDefectScope::class)->handle($first->fresh()));
        $this->assertSame($c['second']->id, app(InspectionAssessmentResolver::class)->assessment($first->fresh(), $c['second']->defect)->id);
    }

    public function test_legacy_inspections_and_creation_without_selection_still_require_every_defect(): void
    {
        $c = $this->context();
        $this->actingAs($c['planner'])->post(route('inspections.store'), ['inspections' => [$this->record($c)]])->assertSessionHasNoErrors();
        $inspection = Inspection::where('status', InspectionStatus::Planned)->firstOrFail();
        $this->assertSame(2, $inspection->defectScopes()->where('requires_reinspection', true)->count());
        $inspection->update(['reinspection_scope_version' => null]);
        $inspection->defectScopes()->delete();
        $this->assertSame(['completed' => 0, 'total' => 2, 'percentage' => 0], app(InspectionReadModelPresenter::class)->progress($inspection->fresh()));
        $this->assertSame(0, app(InspectionAssessmentResolver::class)->query($inspection)->count());
    }

    public function test_partial_reinspection_completes_the_same_workflow_with_m2_still_required(): void
    {
        $c = $this->context();
        $inspection = $this->plan($c, [$c['first']->defect_id]);
        $inspection->update([
            'general_notes' => 'Aspectos gerais preenchidos.',
            'general_drawing' => 'D-TESTE',
            'procedure_number' => 'P-TESTE',
            'report_equipment_name' => 'Equipamento do relatório',
        ]);
        $reviewer = User::factory()->for($c['organization'])->create(['operational_role' => OperationalRole::Reviewer]);
        $releaser = User::factory()->for($c['organization'])->create(['operational_role' => OperationalRole::Releaser]);
        InspectionResponsible::factory()->forInspection($inspection, $reviewer)->create(['responsibility' => InspectionResponsibility::Approver]);
        InspectionResponsible::factory()->forInspection($inspection, $releaser)->create(['responsibility' => InspectionResponsibility::Releaser]);
        foreach ($inspection->overviewBlocks as $block) {
            $block->update(['comment' => 'Vista geral', 'recommendation' => 'Acompanhar']);
            foreach ([1, 2] as $slot) {
                InspectionOverviewPhoto::factory()->forBlock($block, $slot)->ready()->create();
            }
        }
        $this->actingAs($c['inspector'])->post(route('inspections.start', $inspection))->assertSessionHasNoErrors();
        $this->post(route('inspections.submit-for-planning', $inspection))->assertSessionHasErrors('inspection');
        $this->published($c['first']->defect, $inspection->fresh(), 3);
        $this->post(route('inspections.submit-for-planning', $inspection))->assertSessionHasNoErrors();
        $this->assertSame(InspectionStatus::AwaitingM2, $inspection->fresh()->status);
        $this->actingAs($c['planner'])->post(route('inspections.submit-for-review', $inspection))->assertSessionHasErrors('inspection');
        $this->put(route('inspections.classification-m2-links.update', $inspection), ['links' => [[
            'category' => 'CV', 'classification_code' => 'CV-1', 'sap_number' => 'M2-NOVA1',
        ]]])->assertSessionHasNoErrors();
        $this->post(route('inspections.submit-for-review', $inspection))->assertSessionHasNoErrors();
        $this->actingAs($reviewer)->post(route('inspections.start-review', $inspection))->assertSessionHasNoErrors();
        $this->post(route('defect-assessment-correction-requests.store', $c['second']), ['request_message' => 'Não pode corrigir histórico'])->assertForbidden();
        $this->post(route('inspections.defects.assessments.store', [$inspection, $c['second']->defect]), ['condition' => 'reinspected'])->assertForbidden();
        $this->post(route('inspections.approve', $inspection))->assertSessionHasNoErrors();
        $this->actingAs($releaser)->post(route('inspections.release', $inspection))->assertSessionHasNoErrors();
        $this->assertSame(InspectionStatus::Released, $inspection->fresh()->status);
        $this->assertSame(1, $inspection->defectAssessments()->count());
        $this->assertSame('Comentário histórico 2', $c['second']->fresh()->comment);
    }

    public function test_special_treatment_notes_for_historical_assessments_belong_to_the_new_inspection(): void
    {
        $c = $this->context();
        $c['second']->update(['classification_method' => DefectAssessmentClassificationMethod::EngineeringNote, 'classification_code' => null]);
        $oldNote = InspectionSpecialAssessmentNote::query()->create([
            'organization_id' => $c['organization']->id, 'inspection_id' => $c['previous']->id,
            'defect_assessment_id' => $c['second']->id, 'service' => 'Serviço anterior', 'priority' => 'Alta', 'note' => 'Nota anterior',
        ]);
        $inspection = $this->plan($c, [$c['first']->defect_id]);
        $inspection->update(['status' => InspectionStatus::AwaitingM2]);
        $summary = app(BuildInspectionClassificationSummary::class)->build($inspection);
        $this->assertSame('Nota anterior', $summary['special_assessment_rows'][0]['note']);
        $this->actingAs($c['planner'])->put(route('inspections.classification-m2-links.update', $inspection), [
            'links' => [], 'special_rows' => [[
                'assessment_public_id' => $c['second']->public_id, 'service' => 'Novo serviço', 'priority' => 'Alta', 'note' => 'Nota nova',
            ]],
        ])->assertSessionHasNoErrors();
        $this->assertSame('Nota anterior', $oldNote->fresh()->note);
        $this->assertSame('Nota nova', $inspection->specialAssessmentNotes()->firstOrFail()->note);
        $this->assertSame('Comentário histórico 2', $c['second']->fresh()->comment);
    }

    public function test_reassessed_defect_receives_its_previous_special_treatment_as_an_editable_copy(): void
    {
        $c = $this->context();
        $source = InspectionSpecialAssessmentNote::query()->create([
            'organization_id' => $c['organization']->id,
            'inspection_id' => $c['previous']->id,
            'defect_assessment_id' => $c['first']->id,
            'service' => 'Serviço anterior',
            'priority' => 'Alta',
            'note' => 'Nota anterior',
        ]);
        $inspection = $this->plan($c, [$c['first']->defect_id]);
        $inspection->update(['status' => InspectionStatus::InProgress]);
        app(TenantContext::class)->set($c['organization']);

        $assessment = app(AssessExistingDefect::class)->handle(
            $c['inspector'], $inspection, $c['first']->defect, ['condition' => 'reinspected'],
        );
        $copy = $inspection->specialAssessmentNotes()->where('defect_assessment_id', $assessment->id)->firstOrFail();

        $this->assertSame('Serviço anterior', $copy->service);
        $this->assertSame('Alta', $copy->priority);
        $this->assertSame('Nota anterior', $copy->note);
        $copy->update(['note' => 'Nota revisada']);
        $this->assertSame('Nota anterior', $source->fresh()->note);
    }

    public function test_existing_reinspection_does_not_start_inheriting_notes_after_the_change(): void
    {
        $c = $this->context();
        InspectionSpecialAssessmentNote::query()->create([
            'organization_id' => $c['organization']->id,
            'inspection_id' => $c['previous']->id,
            'defect_assessment_id' => $c['first']->id,
            'service' => 'Serviço anterior',
            'priority' => 'Alta',
            'note' => 'Nota anterior',
        ]);
        $legacy = Inspection::factory()->forEquipment($c['equipment'], $c['previous'])->create(['status' => InspectionStatus::InProgress]);
        InspectionResponsible::factory()->forInspection($legacy, $c['inspector'])->create(['responsibility' => InspectionResponsibility::Reviewer]);
        app(TenantContext::class)->set($c['organization']);

        app(AssessExistingDefect::class)->handle($c['inspector'], $legacy, $c['first']->defect, ['condition' => 'reinspected']);

        $this->assertSame(0, $legacy->specialAssessmentNotes()->count());
    }

    private function context(): array
    {
        $organization = Organization::factory()->create();
        $planner = User::factory()->for($organization)->create(['account_type' => UserAccountType::Member, 'operational_role' => OperationalRole::Planner]);
        $inspector = User::factory()->for($organization)->create(['account_type' => UserAccountType::Member, 'operational_role' => OperationalRole::Inspector]);
        $equipment = Equipment::factory()->for($organization)->create();
        $previous = Inspection::factory()->forEquipment($equipment)->create([
            'status' => InspectionStatus::Released, 'released_at' => '2024-01-12', 'inspected_on' => '2024-01-10', 'number' => 'INS-HISTORICA',
        ]);
        $firstDefect = Defect::factory()->forEquipment($equipment, $previous)->create(['sequence_number' => 1]);
        $secondDefect = Defect::factory()->forEquipment($equipment, $previous)->create(['sequence_number' => 2]);
        $first = $this->published($firstDefect, $previous, 1);
        $second = $this->published($secondDefect, $previous, 2);

        return compact('organization', 'planner', 'inspector', 'equipment', 'previous', 'first', 'second');
    }

    private function published(Defect $defect, Inspection $inspection, int $number): DefectAssessment
    {
        $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->complete()->create([
            'comment' => 'Comentário histórico '.$number, 'recommendation' => 'Reparar',
            'location_description' => 'Local histórico', 'gravity' => 5, 'urgency' => 5, 'trend' => 5, 'gut_score' => 125,
            'classification_code' => 'CV-1', 'classification_priority' => 1,
            'classification_snapshot' => ['code' => 'CV-1', 'color' => '#CC1122', 'severity_rank' => 1],
        ]);
        $this->satisfyAssessmentPublicationRequirements($assessment);
        $assessment->update(['quantity_snapshot' => app(DefectAssessmentQuantitySnapshot::class)->build($defect->category, $assessment->quantities()->get())]);

        return $assessment->fresh(['defect', 'photos', 'quantities', 'location']);
    }

    private function record(array $context, array $overrides = []): array
    {
        return array_replace([
            'equipment_id' => $context['equipment']->id, 'inspector_id' => $context['inspector']->id,
            'planned_start_on' => '2026-10-10', 'planned_end_on' => '2026-10-12', 'service_order' => '0000000001',
        ], $overrides);
    }

    private function plan(array $context, array $selected): Inspection
    {
        $this->actingAs($context['planner'])->post(route('inspections.store'), ['inspections' => [
            $this->record($context, ['reinspection_defect_ids' => $selected]),
        ]])->assertSessionHasNoErrors();

        return Inspection::where('equipment_id', $context['equipment']->id)->where('status', InspectionStatus::Planned)->latest('id')->firstOrFail();
    }
}
