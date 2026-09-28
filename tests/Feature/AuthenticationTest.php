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

    public function test_five_failed_logins_block_the_correct_password_until_the_lockout_expires(): void
    {
        User::factory()->create([
            'email' => 'admin@vistoria.test',
            'password' => 'password',
        ]);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10']);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->from('/login')->post('/login', [
                'email' => $attempt === 5 ? 'ADMIN@VISTORIA.TEST' : 'admin@vistoria.test',
                'password' => 'wrong-password',
            ])->assertRedirect('/login')->assertSessionHasErrors('email');
        }

        $this->assertMatchesRegularExpression('/Aguarde [1-9][0-9]* segundos/', session('errors')->first('email'));

        $this->from('/login')->post('/login', [
            'email' => 'admin@vistoria.test',
            'password' => 'password',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->travel(301)->seconds();

        $this->post('/login', [
            'email' => 'admin@vistoria.test',
            'password' => 'password',
        ])->assertRedirectToRoute('dashboard');
        $this->assertAuthenticated();
    }

    public function test_lockout_lasts_five_minutes_from_the_fifth_failure(): void
    {
        User::factory()->create(['email' => 'admin@vistoria.test', 'password' => 'password']);

        $this->post('/login', ['email' => 'admin@vistoria.test', 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');
        $this->travel(240)->seconds();

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->post('/login', [
                'email' => 'admin@vistoria.test',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->travel(61)->seconds();
        $this->from('/login')->post('/login', [
            'email' => 'admin@vistoria.test',
            'password' => 'password',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->travel(240)->seconds();
        $this->post('/login', [
            'email' => 'admin@vistoria.test',
            'password' => 'password',
        ])->assertRedirectToRoute('dashboard');
    }

    public function test_failed_attempts_expire_without_a_lockout_if_fewer_than_five_occur_in_five_minutes(): void
    {
        User::factory()->create(['email' => 'admin@vistoria.test', 'password' => 'password']);

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->post('/login', [
                'email' => 'admin@vistoria.test',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->travel(301)->seconds();

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->post('/login', [
                'email' => 'admin@vistoria.test',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors([
                'email' => 'E-mail ou senha incorretos, ou a conta está inativa.',
            ]);
        }

        $this->post('/login', [
            'email' => 'admin@vistoria.test',
            'password' => 'password',
        ])->assertRedirectToRoute('dashboard');
    }

    public function test_successful_login_clears_the_failed_attempt_counter(): void
    {
        User::factory()->create([
            'email' => 'admin@vistoria.test',
            'password' => 'password',
        ]);

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->from('/login')->post('/login', [
                'email' => 'admin@vistoria.test',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors([
                'email' => 'E-mail ou senha incorretos, ou a conta está inativa.',
            ]);
        }

        $this->post('/login', [
            'email' => 'admin@vistoria.test',
            'password' => 'password',
        ])->assertRedirectToRoute('dashboard');
        $this->post(route('logout'))->assertRedirectToRoute('login');

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->from('/login')->post('/login', [
                'email' => 'admin@vistoria.test',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors([
                'email' => 'E-mail ou senha incorretos, ou a conta está inativa.',
            ]);
        }

        $this->post('/login', [
            'email' => 'admin@vistoria.test',
            'password' => 'password',
        ])->assertRedirectToRoute('dashboard');
        $this->assertAuthenticated();
    }

    public function test_failed_attempts_are_separate_by_email_and_ip(): void
    {
        User::factory()->create(['email' => 'admin@vistoria.test', 'password' => 'password']);
        User::factory()->create(['email' => 'other@vistoria.test', 'password' => 'password']);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', [
                'email' => 'admin@vistoria.test',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post('/login', [
            'email' => 'other@vistoria.test',
            'password' => 'password',
        ])->assertRedirectToRoute('dashboard');
        $this->post(route('logout'))->assertRedirectToRoute('login');

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.21']);
        $this->post('/login', [
            'email' => 'admin@vistoria.test',
            'password' => 'password',
        ])->assertRedirectToRoute('dashboard');
    }

    public function test_unknown_and_existing_emails_share_the_invalid_credentials_message(): void
    {
        User::factory()->create(['email' => 'admin@vistoria.test', 'password' => 'password']);

        foreach (['admin@vistoria.test', 'unknown@vistoria.test'] as $email) {
            $this->from('/login')->post('/login', [
                'email' => $email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors([
                'email' => 'E-mail ou senha incorretos, ou a conta está inativa.',
            ]);
        }

        $this->assertGuest();
    }

    public function test_sixty_first_login_submission_from_one_ip_is_rate_limited(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.30']);

        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->post('/login', [])->assertRedirect();
        }

        $response = $this->post('/login', []);
        $response->assertStatus(429)->assertHeader('Retry-After');
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.31']);
        $this->post('/login', [])->assertRedirect();
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard')->assertRedirectToRoute('login');
    }

    public function test_logout_from_an_inertia_request_performs_a_full_redirect_to_login(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post(route('logout'));

        $response
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('login'));

        $this->assertGuest();
    }

    public function test_logout_from_a_regular_form_redirects_to_login(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->post(route('logout'))
            ->assertRedirectToRoute('login');

        $this->assertGuest();
    }
}
