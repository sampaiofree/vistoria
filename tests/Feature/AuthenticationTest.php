<?php

namespace Tests\Feature;

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
            ->assertDontSee('admin@vistoria.test')
            ->assertDontSee('Conta local');
    }

    public function test_valid_credentials_log_the_user_in(): void
    {
        $this->seed();

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
        $this->seed();

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
