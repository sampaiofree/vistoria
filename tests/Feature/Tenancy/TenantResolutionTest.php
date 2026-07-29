<?php

namespace Tests\Feature\Tenancy;

use App\Models\Organization;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class TenantResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Route::has('test.tenant')) {
            Route::middleware([
                'web',
                'auth',
                'user.active',
                'organization.active',
                'tenant',
            ])->get('/_test/tenant', function (TenantContext $tenant): array {
                return [
                    'organization_id' => $tenant->id(),
                ];
            })->name('test.tenant');
        }
    }

    public function test_it_resolves_the_authenticated_users_organization(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()
            ->for($organization)
            ->create();

        $response = $this
            ->actingAs($user)
            ->get('/_test/tenant');

        $response
            ->assertOk()
            ->assertJson([
                'organization_id' => $organization->id,
            ]);
    }
}
