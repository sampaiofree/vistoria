<?php

declare(strict_types=1);

namespace Tests\Feature\Classification;

use App\Enums\DefectCategory;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\DefectAssessmentQuantity;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Organization;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CompositeQuantityMigrationTest extends TestCase
{
    public function test_upgrade_wraps_legacy_snapshot_and_allows_unique_ordered_items(): void
    {
        $original = DB::getDefaultConnection();
        config(['database.connections.composite_quantity_migration_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection('composite_quantity_migration_test');
        Schema::clearResolvedInstance('db.schema');

        try {
            $paths = glob(database_path('migrations/*.php'));
            sort($paths);
            foreach ($paths as $path) {
                if (basename($path) >= '2026_09_20_000052_allow_multiple_defect_assessment_quantity_items.php') {
                    break;
                }
                (require $path)->up();
            }

            $organization = Organization::factory()->create();
            $equipment = Equipment::factory()->for($organization)->create();
            $inspection = Inspection::factory()->forEquipment($equipment)->create();
            $defect = Defect::factory()->forEquipment($equipment, $inspection)->create([
                'category' => DefectCategory::Civil,
            ]);
            $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->create([
                'quantity_snapshot' => [
                    'source' => 'native_quantity_catalog',
                    'category' => 'CV',
                    'measurement_unit' => 'm3',
                    'total' => '0.6000000000000000',
                    'inputs' => ['length' => '2', 'height' => '0.5', 'width' => '0.3'],
                ],
            ]);
            DefectAssessmentQuantity::factory()->forAssessment($assessment)->create(['position' => 1]);

            $migration = require database_path('migrations/2026_09_20_000052_allow_multiple_defect_assessment_quantity_items.php');
            $migration->up();

            $snapshot = $assessment->refresh()->quantity_snapshot;
            $this->assertSame(2, $snapshot['snapshot_version']);
            $this->assertSame(1, $snapshot['item_count']);
            $this->assertSame('0.6000000000000000', $snapshot['total']);
            $this->assertSame(1, $snapshot['items'][0]['position']);
            $this->assertNull($snapshot['items'][0]['description']);

            DefectAssessmentQuantity::factory()->forAssessment($assessment)->create(['position' => 2]);
            $this->assertSame(2, $assessment->quantities()->count());

            try {
                DefectAssessmentQuantity::factory()->forAssessment($assessment)->create(['position' => 2]);
                $this->fail('A posição deve ser única dentro da avaliação e organização.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('UNIQUE constraint failed', $exception->getMessage());
            }

            $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
            $this->expectException(\RuntimeException::class);
            $migration->down();
        } finally {
            DB::disconnect('composite_quantity_migration_test');
            DB::purge('composite_quantity_migration_test');
            DB::setDefaultConnection($original);
            Schema::clearResolvedInstance('db.schema');
        }
    }
}
