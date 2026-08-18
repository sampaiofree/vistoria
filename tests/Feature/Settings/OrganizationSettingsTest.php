<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Enums\UserAccountType;
use App\Enums\UserStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
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
        ]);

        $response->assertRedirect(route('settings.users.index'))
            ->assertSessionHas('temporary_credentials', fn (array $credentials): bool => $credentials['email'] === 'novo@empresa.test'
                && strlen($credentials['password']) === 16);

        $user = User::query()->where('email', 'novo@empresa.test')->firstOrFail();
        $this->assertTrue($user->must_change_password);
        $this->assertSame(UserStatus::Active, $user->status);
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
}
