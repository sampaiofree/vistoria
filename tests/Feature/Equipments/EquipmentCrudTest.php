<?php

declare(strict_types=1);

namespace Tests\Feature\Equipments;

use App\Enums\InspectionStatus;
use App\Enums\UserAccountType;
use App\Models\Area;
use App\Models\Client;
use App\Models\ClientUnit;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Equipment;
use App\Models\EquipmentDocument;
use App\Models\Inspection;
use App\Models\Organization;
use App\Models\Subarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class EquipmentCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_equipment_and_tenant_is_ignored(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();

        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
            ]);

        [$client, $unit, $area, $subarea] = $this->createActiveHierarchy($organization);

        $response = $this
            ->actingAs($admin)
            ->post(route('equipments.store'), [
                'organization_id' => $otherOrganization->id,
                'client_id' => $client->id,
                'client_unit_id' => $unit->id,
                'area_id' => $area->id,
                'subarea_id' => $subarea->id,
                'tag' => ' u03-06vt002 ',
                'name' => ' Ventilador principal ',
                'description' => '  Descricao tecnica  ',
                'manufacturer' => ' Weg ',
                'model' => ' vx-200 ',
                'serial_number' => ' sn-0001 ',
                'asset_code' => ' patr-01 ',
                'abc_code' => ' a ',
                'installation_location' => ' Forno de Endurecimento ',
                'commissioned_at' => '2026-07-30',
                'notes' => ' Observacoes ',
            ]);

        $response->assertRedirect();

        $equipment = Equipment::query()->firstOrFail();

        $this->assertSame($organization->id, $equipment->organization_id);
        $this->assertSame('U03-06VT002', $equipment->tag);
        $this->assertSame('U03-06VT002', $equipment->normalized_tag);
        $this->assertSame('Ventilador principal', $equipment->name);
        $this->assertSame('Descricao tecnica', $equipment->description);
        $this->assertSame('Weg', $equipment->manufacturer);
        $this->assertSame('vx-200', $equipment->model);
        $this->assertSame('sn-0001', $equipment->serial_number);
        $this->assertSame('PATR-01', $equipment->asset_code);
        $this->assertSame('A', $equipment->abc_code);
        $this->assertSame('Forno de Endurecimento', $equipment->installation_location);
        $this->assertSame('Observacoes', $equipment->notes);
        $this->assertSame('active', $equipment->status->value);
        $this->assertSame('2026-07-30', $equipment->commissioned_at?->toDateString());
    }

    public function test_member_cannot_create_equipment(): void
    {
        $organization = Organization::factory()->create();

        $member = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::Member->value,
            ]);

        $this->actingAs($member)
            ->post(route('equipments.store'), [
                'client_id' => 1,
                'client_unit_id' => 1,
                'area_id' => 1,
                'tag' => 'EQ-001',
                'name' => 'Restrito',
            ])
            ->assertForbidden();
    }

    public function test_duplicate_tag_is_blocked_in_same_unit_and_allowed_in_another_unit(): void
    {
        $organization = Organization::factory()->create();

        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
            ]);

        [$client, $unit, $area, $subarea] = $this->createActiveHierarchy($organization);

        $basePayload = [
            'client_id' => $client->id,
            'client_unit_id' => $unit->id,
            'area_id' => $area->id,
            'subarea_id' => $subarea->id,
            'tag' => 'BOMBA-001',
            'name' => 'Bomba principal',
        ];

        $this->actingAs($admin)
            ->post(route('equipments.store'), $basePayload)
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('equipments.store'), $basePayload)
            ->assertSessionHasErrors('tag');

        $otherUnit = ClientUnit::factory()->forClient($client)->create();
        $otherArea = Area::factory()->forUnit($otherUnit)->create();
        $otherSubarea = Subarea::factory()->forArea($otherArea)->create();

        $this->actingAs($admin)
            ->post(route('equipments.store'), [
                'client_id' => $client->id,
                'client_unit_id' => $otherUnit->id,
                'area_id' => $otherArea->id,
                'subarea_id' => $otherSubarea->id,
                'tag' => 'BOMBA-001',
                'name' => 'Bomba secundaria',
            ])
            ->assertRedirect();
    }

    public function test_inactive_hierarchy_blocks_new_equipment(): void
    {
        $organization = Organization::factory()->create();

        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
            ]);

        $inactiveClient = Client::factory()
            ->for($organization)
            ->create([
                'status' => 'inactive',
            ]);

        $activeUnit = ClientUnit::factory()
            ->forClient($inactiveClient)
            ->create();

        $activeArea = Area::factory()
            ->forUnit($activeUnit)
            ->create();

        $activeSubarea = Subarea::factory()
            ->forArea($activeArea)
            ->create();

        $this->actingAs($admin)
            ->post(route('equipments.store'), [
                'client_id' => $inactiveClient->id,
                'client_unit_id' => $activeUnit->id,
                'area_id' => $activeArea->id,
                'subarea_id' => $activeSubarea->id,
                'tag' => 'EQ-001',
                'name' => 'Bomba',
            ])
            ->assertSessionHasErrors('client_id');

        $activeClient = Client::factory()
            ->for($organization)
            ->create();

        $inactiveUnit = ClientUnit::factory()
            ->forClient($activeClient)
            ->inactive()
            ->create();

        $areaOfInactiveUnit = Area::factory()
            ->forUnit($inactiveUnit)
            ->create();

        $subareaOfInactiveUnit = Subarea::factory()
            ->forArea($areaOfInactiveUnit)
            ->create();

        $this->actingAs($admin)
            ->post(route('equipments.store'), [
                'client_id' => $activeClient->id,
                'client_unit_id' => $inactiveUnit->id,
                'area_id' => $areaOfInactiveUnit->id,
                'subarea_id' => $subareaOfInactiveUnit->id,
                'tag' => 'EQ-002',
                'name' => 'Bomba 2',
            ])
            ->assertSessionHasErrors('client_unit_id');

        $activeUnit = ClientUnit::factory()
            ->forClient($activeClient)
            ->create();

        $inactiveArea = Area::factory()
            ->forUnit($activeUnit)
            ->inactive()
            ->create();

        $subareaOfInactiveArea = Subarea::factory()
            ->forArea($inactiveArea)
            ->create();

        $this->actingAs($admin)
            ->post(route('equipments.store'), [
                'client_id' => $activeClient->id,
                'client_unit_id' => $activeUnit->id,
                'area_id' => $inactiveArea->id,
                'subarea_id' => $subareaOfInactiveArea->id,
                'tag' => 'EQ-003',
                'name' => 'Bomba 3',
            ])
            ->assertSessionHasErrors('area_id');

        $activeArea = Area::factory()
            ->forUnit($activeUnit)
            ->create();

        $inactiveSubarea = Subarea::factory()
            ->forArea($activeArea)
            ->inactive()
            ->create();

        $this->actingAs($admin)
            ->post(route('equipments.store'), [
                'client_id' => $activeClient->id,
                'client_unit_id' => $activeUnit->id,
                'area_id' => $activeArea->id,
                'subarea_id' => $inactiveSubarea->id,
                'tag' => 'EQ-004',
                'name' => 'Bomba 4',
            ])
            ->assertSessionHasErrors('subarea_id');
    }

    public function test_member_can_view_index_and_public_id_binding_rejects_numeric_id(): void
    {
        $organization = Organization::factory()->create();

        $member = User::factory()
            ->for($organization)
            ->create();

        [$client, $unit, $area, $subarea] = $this->createActiveHierarchy($organization);

        $equipment = Equipment::factory()
            ->inStructure($client, $unit, $area, $subarea)
            ->create([
                'tag' => 'U03-06VT002',
                'normalized_tag' => 'U03-06VT002',
            ]);

        $this->actingAs($member)
            ->get(route('equipments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Equipments/Index')
                ->where('can.create', false)
                ->where('equipments.data.0.public_id', $equipment->public_id));

        $this->actingAs($member)
            ->get(route('equipments.show', $equipment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Equipments/Show')
                ->where('equipment.public_id', $equipment->public_id));

        $this->actingAs($member)
            ->get('/equipments/'.$equipment->id)
            ->assertNotFound();
    }

    public function test_admin_can_edit_equipment_and_change_status(): void
    {
        $organization = Organization::factory()->create();

        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
            ]);

        [$client, $unit, $area, $subarea] = $this->createActiveHierarchy($organization);

        $equipment = Equipment::factory()
            ->inStructure($client, $unit, $area, $subarea)
            ->create([
                'tag' => 'EQ-100',
                'normalized_tag' => 'EQ-100',
            ]);

        $this->actingAs($admin)
            ->get(route('equipments.edit', $equipment))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Equipments/Edit')
                ->where('equipment.public_id', $equipment->public_id));

        $this->actingAs($admin)
            ->put(route('equipments.update', $equipment), [
                'client_id' => $client->id,
                'client_unit_id' => $unit->id,
                'area_id' => $area->id,
                'subarea_id' => $subarea->id,
                'tag' => ' eq-101 ',
                'name' => ' Ventilador atualizado ',
                'description' => 'Descricao nova',
                'manufacturer' => ' Weg ',
                'model' => ' VX-200 ',
                'serial_number' => ' SN-200 ',
                'asset_code' => ' patr-02 ',
                'abc_code' => ' b ',
                'installation_location' => ' Forno III ',
                'commissioned_at' => '2026-07-30',
                'notes' => ' Observacoes ',
            ])
            ->assertRedirect(route('equipments.show', $equipment));

        $equipment->refresh();

        $this->assertSame('EQ-101', $equipment->tag);
        $this->assertSame('EQ-101', $equipment->normalized_tag);
        $this->assertSame('Ventilador atualizado', $equipment->name);
        $this->assertSame('Descricao nova', $equipment->description);
        $this->assertSame('Weg', $equipment->manufacturer);
        $this->assertSame('VX-200', $equipment->model);
        $this->assertSame('SN-200', $equipment->serial_number);
        $this->assertSame('PATR-02', $equipment->asset_code);
        $this->assertSame('B', $equipment->abc_code);
        $this->assertSame('Forno III', $equipment->installation_location);
        $this->assertSame('Observacoes', $equipment->notes);

        $this->actingAs($admin)
            ->patch(route('equipments.status', $equipment), [
                'status' => 'inactive',
            ])
            ->assertRedirect();

        $this->assertSame('inactive', $equipment->refresh()->status->value);

        $this->actingAs($admin)
            ->patch(route('equipments.status', $equipment), [
                'status' => 'active',
            ])
            ->assertRedirect();

        $equipment->refresh();

        $this->assertSame('active', $equipment->status->value);

        $this->actingAs($admin)
            ->patch(route('equipments.status', $equipment), [
                'status' => 'decommissioned',
                'reason' => 'Equipamento substituido por modelo mais novo.',
            ])
            ->assertRedirect();

        $equipment->refresh();

        $this->assertSame('decommissioned', $equipment->status->value);
        $this->assertSame('Equipamento substituido por modelo mais novo.', $equipment->decommission_reason);
        $this->assertSame($admin->id, $equipment->decommissioned_by);
        $this->assertNotNull($equipment->decommissioned_at);

        $this->actingAs($admin)
            ->patch(route('equipments.status', $equipment), [
                'status' => 'active',
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_equipment_show_exposes_executive_summary_current_inspection_and_history(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
            ]);

        [$client, $unit, $area, $subarea] = $this->createActiveHierarchy($organization);
        $equipment = Equipment::factory()
            ->inStructure($client, $unit, $area, $subarea)
            ->create([
                'tag' => 'U03-06VT002',
                'normalized_tag' => 'U03-06VT002',
            ]);
        $previous = Inspection::factory()->forEquipment($equipment)->create([
            'number' => 'INS-2025-000001',
            'status' => InspectionStatus::Released,
            'inspected_on' => now()->subYear(),
            'created_at' => now()->subYear(),
        ]);
        $current = Inspection::factory()->forEquipment($equipment, $previous)->create([
            'number' => 'INS-2026-000001',
            'status' => InspectionStatus::InProgress,
            'service_order' => 'OS-2026-0815',
            'scheduled_for' => now()->toDateString(),
            'started_at' => now()->subHour(),
            'created_at' => now()->subHour(),
        ]);

        $criticalDefect = Defect::factory()->forEquipment($equipment, $previous)->create([
            'code' => 'VT002-CV-001',
            'sequence_number' => 1,
            'title' => 'Fissura longitudinal no pedestal de concreto',
        ]);
        $pendingDefect = Defect::factory()->forEquipment($equipment, $previous)->create([
            'code' => 'VT002-CV-002',
            'sequence_number' => 2,
            'title' => 'Falha de selagem entre base e piso',
        ]);

        DefectAssessment::factory()->forDefect($criticalDefect, $current)->complete()->create();
        DefectAssessment::factory()->forDefect($pendingDefect, $current)->draft()->create();
        EquipmentDocument::factory()->forEquipment($equipment)->create([
            'uploaded_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('equipments.show', $equipment))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($current, $previous): void {
                $page
                    ->component('Equipments/Show')
                    ->where('executive_summary.criticality.value', 'CV-2')
                    ->where('executive_summary.criticality.is_provisional', true)
                    ->where('executive_summary.active_defects', 2)
                    ->where('executive_summary.inspections', 2)
                    ->where('executive_summary.current_documents', 1)
                    ->where('current_inspection.public_id', $current->public_id)
                    ->where('current_inspection.progress.completed', 1)
                    ->where('current_inspection.progress.total', 2)
                    ->where('current_inspection.progress.percentage', 50)
                    ->where('current_inspection.show_url', route('inspections.show', $current))
                    ->has('inspection_history', 2)
                    ->where('inspection_history.0.public_id', $current->public_id)
                    ->where('inspection_history.0.is_current', true)
                    ->where('inspection_history.1.public_id', $previous->public_id)
                    ->where('inspection_history.1.is_current', false);
            });
    }

    public function test_member_cannot_update_equipment_or_change_status(): void
    {
        $organization = Organization::factory()->create();

        $member = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::Member->value,
            ]);

        [$client, $unit, $area, $subarea] = $this->createActiveHierarchy($organization);

        $equipment = Equipment::factory()
            ->inStructure($client, $unit, $area, $subarea)
            ->create([
                'tag' => 'EQ-200',
                'normalized_tag' => 'EQ-200',
            ]);

        $this->actingAs($member)
            ->put(route('equipments.update', $equipment), [
                'client_id' => $client->id,
                'client_unit_id' => $unit->id,
                'area_id' => $area->id,
                'subarea_id' => $subarea->id,
                'tag' => 'EQ-201',
                'name' => 'Restrito',
            ])
            ->assertForbidden();

        $this->actingAs($member)
            ->patch(route('equipments.status', $equipment), [
                'status' => 'inactive',
            ])
            ->assertForbidden();
    }

    public function test_equipment_factory_creates_a_coherent_hierarchy(): void
    {
        $organization = Organization::factory()->create();

        $equipment = Equipment::factory()
            ->for($organization)
            ->create();

        $equipment->load(['client', 'unit', 'area', 'subarea']);

        $this->assertSame($organization->id, $equipment->organization_id);
        $this->assertSame($organization->id, $equipment->client->organization_id);
        $this->assertSame($organization->id, $equipment->unit->organization_id);
        $this->assertSame($organization->id, $equipment->area->organization_id);
        $this->assertSame($organization->id, $equipment->subarea?->organization_id);
        $this->assertTrue($equipment->canReceiveInspection());
    }

    public function test_reactivation_requires_active_structure_not_active_status(): void
    {
        $organization = Organization::factory()->create();

        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
            ]);

        [$client, $unit, $area, $subarea] = $this->createActiveHierarchy($organization);

        $equipment = Equipment::factory()
            ->inStructure($client, $unit, $area, $subarea)
            ->inactive()
            ->create([
                'tag' => 'EQ-300',
                'normalized_tag' => 'EQ-300',
            ]);

        $this->actingAs($admin)
            ->patch(route('equipments.status', $equipment), [
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertSame('active', $equipment->refresh()->status->value);
    }

    /**
     * @return array{0: Client, 1: ClientUnit, 2: Area, 3: Subarea}
     */
    private function createActiveHierarchy(Organization $organization): array
    {
        $client = Client::factory()
            ->for($organization)
            ->create();

        $unit = ClientUnit::factory()
            ->forClient($client)
            ->create();

        $area = Area::factory()
            ->forUnit($unit)
            ->create();

        $subarea = Subarea::factory()
            ->forArea($area)
            ->create();

        return [$client, $unit, $area, $subarea];
    }
}
