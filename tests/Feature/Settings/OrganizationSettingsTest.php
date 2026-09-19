<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Enums\OperationalRole;
use App\Enums\UserAccountType;
use App\Enums\UserStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class OrganizationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_company_admin_can_access_company_settings(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->for($organization)->create(['account_type' => UserAccountType::Member]);

        $this->actingAs($member)->get(route('settings.company.edit'))->assertForbidden();
    }

    public function test_company_admin_can_update_company_data(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);

        $this->actingAs($admin)->put(route('settings.company.update'), [
            'name' => 'Empresa Atualizada',
            'legal_name' => 'Empresa Atualizada LTDA',
            'document' => '04.252.011/0001-10',
            'primary_color' => ' #1a2b3c ',
        ])->assertRedirect(route('settings.company.edit'));

        $this->assertSame('Empresa Atualizada', $organization->refresh()->name);
        $this->assertSame('04252011000110', $organization->document);
        $this->assertSame('#1A2B3C', $organization->primary_color);
    }

    public function test_company_admin_can_upload_and_replace_sidebar_icon(): void
    {
        Storage::fake('public');
        $organization = Organization::factory()->create(['icon_path' => 'organizations/old/icon.png']);
        Storage::disk('public')->put($organization->icon_path, 'old-icon');
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);

        $this->actingAs($admin)->post(route('settings.company.update'), [
            '_method' => 'put',
            'name' => $organization->name,
            'legal_name' => $organization->legal_name,
            'document' => $organization->document,
            'primary_color' => '#0F172A',
            'icon' => UploadedFile::fake()->image('icone.webp', 128, 128)->size(200),
        ])->assertRedirect(route('settings.company.edit'));

        $organization->refresh();
        $this->assertNotNull($organization->icon_path);
        Storage::disk('public')->assertExists($organization->icon_path);
        Storage::disk('public')->assertMissing('organizations/old/icon.png');
    }

    public function test_company_admin_can_remove_icon_without_removing_logo(): void
    {
        Storage::fake('public');
        $organization = Organization::factory()->create([
            'logo_path' => 'organizations/company/branding/logo.png',
            'icon_path' => 'organizations/company/branding/icon.png',
        ]);
        Storage::disk('public')->put($organization->logo_path, 'logo');
        Storage::disk('public')->put($organization->icon_path, 'icon');
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);

        $this->actingAs($admin)
            ->delete(route('settings.company.icon.destroy'))
            ->assertRedirect(route('settings.company.edit'));

        $organization->refresh();
        $this->assertNull($organization->icon_path);
        $this->assertSame('organizations/company/branding/logo.png', $organization->logo_path);
        Storage::disk('public')->assertMissing('organizations/company/branding/icon.png');
        Storage::disk('public')->assertExists('organizations/company/branding/logo.png');
    }

    public function test_company_branding_validation_rejects_invalid_color_and_icon(): void
    {
        Storage::fake('public');
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);

        $this->actingAs($admin)->post(route('settings.company.update'), [
            '_method' => 'put',
            'name' => $organization->name,
            'primary_color' => '#FFF',
            'icon' => UploadedFile::fake()->create('icone.svg', 20, 'image/svg+xml'),
        ])->assertSessionHasErrors(['primary_color', 'icon']);

        $this->assertSame('#0F172A', $organization->fresh()->primary_color);
        $this->assertNull($organization->icon_path);
    }

    public function test_company_branding_is_shared_with_the_sidebar(): void
    {
        $organization = Organization::factory()->create([
            'primary_color' => '#123456',
            'icon_path' => 'organizations/company/branding/icon.png',
        ]);
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);

        $this->actingAs($admin)
            ->get(route('settings.company.edit'))
            ->assertInertia(fn ($page) => $page
                ->where('organization.primary_color', '#123456')
                ->where('organization.icon_url', asset('storage/organizations/company/branding/icon.png'))
                ->where('auth.user.organization.primary_color', '#123456')
                ->where('auth.user.organization.icon_url', asset('storage/organizations/company/branding/icon.png')));
    }

    public function test_company_admin_receives_a_one_time_temporary_password_for_new_users(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);

        $response = $this->actingAs($admin)->post(route('settings.users.store'), [
            'name' => 'Novo Membro',
            'email' => 'novo@empresa.test',
            'account_type' => UserAccountType::Member->value,
            'operational_role' => OperationalRole::Inspector->value,
        ]);

        $response->assertRedirect(route('settings.users.index'))
            ->assertSessionHas('temporary_credentials', fn (array $credentials): bool => $credentials['email'] === 'novo@empresa.test'
                && strlen($credentials['password']) === 16);

        $user = User::query()->where('email', 'novo@empresa.test')->firstOrFail();
        $this->assertTrue($user->must_change_password);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertSame(OperationalRole::Inspector, $user->operational_role);
    }

    public function test_reset_password_returns_temporary_credentials_to_the_user_edit_page(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $user = User::factory()->for($organization)->create([
            'password' => 'SenhaAnterior!1',
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('settings.users.edit', $user))
            ->post(route('settings.users.reset-password', $user));

        $response->assertRedirect(route('settings.users.edit', $user))
            ->assertSessionHas('temporary_credentials', fn (array $credentials): bool => $credentials['email'] === $user->email
                && strlen($credentials['password']) === 16);

        $temporaryPassword = $response->getSession()->get('temporary_credentials.password');
        $user->refresh();

        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check($temporaryPassword, $user->password));
        $this->assertNotSame($temporaryPassword, $user->password);
    }

    public function test_company_admin_must_assign_a_valid_operational_role_when_creating_a_user(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $data = [
            'name' => 'Novo Membro',
            'email' => 'novo@empresa.test',
            'account_type' => UserAccountType::Member->value,
        ];

        $this->actingAs($admin)->post(route('settings.users.store'), $data)
            ->assertSessionHasErrors('operational_role');

        $this->post(route('settings.users.store'), [...$data, 'operational_role' => 'invalid'])
            ->assertSessionHasErrors('operational_role');
    }

    public function test_company_admin_can_assign_their_own_operational_role_without_changing_access_type(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin,
            'operational_role' => null,
        ]);

        $this->actingAs($admin)->put(route('settings.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'account_type' => UserAccountType::CompanyAdmin->value,
            'operational_role' => OperationalRole::Releaser->value,
        ])->assertRedirect(route('settings.users.edit', $admin));

        $admin->refresh();
        $this->assertSame(UserAccountType::CompanyAdmin, $admin->account_type);
        $this->assertSame(OperationalRole::Releaser, $admin->operational_role);
    }

    public function test_company_admin_can_change_another_users_operational_role_only_within_their_organization(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create(['account_type' => UserAccountType::CompanyAdmin]);
        $member = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Planner]);
        $otherMember = User::factory()->for($otherOrganization)->create(['operational_role' => OperationalRole::Planner]);

        $this->actingAs($admin)->put(route('settings.users.update', $member), [
            'name' => $member->name,
            'email' => $member->email,
            'account_type' => UserAccountType::Member->value,
            'operational_role' => OperationalRole::Reviewer->value,
        ])->assertRedirect(route('settings.users.edit', $member));

        $this->assertSame(OperationalRole::Reviewer, $member->refresh()->operational_role);

        $this->put(route('settings.users.update', $otherMember), [
            'name' => $otherMember->name,
            'email' => $otherMember->email,
            'account_type' => UserAccountType::Member->value,
            'operational_role' => OperationalRole::Reviewer->value,
        ])->assertNotFound();
    }

    public function test_legacy_user_without_an_operational_role_is_identified_in_user_pages(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'name' => 'Administrador',
            'account_type' => UserAccountType::CompanyAdmin,
        ]);
        $legacyUser = User::factory()->for($organization)->create([
            'name' => 'A Usuário legado',
            'operational_role' => null,
        ]);

        $this->actingAs($admin)->get(route('settings.users.edit', $legacyUser))
            ->assertInertia(fn ($page) => $page
                ->where('user.operational_role', null)
                ->where('user.operational_role_label', 'Papel não definido')
                ->has('operational_role_options', 4));

        $this->get(route('settings.users.index'))
            ->assertInertia(fn ($page) => $page
                ->where('users.data.0.operational_role_label', 'Papel não definido')
                ->where('status_options', [
                    ['value' => UserStatus::Active->value, 'label' => 'Ativo'],
                    ['value' => UserStatus::Inactive->value, 'label' => 'Inativo'],
                ])
                ->has('operational_role_options', 4));
    }

    public function test_user_list_filters_by_operational_role(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin,
            'operational_role' => OperationalRole::Releaser,
        ]);
        $inspector = User::factory()->for($organization)->create([
            'name' => 'Inspetor',
            'operational_role' => OperationalRole::Inspector,
        ]);
        User::factory()->for($organization)->create([
            'name' => 'Revisor',
            'operational_role' => OperationalRole::Reviewer,
        ]);

        $this->actingAs($admin)
            ->get(route('settings.users.index', ['operational_role' => OperationalRole::Inspector->value]))
            ->assertInertia(fn ($page) => $page
                ->where('filters.operational_role', OperationalRole::Inspector->value)
                ->has('users.data', 1)
                ->where('users.data.0.public_id', $inspector->public_id)
                ->where('users.data.0.operational_role_label', 'Inspetor'));
    }

    public function test_user_status_uses_only_active_and_inactive_states(): void
    {
        $this->assertSame([
            UserStatus::Active,
            UserStatus::Inactive,
        ], UserStatus::cases());
        $this->assertFalse(Schema::hasColumn('users', 'suspended_at'));
        $this->assertFalse(Schema::hasColumn('users', 'suspension_reason'));
    }

    public function test_user_must_change_temporary_password_before_using_the_application(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->for($organization)->create([
            'must_change_password' => true,
            'password' => 'temporary-password',
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('account.password.edit'));

        $this->actingAs($user)->put(route('account.password.update'), [
            'current_password' => 'temporary-password',
            'password' => 'New-secure-password-123',
            'password_confirmation' => 'New-secure-password-123',
        ])->assertRedirect(route('dashboard'));

        $this->assertFalse($user->refresh()->must_change_password);
        $this->assertTrue(Hash::check('New-secure-password-123', $user->password));
    }

    public function test_new_password_requires_a_special_character(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->for($organization)->create([
            'password' => 'temporary-password',
        ]);

        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => 'temporary-password',
                'password' => 'NewSecurePassword123',
                'password_confirmation' => 'NewSecurePassword123',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('temporary-password', $user->refresh()->password));
    }

    public function test_new_password_with_six_characters_is_accepted(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->for($organization)->create([
            'password' => 'temporary-password',
        ]);

        $this->actingAs($user)
            ->put(route('account.password.update'), [
                'current_password' => 'temporary-password',
                'password' => 'Aa1@bb',
                'password_confirmation' => 'Aa1@bb',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertFalse($user->refresh()->must_change_password);
        $this->assertTrue(Hash::check('Aa1@bb', $user->password));
    }
}
