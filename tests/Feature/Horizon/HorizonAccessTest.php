<?php

declare(strict_types=1);

namespace Tests\Feature\Horizon;

use App\Enums\UserAccountType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HorizonAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/horizon')->assertRedirect(route('login'));
    }

    public function test_company_user_cannot_access_horizon(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin,
        ]);

        $this->actingAs($user)->get('/horizon')->assertForbidden();
    }

    public function test_active_super_admin_with_changed_password_can_access_horizon(): void
    {
        $user = User::factory()->superAdmin()->create([
            'must_change_password' => false,
        ]);

        $this->actingAs($user)->get('/horizon')->assertOk();
    }

    public function test_super_admin_must_change_temporary_password_before_accessing_horizon(): void
    {
        $user = User::factory()->superAdmin()->create([
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->get('/horizon')
            ->assertRedirect(route('account.password.edit'));
    }
}
