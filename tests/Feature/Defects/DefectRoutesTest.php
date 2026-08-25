<?php

declare(strict_types=1);

namespace Tests\Feature\Defects;

use App\Actions\Classification\ProvisionDefaultDefectTaxonomy;
use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectAssessmentStatus;
use App\Enums\DefectRelationType;
use App\Enums\DefectStatus;
use App\Enums\InspectionLocationMapProcessingStatus;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\PhotoProcessingStatus;
use App\Enums\UserAccountType;
use App\Jobs\ProcessAssessmentPhoto;
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
use App\Services\Defects\DefectStatusSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class DefectRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_photo_role_routes_no_longer_exist(): void
    {
        $this->assertFalse(Route::has('assessment-photos.primary'));
        $this->assertFalse(Route::has('assessment-photos.report-slot'));
    }

    public function test_company_admin_can_create_first_defect_with_draft_assessment_by_default(): void
    {
        [$organization, $admin, $equipment, $inspection] = $this->createInspectionReadyForDefects();

        $response = $this->actingAs($admin)->post(route('inspections.defects.store', $inspection), [
            'title' => 'Vazamento na carcaça',
            'origin_description' => 'Rachadura visível no corpo do equipamento.',
            'location_description' => 'Lado esquerdo da carcaça.',
            'comment' => 'Identificada durante a inspeção de rotina.',
            'recommendation' => 'Monitorar e preparar reparo no próximo ciclo.',
        ]);

        $response->assertRedirect();

        $defect = Defect::query()->firstOrFail();

        $this->assertSame($organization->id, $defect->organization_id);
        $this->assertSame($equipment->id, $defect->equipment_id);
        $this->assertSame($inspection->id, $defect->first_inspection_id);
        $this->assertSame('VT009-CV-001', $defect->code);
        $this->assertSame(DefectStatus::Active->value, $defect->status->value);
        $this->assertSame('civil', $defect->category->value);
        $this->assertSame(1, $defect->sequence_number);
        $this->assertCount(1, $defect->draftAssessments);
        $this->assertNull($defect->latestAssessment);

        $draftAssessment = $defect->draftAssessments->firstOrFail();

        $this->assertSame(DefectAssessmentCondition::New->value, $draftAssessment->condition->value);
        $this->assertSame(DefectAssessmentStatus::Draft->value, $draftAssessment->status->value);
        $this->assertNull($draftAssessment->assessed_at);
        $this->assertNull($draftAssessment->defect_snapshot);

        $this->actingAs($admin)
            ->get(route('defects.show', $defect))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Defects/Show')
                ->where('defect.code', 'VT009-CV-001')
                ->where('defect.current_assessment.status', 'draft')
                ->where('defect.latest_assessment.status', 'draft')
                ->where('defect.latest_complete_assessment', null));
    }

    public function test_company_admin_can_create_first_defect_and_complete_initial_assessment_in_one_step(): void
    {
        [$organization, $admin, $equipment, $inspection] = $this->createInspectionReadyForDefects();

        $this->actingAs($admin)->post(route('inspections.defects.store', $inspection), [
            'title' => 'Vazamento na carcaça',
            'origin_description' => 'Rachadura visível no corpo do equipamento.',
            'location_description' => 'Lado esquerdo da carcaça.',
            'comment' => 'Identificada durante a inspeção de rotina.',
            'recommendation' => 'Monitorar e preparar reparo no próximo ciclo.',
            ...$this->gutScores(),
            'assessment_action' => 'complete',
        ])->assertRedirect();

        $defect = Defect::query()->firstOrFail();

        $this->assertSame($organization->id, $defect->organization_id);
        $this->assertSame($equipment->id, $defect->equipment_id);
        $this->assertSame($inspection->id, $defect->first_inspection_id);
        $this->assertSame('VT009-CV-001', $defect->code);
        $this->assertSame(DefectStatus::Active->value, $defect->status->value);
        $this->assertSame('civil', $defect->category->value);
        $this->assertSame(1, $defect->sequence_number);
        $this->assertNotNull($defect->latestAssessment);
        $this->assertSame(DefectAssessmentCondition::New->value, $defect->latestAssessment->condition->value);
        $this->assertSame(DefectAssessmentStatus::Complete->value, $defect->latestAssessment->status->value);
        $this->assertSame('VT009-CV-001', data_get($defect->latestAssessment->defect_snapshot, 'defect.code'));
        $this->assertSame('Vazamento na carcaça', data_get($defect->latestAssessment->defect_snapshot, 'defect.title'));
        $this->assertCount(0, $defect->draftAssessments);

        $this->actingAs($admin)
            ->get(route('defects.show', $defect))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Defects/Show')
                ->where('defect.code', 'VT009-CV-001')
                ->where('defect.latest_assessment.condition', 'new')
                ->where('defect.latest_assessment.status', 'complete')
                ->where('defect.latest_complete_assessment.status', 'complete')
                ->has('assessments', 1));
    }

    public function test_sequence_increments_per_equipment_and_isolated_by_organization(): void
    {
        [$organization, $admin, $equipment, $inspection] = $this->createInspectionReadyForDefects();

        $this->actingAs($admin)->post(route('inspections.defects.store', $inspection), [
            'title' => 'Primeira avaria',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('inspections.defects.store', $inspection), [
            'title' => 'Segunda avaria',
        ])->assertRedirect();

        $this->assertSame([
            'VT009-CV-001',
            'VT009-CV-002',
        ], Defect::query()
            ->where('organization_id', $organization->id)
            ->orderBy('code')
            ->pluck('code')
            ->all());

        $otherOrganization = Organization::factory()->create();
        $otherAdmin = User::factory()
            ->for($otherOrganization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
            ]);
        $otherEquipment = Equipment::factory()
            ->for($otherOrganization)
            ->create([
                'defect_code_prefix' => 'VT009',
            ]);
        $otherInspection = Inspection::factory()
            ->forEquipment($otherEquipment)
            ->create([
                'number' => 'INS-2026-000777',
                'status' => InspectionStatus::Planned,
            ]);

        InspectionResponsible::factory()
            ->forInspection($otherInspection, $otherAdmin)
            ->create([
                'responsibility' => InspectionResponsibility::Preparer,
                'is_primary' => true,
            ]);

        $this->actingAs($otherAdmin)
            ->post(route('inspections.start', $otherInspection))
            ->assertRedirect();

        $otherInspection->refresh();

        $this->actingAs($otherAdmin)
            ->post(route('inspections.defects.store', $otherInspection), [
                'title' => 'Avaria em outro tenant',
            ])
            ->assertRedirect();

        $this->assertSame(
            ['VT009-CV-001'],
            Defect::query()
                ->where('organization_id', $otherOrganization->id)
                ->orderBy('code')
                ->pluck('code')
                ->all(),
        );
    }

    public function test_sequence_is_scoped_by_the_selected_taxonomy_category(): void
    {
        [$organization, $admin, , $inspection] = $this->createInspectionReadyForDefects();
        $tac = DefectCategory::query()
            ->where('organization_id', $organization->id)
            ->where('code', 'TAC')
            ->firstOrFail();

        $this->actingAs($admin)->post(route('inspections.defects.store', $inspection), [
            'title' => 'Avaria civil',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('inspections.defects.store', $inspection), [
            'defect_category_id' => $tac->id,
            'title' => 'Novo tac',
        ])->assertRedirect();

        $this->assertSame(
            ['VT009-CV-001', 'VT009-TAC-001'],
            Defect::query()
                ->where('organization_id', $organization->id)
                ->orderBy('code')
                ->pluck('code')
                ->all(),
        );
    }

    public function test_equipment_prefix_cannot_change_after_a_defect_exists(): void
    {
        [$organization, $admin, $equipment, $inspection] = $this->createInspectionReadyForDefects();

        $this->actingAs($admin)->post(route('inspections.defects.store', $inspection), [
            'title' => 'Avaria original',
        ])->assertRedirect();

        $this->actingAs($admin)
            ->put(route('equipments.update', $equipment), [
                'client_id' => $equipment->client_id,
                'client_unit_id' => $equipment->client_unit_id,
                'area_id' => $equipment->area_id,
                'subarea_id' => $equipment->subarea_id,
                'tag' => $equipment->tag,
                'defect_code_prefix' => 'VT010',
                'name' => $equipment->name,
                'description' => $equipment->description,
                'manufacturer' => $equipment->manufacturer,
                'model' => $equipment->model,
                'serial_number' => $equipment->serial_number,
                'asset_code' => $equipment->asset_code,
                'abc_code' => $equipment->abc_code,
                'installation_location' => $equipment->installation_location,
                'commissioned_at' => $equipment->commissioned_at?->toDateString(),
                'notes' => $equipment->notes,
            ])
            ->assertSessionHasErrors('defect_code_prefix');
    }

    public function test_defect_creation_is_blocked_without_prefix(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
            ]);
        $equipment = Equipment::factory()
            ->for($organization)
            ->create([
                'defect_code_prefix' => null,
            ]);
        $inspection = Inspection::factory()
            ->forEquipment($equipment)
            ->create([
                'number' => 'INS-2026-000222',
                'status' => InspectionStatus::Planned,
            ]);

        InspectionResponsible::factory()
            ->forInspection($inspection, $admin)
            ->create([
                'responsibility' => InspectionResponsibility::Preparer,
                'is_primary' => true,
            ]);

        $this->actingAs($admin)
            ->post(route('inspections.start', $inspection))
            ->assertRedirect();

        $inspection->refresh();

        $this->actingAs($admin)
            ->post(route('inspections.defects.store', $inspection), [
                'title' => 'Defeito bloqueado',
            ])
            ->assertSessionHasErrors('defect_code_prefix');
    }

    public function test_company_admin_can_assess_existing_defect_reopen_draft_and_recomplete_it(): void
    {
        [, $admin, , $firstInspection] = $this->createInspectionReadyForDefects();

        $this->actingAs($admin)->post(route('inspections.defects.store', $firstInspection), [
            'title' => 'Vazamento na carcaça',
            'origin_description' => 'Rachadura visível no corpo do equipamento.',
            'location_description' => 'Lado esquerdo da carcaça.',
            'comment' => 'Avaliação inicial concluída.',
            'recommendation' => 'Monitorar e preparar reparo no próximo ciclo.',
            ...$this->gutScores(),
            'assessment_action' => 'complete',
        ])->assertRedirect();

        $defect = Defect::query()->firstOrFail();
        $firstAssessment = $defect->latestAssessment;

        $secondInspection = Inspection::factory()
            ->reinspection($firstInspection)
            ->create([
                'number' => 'INS-2026-000002',
                'status' => InspectionStatus::Planned,
            ]);

        InspectionResponsible::factory()
            ->forInspection($secondInspection, $admin)
            ->create([
                'responsibility' => InspectionResponsibility::Preparer,
                'is_primary' => true,
            ]);

        $this->actingAs($admin)
            ->post(route('inspections.start', $secondInspection))
            ->assertRedirect();

        $secondInspection->refresh();

        $storeResponse = $this->actingAs($admin)->post(route('inspections.defects.assessments.store', [$secondInspection, $defect]), [
            'condition' => DefectAssessmentCondition::Repaired->value,
            'location_description' => 'Parte inferior',
            'comment' => 'Avaria reparada na reinspeção.',
            'recommendation' => 'Manter monitoramento.',
            ...$this->gutScores(),
            'assessment_action' => 'complete',
        ]);

        $defect->refresh();

        $secondAssessment = DefectAssessment::query()
            ->where('defect_id', $defect->id)
            ->where('inspection_id', $secondInspection->id)
            ->firstOrFail();

        $storeResponse->assertRedirect(route('defect-assessments.show', $secondAssessment));

        $this->assertSame($firstAssessment->id, $secondAssessment->previous_assessment_id);
        $this->assertSame(DefectAssessmentCondition::Repaired->value, $secondAssessment->condition->value);
        $this->assertSame(DefectAssessmentStatus::Complete->value, $secondAssessment->status->value);
        $this->assertSame(DefectStatus::Repaired->value, $defect->status->value);

        $this->actingAs($admin)->patch(route('defect-assessments.status.update', $secondAssessment), [
            'status' => DefectAssessmentStatus::Draft->value,
            'condition' => DefectAssessmentCondition::Unchanged->value,
            'location_description' => 'Parte inferior',
            'comment' => 'Reaberto para ajuste.',
            'recommendation' => 'Manter monitoramento.',
            'reason' => null,
            'internal_notes' => 'Voltou para rascunho.',
        ])->assertRedirect(route('defect-assessments.show', $secondAssessment));

        $secondAssessment->refresh();
        $defect->refresh();

        $this->assertSame(DefectAssessmentCondition::Unchanged->value, $secondAssessment->condition->value);
        $this->assertSame(DefectAssessmentStatus::Draft->value, $secondAssessment->status->value);
        $this->assertSame(DefectStatus::Active->value, $defect->status->value);

        $this->actingAs($admin)->patch(route('defect-assessments.status.update', $secondAssessment), [
            'status' => DefectAssessmentStatus::Complete->value,
            'condition' => DefectAssessmentCondition::Repaired->value,
            'location_description' => 'Parte inferior',
            'comment' => 'Reparo confirmado.',
            'recommendation' => 'Manter monitoramento.',
            'reason' => null,
            'internal_notes' => 'Concluída após revisão.',
        ])->assertRedirect(route('defect-assessments.show', $secondAssessment));

        $secondAssessment->refresh();
        $defect->refresh();

        $this->assertSame(DefectAssessmentCondition::Repaired->value, $secondAssessment->condition->value);
        $this->assertSame(DefectAssessmentStatus::Complete->value, $secondAssessment->status->value);
        $this->assertSame(DefectStatus::Repaired->value, $defect->status->value);
    }

    public function test_published_assessment_with_map_markers_cannot_return_to_draft_and_can_be_republished(): void
    {
        [, $admin, , $inspection] = $this->createInspectionReadyForDefects();

        $this->actingAs($admin)->post(route('inspections.defects.store', $inspection), [
            'title' => 'Avaria localizada no mapa',
            'comment' => 'Avaliação publicada originalmente.',
            'recommendation' => 'Monitorar o ponto indicado.',
            ...$this->gutScores(),
            'assessment_action' => 'complete',
        ])->assertRedirect();

        $assessment = DefectAssessment::query()->with('defect.categoryDefinition')->firstOrFail();
        $assessment->update([
            'assessed_at' => now()->subDay(),
            'defect_snapshot' => ['legacy' => true],
        ]);
        $previousAssessedAt = $assessment->assessed_at;
        $map = InspectionLocationMap::factory()
            ->forInspection($inspection, $assessment->defect->categoryDefinition)
            ->create(['processing_status' => InspectionLocationMapProcessingStatus::Ready]);
        $marker = InspectionLocationMarker::factory()->forMapAndAssessment($map, $assessment)->create();
        $payload = [
            'condition' => DefectAssessmentCondition::New->value,
            'location_description' => 'Face inferior do equipamento.',
            'comment' => 'Texto revisado para republicação.',
            'recommendation' => 'Manter acompanhamento periódico.',
            'reason' => null,
            'internal_notes' => null,
        ];

        $this->actingAs($admin)->patch(route('defect-assessments.status.update', $assessment), [
            ...$payload,
            'status' => DefectAssessmentStatus::Draft->value,
        ])->assertSessionHasErrors('status');

        $assessment->refresh();
        $this->assertSame(DefectAssessmentStatus::Complete, $assessment->status);
        $this->assertSame('Avaliação publicada originalmente.', $assessment->comment);

        $this->actingAs($admin)->patch(route('defect-assessments.status.update', $assessment), [
            ...$payload,
            'status' => DefectAssessmentStatus::Complete->value,
            'condition' => DefectAssessmentCondition::NotLocated->value,
            'reason' => 'Ponto não localizado durante a revisão.',
        ])->assertSessionHasErrors('condition');

        $assessment->refresh();
        $this->assertSame(DefectAssessmentCondition::New, $assessment->condition);
        $this->assertSame(DefectAssessmentStatus::Complete, $assessment->status);

        $this->actingAs($admin)->patch(route('defect-assessments.status.update', $assessment), [
            ...$payload,
            'status' => DefectAssessmentStatus::Complete->value,
        ])->assertRedirect(route('defect-assessments.show', $assessment))
            ->assertSessionHasNoErrors();

        $assessment->refresh();
        $this->assertSame(DefectAssessmentStatus::Complete, $assessment->status);
        $this->assertSame('Texto revisado para republicação.', $assessment->comment);
        $this->assertGreaterThan($previousAssessedAt, $assessment->assessed_at);
        $this->assertNull(data_get($assessment->defect_snapshot, 'legacy'));
        $this->assertDatabaseHas('inspection_location_markers', [
            'id' => $marker->id,
            'deleted_at' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('defect-assessments.show', $assessment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('capabilities.keep_published', true)
                ->where('capabilities.can_move_to_draft', false)
                ->where('capabilities.location_marker_count', 1)
                ->where('capabilities.update', true));
    }

    public function test_completing_assessment_requires_comment(): void
    {
        [, $admin, , $firstInspection] = $this->createInspectionReadyForDefects();

        $this->actingAs($admin)->post(route('inspections.defects.store', $firstInspection), [
            'title' => 'Vazamento na carcaça',
            'comment' => 'Avaliação inicial concluída.',
            ...$this->gutScores(),
            'assessment_action' => 'complete',
        ])->assertRedirect();

        $defect = Defect::query()->firstOrFail();

        $secondInspection = Inspection::factory()
            ->reinspection($firstInspection)
            ->create([
                'number' => 'INS-2026-000002',
                'status' => InspectionStatus::Planned,
            ]);

        InspectionResponsible::factory()
            ->forInspection($secondInspection, $admin)
            ->create([
                'responsibility' => InspectionResponsibility::Preparer,
                'is_primary' => true,
            ]);

        $this->actingAs($admin)
            ->post(route('inspections.start', $secondInspection))
            ->assertRedirect();

        $secondInspection->refresh();

        $this->actingAs($admin)->post(route('inspections.defects.assessments.store', [$secondInspection, $defect]), [
            'condition' => DefectAssessmentCondition::Unchanged->value,
            'location_description' => 'Parte superior',
            'recommendation' => 'Sem ação imediata.',
            ...$this->gutScores(),
            'assessment_action' => 'complete',
        ])->assertSessionHasErrors('comment');
    }

    public function test_completing_not_located_assessment_requires_reason(): void
    {
        [, $admin, , $firstInspection] = $this->createInspectionReadyForDefects();

        $this->actingAs($admin)->post(route('inspections.defects.store', $firstInspection), [
            'title' => 'Vazamento na carcaça',
            'comment' => 'Avaliação inicial concluída.',
            ...$this->gutScores(),
            'assessment_action' => 'complete',
        ])->assertRedirect();

        $defect = Defect::query()->firstOrFail();

        $secondInspection = Inspection::factory()
            ->reinspection($firstInspection)
            ->create([
                'number' => 'INS-2026-000002',
                'status' => InspectionStatus::Planned,
            ]);

        InspectionResponsible::factory()
            ->forInspection($secondInspection, $admin)
            ->create([
                'responsibility' => InspectionResponsibility::Preparer,
                'is_primary' => true,
            ]);

        $this->actingAs($admin)
            ->post(route('inspections.start', $secondInspection))
            ->assertRedirect();

        $secondInspection->refresh();

        $this->actingAs($admin)->post(route('inspections.defects.assessments.store', [$secondInspection, $defect]), [
            'condition' => DefectAssessmentCondition::NotLocated->value,
            'location_description' => 'Sem acesso ao ponto de inspeção.',
            'comment' => 'Comentário presente.',
            'recommendation' => 'Revisar na próxima visita.',
            ...$this->gutScores(),
            'assessment_action' => 'complete',
        ])->assertSessionHasErrors('reason');
    }

    public function test_repaired_defect_can_create_a_recurrence_with_a_new_code(): void
    {
        [, $admin, $equipment, $firstInspection] = $this->createInspectionReadyForDefects();

        $this->actingAs($admin)->post(route('inspections.defects.store', $firstInspection), [
            'title' => 'Avaria original',
            'comment' => 'Registrada.',
            ...$this->gutScores(),
            'assessment_action' => 'complete',
        ])->assertRedirect();

        $source = Defect::query()->firstOrFail();
        $sourceAssessment = $source->latestAssessment;
        $sourceAssessment->update([
            'condition' => DefectAssessmentCondition::Repaired,
            'status' => DefectAssessmentStatus::Complete,
            'assessed_at' => now(),
        ]);
        app(DefectStatusSynchronizer::class)->handle($source, $admin);
        $source->refresh();

        $inspection = Inspection::factory()->reinspection($firstInspection)->create([
            'status' => InspectionStatus::InProgress,
            'number' => 'INS-2026-000003',
        ]);
        InspectionResponsible::factory()->forInspection($inspection, $admin)->create([
            'responsibility' => InspectionResponsibility::Preparer,
            'is_primary' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('inspections.defects.related.store', [$inspection, $source]), [
            'relation_type' => DefectRelationType::Recurrence->value,
            'title' => 'Nova ocorrência no mesmo ponto',
            'location_description' => 'Mesmo ponto da ocorrência anterior.',
        ]);

        $response->assertRedirect();
        $target = Defect::query()->where('id', '!=', $source->getKey())->firstOrFail();

        $this->assertSame('VT009-CV-002', $target->code);
        $this->assertDatabaseHas('defect_relations', [
            'source_defect_id' => $source->id,
            'target_defect_id' => $target->id,
            'relation_type' => DefectRelationType::Recurrence->value,
        ]);
    }

    public function test_reinspection_checklist_blocks_submission_until_active_defects_are_assessed(): void
    {
        [, $admin, , $firstInspection] = $this->createInspectionReadyForDefects();

        $this->actingAs($admin)->post(route('inspections.defects.store', $firstInspection), [
            'title' => 'Avaria a acompanhar',
            'comment' => 'Registrada.',
            ...$this->gutScores(),
            'assessment_action' => 'complete',
        ])->assertRedirect();

        $defect = Defect::query()->firstOrFail();
        $inspection = Inspection::factory()->reinspection($firstInspection)->create([
            'status' => InspectionStatus::InProgress,
            'number' => 'INS-2026-000004',
        ]);
        InspectionResponsible::factory()->forInspection($inspection, $admin)->create([
            'responsibility' => InspectionResponsibility::Preparer,
            'is_primary' => true,
        ]);
        InspectionResponsible::factory()->forInspection($inspection)->create([
            'responsibility' => InspectionResponsibility::Reviewer,
        ]);

        $this->actingAs($admin)
            ->get(route('inspections.reinspection-checklist', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inspections/ReinspectionChecklist')
                ->where('checklist.total', 1)
                ->where('checklist.pending', 1)
                ->where('checklist.items.0.defect_code', $defect->code));

        $this->actingAs($admin)
            ->post(route('inspections.submit-for-review', $inspection))
            ->assertSessionHasErrors('inspection');

        $this->assertSame(InspectionStatus::InProgress, $inspection->refresh()->status);
    }

    public function test_company_admin_can_upload_photo_for_assessment_and_queue_processing(): void
    {
        [, $admin, , $inspection] = $this->createInspectionReadyForDefects();
        Queue::fake();
        Storage::fake('inspection_photos');

        $this->actingAs($admin)->post(route('inspections.defects.store', $inspection), [
            'title' => 'Avaria com evidência',
            'comment' => 'Registro inicial.',
            ...$this->gutScores(),
            'assessment_action' => 'complete',
        ])->assertRedirect();

        $assessment = DefectAssessment::query()->firstOrFail();
        $response = $this->actingAs($admin)->post(route('defect-assessments.photos.store', $assessment), [
            'file' => UploadedFile::fake()->image('fissura.jpg', 1200, 800)->size(3000),
            'captured_at' => '2026-08-24T14:30:00-03:00',
        ]);

        $response->assertRedirect();
        $photo = $assessment->photos()->firstOrFail();

        $this->assertSame($assessment->organization_id, $photo->organization_id);
        $this->assertSame('pending', $photo->processing_status->value);
        $this->assertSame('detail', $photo->photo_type->value);
        $this->assertNull($photo->caption);
        $this->assertSame(strtotime('2026-08-24T14:30:00-03:00'), $photo->captured_at?->getTimestamp());
        $this->assertGreaterThan(2 * 1024 * 1024, $photo->original_size);
        $this->assertNotNull($photo->original_path);
        Storage::disk('inspection_photos')->assertExists($photo->original_path);
        Queue::assertPushed(ProcessAssessmentPhoto::class, fn (ProcessAssessmentPhoto $job): bool => $job->photoId === $photo->id
            && $job->queue === 'images');
    }

    public function test_user_can_reorder_and_remove_assessment_photos(): void
    {
        [, $admin, , $inspection] = $this->createInspectionReadyForDefects();
        Queue::fake();
        Storage::fake('inspection_photos');

        $this->actingAs($admin)->post(route('inspections.defects.store', $inspection), [
            'title' => 'Avaria fotografada',
            'comment' => 'Registro inicial.',
            ...$this->gutScores(),
            'assessment_action' => 'complete',
        ])->assertRedirect();
        $assessment = DefectAssessment::query()->firstOrFail();

        foreach (['primeira.jpg', 'segunda.jpg'] as $name) {
            $this->actingAs($admin)->post(route('defect-assessments.photos.store', $assessment), [
                'file' => UploadedFile::fake()->image($name, 800, 600),
            ])->assertRedirect();
        }

        $photos = $assessment->photos()->get();
        $first = $photos->get(0);
        $second = $photos->get(1);
        $this->assertSame(1, $first->position);
        $this->assertSame(2, $second->position);
        $second->update(['processing_status' => PhotoProcessingStatus::Ready, 'optimized_path' => $second->original_path, 'thumbnail_path' => $second->original_path]);

        $this->actingAs($admin)
            ->patch(route('defect-assessments.photos.reorder', $assessment), ['photo_ids' => [$second->public_id, $first->public_id]])
            ->assertRedirect();
        $this->assertSame(1, $second->refresh()->position);
        $this->assertSame(2, $first->refresh()->position);

        $failed = $first->refresh();
        $failed->update(['processing_status' => PhotoProcessingStatus::Failed]);

        $this->actingAs($admin)->delete(route('assessment-photos.destroy', $failed))->assertRedirect();
        $this->assertSoftDeleted('assessment_photos', ['id' => $failed->id]);
    }

    public function test_submission_is_blocked_until_required_assessment_photos_are_ready(): void
    {
        [, $admin, , $inspection] = $this->createInspectionReadyForDefects();
        $reviewer = User::factory()->for($admin->organization)->create();
        InspectionResponsible::factory()->forInspection($inspection, $reviewer)->create([
            'responsibility' => InspectionResponsibility::Reviewer,
            'is_primary' => true,
        ]);

        $this->actingAs($admin)->post(route('inspections.defects.store', $inspection), [
            'title' => 'Avaria que exige evidência',
            'comment' => 'Registro inicial.',
            ...$this->gutScores(),
            'assessment_action' => 'complete',
        ])->assertRedirect();
        $assessment = DefectAssessment::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('inspections.submit-for-review', $inspection))
            ->assertSessionHasErrors('inspection');
        $this->assertSame(InspectionStatus::InProgress, $inspection->refresh()->status);

        Queue::fake();
        Storage::fake('inspection_photos');
        $this->actingAs($admin)->post(route('defect-assessments.photos.store', $assessment), [
            'file' => UploadedFile::fake()->image('evidencia-1.jpg', 800, 600),
        ])->assertRedirect();
        $firstPhoto = $assessment->photos()->firstOrFail();
        $firstPhoto->update([
            'processing_status' => PhotoProcessingStatus::Ready,
            'optimized_path' => $firstPhoto->original_path,
            'thumbnail_path' => $firstPhoto->original_path,
        ]);

        $this->actingAs($admin)
            ->post(route('inspections.submit-for-review', $inspection))
            ->assertSessionHasErrors('inspection');

        $this->actingAs($admin)->post(route('defect-assessments.photos.store', $assessment), [
            'file' => UploadedFile::fake()->image('evidencia-2.jpg', 800, 600),
        ])->assertRedirect();
        $assessment->photos()->get()->each(function (AssessmentPhoto $photo): void {
            $photo->update([
                'processing_status' => PhotoProcessingStatus::Ready,
                'optimized_path' => $photo->original_path,
                'thumbnail_path' => $photo->original_path,
            ]);
        });

        $this->actingAs($admin)->post(route('defect-assessments.photos.store', $assessment), [
            'file' => UploadedFile::fake()->image('evidencia-extra.jpg', 800, 600),
        ])->assertRedirect();

        $this->actingAs($admin)
            ->post(route('inspections.submit-for-review', $inspection))
            ->assertSessionHasErrors('inspection');
        $this->assertSame(InspectionStatus::InProgress, $inspection->refresh()->status);

        $assessment->photos()->whereNull('optimized_path')->get()->each(function (AssessmentPhoto $photo): void {
            $photo->update([
                'processing_status' => PhotoProcessingStatus::Ready,
                'optimized_path' => $photo->original_path,
                'thumbnail_path' => $photo->original_path,
            ]);
        });

        $this->actingAs($admin)
            ->post(route('inspections.submit-for-review', $inspection))
            ->assertRedirect();
        $this->assertSame(InspectionStatus::AwaitingReview, $inspection->refresh()->status);
    }

    /**
     * @return array{0:Organization,1:User,2:Equipment,3:Inspection}
     */
    private function createInspectionReadyForDefects(): array
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
            ]);
        $equipment = Equipment::factory()
            ->for($organization)
            ->create([
                'defect_code_prefix' => 'VT009',
            ]);
        $category = app(ProvisionDefaultDefectTaxonomy::class)->handle($organization->id);

        $category->classifications()->orderBy('position')->get()->each(
            fn ($classification, int $index) => $classification->update([
                'lower_limit' => $index + 1,
                'upper_limit' => $index + 1,
            ]),
        );
        $inspection = Inspection::factory()
            ->forEquipment($equipment)
            ->create([
                'number' => 'INS-2026-000001',
                'status' => InspectionStatus::Planned,
            ]);

        InspectionResponsible::factory()
            ->forInspection($inspection, $admin)
            ->create([
                'responsibility' => InspectionResponsibility::Preparer,
                'is_primary' => true,
            ]);

        $this->actingAs($admin)
            ->post(route('inspections.start', $inspection))
            ->assertRedirect();

        $inspection->refresh();

        return [$organization, $admin, $equipment, $inspection];
    }

    /** @return array{gravity:int,urgency:int,trend:int} */
    private function gutScores(): array
    {
        return ['gravity' => 1, 'urgency' => 1, 'trend' => 1];
    }
}
