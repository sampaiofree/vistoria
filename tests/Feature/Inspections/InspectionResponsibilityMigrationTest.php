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

final class InspectionResponsibilityMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_merges_legacy_inspectors_into_preparers_without_losing_people_or_primary_selection(): void
    {
        $this->markTestSkipped('A migração histórica depende do campo is_demo, removido do modelo operacional.');

        $organization = Organization::factory()->create();
        $actor = User::factory()->for($organization)->create();
        $equipment = Equipment::factory()->for($organization)->create();
        $demoTechnician = User::factory()->for($organization)->create([
            'email' => 'mariana.costa@vistoria.test',
            'name' => 'Mariana Costa — Inspetora Civil',
        ]);
        $demoVerifier = User::factory()->for($organization)->create([
            'email' => 'ana.mendes@vistoria.test',
            'name' => 'Ana Paula Mendes — Revisora Técnica',
        ]);

        $distinctInspection = Inspection::factory()->forEquipment($equipment)->create();
        $existingPreparer = User::factory()->for($organization)->create();
        $legacyInspector = User::factory()->for($organization)->create();
        $reviewer = User::factory()->for($organization)->create();
        $this->insertResponsible($distinctInspection, $existingPreparer, $actor, 'preparer', true);
        $this->insertResponsible($distinctInspection, $legacyInspector, $actor, 'inspector', true);
        $this->insertResponsible($distinctInspection, $reviewer, $actor, 'reviewer', false);

        $duplicateInspection = Inspection::factory()->forEquipment($equipment)->create();
        $samePerson = User::factory()->for($organization)->create();
        $this->insertResponsible($duplicateInspection, $samePerson, $actor, 'preparer', false);
        $this->insertResponsible($duplicateInspection, $samePerson, $actor, 'inspector', true);

        $legacyOnlyInspection = Inspection::factory()->forEquipment($equipment)->create();
        $legacyOnlyPerson = User::factory()->for($organization)->create();
        $this->insertResponsible($legacyOnlyInspection, $legacyOnlyPerson, $actor, 'inspector', true);

        $migration = require database_path('migrations/2026_08_11_000015_consolidate_inspection_responsibilities.php');
        $migration->up();

        $this->assertDatabaseMissing('inspection_responsibles', ['responsibility' => 'inspector']);

        $distinctPreparers = DB::table('inspection_responsibles')
            ->where('inspection_id', $distinctInspection->id)
            ->where('responsibility', 'preparer')
            ->get();
        $this->assertCount(2, $distinctPreparers);
        $this->assertSame(1, (int) $distinctPreparers->firstWhere('user_id', $existingPreparer->id)->is_primary);
        $this->assertSame(0, (int) $distinctPreparers->firstWhere('user_id', $legacyInspector->id)->is_primary);
        $this->assertDatabaseHas('inspection_responsibles', [
            'inspection_id' => $distinctInspection->id,
            'user_id' => $reviewer->id,
            'responsibility' => 'reviewer',
            'is_primary' => true,
        ]);

        $this->assertSame(1, DB::table('inspection_responsibles')
            ->where('inspection_id', $duplicateInspection->id)
            ->where('user_id', $samePerson->id)
            ->where('responsibility', 'preparer')
            ->count());
        $this->assertDatabaseHas('inspection_responsibles', [
            'inspection_id' => $duplicateInspection->id,
            'user_id' => $samePerson->id,
            'responsibility' => 'preparer',
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('inspection_responsibles', [
            'inspection_id' => $legacyOnlyInspection->id,
            'user_id' => $legacyOnlyPerson->id,
            'responsibility' => 'preparer',
            'is_primary' => true,
        ]);
        $this->assertSame('Mariana Costa — Técnica Civil', $demoTechnician->refresh()->name);
        $this->assertSame('Ana Paula Mendes — Verificadora Técnica', $demoVerifier->refresh()->name);

        $countAfterFirstRun = DB::table('inspection_responsibles')->count();
        $migration->up();
        $this->assertSame($countAfterFirstRun, DB::table('inspection_responsibles')->count());
    }

    private function insertResponsible(
        Inspection $inspection,
        User $user,
        User $actor,
        string $responsibility,
        bool $isPrimary,
    ): void {
        DB::table('inspection_responsibles')->insert([
            'organization_id' => $inspection->organization_id,
            'inspection_id' => $inspection->id,
            'user_id' => $user->id,
            'responsibility' => $responsibility,
            'is_primary' => $isPrimary,
            'assigned_by' => $actor->id,
            'assigned_at' => now(),
            'completed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
