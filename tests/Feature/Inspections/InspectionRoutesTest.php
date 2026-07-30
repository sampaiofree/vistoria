<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use App\Enums\UserAccountType;
use App\Models\Equipment;
use App\Models\Inspection;
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
            ]);
        $equipment = Equipment::factory()
            ->for($organization)
            ->create();

        $this->actingAs($admin)
            ->get(route('inspections.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inspections/Create')
                ->has('equipment', 1)
                ->has('inspection_types', 2)
                ->has('released_inspections', 0));

        $response = $this->actingAs($admin)->post(route('inspections.store'), [
            'equipment_id' => $equipment->id,
            'inspection_type' => InspectionType::Initial->value,
            'scheduled_at' => '2026-07-30',
            'general_notes' => 'Notas da criação',
        ]);

        $response->assertRedirect();

        $inspection = Inspection::query()->firstOrFail();

        $this->assertSame($organization->id, $inspection->organization_id);
        $this->assertSame($equipment->id, $inspection->equipment_id);
        $this->assertSame('planned', $inspection->status->value);
        $this->assertSame('initial', $inspection->inspection_type->value);
        $this->assertNotEmpty($inspection->number);
        $this->assertNotEmpty($inspection->public_id);
        $this->assertSame($equipment->tag, $inspection->context_snapshot['equipment']['tag']);
        $this->assertSame(1, $inspection->statusHistories()->count());

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
                ->has('inspection.context_snapshot')
                ->has('inspection.reference_document_ids')
                ->has('inspection.history', 1)
                ->has('capabilities.update_planned.action')
                ->has('capabilities.assign_responsibles.action')
                ->has('capabilities.manage_references.action')
                ->where('capabilities.transition', true)
                ->has('transitions', 1)
                ->where('transitions.0.key', 'cancel'));
    }

    public function test_company_admin_can_edit_only_planned_inspection_fields_through_real_routes(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
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
                'scheduled_for' => '2026-07-30',
                'general_notes' => 'Notas antigas',
            ]);

        $this->actingAs($admin)
            ->get(route('inspections.edit', $inspection))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inspections/Edit')
                ->where('inspection.number', $inspection->number)
                ->where('inspection.scheduled_for_input', '2026-07-30')
                ->where('inspection.service_order', 'OS-ANTIGA')
                ->where('inspection.general_notes', 'Notas antigas'));

        $otherEquipment = Equipment::factory()
            ->for($organization)
            ->create();

        $response = $this->actingAs($admin)->put(route('inspections.update', $inspection), [
            'equipment_id' => $otherEquipment->id,
            'inspection_type' => InspectionType::Reinspection->value,
            'previous_inspection_id' => null,
            'service_order' => 'OS-NOVA',
            'external_report_number' => 'REL-NOVO',
            'procedure_number' => 'PROC-NOVO',
            'atmospheric_classification' => 'C4',
            'scheduled_for' => '2026-08-15',
            'general_notes' => 'Notas atualizadas',
        ]);

        $response->assertRedirect(route('inspections.show', $inspection));

        $inspection->refresh();

        $this->assertSame($equipment->id, $inspection->equipment_id);
        $this->assertSame(InspectionType::Initial, $inspection->inspection_type);
        $this->assertSame('OS-NOVA', $inspection->service_order);
        $this->assertSame('REL-NOVO', $inspection->external_report_number);
        $this->assertSame('PROC-NOVO', $inspection->procedure_number);
        $this->assertSame('C4', $inspection->atmospheric_classification);
        $this->assertSame('2026-08-15', $inspection->scheduled_for?->toDateString());
        $this->assertSame('Notas atualizadas', $inspection->general_notes);
    }

    public function test_company_admin_can_create_reinspection_only_from_a_released_previous_inspection(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
            ]);

        $equipment = Equipment::factory()
            ->for($organization)
            ->create();
        $otherEquipment = Equipment::factory()
            ->for($organization)
            ->create();

        $releasedPrevious = Inspection::factory()
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

        $this->actingAs($admin)
            ->post(route('inspections.store'), [
                'equipment_id' => $equipment->id,
                'inspection_type' => InspectionType::Initial->value,
                'previous_inspection_id' => $releasedPrevious->id,
            ])
            ->assertSessionHasErrors('previous_inspection_id');

        $this->actingAs($admin)
            ->post(route('inspections.store'), [
                'equipment_id' => $equipment->id,
                'inspection_type' => InspectionType::Reinspection->value,
                'previous_inspection_id' => $foreignReleasedPrevious->id,
            ])
            ->assertSessionHasErrors('previous_inspection_id');

        $this->actingAs($admin)
            ->post(route('inspections.store'), [
                'equipment_id' => $equipment->id,
                'inspection_type' => InspectionType::Reinspection->value,
                'previous_inspection_id' => $releasedPrevious->id,
                'scheduled_at' => '2026-07-30',
                'general_notes' => 'Reinspeção de teste',
            ])
            ->assertRedirect();

        $reinspection = Inspection::query()
            ->where('organization_id', $organization->id)
            ->where('inspection_type', InspectionType::Reinspection)
            ->where('previous_inspection_id', $releasedPrevious->id)
            ->firstOrFail();

        $this->assertSame($equipment->id, $reinspection->equipment_id);
        $this->assertSame(InspectionStatus::Planned, $reinspection->status);
        $this->assertSame('2026-07-30', $reinspection->scheduled_for?->toDateString());
        $this->assertSame('Reinspeção de teste', $reinspection->general_notes);
        $this->assertDatabaseCount('inspections', 3);
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
                'scheduled_for' => '2026-08-01',
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

    public function test_member_can_view_index_but_cannot_create_inspection(): void
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

        Inspection::factory()
            ->forEquipment($equipment)
            ->create();

        $this->actingAs($member)
            ->get(route('inspections.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inspections/Index')
                ->where('capabilities.create', false)
                ->has('inspections.data', 1));

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
