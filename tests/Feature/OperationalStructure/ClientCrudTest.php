<?php

namespace Tests\Feature\OperationalStructure;

use App\Enums\UserAccountType;
use App\Models\Client;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

final class ClientCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_index_exposes_creation_only_when_the_organization_has_no_client(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin->value]);

        $this->actingAs($admin)
            ->get(route('clients.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.create', true)
                ->where('create_url', route('clients.create')));

        $this->get(route('clients.create'))
            ->assertInertia(fn (Assert $page) => $page->component('Clients/Create'));

        Client::factory()->for($organization)->create();

        $this->get(route('clients.index'))
            ->assertInertia(fn (Assert $page) => $page->where('can.create', false));

        $this->get(route('clients.create'))->assertForbidden();
    }

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

    public function test_admin_cannot_create_a_second_client_in_the_same_organization(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        Client::factory()->for($organization)->create();

        $this->actingAs($admin)
            ->post(route('clients.store'), ['name' => 'Segundo cliente'])
            ->assertForbidden();

        $this->assertDatabaseCount('clients', 1);
    }

    public function test_each_organization_can_have_its_own_single_client(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();
        $adminA = User::factory()->for($organizationA)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $adminB = User::factory()->for($organizationB)->create(['account_type' => UserAccountType::CompanyAdmin]);

        $this->actingAs($adminA)->post(route('clients.store'), ['name' => 'Cliente A'])->assertRedirect();
        $this->actingAs($adminB)->post(route('clients.store'), ['name' => 'Cliente B'])->assertRedirect();

        $this->assertDatabaseHas('clients', ['organization_id' => $organizationA->id, 'name' => 'Cliente A']);
        $this->assertDatabaseHas('clients', ['organization_id' => $organizationB->id, 'name' => 'Cliente B']);
    }

    public function test_unique_client_migration_refuses_multiple_legacy_records_without_changing_data(): void
    {
        $migration = require database_path('migrations/2026_09_16_000043_limit_clients_to_one_per_organization.php');
        $migration->down();
        $organization = Organization::factory()->create();
        Client::factory()->for($organization)->create();
        DB::table('clients')->insert([
            'organization_id' => $organization->id,
            'public_id' => (string) Str::ulid(),
            'name' => 'Cliente legado adicional',
            'status' => 'active',
        ]);

        try {
            $migration->up();
            $this->fail('A migração deveria bloquear múltiplos clientes legados.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString((string) $organization->id, $exception->getMessage());
        }

        $this->assertDatabaseCount('clients', 2);

        DB::table('clients')->where('name', 'Cliente legado adicional')->delete();
        $migration->up();
    }

    public function test_member_cannot_access_client_pages(): void
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

        $this->actingAs($member)->get(route('clients.index'))->assertForbidden();
        $this->get(route('clients.show', $clientWithLogo))->assertForbidden();
    }

    public function test_client_navigation_is_nested_in_administrator_settings_only(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $member = User::factory()->for($organization)->create();

        $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('navigation.3.label', 'Configurações')
            ->where('navigation.3.children.2.label', 'Cliente')
            ->where('navigation.3.children.2.href', route('clients.index'))
            ->missing('navigation.3.children.3'));

        $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->has('navigation', 2));
    }

    public function test_client_show_page_includes_client_logo_url(): void
    {
        Storage::fake('public');

        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $client = Client::factory()->for($organization)->create([
            'logo_path' => 'organizations/'.$organization->id.'/clients/logo.png',
        ]);

        $this
            ->actingAs($admin)
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
