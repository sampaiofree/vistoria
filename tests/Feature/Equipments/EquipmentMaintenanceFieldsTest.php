<?php

declare(strict_types=1);

namespace Tests\Feature\Equipments;

use App\Actions\Equipments\CreateEquipment;
use App\Actions\Equipments\UpdateEquipment;
use App\Actions\Inspections\CreateInspection;
use App\Enums\InspectionType;
use App\Enums\UserAccountType;
use App\Models\Client;
use App\Models\Defect;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Organization;
use App\Models\SapM2Note;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class EquipmentMaintenanceFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin->value]);
        $client = Client::factory()->for($organization)->create();

        return [$organization, $admin, $client];
    }

    private function payload(Client $client, array $overrides = []): array
    {
        return array_replace([
            'client_id' => $client->id,
            'numero_cliente' => 'SAM-000123',
            'numero_interno' => 'SEND-000123',
            'maintenance_plan_code' => '000000001234',
            'maintenance_item_code' => '000123',
            'defect_code_prefix' => 'PF-000123',
            'tag' => ' prédio ',
            'name' => 'Denominação do local',
            'description' => 'Descrição de manutenção',
            'installation_location' => 'LOC-00008',
            'area_code' => 'U00',
            'area_name' => 'Área da usina',
            'subarea_code' => '08',
            'subarea_name' => 'Subárea de teste',
            'task_list_group' => '00012345',
            'task_list_group_counter' => 'T5',
            'abc_code' => 'D',
        ], $overrides);
    }

    public function test_all_spreadsheet_fields_are_persisted_and_available_in_forms_and_details(): void
    {
        [$organization, $admin, $client] = $this->context();
        $data = $this->payload($client);
        $this->actingAs($admin)->post(route('equipments.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $equipment = Equipment::sole();
        unset($data['tag']);
        $this->assertDatabaseHas('equipments', $data + ['tag' => 'PRÉDIO', 'normalized_tag' => 'PRÉDIO']);
        foreach (['edit', 'show'] as $page) {
            $this->get(route('equipments.'.$page, $equipment))->assertInertia(fn (Assert $page) => $page
                ->where('equipment.maintenance_item_code', '000123')
                ->where('equipment.numero_cliente', 'SAM-000123')
                ->where('equipment.numero_interno', 'SEND-000123')
                ->where('equipment.subarea_code', '08')
                ->where('equipment.task_list_group_counter', 'T5')
                ->where('equipment.abc_code', 'D')
                ->missing('equipment.client_id')
                ->missing('equipment.area_id')->missing('equipment.subarea_id')->missing('equipment.client_unit_id'));
        }
        $this->get(route('equipments.edit', $equipment))->assertInertia(fn (Assert $page) => $page
            ->has('abc_options', 4)
            ->where('abc_options.0.value', 'A')
            ->where('abc_options.3.value', 'D')
            ->where('related_records.inspections_count', 0)
            ->where('related_records.defects_count', 0)
            ->where('related_records.requires_confirmation', false));
        $this->get(route('equipments.index', ['search' => '000123']))->assertInertia(fn (Assert $page) => $page
            ->has('equipments.data', 1)
            ->where('equipments.data.0.maintenance_item_code', '000123')
            ->where('equipments.data.0.numero_cliente', 'SAM-000123')
            ->where('equipments.data.0.numero_interno', 'SEND-000123')
            ->where('equipments.data.0.defect_code_prefix', 'PF-000123')
            ->where('equipments.data.0.description', 'Descrição de manutenção')
            ->where('equipments.data.0.area_name', 'Área da usina')
            ->where('equipments.data.0.subarea_name', 'Subárea de teste')
            ->missing('clients')
            ->missing('filters.client')
            ->missing('filters.status')
            ->missing('status_options')
            ->missing('equipments.data.0.name'));
    }

    public function test_index_searches_each_visible_equipment_field(): void
    {
        [$organization, $admin, $client] = $this->context();
        Equipment::factory()->inStructure($client)->create([
            'maintenance_item_code' => 'ITEM-ALVO',
            'tag' => 'TAG-ALVO',
            'normalized_tag' => 'TAG-ALVO',
            'defect_code_prefix' => 'PFX-ALVO',
            'description' => 'Descrição exclusiva',
            'area_name' => 'Área exclusiva',
            'subarea_name' => 'Subárea exclusiva',
        ]);

        foreach (['ITEM-ALVO', 'TAG-ALVO', 'PFX-ALVO', 'Descrição exclusiva', 'Área exclusiva', 'Subárea exclusiva'] as $search) {
            $this->actingAs($admin)->get(route('equipments.index', ['search' => $search]))->assertInertia(fn (Assert $page) => $page
                ->has('equipments.data', 1)
                ->where('equipments.data.0.maintenance_item_code', 'ITEM-ALVO'));
        }
    }

    public function test_repeated_tags_are_allowed_on_creation_and_update(): void
    {
        [$organization, $admin, $client] = $this->context();
        $this->actingAs($admin)->post(route('equipments.store'), $this->payload($client))->assertSessionHasNoErrors();
        $this->post(route('equipments.store'), $this->payload($client, ['numero_cliente' => 'SAM-000124', 'numero_interno' => 'SEND-000124', 'maintenance_item_code' => '000124', 'defect_code_prefix' => 'PF-000124']))->assertSessionHasNoErrors();
        $equipment = Equipment::where('maintenance_item_code', '000124')->sole();
        $this->put(route('equipments.update', $equipment), $this->payload($client, ['numero_cliente' => 'SAM-000124', 'numero_interno' => 'SEND-000124', 'maintenance_item_code' => '000124', 'defect_code_prefix' => 'PF-000124']))->assertSessionHasNoErrors();
        $this->assertSame(2, Equipment::where('normalized_tag', 'PRÉDIO')->count());
    }

    public function test_item_is_unique_in_an_organization_including_deleted_records(): void
    {
        [$organization, $admin, $client] = $this->context();
        $existing = Equipment::factory()->inStructure($client)->create(['maintenance_item_code' => '000123']);
        $this->actingAs($admin)->post(route('equipments.store'), $this->payload($client))->assertSessionHasErrors('maintenance_item_code');
        $existing->delete();
        $this->post(route('equipments.store'), $this->payload($client))->assertSessionHasErrors('maintenance_item_code');
    }

    public function test_admin_can_permanently_delete_an_unrelated_equipment_and_reuse_its_identifiers(): void
    {
        [, $admin, $client] = $this->context();
        $payload = $this->payload($client);

        $this->actingAs($admin)->post(route('equipments.store'), $payload)->assertSessionHasNoErrors();
        $equipment = Equipment::sole();

        $this->delete(route('equipments.destroy', $equipment))
            ->assertRedirect(route('equipments.index'))
            ->assertSessionHas('success', 'Item de manutenção excluído.');

        $this->assertNull(Equipment::withTrashed()->find($equipment->id));
        $this->post(route('equipments.store'), $payload)->assertSessionHasNoErrors();
    }

    public function test_only_an_admin_from_the_equipment_organization_can_delete_it(): void
    {
        [$organization, $admin, $client] = $this->context();
        $equipment = Equipment::factory()->inStructure($client)->create();
        $member = User::factory()->for($organization)->create(['account_type' => UserAccountType::Member->value]);
        $otherAdmin = User::factory()->for(Organization::factory())->create(['account_type' => UserAccountType::CompanyAdmin->value]);

        $this->actingAs($member)->delete(route('equipments.destroy', $equipment))->assertForbidden();
        $this->actingAs($otherAdmin)->delete(route('equipments.destroy', $equipment))->assertNotFound();
        $this->assertDatabaseHas('equipments', ['id' => $equipment->id]);
        $this->actingAs($admin);
    }

    public function test_linked_records_hide_the_delete_action_and_block_deletion(): void
    {
        [, $admin, $client] = $this->context();
        $equipment = Equipment::factory()->inStructure($client)->create();
        Inspection::factory()->forEquipment($equipment)->create();

        $this->actingAs($admin)->get(route('equipments.index', ['search' => $equipment->maintenance_item_code]))
            ->assertInertia(fn (Assert $page) => $page->where('equipments.data.0.can_delete', false));

        $this->delete(route('equipments.destroy', $equipment))
            ->assertRedirect(route('equipments.index'))
            ->assertSessionHas('error', 'Este item de manutenção não pode ser excluído porque possui registros vinculados.');

        $this->assertDatabaseHas('equipments', ['id' => $equipment->id]);
    }

    public function test_deletion_is_rechecked_when_a_technical_record_is_created_after_listing(): void
    {
        [$organization, $admin, $client] = $this->context();
        $equipment = Equipment::factory()->inStructure($client)->create();

        $this->actingAs($admin)->get(route('equipments.index', ['search' => $equipment->maintenance_item_code]))
            ->assertInertia(fn (Assert $page) => $page->where('equipments.data.0.can_delete', true));

        SapM2Note::query()->create([
            'organization_id' => $organization->id,
            'equipment_id' => $equipment->id,
            'sap_number' => 'M2-001',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->delete(route('equipments.destroy', $equipment))
            ->assertRedirect(route('equipments.index'))
            ->assertSessionHas('error', 'Este item de manutenção não pode ser excluído porque possui registros vinculados.');
        $this->assertDatabaseHas('equipments', ['id' => $equipment->id]);
    }

    public function test_same_item_in_different_organizations_is_allowed(): void
    {
        [$organization, $admin, $client] = $this->context();
        Equipment::factory()->create(['maintenance_item_code' => '000123']);
        $this->actingAs($admin)->post(route('equipments.store'), $this->payload($client))->assertSessionHasNoErrors();
        $this->assertSame(2, Equipment::where('maintenance_item_code', '000123')->count());
    }

    public function test_customer_and_internal_numbers_are_required_unique_per_organization_and_normalized(): void
    {
        [$organization, $admin, $client] = $this->context();
        Equipment::factory()->inStructure($client)->create([
            'numero_cliente' => 'SAM-000123',
            'numero_interno' => 'SEND-000123',
        ]);

        $this->actingAs($admin)
            ->post(route('equipments.store'), $this->payload($client))
            ->assertSessionHasErrors(['numero_cliente', 'numero_interno']);

        $this->post(route('equipments.store'), $this->payload($client, [
            'numero_cliente' => '',
            'numero_interno' => '',
            'maintenance_item_code' => '000124',
            'defect_code_prefix' => 'PF-000124',
        ]))->assertSessionHasErrors(['numero_cliente', 'numero_interno']);

        $this->post(route('equipments.store'), $this->payload($client, [
            'numero_cliente' => str_repeat('C', 51),
            'numero_interno' => str_repeat('I', 51),
            'maintenance_item_code' => '000125',
            'defect_code_prefix' => 'PF-000125',
        ]))->assertSessionHasErrors(['numero_cliente', 'numero_interno']);

        $otherOrganization = Organization::factory()->create();
        $otherAdmin = User::factory()->for($otherOrganization)->create(['account_type' => UserAccountType::CompanyAdmin->value]);
        $otherClient = Client::factory()->for($otherOrganization)->create();

        $this->actingAs($otherAdmin)
            ->post(route('equipments.store'), $this->payload($otherClient, [
                'numero_cliente' => ' sam-000123 ',
                'numero_interno' => ' send-000123 ',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('equipments', [
            'organization_id' => $otherOrganization->id,
            'numero_cliente' => 'SAM-000123',
            'numero_interno' => 'SEND-000123',
        ]);
    }

    public function test_customer_and_internal_numbers_cannot_be_reused_after_soft_deletion(): void
    {
        [$organization, $admin, $client] = $this->context();
        Equipment::factory()->inStructure($client)->create([
            'numero_cliente' => 'SAM-000123',
            'numero_interno' => 'SEND-000123',
        ])->delete();

        $this->actingAs($admin)
            ->post(route('equipments.store'), $this->payload($client))
            ->assertSessionHasErrors(['numero_cliente', 'numero_interno']);
    }

    public function test_update_accepts_own_item_preserves_prefix_and_persists_changed_fields(): void
    {
        [$organization, $admin, $client] = $this->context();
        $equipment = Equipment::factory()->inStructure($client)->create(['maintenance_item_code' => '000123', 'defect_code_prefix' => 'ANTIGO']);
        $this->actingAs($admin)->put(route('equipments.update', $equipment), $this->payload($client, ['area_code' => 'U04', 'area_name' => 'Nova área', 'maintenance_plan_code' => '000099', 'defect_code_prefix' => 'ANTIGO']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('equipments', ['id' => $equipment->id, 'maintenance_plan_code' => '000099', 'area_code' => 'U04', 'area_name' => 'Nova área', 'subarea_code' => '08', 'task_list_group_counter' => 'T5', 'defect_code_prefix' => 'ANTIGO']);
    }

    public function test_non_administrator_cannot_edit_an_equipment_even_with_confirmation(): void
    {
        [$organization, $admin, $client] = $this->context();
        $equipment = Equipment::factory()->inStructure($client)->create();
        $member = User::factory()->for($organization)->create(['account_type' => UserAccountType::Member->value]);

        $this->actingAs($member)->get(route('equipments.edit', $equipment))->assertForbidden();
        $this->put(route('equipments.update', $equipment), $this->payload($client, [
            'confirm_related_records_edit' => true,
        ]))->assertForbidden();
    }

    public function test_update_rejects_an_item_belonging_to_another_equipment(): void
    {
        [$organization, $admin, $client] = $this->context();
        Equipment::factory()->inStructure($client)->create(['maintenance_item_code' => '000123']);
        $equipment = Equipment::factory()->inStructure($client)->create();
        $this->actingAs($admin)->put(route('equipments.update', $equipment), $this->payload($client))->assertSessionHasErrors('maintenance_item_code');
    }

    public function test_legacy_equipment_requires_an_item_when_edited_but_remains_unchanged_on_rejection(): void
    {
        [$organization, $admin, $client] = $this->context();
        $equipment = Equipment::factory()->inStructure($client)->create(['maintenance_item_code' => null]);
        $this->actingAs($admin)->put(route('equipments.update', $equipment), $this->payload($client, ['maintenance_item_code' => '']))->assertSessionHasErrors('maintenance_item_code');
        $this->assertNull($equipment->refresh()->maintenance_item_code);
        $this->post(route('equipments.store'), $this->payload($client, ['maintenance_item_code' => '']))->assertSessionHasErrors('maintenance_item_code');
    }

    public function test_optional_empty_values_are_null_and_an_explicit_prefix_is_respected(): void
    {
        [$organization, $admin, $client] = $this->context();
        $blankFields = array_fill_keys(['maintenance_plan_code', 'area_code', 'area_name', 'subarea_code', 'subarea_name', 'task_list_group', 'task_list_group_counter', 'abc_code'], '');
        $this->actingAs($admin)->post(route('equipments.store'), $this->payload($client, $blankFields + ['defect_code_prefix' => ' manual ']))->assertSessionHasNoErrors();
        $equipment = Equipment::sole();
        foreach (array_keys($blankFields) as $field) {
            $this->assertNull($equipment->$field);
        }
        $this->assertSame('MANUAL', $equipment->defect_code_prefix);
    }

    public function test_equipment_rejects_an_unknown_abc_code(): void
    {
        [, $admin, $client] = $this->context();

        $this->actingAs($admin)
            ->post(route('equipments.store'), $this->payload($client, ['abc_code' => 'E']))
            ->assertSessionHasErrors('abc_code');

        $this->assertDatabaseCount('equipments', 0);
    }

    public function test_new_inspections_snapshot_the_new_fields_without_changing_existing_snapshots(): void
    {
        [$organization, $admin, $client] = $this->context();
        app(TenantContext::class)->set($organization);
        $equipment = app(CreateEquipment::class)->handle($admin, $this->payload($client));
        $historical = Inspection::factory()->forEquipment($equipment)->create(['context_snapshot' => ['equipment' => ['tag' => 'ANTIGO']]]);
        $snapshotBefore = $historical->context_snapshot;
        // A terminal historical inspection does not prevent creating a new one.
        $historical->update(['status' => 'canceled']);
        $inspection = app(CreateInspection::class)->handle($admin, $equipment, [
            'inspection_type' => InspectionType::Initial->value,
            'planned_start_on' => '2026-09-24',
            'planned_end_on' => '2026-09-24',
        ]);
        foreach (['numero_cliente', 'numero_interno', 'maintenance_plan_code', 'maintenance_item_code', 'area_code', 'area_name', 'subarea_code', 'subarea_name', 'task_list_group', 'task_list_group_counter'] as $field) {
            $this->assertSame($equipment->$field, $inspection->context_snapshot['equipment'][$field]);
        }
        $this->assertSame(['organization', 'client', 'equipment'], array_keys($inspection->context_snapshot));
        $equipment->update(['maintenance_item_code' => 'NOVO', 'subarea_code' => '09']);
        $this->assertSame('000123', $inspection->refresh()->context_snapshot['equipment']['maintenance_item_code']);
        $this->assertSame($snapshotBefore, $historical->refresh()->context_snapshot);
    }

    public function test_database_enforces_item_uniqueness_even_for_deleted_equipment(): void
    {
        [$organization, $admin, $client] = $this->context();
        Equipment::factory()->inStructure($client)->create(['maintenance_item_code' => '000123'])->delete();
        $this->expectException(QueryException::class);
        Equipment::factory()->inStructure($client)->create(['maintenance_item_code' => '000123']);
    }

    public function test_actions_require_an_item_without_http_validation(): void
    {
        [$organization, $admin, $client] = $this->context();
        app(TenantContext::class)->set($organization);
        $this->expectException(ValidationException::class);
        app(CreateEquipment::class)->handle($admin, $this->payload($client, ['maintenance_item_code' => '']));
    }

    public function test_equipment_with_a_defect_requires_confirmation_and_can_be_fully_edited(): void
    {
        [$organization, $admin, $client] = $this->context();
        $equipment = Equipment::factory()->inStructure($client)->create(['maintenance_item_code' => '000123', 'defect_code_prefix' => 'ANTIGO']);
        $defect = Defect::factory()->forEquipment($equipment)->create();
        $defectCode = $defect->code;

        $this->actingAs($admin)->get(route('equipments.edit', $equipment))->assertInertia(fn (Assert $page) => $page
            ->where('related_records.inspections_count', 1)
            ->where('related_records.defects_count', 1)
            ->where('related_records.requires_confirmation', true));
        $this->put(route('equipments.update', $equipment), $this->payload($client, ['maintenance_item_code' => '000124', 'defect_code_prefix' => 'NOVO']))
            ->assertSessionHasErrors('confirm_related_records_edit');

        $this->put(route('equipments.update', $equipment), $this->payload($client, [
            'maintenance_item_code' => '000124',
            'defect_code_prefix' => 'NOVO',
            'confirm_related_records_edit' => true,
        ]))->assertSessionHasNoErrors();

        $this->assertSame('NOVO', $equipment->refresh()->defect_code_prefix);
        $this->assertSame('000124', $equipment->maintenance_item_code);
        $this->assertSame($defectCode, $defect->refresh()->code);
    }

    public function test_prefix_can_change_before_the_first_defect(): void
    {
        [$organization, $admin, $client] = $this->context();
        $equipment = Equipment::factory()->inStructure($client)->create(['maintenance_item_code' => '000123', 'defect_code_prefix' => 'ANTIGO']);

        $this->actingAs($admin)->put(route('equipments.update', $equipment), $this->payload($client, ['defect_code_prefix' => 'NOVO']))->assertSessionHasNoErrors();

        $this->assertSame('NOVO', $equipment->refresh()->defect_code_prefix);
    }

    public function test_equipment_with_an_inspection_requires_confirmation_and_preserves_its_snapshot(): void
    {
        [$organization, $admin, $client] = $this->context();
        $equipment = Equipment::factory()->inStructure($client)->create();
        $inspection = Inspection::factory()->forEquipment($equipment)->create();
        $snapshotBefore = $inspection->context_snapshot;

        $this->actingAs($admin)->get(route('equipments.edit', $equipment))->assertInertia(fn (Assert $page) => $page
            ->where('related_records.inspections_count', 1)
            ->where('related_records.defects_count', 0)
            ->where('related_records.requires_confirmation', true));
        $this->put(route('equipments.update', $equipment), $this->payload($client))
            ->assertSessionHasErrors('confirm_related_records_edit');

        $this->put(route('equipments.update', $equipment), $this->payload($client, [
            'maintenance_item_code' => '000124',
            'tag' => 'ATUALIZADO',
            'confirm_related_records_edit' => true,
        ]))->assertSessionHasNoErrors();
        $this->assertSame('000124', $equipment->refresh()->maintenance_item_code);
        $this->assertSame('ATUALIZADO', $equipment->tag);
        $this->assertSame($snapshotBefore, $inspection->refresh()->context_snapshot);

        app(TenantContext::class)->set($organization);
        try {
            app(UpdateEquipment::class)->handle($admin, $equipment, $this->payload($client));
            $this->fail('A atualização direta deveria exigir confirmação para equipamento com vínculos.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('confirm_related_records_edit', $exception->errors());
        }

        app(UpdateEquipment::class)->handle($admin, $equipment, $this->payload($client, [
            'maintenance_item_code' => '000125',
            'confirm_related_records_edit' => true,
        ]));
        $this->assertSame('000125', $equipment->refresh()->maintenance_item_code);
    }

    public function test_explicit_prefix_conflict_is_reported_without_a_database_error(): void
    {
        [$organization, $admin, $client] = $this->context();
        Equipment::factory()->inStructure($client)->create(['defect_code_prefix' => '000123']);
        $this->actingAs($admin)->post(route('equipments.store'), $this->payload($client, ['defect_code_prefix' => '000123']))->assertSessionHasErrors('defect_code_prefix');
    }

    public function test_latest_migration_preserves_legacy_equipment_links_prefix_and_snapshot_without_backfill(): void
    {
        [$organization, $admin, $client] = $this->context();
        $equipment = Equipment::factory()->inStructure($client)->create(['maintenance_item_code' => null, 'defect_code_prefix' => 'LEGADO']);
        $inspection = Inspection::factory()->forEquipment($equipment)->create(['context_snapshot' => ['equipment' => ['tag' => $equipment->tag]]]);
        $snapshot = $inspection->context_snapshot;
        $migration = require database_path('migrations/2026_09_16_000042_add_maintenance_fields_to_equipments.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('equipments', 'maintenance_item_code'));
        $migration->up();
        foreach (['maintenance_plan_code', 'maintenance_item_code', 'area_code', 'area_name', 'subarea_code', 'subarea_name', 'task_list_group', 'task_list_group_counter'] as $field) {
            $this->assertNull($equipment->refresh()->$field);
        }
        $this->assertSame('LEGADO', $equipment->defect_code_prefix);
        $this->assertSame($equipment->id, $inspection->refresh()->equipment_id);
        $this->assertSame($snapshot, $inspection->context_snapshot);
        Equipment::factory()->inStructure($client)->create(['tag' => $equipment->tag, 'normalized_tag' => $equipment->normalized_tag]);
        $this->assertSame(2, Equipment::where('normalized_tag', $equipment->normalized_tag)->count());
    }

    public function test_update_action_checks_uniqueness_without_http_validation(): void
    {
        [$organization, $admin, $client] = $this->context();
        app(TenantContext::class)->set($organization);
        Equipment::factory()->inStructure($client)->create(['maintenance_item_code' => '000123']);
        $equipment = Equipment::factory()->inStructure($client)->create();
        $this->expectException(ValidationException::class);
        app(UpdateEquipment::class)->handle($admin, $equipment, $this->payload($client));
    }

    public function test_removed_structure_routes_are_not_registered(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            $this->assertDoesNotMatchRegularExpression('/^(units\.|areas\.|subareas\.|clients\.units\.)/', (string) $route->getName());
        }
    }

    public function test_inspection_search_uses_the_item_without_the_removed_unit_relationship(): void
    {
        [$organization, $admin, $client] = $this->context();
        $equipment = Equipment::factory()->inStructure($client)->create(['maintenance_item_code' => '000123']);
        Inspection::factory()->forEquipment($equipment)->create();
        $this->actingAs($admin)->get(route('inspections.index', ['search' => '000123']))->assertInertia(fn (Assert $page) => $page
            ->has('inspections.data', 1)
            ->missing('filters.client')
            ->missing('options.clients')
            ->missing('inspections.data.0.equipment.client')
            ->missing('filters.unit')
            ->missing('options.units'));
    }
}
