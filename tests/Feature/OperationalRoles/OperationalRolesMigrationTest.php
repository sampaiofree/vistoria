<?php

namespace Tests\Feature\OperationalRoles;

use App\Enums\OperationalRole;
use App\Enums\UserAccountType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

final class OperationalRolesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_roles_are_backfilled_without_creating_roles_for_clients(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin,
            'operational_role' => OperationalRole::Reviewer,
        ]);
        $member = User::factory()->for($organization)->create(['operational_role' => OperationalRole::Inspector]);
        $client = User::factory()->for($organization)->create(['account_type' => UserAccountType::Client]);

        $migration = require database_path('migrations/2026_10_11_000072_create_user_operational_roles_table.php');
        $migration->down();
        $migration->up();

        $this->assertDatabaseCount('user_operational_roles', 2);
        $this->assertTrue($admin->fresh()->hasOperationalRole(OperationalRole::Reviewer));
        $this->assertTrue($member->fresh()->hasOperationalRole(OperationalRole::Inspector));
        $this->assertSame([], $client->fresh()->operationalRoleValues());
    }

    public function test_migration_stops_before_creating_table_when_legacy_account_has_no_role(): void
    {
        $organization = Organization::factory()->create();
        User::factory()->for($organization)->create([
            'account_type' => UserAccountType::CompanyAdmin,
            'operational_role' => null,
        ]);

        $migration = require database_path('migrations/2026_10_11_000072_create_user_operational_roles_table.php');
        $migration->down();
        try {
            $migration->up();
            $this->fail('A migração deveria interromper diante de uma conta sem função.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('auditoria', $exception->getMessage());
            $this->assertFalse(Schema::hasTable('user_operational_roles'));
        }
    }
}
