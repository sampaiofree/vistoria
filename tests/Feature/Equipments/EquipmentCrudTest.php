<?php

declare(strict_types=1);

namespace Tests\Feature\Equipments;

use App\Enums\UserAccountType;
use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EquipmentCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_equipment_for_the_automatic_active_client(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin->value]);
        $client = Client::factory()->for($organization)->create();

        $this->actingAs($admin)->post(route('equipments.store'), ['maintenance_item_code' => '000001', 'tag' => 'EQ-001', 'defect_code_prefix' => 'EQ001', 'name' => 'Bomba'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('equipments', ['client_id' => $client->id, 'tag' => 'EQ-001']);
    }

    public function test_inactive_client_cannot_receive_a_new_equipment(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin->value]);
        $client = Client::factory()->for($organization)->inactive()->create();
        $this->actingAs($admin)->post(route('equipments.store'), ['maintenance_item_code' => '000001', 'tag' => 'EQ-001', 'defect_code_prefix' => 'EQ001', 'name' => 'Bomba'])->assertSessionHasErrors('client');
    }

    public function test_equipment_requires_a_configured_client(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin->value]);

        $this->actingAs($admin)->post(route('equipments.store'), [
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
            'maintenance_item_code' => '000001',
            'tag' => 'EQ-001',
            'defect_code_prefix' => 'EQ001',
            'name' => 'Bomba',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('equipments', ['client_id' => $client->id, 'tag' => 'EQ-001']);
    }
}
