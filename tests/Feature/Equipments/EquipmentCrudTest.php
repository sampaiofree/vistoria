<?php

declare(strict_types=1);

namespace Tests\Feature\Equipments;

use App\Enums\UserAccountType;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class EquipmentCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_equipment_for_the_automatic_active_client(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin->value]);
        $client = Client::factory()->for($organization)->create();

        $this->actingAs($admin)->post(route('equipments.store'), ['numero_cliente' => 'SAM-001', 'numero_interno' => 'SEND-001', 'maintenance_item_code' => '000001', 'tag' => 'EQ-001', 'defect_code_prefix' => 'EQ001', 'name' => 'Bomba'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('equipments', ['client_id' => $client->id, 'tag' => 'EQ-001']);
    }

    public function test_inactive_client_cannot_receive_a_new_equipment(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin->value]);
        $client = Client::factory()->for($organization)->inactive()->create();
        $this->actingAs($admin)->post(route('equipments.store'), ['numero_cliente' => 'SAM-001', 'numero_interno' => 'SEND-001', 'maintenance_item_code' => '000001', 'tag' => 'EQ-001', 'defect_code_prefix' => 'EQ001', 'name' => 'Bomba'])->assertSessionHasErrors('client');
    }

    public function test_equipment_requires_a_configured_client(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin->value]);

        $this->actingAs($admin)->post(route('equipments.store'), [
            'numero_cliente' => 'SAM-001',
            'numero_interno' => 'SEND-001',
            'maintenance_item_code' => '000001',
            'tag' => 'EQ-001',
            'defect_code_prefix' => 'EQ001',
            'name' => 'Bomba',
        ])->assertSessionHasErrors('client');
    }

    public function test_equipment_ignores_a_submitted_client_id_and_uses_the_tenant_client(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin->value]);
        $client = Client::factory()->for($organization)->create();
        $otherClient = Client::factory()->for($otherOrganization)->create();

        $this->actingAs($admin)->post(route('equipments.store'), [
            'client_id' => $otherClient->id,
            'numero_cliente' => 'SAM-001',
            'numero_interno' => 'SEND-001',
            'maintenance_item_code' => '000001',
            'tag' => 'EQ-001',
            'defect_code_prefix' => 'EQ001',
            'name' => 'Bomba',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('equipments', ['client_id' => $client->id, 'tag' => 'EQ-001']);
    }

    public function test_equipment_pages_expose_the_configured_client_and_organization_names_for_identifiers(): void
    {
        $organization = Organization::factory()->create(['name' => 'SEND Engenharia']);
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin->value]);
        $client = Client::factory()->for($organization)->create(['name' => 'Samarco Mineração']);
        $equipment = Equipment::factory()->inStructure($client)->create();

        $this->actingAs($admin)->get(route('equipments.create'))->assertInertia(fn (Assert $page) => $page
            ->where('identifier_context.client_name', 'Samarco Mineração')
            ->where('identifier_context.organization_name', 'SEND Engenharia'));

        foreach (['edit', 'show'] as $page) {
            $this->get(route('equipments.'.$page, $equipment))->assertInertia(fn (Assert $page) => $page
                ->where('identifier_context.client_name', 'Samarco Mineração')
                ->where('identifier_context.organization_name', 'SEND Engenharia'));
        }
    }

    public function test_equipment_create_exposes_no_client_name_when_no_client_is_configured(): void
    {
        $organization = Organization::factory()->create(['name' => 'SEND Engenharia']);
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin->value]);

        $this->actingAs($admin)->get(route('equipments.create'))->assertInertia(fn (Assert $page) => $page
            ->where('identifier_context.client_name', null)
            ->where('identifier_context.organization_name', 'SEND Engenharia'));
    }
}
