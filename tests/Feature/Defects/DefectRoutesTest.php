<?php

declare(strict_types=1);

namespace Tests\Feature\Defects;

use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectAssessmentStatus;
use App\Enums\DefectCategory;
use App\Enums\DefectRelationType;
use App\Enums\DefectStatus;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Enums\PhotoProcessingStatus;
use App\Enums\UserAccountType;
use App\Jobs\ProcessAssessmentPhoto;
use App\Models\AssessmentPhoto;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Equipment;
use App\Models\Inspection;
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
        $this->assertSame('CV', $defect->category->value);
        $this->assertSame(1, $defect->sequence_number);
        $this->assertCount(1, $defect->draftAssessments);
        $this->assertNull($defect->latestAssessment);

        $draftAssessment = $defect->draftAssessments->firstOrFail();

        $this->assertSame(DefectAssessmentCondition::New->value, $draftAssessment->condition->value);
        $this->assertSame(DefectAssessmentStatus::Draft->value, $draftAssessment->status->value);
        $this->assertNull($draftAssessment->assessed_at);
        $this->assertNull($draftAssessment->defect_snapshot);

        $this->actingAs($admin)
            ->patch(route('defect-assessments.update', $draftAssessment), [
                'condition' => DefectAssessmentCondition::Reclassified->value,
                'status' => DefectAssessmentStatus::Draft->value,
            ])
            ->assertRedirect(route('defect-assessments.show', $draftAssessment))
            ->assertSessionHasNoErrors();
        $this->assertSame(DefectAssessmentCondition::Reclassified, $draftAssessment->refresh()->condition);

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

    public function test_company_admin_can_publish_initial_assessment_after_adding_quantity_and_photos(): void
    {
        [$organization, $admin, $equipment, $inspection] = $this->createInspectionReadyForDefects();

        $defect = $this->createAndPublishDefect($admin, $inspection, [
            'title' => 'Vazamento na carcaça',
            'origin_description' => 'Rachadura visível no corpo do equipamento.',
            'location_description' => 'Lado esquerdo da carcaça.',
            'comment' => 'Identificada durante a inspeção de rotina.',
            'recommendation' => 'Monitorar e preparar reparo no próximo ciclo.',
        ]);

        $this->assertSame($organization->id, $defect->organization_id);
        $this->assertSame($equipment->id, $defect->equipment_id);
        $this->assertSame($inspection->id, $defect->first_inspection_id);
        $this->assertSame('VT009-CV-001', $defect->code);
        $this->assertSame(DefectStatus::Active->value, $defect->status->value);
        $this->assertSame('CV', $defect->category->value);
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
                'operational_role' => OperationalRole::Inspector->value,
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

    public function test_sequence_is_scoped_by_the_selected_native_category(): void
    {
        [$organization, $admin, , $inspection] = $this->createInspectionReadyForDefects();
        $tac = DefectCategory::AnticorrosiveTreatment;

        $this->actingAs($admin)->post(route('inspections.defects.store', $inspection), [
            'title' => 'Avaria civil',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('inspections.defects.store', $inspection), [
            'category' => $tac->value,
            'title' => 'Novo tac',
        ])->assertRedirect();

        $this->post(route('inspections.defects.store', $inspection), [
            'category' => DefectCategory::StructuralRecovery->value,
            'title' => 'Novo rec',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->post(route('inspections.defects.store', $inspection), [
            'category' => $tac->value,
            'title' => 'Segundo tac',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(
            ['VT009-CV-001', 'VT009-REC-001', 'VT009-TAC-001', 'VT009-TAC-002'],
            Defect::query()
                ->where('organization_id', $organization->id)
                ->orderBy('code')
                ->pluck('code')
                ->all(),
        );
    }

    public function test_unknown_and_retired_category_values_are_rejected_without_allocating_numbers(): void
    {
        [, $admin, , $inspection] = $this->createInspectionReadyForDefects();
        foreach (['civil', 'unknown'] as $category) {
            $this->actingAs($admin)->post(route('inspections.defects.store', $inspection), [
                'category' => $category, 'title' => 'Categoria inválida',
            ])->assertSessionHasErrors('category');
        }
        $this->assertDatabaseCount('defects', 0);
        $this->assertDatabaseCount('defect_code_sequences', 0);
    }

    public function test_equipment_cannot_be_edited_after_a_defect_exists(): void
    {
        [$organization, $admin, $equipment, $inspection] = $this->createInspectionReadyForDefects();

        $this->actingAs($admin)->post(route('inspections.defects.store', $inspection), [
            'title' => 'Avaria original',
        ])->assertRedirect();

        $this->actingAs($admin)
            ->put(route('equipments.update', $equipment), [
                'maintenance_item_code' => $equipment->maintenance_item_code,
                'tag' => $equipment->tag,
                'defect_code_prefix' => 'VT010',
                'name' => $equipment->name,
            ])
            ->assertForbidden();
    }

    public function test_defect_creation_is_blocked_without_prefix(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
                'operational_role' => OperationalRole::Inspector->value,
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

        $defect = $this->createAndPublishDefect($admin, $firstInspection, [
            'title' => 'Vazamento na carcaça',
            'origin_description' => 'Rachadura visível no corpo do equipamento.',
            'location_description' => 'Lado esquerdo da carcaça.',
            'comment' => 'Avaliação inicial concluída.',
            'recommendation' => 'Monitorar e preparar reparo no próximo ciclo.',
        ]);
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
            'condition' => DefectAssessmentCondition::Reinspected->value,
            'assessment_action' => 'draft',
        ]);

        $defect->refresh();

        $secondAssessment = DefectAssessment::query()
            ->where('defect_id', $defect->id)
            ->where('inspection_id', $secondInspection->id)
            ->firstOrFail();

        $storeResponse->assertRedirect(route('defect-assessments.show', $secondAssessment));
        $this->assertSame(DefectAssessmentCondition::Reinspected, $secondAssessment->condition);
        $this->assertNull($secondAssessment->location_description);
        $this->assertNull($secondAssessment->comment);
        $this->assertNull($secondAssessment->recommendation);
        $this->assertNull($secondAssessment->gravity);
        $this->assertNull($secondAssessment->urgency);
        $this->assertNull($secondAssessment->trend);
        $this->assertNull($secondAssessment->classification_id);
        $this->assertSame(0, $secondAssessment->quantities()->count());
        $this->assertCount(0, $secondAssessment->photos);

        $this->actingAs($admin)->post(route('inspections.defects.assessments.store', [$secondInspection, $defect]), [
            'condition' => DefectAssessmentCondition::Reinspected->value,
            'assessment_action' => 'draft',
        ])->assertSessionHasErrors('inspection');
        $this->assertSame(2, DefectAssessment::query()->where('defect_id', $defect->id)->count());

        $this->actingAs($admin)
            ->get(route('defect-assessments.show', $secondAssessment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('DefectAssessments/Show')
                ->where('origin_type', 'inherited')
                ->where('assessment.defect.code', $defect->code)
                ->where('previous_assessment_summary.id', $firstAssessment->id)
                ->where('previous_assessment_summary.condition', 'new')
                ->has('assessment_history', 1));

        $this->satisfyAssessmentPublicationRequirements($secondAssessment);
        $this->actingAs($admin)->post(route('defect-assessments.complete', $secondAssessment), [
            'condition' => DefectAssessmentCondition::Treated->value,
            'location_description' => 'Parte inferior',
            'comment' => 'Avaria reparada na reinspeção.',
            'recommendation' => 'Manter monitoramento.',
        ])->assertRedirect(route('defect-assessments.show', $secondAssessment));

        $secondAssessment->refresh();
        $defect->refresh();

        $this->assertSame($firstAssessment->id, $secondAssessment->previous_assessment_id);
        $this->assertSame(DefectAssessmentCondition::Treated->value, $secondAssessment->condition->value);
        $this->assertSame(DefectAssessmentStatus::Complete->value, $secondAssessment->status->value);
        $this->assertSame(DefectStatus::Repaired->value, $defect->status->value);
        $this->assertSame('VT009-CV-001', $defect->code);
        $this->assertDatabaseCount('defects', 1);
        $this->assertDatabaseCount('defect_assessments', 2);

        $this->actingAs($admin)
            ->get(route('inspections.report-preview', $secondInspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.evolution_rows.0.code', 'VT009-CV-001')
                ->where('content.evolution_rows.0.condition', 'treated')
                ->where('content.evolution_rows.0.previous_classification.code', fn ($code): bool => is_string($code) && $code !== '')
                ->where('content.evolution_rows.0.current_classification.code', '—'));

        $this->actingAs($admin)->patch(route('defect-assessments.status.update', $secondAssessment), [
            'status' => DefectAssessmentStatus::Draft->value,
            'condition' => DefectAssessmentCondition::Reinspected->value,
            'location_description' => 'Parte inferior',
            'comment' => 'Reaberto para ajuste.',
            'recommendation' => 'Manter monitoramento.',
            'reason' => null,
            'internal_notes' => 'Voltou para rascunho.',
        ])->assertRedirect(route('defect-assessments.show', $secondAssessment));

        $secondAssessment->refresh();
        $defect->refresh();

        $this->assertSame(DefectAssessmentCondition::Reinspected->value, $secondAssessment->condition->value);
        $this->assertSame(DefectAssessmentStatus::Draft->value, $secondAssessment->status->value);
        $this->assertSame(DefectStatus::Active->value, $defect->status->value);

        $this->actingAs($admin)->patch(route('defect-assessments.status.update', $secondAssessment), [
            'status' => DefectAssessmentStatus::Complete->value,
            'condition' => DefectAssessmentCondition::Treated->value,
            'location_description' => 'Parte inferior',
            'comment' => 'Reparo confirmado.',
            'recommendation' => 'Manter monitoramento.',
            'reason' => null,
            'internal_notes' => 'Concluída após revisão.',
        ])->assertRedirect(route('defect-assessments.show', $secondAssessment));

        $secondAssessment->refresh();
        $defect->refresh();

        $this->assertSame(DefectAssessmentCondition::Treated->value, $secondAssessment->condition->value);
        $this->assertSame(DefectAssessmentStatus::Complete->value, $secondAssessment->status->value);
        $this->assertSame(DefectStatus::Repaired->value, $defect->status->value);
    }

    public function test_assessment_history_skips_canceled_inspections_while_following_the_previous_chain(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin,
        ]);
        $equipment = Equipment::factory()->for($organization)->create();
        $firstInspection = Inspection::factory()->forEquipment($equipment)->create([
            'status' => InspectionStatus::Released,
            'number' => 'INS-001',
        ]);
        $canceledInspection = Inspection::factory()->reinspection($firstInspection)->create([
            'status' => InspectionStatus::Canceled,
            'number' => 'INS-002',
            'canceled_at' => now(),
        ]);
        $currentInspection = Inspection::factory()->reinspection($canceledInspection)->create([
            'status' => InspectionStatus::InProgress,
            'number' => 'INS-003',
        ]);
        $defect = Defect::factory()->forEquipment($equipment, $firstInspection)->create([
            'code' => 'VT009-CV-001',
            'sequence_number' => 1,
        ]);
        $firstAssessment = DefectAssessment::factory()
            ->forDefect($defect, $firstInspection)
            ->complete()
            ->create();
        $canceledAssessment = DefectAssessment::factory()
            ->forDefect($defect, $canceledInspection)
            ->complete()
            ->create([
                'condition' => DefectAssessmentCondition::Reclassified,
                'previous_assessment_id' => $firstAssessment->id,
            ]);
        $canceledOnlyDefect = Defect::factory()->forEquipment($equipment, $canceledInspection)->create([
            'code' => 'VT009-CV-002',
            'sequence_number' => 2,
        ]);
        DefectAssessment::factory()
            ->forDefect($canceledOnlyDefect, $canceledInspection)
            ->complete()
            ->create();
        $currentAssessment = DefectAssessment::factory()
            ->forDefect($defect, $currentInspection)
            ->draft()
            ->create([
                'condition' => DefectAssessmentCondition::Reinspected,
                'previous_assessment_id' => $canceledAssessment->id,
            ]);
        $futureInspection = Inspection::factory()->reinspection($currentInspection)->create([
            'status' => InspectionStatus::Released,
            'number' => 'INS-004',
        ]);
        $futureAssessment = DefectAssessment::factory()
            ->forDefect($defect, $futureInspection)
            ->complete()
            ->create([
                'condition' => DefectAssessmentCondition::Reclassified,
                'previous_assessment_id' => $canceledAssessment->id,
            ]);
        $currentAssessment->update(['previous_assessment_id' => $futureAssessment->id]);

        $this->actingAs($admin)
            ->get(route('defect-assessments.show', $currentAssessment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('assessment_history', 1)
                ->where('assessment_history.0.id', $firstAssessment->id)
                ->where('assessment_history.0.inspection.number', 'INS-001')
                ->where('previous_assessment_summary.id', $firstAssessment->id));

        $this->actingAs($admin)
            ->get(route('inspections.defects', $currentInspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('content.items', 1)
                ->where('content.items.0.code', 'VT009-CV-001')
                ->where('content.items.0.previous_assessment_summary.id', $firstAssessment->id));
    }

    public function test_published_located_assessment_can_return_to_draft_and_be_republished(): void
    {
        [, $admin, , $inspection] = $this->createInspectionReadyForDefects();

        $this->createAndPublishDefect($admin, $inspection, [
            'title' => 'Avaria localizada no mapa',
            'comment' => 'Avaliação publicada originalmente.',
            'recommendation' => 'Monitorar o ponto indicado.',
        ]);

        $assessment = DefectAssessment::query()->with('defect')->firstOrFail();
        $assessment->update([
            'assessed_at' => now()->subDay(),
            'defect_snapshot' => ['legacy' => true],
        ]);
        $previousAssessedAt = $assessment->assessed_at;
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
        ])->assertRedirect(route('defect-assessments.show', $assessment))
            ->assertSessionHasNoErrors();

        $assessment->refresh();
        $this->assertSame(DefectAssessmentStatus::Draft, $assessment->status);
        $this->assertSame('Texto revisado para republicação.', $assessment->comment);

        $this->actingAs($admin)->patch(route('defect-assessments.status.update', $assessment), [
            ...$payload,
            'status' => DefectAssessmentStatus::Complete->value,
            'condition' => DefectAssessmentCondition::Canceled->value,
            'reason' => 'Ponto não localizado durante a revisão.',
        ])->assertRedirect(route('defect-assessments.show', $assessment))
            ->assertSessionHasNoErrors();

        $assessment->refresh();
        $this->assertSame(DefectAssessmentCondition::Canceled, $assessment->condition);
        $this->assertSame(DefectAssessmentStatus::Complete, $assessment->status);
        $this->assertSame('Texto revisado para republicação.', $assessment->comment);
        $this->assertGreaterThan($previousAssessedAt, $assessment->assessed_at);
        $this->assertNull(data_get($assessment->defect_snapshot, 'legacy'));
        $this->assertNull($assessment->gut_snapshot);
        $this->assertNull($assessment->quantity_snapshot);
        $this->assertSame(DefectStatus::Active, $assessment->defect->refresh()->status);
        $this->assertDatabaseHas('defect_assessment_locations', [
            'defect_assessment_id' => $assessment->id,
        ]);

        $this->actingAs($admin)
            ->get(route('defect-assessments.show', $assessment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('capabilities.keep_published', true)
                ->where('capabilities.can_move_to_draft', true)
                ->where('capabilities.location_marker_count', 1)
                ->where('capabilities.update', true));
    }

    public function test_completing_assessment_requires_comment(): void
    {
        [, $admin, , $firstInspection] = $this->createInspectionReadyForDefects();

        $defect = $this->createAndPublishDefect($admin, $firstInspection, [
            'title' => 'Vazamento na carcaça',
            'comment' => 'Avaliação inicial concluída.',
        ]);

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
            'condition' => DefectAssessmentCondition::Reinspected->value,
            'location_description' => 'Parte superior',
            'recommendation' => 'Sem ação imediata.',
            ...$this->gutScores(),
            'assessment_action' => 'complete',
        ])->assertSessionHasErrors('comment');
    }

    public function test_completing_not_located_assessment_requires_reason(): void
    {
        [, $admin, , $firstInspection] = $this->createInspectionReadyForDefects();

        $defect = $this->createAndPublishDefect($admin, $firstInspection, [
            'title' => 'Vazamento na carcaça',
            'comment' => 'Avaliação inicial concluída.',
        ]);

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
            'condition' => DefectAssessmentCondition::Canceled->value,
            'location_description' => 'Sem acesso ao ponto de inspeção.',
            'comment' => 'Comentário presente.',
            'recommendation' => 'Revisar na próxima visita.',
            ...$this->gutScores(),
            'assessment_action' => 'complete',
        ])->assertSessionHasErrors('reason');
    }

    public function test_publishing_an_assessment_that_requires_evidence_requires_a_recommendation(): void
    {
        [, $admin, , $inspection] = $this->createInspectionReadyForDefects();

        $this->actingAs($admin)->post(route('inspections.defects.store', $inspection), [
            'title' => 'Avaria sem recomendação',
            'comment' => 'Registro técnico preenchido.',
            ...$this->gutScores(),
            'assessment_action' => 'complete',
        ])->assertSessionHasErrors('recommendation');

        $this->actingAs($admin)->post(route('inspections.defects.store', $inspection), [
            'title' => 'Avaria em rascunho sem recomendação',
            'assessment_action' => 'draft',
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_canceled_assessment_can_be_published_without_a_recommendation(): void
    {
        [, $admin, , $firstInspection] = $this->createInspectionReadyForDefects();

        $defect = $this->createAndPublishDefect($admin, $firstInspection, [
            'title' => 'Avaria que será cancelada',
            'comment' => 'Avaliação inicial concluída.',
        ]);

        $secondInspection = Inspection::factory()->reinspection($firstInspection)->create([
            'number' => 'INS-2026-000002',
            'status' => InspectionStatus::Planned,
        ]);
        InspectionResponsible::factory()->forInspection($secondInspection, $admin)->create([
            'responsibility' => InspectionResponsibility::Preparer,
            'is_primary' => true,
        ]);
        $this->actingAs($admin)->post(route('inspections.start', $secondInspection))->assertRedirect();

        $this->actingAs($admin)->post(route('inspections.defects.assessments.store', [$secondInspection, $defect]), [
            'condition' => DefectAssessmentCondition::Canceled->value,
            'comment' => 'Avaria cancelada após a revisão.',
            'reason' => 'O registro anterior não se confirmou.',
            'assessment_action' => 'complete',
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_repaired_defect_can_create_a_recurrence_with_a_new_code(): void
    {
        [, $admin, $equipment, $firstInspection] = $this->createInspectionReadyForDefects();

        $this->createAndPublishDefect($admin, $firstInspection, [
            'title' => 'Avaria original',
            'comment' => 'Registrada.',
        ]);

        $source = Defect::query()->firstOrFail();
        $sourceAssessment = $source->latestAssessment;
        $sourceAssessment->update([
            'condition' => DefectAssessmentCondition::Treated,
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

        $defect = $this->createAndPublishDefect($admin, $firstInspection, [
            'title' => 'Avaria a acompanhar',
            'comment' => 'Registrada.',
        ]);
        $originalAssessment = $defect->latestAssessment;
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
            ->assertRedirect(route('inspections.defects', $inspection));

        $this->actingAs($admin)
            ->get(route('inspections.defects', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inspections/Show')
                ->where('active_tab', 'defects')
                ->where('content.items.0.code', $defect->code)
                ->where('content.items.0.origin_type', 'inherited')
                ->where('content.items.0.assessment', null)
                ->where('content.items.0.previous_assessment_summary.condition', 'new')
                ->where('content.items.0.assessment_store_url', fn ($url): bool => is_string($url) && $url !== ''));

        $this->actingAs($admin)
            ->get(route('defect-assessments.show', $originalAssessment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('assessment.public_id', $originalAssessment->public_id)
                ->where('reinspection_action.inspection.number', 'INS-2026-000004')
                ->where('reinspection_action.assessment_url', null)
                ->where('reinspection_action.assessment_store_url', fn ($url): bool => is_string($url) && $url !== ''));

        $this->actingAs($admin)
            ->post(route('inspections.submit-for-review', $inspection))
            ->assertSessionHasErrors('inspection');

        $this->assertSame(InspectionStatus::InProgress, $inspection->refresh()->status);
    }

    public function test_unified_list_hides_current_repairs_by_default_and_excludes_future_defects(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin,
        ]);
        $equipment = Equipment::factory()->for($organization)->create();
        $firstInspection = Inspection::factory()->forEquipment($equipment)->create([
            'status' => InspectionStatus::Released,
            'number' => 'INS-001',
        ]);
        $secondInspection = Inspection::factory()->reinspection($firstInspection)->create([
            'status' => InspectionStatus::Released,
            'number' => 'INS-002',
        ]);
        $thirdInspection = Inspection::factory()->reinspection($secondInspection)->create([
            'status' => InspectionStatus::InProgress,
            'number' => 'INS-003',
        ]);

        $repaired = Defect::factory()->forEquipment($equipment, $firstInspection)->repaired()->create([
            'code' => 'VT009-CV-001',
            'sequence_number' => 1,
        ]);
        $firstAssessment = DefectAssessment::factory()->forDefect($repaired, $firstInspection)->complete()->create();
        DefectAssessment::factory()->forDefect($repaired, $secondInspection)->repaired()->create([
            'previous_assessment_id' => $firstAssessment->id,
        ]);

        $current = Defect::factory()->forEquipment($equipment, $secondInspection)->create([
            'code' => 'VT009-CV-002',
            'sequence_number' => 2,
        ]);
        DefectAssessment::factory()->forDefect($current, $secondInspection)->complete()->create();

        $future = Defect::factory()->forEquipment($equipment, $thirdInspection)->create([
            'code' => 'VT009-CV-003',
            'sequence_number' => 3,
        ]);
        DefectAssessment::factory()->forDefect($future, $thirdInspection)->create();

        $this->actingAs($admin)
            ->get(route('inspections.defects', $secondInspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('content.items', 2)
                ->where('content.items.0.code', 'VT009-CV-001')
                ->where('content.items.0.is_repaired', true)
                ->where('content.items.1.code', 'VT009-CV-002')
                ->where('content.filters.0.key', 'active')
                ->where('content.filters.0.count', 1)
                ->where('content.filters.1.key', 'all')
                ->where('content.filters.1.count', 2)
                ->where('content.filters.3.key', 'treated')
                ->where('content.filters.3.count', 1));
    }

    public function test_reinspection_lists_ten_inherited_defects_with_their_permanent_codes(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin,
        ]);
        $equipment = Equipment::factory()->for($organization)->create();
        $firstInspection = Inspection::factory()->forEquipment($equipment)->create([
            'status' => InspectionStatus::Released,
            'number' => 'INS-001',
        ]);
        $reinspection = Inspection::factory()->reinspection($firstInspection)->create([
            'status' => InspectionStatus::InProgress,
            'number' => 'INS-002',
        ]);
        InspectionResponsible::factory()->forInspection($reinspection, $admin)->create([
            'responsibility' => InspectionResponsibility::Preparer,
            'is_primary' => true,
        ]);

        foreach (range(1, 10) as $sequence) {
            $defect = Defect::factory()->forEquipment($equipment, $firstInspection)->create([
                'code' => sprintf('VT009-CV-%03d', $sequence),
                'sequence_number' => $sequence,
            ]);
            DefectAssessment::factory()->forDefect($defect, $firstInspection)->complete()->create();
        }

        $this->actingAs($admin)
            ->get(route('inspections.defects', $reinspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('content.items', 10)
                ->where('summary.total', 10)
                ->where('summary.pending', 10)
                ->where('content.items.0.code', 'VT009-CV-001')
                ->where('content.items.9.code', 'VT009-CV-010')
                ->where('content.items.0.origin_type', 'inherited')
                ->where('content.items.0.assessment', null)
                ->where('content.items.0.previous_assessment_summary.condition', 'new'));
    }

    public function test_company_admin_can_upload_photo_for_assessment_and_queue_processing(): void
    {
        [, $admin, , $inspection] = $this->createInspectionReadyForDefects();
        Queue::fake();
        Storage::fake('inspection_photos');

        $this->actingAs($admin)->post(route('inspections.defects.store', $inspection), [
            'title' => 'Avaria com evidência',
            'comment' => 'Registro inicial.',
            'assessment_action' => 'draft',
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
            'assessment_action' => 'draft',
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

    public function test_publication_requires_quantity_and_two_ready_photos_before_submission(): void
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
            'assessment_action' => 'draft',
        ])->assertRedirect();
        $assessment = DefectAssessment::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('defect-assessments.complete', $assessment), [
                'condition' => DefectAssessmentCondition::New->value,
                'comment' => 'Registro inicial.',
                ...$this->gutScores(),
            ])
            ->assertSessionHasErrors(['quantity', 'photos', 'location_map', 'location']);

        $this->actingAs($admin)
            ->post(route('defect-assessments.quantities.store', $assessment), [
                'quantity' => [
                    'length' => 1.5,
                    'height' => 1,
                    'width' => 1,
                    'quantity' => 1,
                ],
            ])
            ->assertRedirect();

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
            ->post(route('defect-assessments.complete', $assessment), [
                'condition' => DefectAssessmentCondition::New->value,
                'comment' => 'Registro inicial.',
            ])
            ->assertSessionHasErrors('photos');

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
            ->post(route('defect-assessments.complete', $assessment), [
                'condition' => DefectAssessmentCondition::New->value,
                'comment' => 'Registro inicial.',
            ])
            ->assertSessionHasErrors('photos');
        $this->assertSame(InspectionStatus::InProgress, $inspection->refresh()->status);

        $assessment->photos()->whereNull('optimized_path')->get()->each(function (AssessmentPhoto $photo): void {
            $photo->update([
                'processing_status' => PhotoProcessingStatus::Ready,
                'optimized_path' => $photo->original_path,
                'thumbnail_path' => $photo->original_path,
            ]);
        });
        $this->locateAssessment($assessment);

        $this->actingAs($admin)
            ->post(route('defect-assessments.complete', $assessment), [
                'condition' => DefectAssessmentCondition::New->value,
                'comment' => 'Registro inicial.',
            ])
            ->assertRedirect(route('defect-assessments.show', $assessment))
            ->assertSessionHasNoErrors();

        $quantitySnapshot = $assessment->refresh()->quantity_snapshot;
        $this->assertSame(2, $quantitySnapshot['snapshot_version']);
        $this->assertSame(1, $quantitySnapshot['item_count']);
        $this->assertSame('1.5000000000000000', $quantitySnapshot['total']);
        $this->assertCount(1, $quantitySnapshot['items']);

        $this->actingAs($admin)
            ->post(route('inspections.submit-for-review', $inspection))
            ->assertRedirect();
        $this->assertSame(InspectionStatus::AwaitingReview, $inspection->refresh()->status);
    }

    public function test_condition_without_observation_can_be_published_without_quantity_or_photos(): void
    {
        [, $admin, , $firstInspection] = $this->createInspectionReadyForDefects();
        $defect = $this->createAndPublishDefect($admin, $firstInspection, [
            'title' => 'Avaria sem acesso nesta inspeção',
            'comment' => 'Registro original da avaria.',
        ]);
        $inspection = Inspection::factory()->reinspection($firstInspection)->create([
            'status' => InspectionStatus::InProgress,
            'number' => 'INS-2026-000099',
        ]);
        InspectionResponsible::factory()->forInspection($inspection, $admin)->create([
            'responsibility' => InspectionResponsibility::Preparer,
            'is_primary' => true,
        ]);

        $this->actingAs($admin)->post(route('inspections.defects.assessments.store', [$inspection, $defect]), [
            'condition' => DefectAssessmentCondition::Reinspected->value,
            'assessment_action' => 'draft',
        ])->assertRedirect();

        $assessment = DefectAssessment::query()
            ->where('inspection_id', $inspection->id)
            ->where('defect_id', $defect->id)
            ->firstOrFail();

        $this->actingAs($admin)
            ->post(route('defect-assessments.complete', $assessment), [
                'condition' => DefectAssessmentCondition::CanceledWithoutRepair->value,
                'comment' => 'O ponto não pôde ser inspecionado.',
                'reason' => 'Acesso bloqueado durante a vistoria.',
            ])
            ->assertRedirect(route('defect-assessments.show', $assessment))
            ->assertSessionHasNoErrors();

        $this->assertSame(DefectAssessmentStatus::Complete, $assessment->refresh()->status);
        $this->assertSame(0, $assessment->quantities()->count());
        $this->assertNull($assessment->quantity_snapshot);
        $this->assertNull($assessment->gut_snapshot);
        $this->assertCount(0, $assessment->photos);
        $this->assertSame(DefectStatus::Active, $defect->refresh()->status);

        $this->actingAs($admin)
            ->get(route('inspections.report-preview', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('content.findings.0.condition', 'canceled_sr')
                ->where('content.findings.0.reason', 'Acesso bloqueado durante a vistoria.')
                ->where('content.evolution_rows.0.condition', 'canceled_sr')
                ->where('content.evolution_rows.0.current_classification.code', '—'));
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
                'operational_role' => OperationalRole::Inspector->value,
            ]);
        $equipment = Equipment::factory()
            ->for($organization)
            ->create([
                'defect_code_prefix' => 'VT009',
            ]);
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

    /** @param array<string, mixed> $data */
    private function createAndPublishDefect(User $actor, Inspection $inspection, array $data): Defect
    {
        $this->actingAs($actor)->post(route('inspections.defects.store', $inspection), [
            ...$data,
            'assessment_action' => 'draft',
        ])->assertRedirect();

        $defect = Defect::query()
            ->where('first_inspection_id', $inspection->id)
            ->where('title', $data['title'])
            ->latest('id')
            ->firstOrFail();
        $assessment = $defect->assessments()->where('inspection_id', $inspection->id)->firstOrFail();

        $this->satisfyAssessmentPublicationRequirements($assessment);

        $this->actingAs($actor)->post(route('defect-assessments.complete', $assessment), [
            'condition' => $assessment->condition->value,
            'location_description' => $data['location_description'] ?? null,
            'comment' => $data['comment'] ?? null,
            'recommendation' => $data['recommendation'] ?? null,
            'reason' => $data['reason'] ?? null,
            'internal_notes' => $data['internal_notes'] ?? null,
            ...$this->gutScores(),
        ])->assertRedirect(route('defect-assessments.show', $assessment));

        return $defect->refresh();
    }

    /** @return array<string, mixed> */
    private function gutScores(): array
    {
        return [
            'safety_impact_code' => 'no_accident_risk',
            'asset_impact_code' => 'secondary_without_asset_impact',
            'urgency_option_code' => 'non_structural_masonry_wall',
            'trend_group_code' => 'cracking',
            'trend_manual_description' => 'Condição estável observada em campo.',
            'trend_manual_score' => 1,
        ];
    }
}
