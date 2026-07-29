<?php

namespace Tests\Feature\Tenancy;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrganizationStatusAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_from_suspended_organization_cannot_access_the_dashboard(): void
    {
        $organization = Organization::factory()
            ->suspended()
            ->create();

        $user = User::factory()
            ->for($organization)
            ->create();

        $response = $this
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
