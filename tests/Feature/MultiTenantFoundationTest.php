<?php

namespace Tests\Feature;

use App\Enums\OrganizationStatus;
use App\Enums\UserAccountType;
use App\Enums\UserStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class MultiTenantFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_default_seeder_creates_the_organization_and_admin_user(): void
    {
        $this->seed();

        $organization = Organization::query()
            ->where('document', '21798932000100')
            ->firstOrFail();

        $user = User::query()
            ->where('email', 'admin@vistoria.test')
            ->firstOrFail();

        $superAdmin = User::query()
            ->where('email', 'superadmin@vistoria.test')
            ->firstOrFail();

        $this->assertSame(OrganizationStatus::Active, $organization->status);
        $this->assertNotEmpty($organization->public_id);
        $this->assertSame('America/Sao_Paulo', $organization->timezone);
        $this->assertSame(UserAccountType::CompanyAdmin, $user->account_type);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertNotEmpty($user->public_id);
        $this->assertSame($organization->id, $user->organization_id);
        $this->assertTrue($user->organization->is($organization));
        $this->assertTrue($organization->users->contains($user));
        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertNull($superAdmin->organization_id);
    }

    public function test_super_admin_users_can_exist_without_an_organization(): void
    {
        $user = User::factory()
            ->superAdmin()
            ->create();

        $this->assertNull($user->organization_id);
        $this->assertTrue($user->isSuperAdmin());
        $this->assertFalse($user->isCompanyAdmin());
        $this->assertTrue($user->isActive());
    }

    public function test_super_admin_users_cannot_belong_to_an_organization(): void
    {
        $this->expectException(LogicException::class);

        $organization = Organization::factory()->create();

        User::factory()
            ->superAdmin()
            ->create([
                'organization_id' => $organization->id,
            ]);
    }

    public function test_non_super_admin_users_must_belong_to_an_organization(): void
    {
        $this->expectException(LogicException::class);

        User::factory()->create([
            'organization_id' => null,
            'account_type' => UserAccountType::Member->value,
        ]);
    }

    public function test_users_can_be_linked_to_an_organization(): void
    {
        $organization = Organization::factory()->create();

        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $this->assertTrue($organization->users->contains($user));
        $this->assertTrue($user->organization->is($organization));
        $this->assertSame('public_id', $organization->getRouteKeyName());
        $this->assertSame('public_id', $user->getRouteKeyName());
    }

    public function test_suspension_metadata_is_preserved(): void
    {
        $organization = Organization::factory()->suspended()->create();
        $user = User::factory()->for($organization)->suspended()->create();

        $this->assertSame(OrganizationStatus::Suspended, $organization->status);
        $this->assertNotNull($organization->suspended_at);
        $this->assertSame('Suspensa para teste.', $organization->suspension_reason);
        $this->assertSame(UserStatus::Suspended, $user->status);
        $this->assertNotNull($user->suspended_at);
        $this->assertSame('Suspenso para teste.', $user->suspension_reason);
    }
}
