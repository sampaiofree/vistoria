<?php

namespace Tests\Feature\OperationalStructure;

use App\Enums\UserAccountType;
use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ClientCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_client_and_document_is_normalized(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();

        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
            ]);

        $response = $this
            ->actingAs($admin)
            ->post(route('clients.store'), [
                'name' => '  Cliente Base  ',
                'legal_name' => 'Cliente Base LTDA',
                'document' => '11.222.333/0001-44',
                'email' => 'contato@cliente.test',
                'phone' => '(11) 99999-9999',
                'notes' => 'Observacao',
                'organization_id' => $otherOrganization->id,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('clients', [
            'organization_id' => $organization->id,
            'name' => 'Cliente Base',
            'document' => '11222333000144',
        ]);

        $client = Client::query()
            ->where('organization_id', $organization->id)
            ->where('document', '11222333000144')
            ->firstOrFail();

        $this->assertSame('Cliente Base', $client->name);
        $this->assertSame('11222333000144', $client->document);
    }

    public function test_member_cannot_create_client(): void
    {
        $organization = Organization::factory()->create();

        $member = User::factory()
            ->for($organization)
            ->create();

        $this->actingAs($member)
            ->post(route('clients.store'), [
                'name' => 'Cliente Restrito',
            ])
            ->assertForbidden();
    }

    public function test_member_can_view_clients_index_as_inertia_page(): void
    {
        Storage::fake('public');

        $organization = Organization::factory()->create();

        $member = User::factory()
            ->for($organization)
            ->create();

        $clientWithLogo = Client::factory()
            ->for($organization)
            ->create([
                'name' => 'Cliente com logo',
                'logo_path' => 'organizations/'.$organization->id.'/clients/logo.png',
            ]);

        Client::factory()->for($organization)->create(['name' => 'Z cliente sem logo']);

        $response = $this
            ->actingAs($member)
            ->get(route('clients.index'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Index')
                ->where('can.create', false)
                ->where('clients.data.0.logo_url', Storage::disk('public')->url($clientWithLogo->logo_path))
                ->where('clients.data.0.can_update', false));
    }

    public function test_client_show_page_includes_client_logo_url(): void
    {
        Storage::fake('public');

        $organization = Organization::factory()->create();
        $member = User::factory()->for($organization)->create();
        $client = Client::factory()->for($organization)->create([
            'logo_path' => 'organizations/'.$organization->id.'/clients/logo.png',
        ]);

        $this
            ->actingAs($member)
            ->get(route('clients.show', $client))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clients/Show')
                ->where('client.logo_url', Storage::disk('public')->url($client->logo_path)));
    }

    public function test_users_cannot_view_client_from_another_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $userA = User::factory()
            ->for($organizationA)
            ->create();

        $clientB = Client::factory()
            ->for($organizationB)
            ->create();

        $this->actingAs($userA)
            ->get(route('clients.show', $clientB))
            ->assertNotFound();
    }

    public function test_cross_tenant_update_is_rejected_before_validation(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();

        $adminA = User::factory()
            ->for($organizationA)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
            ]);

        $clientB = Client::factory()
            ->for($organizationB)
            ->create();

        $this->actingAs($adminA)
            ->put(route('clients.update', $clientB), [
                'name' => '',
                'document' => $clientB->document,
            ])
            ->assertForbidden()
            ->assertSessionDoesntHaveErrors();
    }

    public function test_admin_can_deactivate_client_and_it_remains_viewable(): void
    {
        $organization = Organization::factory()->create();

        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
            ]);

        $client = Client::factory()
            ->for($organization)
            ->create();

        $this->actingAs($admin)
            ->patch(route('clients.status', $client), [
                'status' => 'inactive',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'status' => 'inactive',
        ]);

        $this->actingAs($admin)
            ->get(route('clients.show', $client))
            ->assertOk();
    }

    public function test_admin_can_upload_client_logo_when_updating(): void
    {
        Storage::fake('public');

        $organization = Organization::factory()->create();
        $admin = User::factory()
            ->for($organization)
            ->create([
                'account_type' => UserAccountType::CompanyAdmin->value,
            ]);
        $client = Client::factory()->for($organization)->create();

        $this->actingAs($admin)
            ->put(route('clients.update', $client), [
                'name' => $client->name,
                'logo' => UploadedFile::fake()->image('logo.png', 120, 120),
            ])
            ->assertRedirect();

        $client->refresh();

        $this->assertNotNull($client->logo_path);
        Storage::disk('public')->assertExists($client->logo_path);
    }
}
