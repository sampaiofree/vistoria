<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Enums\OperationalRole;
use App\Enums\UserAccountType;
use App\Enums\UserStatus;
use App\Models\Client;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ClientUserAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_and_manages_client_accounts_from_the_existing_user_pages(): void
    {
        $organization = Organization::factory()->create();
        $client = Client::factory()->for($organization)->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);

        $this->actingAs($admin)->get(route('clients.show', $client))
            ->assertInertia(fn ($page) => $page->where('users_url', route('settings.users.index', ['account_type' => 'client'])));
        $this->get(route('settings.users.create', ['account_type' => 'client']))
            ->assertInertia(fn ($page) => $page
                ->where('initial_account_type', 'client')
                ->where('cancel_url', route('settings.users.index', ['account_type' => 'client'])));

        $response = $this->post(route('settings.users.store'), [
            'name' => 'Pessoa da Samarco',
            'email' => 'pessoa@samarco.test',
            'account_type' => 'client',
            'operational_role' => '',
        ]);

        $response->assertRedirect(route('settings.users.index', ['account_type' => 'client']))
            ->assertSessionHas('temporary_credentials', fn (array $credentials): bool => $credentials['email'] === 'pessoa@samarco.test' && strlen($credentials['password']) === 16);

        $user = User::query()->where('email', 'pessoa@samarco.test')->firstOrFail();
        $this->assertSame($organization->id, $user->organization_id);
        $this->assertSame(UserAccountType::Client, $user->account_type);
        $this->assertNull($user->operational_role);
        $this->assertTrue($user->must_change_password);

        $this->get(route('settings.users.index', ['account_type' => 'client']))
            ->assertInertia(fn ($page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.public_id', $user->public_id)
                ->where('users.data.0.account_type_label', 'Cliente')
                ->where('users.data.0.operational_role_label', '—')
                ->where('create_url', route('settings.users.create', ['account_type' => 'client'])));

        $this->put(route('settings.users.update', $user), [
            'name' => 'Contato da Samarco',
            'email' => 'contato@samarco.test',
            'account_type' => 'client',
        ])->assertRedirect(route('settings.users.edit', $user));
        $this->assertSame('Contato da Samarco', $user->refresh()->name);
        $this->assertNull($user->operational_role);

        $this->from(route('settings.users.edit', $user))
            ->post(route('settings.users.reset-password', $user))
            ->assertSessionHas('temporary_credentials', fn (array $credentials): bool => $credentials['email'] === 'contato@samarco.test' && Hash::check($credentials['password'], $user->refresh()->password));

        $this->patch(route('settings.users.status', $user), ['status' => 'inactive'])->assertRedirect();
        $this->assertSame(UserStatus::Inactive, $user->refresh()->status);
    }

    public function test_client_role_is_rejected_and_internal_users_still_require_one(): void
    {
        $organization = Organization::factory()->create();
        Client::factory()->for($organization)->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $this->actingAs($admin);

        $this->post(route('settings.users.store'), [
            'name' => 'Contato', 'email' => 'contato@cliente.test', 'account_type' => 'client',
            'operational_role' => OperationalRole::Inspector->value,
        ])->assertSessionHasErrors('operational_role');

        $this->post(route('settings.users.store'), [
            'name' => 'Interno', 'email' => 'interno@empresa.test', 'account_type' => 'member',
        ])->assertSessionHasErrors('operational_role');

        $member = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);
        $this->put(route('settings.users.update', $member), [
            'name' => $member->name, 'email' => $member->email, 'account_type' => 'client',
            'operational_role' => OperationalRole::Inspector->value,
        ])->assertSessionHasErrors('operational_role');
        $this->assertSame(UserAccountType::Member, $member->refresh()->account_type);
    }

    public function test_client_account_requires_the_organizations_client(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        Client::factory()->for($otherOrganization)->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);

        $this->actingAs($admin)->post(route('settings.users.store'), [
            'name' => 'Contato', 'email' => 'contato@cliente.test', 'account_type' => 'client',
        ])->assertSessionHasErrors('account_type');
        $this->assertDatabaseMissing('users', ['email' => 'contato@cliente.test']);
    }

    public function test_last_active_admin_cannot_be_converted_to_client(): void
    {
        $organization = Organization::factory()->create();
        Client::factory()->for($organization)->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $otherAdmin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);

        $data = ['name' => $otherAdmin->name, 'email' => $otherAdmin->email, 'account_type' => 'client'];
        $this->actingAs($admin)->put(route('settings.users.update', $otherAdmin), $data)->assertRedirect();
        $this->assertSame(UserAccountType::Client, $otherAdmin->refresh()->account_type);
        $this->assertNull($otherAdmin->operational_role);

        $this->put(route('settings.users.update', $admin), [
            'name' => $admin->name, 'email' => $admin->email, 'account_type' => 'client',
        ])->assertSessionHasErrors('account_type');
        $this->assertSame(UserAccountType::CompanyAdmin, $admin->refresh()->account_type);
    }

    public function test_user_assigned_to_open_inspection_cannot_be_converted_to_client(): void
    {
        $organization = Organization::factory()->create();
        Client::factory()->for($organization)->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $inspector = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);
        $inspection = Inspection::factory()->create(['organization_id' => $organization->id]);
        InspectionResponsible::factory()->forInspection($inspection, $inspector)->create();

        $this->actingAs($admin)->put(route('settings.users.update', $inspector), [
            'name' => $inspector->name, 'email' => $inspector->email, 'account_type' => 'client',
        ])->assertSessionHasErrors('account_type');
        $this->assertSame(UserAccountType::Member, $inspector->refresh()->account_type);
    }

    public function test_client_login_opens_inspections_and_internal_routes_remain_closed(): void
    {
        $organization = Organization::factory()->create();
        $client = Client::factory()->for($organization)->create();
        $user = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::Client,
            'operational_role' => null,
            'password' => 'SenhaDeTeste!123',
        ]);

        $this->from(route('login'))->post(route('login'), [
            'email' => $user->email, 'password' => 'SenhaDeTeste!123',
        ])->assertRedirect(route('inspections.index'));
        $this->assertAuthenticatedAs($user);

        $this->get(route('dashboard'))->assertRedirect(route('inspections.index'));
        $this->get(route('inspections.index'))->assertOk();
        $this->get(route('equipments.index'))->assertForbidden();
        $this->get(route('settings.users.index'))->assertForbidden();
        $this->post(route('inspections.store'), [])->assertForbidden();
    }

    public function test_client_temporary_password_must_be_changed_before_inspection_access(): void
    {
        $organization = Organization::factory()->create();
        Client::factory()->for($organization)->create();
        $user = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::Client,
            'operational_role' => null,
            'password' => 'SenhaDeTeste!123',
            'must_change_password' => true,
        ]);

        $this->post(route('login'), [
            'email' => $user->email, 'password' => 'SenhaDeTeste!123',
        ])->assertRedirect(route('account.password.edit'));
        $this->get(route('inspections.index'))->assertRedirect(route('account.password.edit'));
        $this->put(route('account.password.update'), [
            'current_password' => 'SenhaDeTeste!123',
            'password' => 'NovaSenhaDeTeste!456',
            'password_confirmation' => 'NovaSenhaDeTeste!456',
        ])->assertRedirect(route('inspections.index'))->assertSessionHasNoErrors();
        $this->get(route('inspections.index'))->assertOk();
    }
}
