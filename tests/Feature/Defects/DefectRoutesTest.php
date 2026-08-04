<?php

declare(strict_types=1);

namespace Tests\Feature\Defects;

use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectAssessmentStatus;
use App\Enums\DefectStatus;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\UserAccountType;
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

final class DefectRoutesTest extends TestCase
{
    use RefreshDatabase;

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
                'responsibility' => InspectionResponsibility::Inspector,
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
                'responsibility' => InspectionResponsibility::Inspector,
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
                'responsibility' => InspectionResponsibility::Inspector,
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

        $this->actingAs($admin)->patch(route('defect-assessments.update', $secondAssessment), [
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

        $this->actingAs($admin)->post(route('defect-assessments.complete', $secondAssessment), [
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

    public function test_completing_assessment_requires_comment(): void
    {
        [, $admin, , $firstInspection] = $this->createInspectionReadyForDefects();

        $this->actingAs($admin)->post(route('inspections.defects.store', $firstInspection), [
            'title' => 'Vazamento na carcaça',
            'comment' => 'Avaliação inicial concluída.',
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
                'responsibility' => InspectionResponsibility::Inspector,
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
            'assessment_action' => 'complete',
        ])->assertSessionHasErrors('comment');
    }

    public function test_completing_not_located_assessment_requires_reason(): void
    {
        [, $admin, , $firstInspection] = $this->createInspectionReadyForDefects();

        $this->actingAs($admin)->post(route('inspections.defects.store', $firstInspection), [
            'title' => 'Vazamento na carcaça',
            'comment' => 'Avaliação inicial concluída.',
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
                'responsibility' => InspectionResponsibility::Inspector,
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
            'assessment_action' => 'complete',
        ])->assertSessionHasErrors('reason');
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
        $inspection = Inspection::factory()
            ->forEquipment($equipment)
            ->create([
                'number' => 'INS-2026-000001',
                'status' => InspectionStatus::Planned,
            ]);

        InspectionResponsible::factory()
            ->forInspection($inspection, $admin)
            ->create([
                'responsibility' => InspectionResponsibility::Inspector,
                'is_primary' => true,
            ]);

        $this->actingAs($admin)
            ->post(route('inspections.start', $inspection))
            ->assertRedirect();

        $inspection->refresh();

        return [$organization, $admin, $equipment, $inspection];
    }
}
