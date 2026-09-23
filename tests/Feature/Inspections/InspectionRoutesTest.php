<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use App\Enums\OperationalRole;
use App\Enums\UserAccountType;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\InspectionStatusHistory;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class InspectionRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_create_and_view_inspection_through_real_routes(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
                'operational_role' => OperationalRole::Planner,
            ]);
        $equipment = Equipment::factory()
            ->for($organization)
            ->create();
        $inspector = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);

        $this->actingAs($admin)
            ->get(route('inspections.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inspections/Create')
                ->has('selected_equipment', 0)
                ->where('equipment_search_url', route('inspections.equipment-options'))
                ->has('inspectors', 1)
                ->missing('atmospheric_options'));

        $response = $this->actingAs($admin)->post(route('inspections.store'), [
            'inspections' => [[
                'equipment_id' => $equipment->id,
                'planned_start_on' => '2026-09-20',
                'planned_end_on' => '2026-09-22',
                'inspector_id' => $inspector->id,
            ]],
        ]);

        $response->assertRedirect(route('inspections.index'));

        $inspection = Inspection::query()->firstOrFail();

        $this->assertSame($organization->id, $inspection->organization_id);
        $this->assertSame($equipment->id, $inspection->equipment_id);
        $this->assertSame('planned', $inspection->status->value);
        $this->assertSame('initial', $inspection->inspection_type->value);
        $this->assertNull($inspection->atmospheric_classification);
        $this->assertSame($equipment->numero_cliente, $inspection->external_report_number);
        $this->assertSame('PROJETISTA II', $inspection->report_designer);
        $this->assertSame($equipment->numero_interno, $inspection->designer_i_report_number);
        $this->assertNotEmpty($inspection->number);
        $this->assertNotEmpty($inspection->public_id);
        $this->assertSame($equipment->tag, $inspection->context_snapshot['equipment']['tag']);
        $this->assertSame(1, $inspection->statusHistories()->count());
        $this->assertNull($inspection->general_notes);

        $this->actingAs($admin)
            ->get(route('inspections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inspections/Index')
                ->where('capabilities.create', true)
                ->has('inspections.data', 1)
                ->where('inspections.data.0.type', 'initial')
                ->where('inspections.data.0.status', 'planned'));

        $this->actingAs($admin)
            ->get(route('inspections.show', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inspections/Show')
                ->where('inspection.number', $inspection->number)
                ->where('inspection.type', 'initial')
                ->where('inspection.status', 'planned')
                ->where('inspection.planned_start_on', '20/09/2026')
                ->where('inspection.planned_end_on', '22/09/2026')
                ->where('report_metadata.external_report_number', $equipment->numero_cliente)
                ->where('report_metadata.designer_i_report_number', $equipment->numero_interno)
                ->has('inspection.context_snapshot')
                ->has('inspection.history', 1)
                ->has('capabilities.update_planned.action')
                ->where('capabilities.assign_responsibles', false)
                ->where('capabilities.transition', true)
                ->has('transitions', 1)
                ->where('transitions.0.key', 'cancel'));
    }

    public function test_company_admin_sees_equipment_error_when_an_open_inspection_already_exists(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
                'operational_role' => OperationalRole::Planner,
            ]);
        $equipment = Equipment::factory()
            ->for($organization)
            ->create();
        $inspector = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);

        Inspection::factory()
            ->forEquipment($equipment)
            ->create([
                'status' => InspectionStatus::InProgress,
            ]);

        $this->actingAs($admin)
            ->from(route('inspections.create'))
            ->post(route('inspections.store'), [
                'inspections' => [[
                    'equipment_id' => $equipment->id,
                    'planned_start_on' => '2026-07-30',
                    'planned_end_on' => '2026-07-30',
                    'inspector_id' => $inspector->id,
                ]],
            ])
            ->assertRedirect(route('inspections.create'))
            ->assertSessionHasErrors([
                'inspections.0.equipment_id' => 'O equipamento já possui uma inspeção aberta.',
            ]);

        $this->assertDatabaseCount('inspections', 1);
    }

    public function test_planning_window_is_required_and_end_cannot_precede_start(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
            'operational_role' => OperationalRole::Planner,
        ]);
        $equipment = Equipment::factory()->for($organization)->create();

        $this->actingAs($admin)
            ->from(route('inspections.create'))
            ->post(route('inspections.store'), ['inspections' => [['equipment_id' => $equipment->id]]])
            ->assertRedirect(route('inspections.create'))
            ->assertSessionHasErrors(['inspections.0.planned_start_on', 'inspections.0.planned_end_on', 'inspections.0.inspector_id']);

        $this->actingAs($admin)
            ->from(route('inspections.create'))
            ->post(route('inspections.store'), [
                'inspections' => [[
                    'equipment_id' => $equipment->id,
                    'planned_start_on' => '2026-08-20',
                    'planned_end_on' => '2026-08-19',
                ]],
            ])
            ->assertRedirect(route('inspections.create'))
            ->assertSessionHasErrors(['inspections.0.planned_end_on']);

        $this->assertDatabaseCount('inspections', 0);
    }

    public function test_planning_rejects_an_unknown_atmospheric_classification(): void
    {
        $organization = Organization::factory()->create();
        $planner = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
            'operational_role' => OperationalRole::Planner,
        ]);
        $equipment = Equipment::factory()->for($organization)->create();
        $inspector = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);

        $this->actingAs($planner)
            ->from(route('inspections.create'))
            ->post(route('inspections.store'), ['inspections' => [[
                'equipment_id' => $equipment->id,
                'atmospheric_classification' => 'C1',
                'planned_start_on' => '2026-08-20',
                'planned_end_on' => '2026-08-20',
                'inspector_id' => $inspector->id,
            ]]])
            ->assertRedirect(route('inspections.create'))
            ->assertSessionHasErrors('inspections.0.atmospheric_classification');
    }

    public function test_linked_planner_can_edit_only_planning_fields_and_replaces_the_inspector(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
                'operational_role' => OperationalRole::Planner,
            ]);
        $equipment = Equipment::factory()
            ->for($organization)
            ->create();
        $inspection = Inspection::factory()
            ->forEquipment($equipment)
            ->create([
                'service_order' => 'OS-ANTIGA',
                'external_report_number' => 'REL-ANTIGO',
                'procedure_number' => 'PROC-ANTIGO',
                'atmospheric_classification' => 'C3',
                'planned_start_on' => '2026-07-30',
                'planned_end_on' => '2026-07-30',
                'general_notes' => 'Notas antigas',
            ]);
        InspectionResponsible::factory()
            ->forInspection($inspection, $admin)
            ->create(['responsibility' => InspectionResponsibility::Preparer, 'is_primary' => true]);
        $oldInspector = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);
        InspectionResponsible::factory()
            ->forInspection($inspection, $oldInspector)
            ->create(['responsibility' => InspectionResponsibility::Reviewer, 'is_primary' => true]);

        $this->actingAs($admin)
            ->get(route('inspections.edit', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inspections/Edit')
                ->where('inspection.number', $inspection->number)
                ->where('inspection.planned_start_on_input', '2026-07-30')
                ->where('inspection.planned_end_on_input', '2026-07-30')
                ->where('inspection.service_order', 'OS-ANTIGA')
                ->has('atmospheric_options', 5)
                ->missing('inspection.general_notes'));

        $otherEquipment = Equipment::factory()
            ->for($organization)
            ->create();
        $inspector = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);
        $previousInspection = Inspection::factory()
            ->forEquipment($otherEquipment)
            ->create([
                'status' => InspectionStatus::Released,
                'released_at' => now(),
            ]);

        $this->actingAs($admin)->put(route('inspections.update', $inspection), [
            'equipment_id' => $otherEquipment->id,
            'inspector_id' => $inspector->id,
            'service_order' => 'OS-NOVA',
            'atmospheric_classification' => 'c5',
            'planned_start_on' => '2026-08-15',
            'planned_end_on' => '2026-08-20',
            'external_report_number' => 'REL-NOVO',
        ])->assertSessionHasErrors('external_report_number');

        $response = $this->actingAs($admin)->put(route('inspections.update', $inspection), [
            'equipment_id' => $otherEquipment->id,
            'inspector_id' => $inspector->id,
            'service_order' => 'OS-NOVA',
            'atmospheric_classification' => 'c5',
            'planned_start_on' => '2026-08-15',
            'planned_end_on' => '2026-08-20',
        ]);

        $response->assertRedirect(route('inspections.show', $inspection));

        $inspection->refresh();

        $this->assertSame($otherEquipment->id, $inspection->equipment_id);
        $this->assertSame($previousInspection->id, $inspection->previous_inspection_id);
        $this->assertSame(InspectionType::Reinspection, $inspection->inspection_type);
        $this->assertSame('OS-NOVA', $inspection->service_order);
        $this->assertSame('REL-ANTIGO', $inspection->external_report_number);
        $this->assertSame('PROC-ANTIGO', $inspection->procedure_number);
        $this->assertSame('C5', $inspection->atmospheric_classification);
        $this->assertSame('2026-08-15', $inspection->planned_start_on?->toDateString());
        $this->assertSame('2026-08-20', $inspection->planned_end_on?->toDateString());
        $this->assertSame('Notas antigas', $inspection->general_notes);
        $this->assertSame($otherEquipment->tag, $inspection->context_snapshot['equipment']['tag']);
        $this->assertSame(1, $inspection->responsibles()->where('responsibility', InspectionResponsibility::Reviewer)->count());
        $this->assertDatabaseHas('inspection_responsibles', [
            'inspection_id' => $inspection->id,
            'user_id' => $inspector->id,
            'responsibility' => InspectionResponsibility::Reviewer->value,
            'is_primary' => true,
        ]);
    }

    public function test_unlinked_administrator_and_other_operational_roles_cannot_edit_planning(): void
    {
        $organization = Organization::factory()->create();
        $planner = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Planner]);
        $administrator = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
            'operational_role' => OperationalRole::Planner,
        ]);
        $inspector = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);
        $inspection = Inspection::factory()->forEquipment(Equipment::factory()->for($organization)->create())->create();
        InspectionResponsible::factory()
            ->forInspection($inspection, $planner)
            ->create(['responsibility' => InspectionResponsibility::Preparer, 'is_primary' => true]);

        foreach ([$administrator, $inspector] as $user) {
            $this->actingAs($user)
                ->put(route('inspections.update', $inspection), [
                    'equipment_id' => $inspection->equipment_id,
                    'inspector_id' => $inspector->id,
                    'planned_start_on' => '2026-08-15',
                    'planned_end_on' => '2026-08-20',
                ])
                ->assertForbidden();
        }
    }

    public function test_company_admin_automatically_creates_reinspection_from_the_latest_released_inspection(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
                'operational_role' => OperationalRole::Planner,
            ]);

        $equipment = Equipment::factory()
            ->for($organization)
            ->create();
        $otherEquipment = Equipment::factory()
            ->for($organization)
            ->create();
        $inspector = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);

        $olderReleasedPrevious = Inspection::factory()
            ->forEquipment($equipment)
            ->create([
                'status' => InspectionStatus::Released,
                'released_at' => now()->subDay(),
            ]);
        $latestReleasedPrevious = Inspection::factory()
            ->forEquipment($equipment)
            ->create([
                'status' => InspectionStatus::Released,
                'released_at' => now(),
            ]);
        $foreignReleasedPrevious = Inspection::factory()
            ->forEquipment($otherEquipment)
            ->create([
                'status' => InspectionStatus::Released,
                'released_at' => now(),
            ]);

        $response = $this->actingAs($admin)->post(route('inspections.store'), [
            'inspections' => [[
                'equipment_id' => $equipment->id,
                'planned_start_on' => '2026-07-30',
                'planned_end_on' => '2026-07-30',
                'inspector_id' => $inspector->id,
            ]],
        ]);
        $response->assertRedirect(route('inspections.index'));

        $reinspection = Inspection::query()
            ->where('organization_id', $organization->id)
            ->where('inspection_type', InspectionType::Reinspection)
            ->where('previous_inspection_id', $latestReleasedPrevious->id)
            ->firstOrFail();

        $this->assertSame($equipment->id, $reinspection->equipment_id);
        $this->assertSame(InspectionStatus::Planned, $reinspection->status);
        $this->assertSame('2026-07-30', $reinspection->planned_start_on?->toDateString());
        $this->assertSame('2026-07-30', $reinspection->planned_end_on?->toDateString());
        $this->assertNull($reinspection->general_notes);
        $this->assertNotSame($olderReleasedPrevious->id, $reinspection->previous_inspection_id);
        $this->assertNotSame($foreignReleasedPrevious->id, $reinspection->previous_inspection_id);
        $this->assertDatabaseCount('inspections', 4);
    }

    public function test_company_admin_ignores_canceled_and_foreign_inspections_when_determining_type(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
                'operational_role' => OperationalRole::Planner,
            ]);
        $equipment = Equipment::factory()->for($organization)->create();
        $otherEquipment = Equipment::factory()->for($otherOrganization)->create();
        $inspector = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);

        Inspection::factory()->forEquipment($equipment)->create([
            'status' => InspectionStatus::Canceled,
            'canceled_at' => now(),
        ]);
        Inspection::factory()->forEquipment($otherEquipment)->create([
            'status' => InspectionStatus::Released,
            'released_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('inspections.store'), [
            'inspections' => [[
                'equipment_id' => $equipment->id,
                'planned_start_on' => '2026-07-30',
                'planned_end_on' => '2026-07-30',
                'inspector_id' => $inspector->id,
            ]],
        ]);
        $response->assertRedirect(route('inspections.index'));

        $inspection = Inspection::query()
            ->where('equipment_id', $equipment->id)
            ->where('status', InspectionStatus::Planned)
            ->firstOrFail();

        $this->assertSame(InspectionType::Initial, $inspection->inspection_type);
        $this->assertNull($inspection->previous_inspection_id);
    }

    public function test_company_admin_cannot_edit_inspection_after_it_starts_through_real_routes(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
            ]);
        $inspection = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create([
                'status' => InspectionStatus::InProgress,
            ]);

        $this->actingAs($admin)
            ->get(route('inspections.edit', $inspection))
            ->assertForbidden();

        $this->actingAs($admin)
            ->put(route('inspections.update', $inspection), [
                'service_order' => 'OS-BLOQUEADA',
                'planned_start_on' => '2026-08-01',
                'planned_end_on' => '2026-08-01',
            ])
            ->assertForbidden();
    }

    public function test_company_admin_can_filter_inspections_by_status_and_type_through_real_routes(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
            ]);

        $releasedPrevious = Inspection::factory()
            ->forEquipment(Equipment::factory()->for($organization)->create())
            ->create([
                'status' => InspectionStatus::Released,
                'released_at' => now(),
            ]);

        $releasedReinspection = Inspection::factory()
            ->reinspection($releasedPrevious)
            ->create([
                'status' => InspectionStatus::Released,
                'released_at' => now(),
            ]);

        $this->actingAs($admin)
            ->get(route('inspections.index', [
                'status' => InspectionStatus::Released->value,
                'type' => InspectionType::Reinspection->value,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inspections/Index')
                ->has('inspections.data', 1)
                ->where('inspections.data.0.id', $releasedReinspection->id)
                ->where('filters.status', InspectionStatus::Released->value)
                ->where('filters.type', InspectionType::Reinspection->value));
    }

    public function test_inspections_are_ordered_by_planned_start_with_null_dates_last_and_filterable_by_status(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
        ]);
        $equipment = Equipment::factory()->for($organization)->create();

        $later = Inspection::factory()->forEquipment($equipment)->create([
            'number' => 'INS-2026-000003',
            'status' => InspectionStatus::Planned,
            'planned_start_on' => '2026-08-20',
        ]);
        $withoutPlannedDate = Inspection::factory()->forEquipment($equipment)->create([
            'number' => 'INS-2026-000002',
            'status' => InspectionStatus::Released,
            'planned_start_on' => null,
        ]);
        $earlier = Inspection::factory()->forEquipment($equipment)->create([
            'number' => 'INS-2026-000001',
            'status' => InspectionStatus::Planned,
            'planned_start_on' => '2026-08-10',
        ]);

        $this->actingAs($admin)
            ->get(route('inspections.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', '')
                ->has('options.statuses', count(InspectionStatus::cases()))
                ->where('inspections.data.0.id', $earlier->id)
                ->where('inspections.data.1.id', $later->id)
                ->where('inspections.data.2.id', $withoutPlannedDate->id));

        $this->actingAs($admin)
            ->get(route('inspections.index', ['status' => InspectionStatus::Planned->value]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', InspectionStatus::Planned->value)
                ->has('inspections.data', 2)
                ->where('inspections.data.0.id', $earlier->id)
                ->where('inspections.data.1.id', $later->id));
    }

    public function test_compact_filters_combine_search_status_and_planned_period(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
        ]);
        $equipment = Equipment::factory()->for($organization)->create([
            'tag' => 'P-100',
            'name' => 'Bomba de processo',
        ]);
        $matching = Inspection::factory()->forEquipment($equipment)->create([
            'status' => InspectionStatus::Planned,
            'service_order' => 'OS-ALVO',
            'planned_start_on' => '2026-08-10',
            'planned_end_on' => '2026-08-12',
        ]);
        Inspection::factory()->forEquipment($equipment)->create([
            'status' => InspectionStatus::Planned,
            'service_order' => 'OS-FORA-DO-PERIODO',
            'planned_start_on' => '2026-09-10',
        ]);
        Inspection::factory()->forEquipment($equipment)->create([
            'status' => InspectionStatus::Released,
            'service_order' => 'OS-ALVO',
            'planned_start_on' => '2026-08-10',
        ]);

        $this->actingAs($admin)
            ->get(route('inspections.index', [
                'search' => 'OS-ALVO',
                'status' => InspectionStatus::Planned->value,
                'scheduled_from' => '2026-08-01',
                'scheduled_to' => '2026-08-31',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', 'OS-ALVO')
                ->where('filters.status', InspectionStatus::Planned->value)
                ->where('filters.scheduled_from', '2026-08-01')
                ->where('filters.scheduled_to', '2026-08-31')
                ->has('inspections.data', 1)
                ->where('inspections.data.0.id', $matching->id));

        $this->actingAs($admin)
            ->get(route('inspections.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', '')
                ->where('filters.status', '')
                ->where('filters.scheduled_from', '')
                ->where('filters.scheduled_to', ''));
    }

    public function test_list_exposes_the_temporal_milestone_that_corresponds_to_each_status(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin->value,
        ]);
        $equipment = Equipment::factory()->for($organization)->create();

        $expectations = [
            [InspectionStatus::Planned, ['planned_start_on' => '2026-08-01', 'planned_end_on' => '2026-08-02'], 'Planejamento', '01/08/2026 a 02/08/2026'],
            [InspectionStatus::InProgress, ['started_at' => '2026-08-03 10:00:00'], 'Iniciada em', '03/08/2026'],
            [InspectionStatus::AwaitingReview, ['field_completed_at' => '2026-08-04 10:00:00'], 'Concluída em', '04/08/2026'],
            [InspectionStatus::InReview, [], 'Revisão iniciada em', '05/08/2026'],
            [InspectionStatus::InCorrection, [], 'Devolvida em', '06/08/2026'],
            [InspectionStatus::AwaitingRelease, ['approved_at' => '2026-08-07 10:00:00'], 'Aprovada em', '07/08/2026'],
            [InspectionStatus::Released, ['released_at' => '2026-08-08 10:00:00'], 'Liberada em', '08/08/2026'],
            [InspectionStatus::Canceled, ['canceled_at' => '2026-08-09 10:00:00'], 'Cancelada em', '09/08/2026'],
        ];

        foreach ($expectations as [$status, $attributes, $label, $value]) {
            $inspection = Inspection::factory()->forEquipment($equipment)->create([
                'status' => $status,
                ...$attributes,
            ]);

            if (in_array($status, [InspectionStatus::InReview, InspectionStatus::InCorrection], true)) {
                InspectionStatusHistory::factory()
                    ->forInspection($inspection, $admin, $status)
                    ->create(['created_at' => $status === InspectionStatus::InReview ? '2026-08-05 10:00:00' : '2026-08-06 10:00:00']);
            }

            $this->actingAs($admin)
                ->get(route('inspections.index', ['status' => $status->value]))
                ->assertInertia(fn (Assert $page) => $page
                    ->has('inspections.data', 1)
                    ->where('inspections.data.0.status_milestone.label', $label)
                    ->where('inspections.data.0.status_milestone.value', $value));
        }
    }

    public function test_member_only_sees_and_accesses_inspections_to_which_they_are_assigned(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::Member->value,
            ]);
        $equipment = Equipment::factory()
            ->for($organization)
            ->create();

        $assignedInspection = Inspection::factory()
            ->forEquipment($equipment)
            ->create();
        $unassignedInspection = Inspection::factory()
            ->forEquipment($equipment)
            ->create();

        InspectionResponsible::factory()
            ->forInspection($assignedInspection, $member)
            ->create([
                'responsibility' => InspectionResponsibility::Reviewer,
            ]);

        $this->actingAs($member)
            ->get(route('inspections.index', ['status' => InspectionStatus::Planned->value]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inspections/Index')
                ->where('capabilities.create', false)
                ->where('filters.status', InspectionStatus::Planned->value)
                ->has('inspections.data', 1)
                ->where('inspections.data.0.id', $assignedInspection->id));

        $this->actingAs($member)
            ->get(route('inspections.show', $unassignedInspection))
            ->assertForbidden();

        $this->actingAs($member)
            ->get(route('inspections.create'))
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('inspections.store'), [
                'equipment_id' => $equipment->id,
                'inspection_type' => InspectionType::Initial->value,
            ])
            ->assertForbidden();
    }

    public function test_cross_tenant_show_binding_returns_404(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();

        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
            ]);
        $otherAdmin = User::factory()
            ->for($otherOrganization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
            ]);
        $equipment = Equipment::factory()
            ->for($organization)
            ->create();

        $inspection = Inspection::factory()
            ->forEquipment($equipment)
            ->create();

        $this->actingAs($admin)
            ->get(route('inspections.show', $inspection))
            ->assertOk();

        $this->actingAs($otherAdmin)
            ->get(route('inspections.show', $inspection))
            ->assertNotFound();
    }
}
