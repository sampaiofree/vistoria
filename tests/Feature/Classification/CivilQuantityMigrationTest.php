<?php

declare(strict_types=1);

namespace Tests\Feature\Classification;

use App\Enums\DefectCategory;
use App\Enums\MeasurementUnit;
use App\Enums\QuantityCalculationMode;
use App\Enums\QuantityCalculationType;
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

final class CivilQuantityMigrationTest extends TestCase
{
    public function test_upgrade_preserves_existing_generic_quantities_uniqueness_and_foreign_keys(): void
    {
        $original = DB::getDefaultConnection();
        config(['database.connections.civil_quantity_migration_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection('civil_quantity_migration_test');
        Schema::clearResolvedInstance('db.schema');

        try {
            $paths = glob(database_path('migrations/*.php'));
            sort($paths);
            foreach ($paths as $path) {
                if (basename($path) >= '2026_09_18_000050_structure_native_defect_assessment_quantities.php') {
                    break;
                }
                (require $path)->up();
            }

            $organization = Organization::factory()->create();
            $equipment = Equipment::factory()->for($organization)->create();
            $inspection = Inspection::factory()->forEquipment($equipment)->create();
            $defect = Defect::factory()->forEquipment($equipment, $inspection)->create(['category' => DefectCategory::AnticorrosiveTreatment]);
            $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->create();
            $quantity = DefectAssessmentQuantity::factory()->forAssessment($assessment)->create([
                'measurement_value' => 2.75, 'measurement_unit' => MeasurementUnit::SquareMeter,
            ]);

            $migration = require database_path('migrations/2026_09_18_000050_structure_native_defect_assessment_quantities.php');
            $migration->up();

            $this->assertSame(2.75, $quantity->refresh()->value());
            $this->assertSame(MeasurementUnit::SquareMeter, $quantity->measurement_unit);
            $this->assertSame(DefectCategory::AnticorrosiveTreatment, $quantity->category);
            $this->assertSame(QuantityCalculationType::TacArea, $quantity->calculation_type);
            $this->assertSame(QuantityCalculationMode::Manual, $quantity->mode);
            $this->assertSame(0, $quantity->formula_version);
            $this->assertSame([], $quantity->inputs);
            $this->assertSame('legacy_quantity', $quantity->formula_snapshot['source']);
            $this->assertTrue(Schema::hasColumns('defect_assessment_quantities', [
                'category', 'calculation_type', 'rec_element', 'inputs', 'unit_value', 'mode',
                'formula_version', 'formula_snapshot',
            ]));
            $this->assertTrue(Schema::hasColumn('defect_assessments', 'quantity_snapshot'));
            $this->assertSame(1, DefectAssessmentQuantity::count());
            $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
            $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);

            try {
                DefectAssessmentQuantity::factory()->forAssessment($assessment)->create();
                $this->fail('A unicidade do quantitativo por avaliação deve ser preservada.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('UNIQUE constraint failed', $exception->getMessage());
            }

            $assessment->delete();
            $this->assertSame(0, DefectAssessmentQuantity::count());

            $this->expectException(\RuntimeException::class);
            $migration->down();
        } finally {
            DB::disconnect('civil_quantity_migration_test');
            DB::purge('civil_quantity_migration_test');
            DB::setDefaultConnection($original);
            Schema::clearResolvedInstance('db.schema');
        }
    }
}
