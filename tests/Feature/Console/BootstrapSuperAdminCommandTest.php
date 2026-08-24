<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\UserAccountType;
use App\Enums\UserStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class BootstrapSuperAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_production_super_admin_with_a_temporary_password(): void
    {
        $this->artisan('app:bootstrap-super-admin')
            ->expectsOutputToContain('Superadministrador sampaio.free@gmail.com criado com sucesso.')
            ->expectsOutputToContain('Senha temporária:')
            ->assertSuccessful();

        $user = User::query()->where('email', 'sampaio.free@gmail.com')->firstOrFail();

        $this->assertSame('Administrador Master', $user->name);
        $this->assertNull($user->organization_id);
        $this->assertSame(UserAccountType::SuperAdmin, $user->account_type);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertTrue($user->must_change_password);
        $this->assertNotSame('password', $user->password);
        $this->assertFalse(Hash::check('password', $user->password));
    }

    public function test_it_does_not_change_an_existing_compatible_super_admin(): void
    {
        $user = User::factory()->superAdmin()->create([
            'email' => 'sampaio.free@gmail.com',
            'name' => 'Nome já existente',
            'password' => 'Existing-secure-password-123',
            'must_change_password' => false,
        ]);

        $this->artisan('app:bootstrap-super-admin')
            ->expectsOutputToContain('já está configurado')
            ->assertSuccessful();

        $user->refresh();

        $this->assertSame('Nome já existente', $user->name);
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('Existing-secure-password-123', $user->password));
    }

    public function test_it_rejects_an_existing_account_that_is_not_a_super_admin(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->for($organization)->create([
            'email' => 'sampaio.free@gmail.com',
            'account_type' => UserAccountType::CompanyAdmin,
            'password' => 'Existing-secure-password-123',
        ]);

        $this->artisan('app:bootstrap-super-admin')
            ->expectsOutputToContain('já pertence a uma conta incompatível')
            ->assertExitCode(1);

        $this->assertSame(UserAccountType::CompanyAdmin, $user->refresh()->account_type);
        $this->assertSame($organization->getKey(), $user->organization_id);
        $this->assertTrue(Hash::check('Existing-secure-password-123', $user->password));
    }
}
