<?php

namespace Tests\Feature;

use App\Enums\OrganizationStatus;
use App\Enums\UserAccountType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_visible(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Boas-vindas ao Vistoria')
            ->assertDontSee('Credenciais de demonstração');
    }

    public function test_valid_credentials_log_the_user_in(): void
    {
        $organization = Organization::factory()->create(['status' => OrganizationStatus::Active]);
        User::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'admin@vistoria.test',
            'password' => 'password',
            'account_type' => UserAccountType::CompanyAdmin,
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@vistoria.test',
            'password' => 'password',
        ]);

        $response->assertRedirectToRoute('dashboard');
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'admin@vistoria.test')->firstOrFail();

        $this->assertNotNull($user->last_login_at);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $organization = Organization::factory()->create(['status' => OrganizationStatus::Active]);
        User::factory()->create([
            'organization_id' => $organization->id,
            'email' => 'admin@vistoria.test',
            'password' => 'password',
            'account_type' => UserAccountType::CompanyAdmin,
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'admin@vistoria.test',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard')->assertRedirectToRoute('login');
    }
}
