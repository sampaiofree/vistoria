<?php

declare(strict_types=1);

namespace Tests\Feature\Inspections;

use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class InspectionResponsibleUniquenessMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_keeps_the_primary_or_oldest_assignment_and_enforces_one_user_per_role(): void
    {
        $organization = Organization::factory()->create();
        $inspection = Inspection::factory()->forEquipment(Equipment::factory()->for($organization)->create())->create();
        $actor = User::factory()->for($organization)->create();
        $oldest = User::factory()->for($organization)->create();
        $primary = User::factory()->for($organization)->create();

        $migration = require database_path('migrations/2026_09_17_000048_enforce_one_inspection_responsible_per_role.php');
        $migration->down();

        foreach ([[$oldest, false, now()->subDay()], [$primary, true, now()]] as [$user, $isPrimary, $assignedAt]) {
            DB::table('inspection_responsibles')->insert([
                'organization_id' => $organization->id,
                'inspection_id' => $inspection->id,
                'user_id' => $user->id,
                'responsibility' => 'reviewer',
                'is_primary' => $isPrimary,
                'assigned_by' => $actor->id,
                'assigned_at' => $assignedAt,
                'created_at' => $assignedAt,
                'updated_at' => $assignedAt,
            ]);
        }

        $migration->up();

        $this->assertSame(1, DB::table('inspection_responsibles')
            ->where('inspection_id', $inspection->id)
            ->where('responsibility', 'reviewer')
            ->count());
        $this->assertDatabaseHas('inspection_responsibles', [
            'inspection_id' => $inspection->id,
            'user_id' => $primary->id,
            'responsibility' => 'reviewer',
            'is_primary' => true,
        ]);
    }
}
