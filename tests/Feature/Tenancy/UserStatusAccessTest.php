<?php

namespace Tests\Feature\Tenancy;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserStatusAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_user_cannot_access_protected_routes(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()
            ->for($organization)
            ->inactive()
            ->create();

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
