<?php

declare(strict_types=1);

namespace Tests\Feature\Classification;

use App\Enums\DefectCategory;
use App\Models\AssessmentPhoto;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\DefectAssessmentQuantity;
use App\Models\Equipment;
use App\Models\EquipmentDocument;
use App\Models\Inspection;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class NativeDefectCatalogMigrationTest extends TestCase
{
    public function test_upgrade_cleans_development_data_and_preserves_other_records_and_foreign_keys(): void
    {
        Storage::fake('inspection_photos');

        $this->onPreviousSchema(function (Organization $organization): void {
            $user = User::factory()->for($organization)->create();
            $equipment = Equipment::factory()->for($organization)->create();
            $document = EquipmentDocument::factory()->forEquipment($equipment)->create();
            $inspection = Inspection::factory()->forEquipment($equipment)->create();
            $categoryId = DB::table('defect_categories')->where('organization_id', $organization->id)->where('code', 'CV')->value('id');
            $classificationId = DB::table('defect_classifications')->where('defect_category_id', $categoryId)->where('code', 'CV-1')->value('id');
            $defect = Defect::factory()->forEquipment($equipment, $inspection)->create();
            $secondDefect = Defect::factory()->forEquipment($equipment, $inspection)->create();
            DB::table('defects')->update(['defect_category_id' => $categoryId]);
            $assessment = DefectAssessment::factory()->forDefect($defect, $inspection)->create();
            DB::table('defect_assessments')->where('id', $assessment->id)->update(['defect_classification_id' => $classificationId]);
            $nextInspection = Inspection::factory()->reinspection($inspection)->create();
            DefectAssessment::factory()->forDefect($defect, $nextInspection)->create(['previous_assessment_id' => $assessment->id]);
            DefectAssessmentQuantity::factory()->forAssessment($assessment)->create();
            $photo = AssessmentPhoto::factory()->ready()->create([
                'organization_id' => $organization->id, 'inspection_id' => $inspection->id, 'defect_assessment_id' => $assessment->id,
                'disk' => 'inspection_photos',
                'original_path' => 'assessments/original.jpg',
                'optimized_path' => 'assessments/optimized.jpg',
                'thumbnail_path' => 'assessments/thumbnail.jpg',
            ]);
            foreach ([$photo->original_path, $photo->optimized_path, $photo->thumbnail_path] as $path) {
                Storage::disk('inspection_photos')->put($path, 'photo');
            }
            $mapId = DB::table('inspection_location_maps')->insertGetId([
                'public_id' => (string) Str::ulid(), 'organization_id' => $organization->id,
                'equipment_id' => $equipment->id, 'inspection_id' => $inspection->id,
                'defect_category_id' => $categoryId, 'title' => 'Mapa de desenvolvimento',
                'equipment_document_id' => $document->id,
            ]);
            $markerId = DB::table('inspection_location_markers')->insertGetId([
                'public_id' => (string) Str::ulid(), 'organization_id' => $organization->id,
                'equipment_id' => $equipment->id, 'inspection_id' => $inspection->id,
                'inspection_location_map_id' => $mapId, 'defect_assessment_id' => $assessment->id,
                'geometry' => json_encode(['version' => 1, 'shapes' => [['type' => 'point', 'x' => 0.5, 'y' => 0.5]]]),
            ]);
            DB::table('inspection_location_marker_photos')->insert([
                'organization_id' => $organization->id, 'inspection_id' => $inspection->id,
                'inspection_location_marker_id' => $markerId, 'assessment_photo_id' => $photo->id,
            ]);
            DB::table('defect_code_sequences')->insert([
                'organization_id' => $organization->id, 'equipment_id' => $equipment->id,
                'category' => 'civil', 'defect_category_id' => $categoryId, 'last_number' => 2,
            ]);
            DB::table('defect_relations')->insert([
                'organization_id' => $organization->id, 'equipment_id' => $equipment->id,
                'source_defect_id' => $defect->id, 'target_defect_id' => $secondDefect->id, 'relation_type' => 'related', 'created_at' => now(),
            ]);

            $this->migration()->up();

            foreach (['inspection_location_marker_photos', 'inspection_location_markers', 'inspection_location_maps', 'assessment_photos', 'defect_assessment_quantities', 'defect_assessments', 'defect_relations', 'defects', 'defect_code_sequences'] as $table) {
                $this->assertSame(0, DB::table($table)->count(), $table);
            }
            $this->assertSame($organization->id, $organization->refresh()->id);
            $this->assertSame($user->id, $user->refresh()->id);
            $this->assertSame($equipment->id, $equipment->refresh()->id);
            $this->assertSame($document->id, $document->refresh()->id);
            $this->assertSame($inspection->id, $inspection->refresh()->id);
            $this->assertSame($nextInspection->id, $nextInspection->refresh()->id);
            Storage::disk('inspection_photos')->assertMissing([
                'assessments/original.jpg',
                'assessments/optimized.jpg',
                'assessments/thumbnail.jpg',
            ]);
            $this->assertNativeSchema();

            $nativeDefect = Defect::factory()->forEquipment($equipment, $inspection)->create(['category' => DefectCategory::StructuralRecovery]);
            $this->assertSame(DefectCategory::StructuralRecovery, $nativeDefect->refresh()->category);
            $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
            $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
        });
    }

    public function test_historical_provisioning_and_native_migration_work_without_runtime_catalog_models(): void
    {
        $this->onPreviousSchema(function (Organization $organization): void {
            $this->assertSame(['CV', 'REC', 'TAC'], DB::table('defect_categories')->where('organization_id', $organization->id)->orderBy('code')->pluck('code')->all());
            $this->assertSame(13, DB::table('defect_classifications')->count());
            $this->assertSame(45, DB::table('defect_category_gut_options')->count());
            $this->migration()->up();
            $this->assertNativeSchema();
            Organization::factory()->create();
            $this->assertSame(2, Organization::count());
        });
    }

    private function assertNativeSchema(): void
    {
        foreach (['defect_categories', 'defect_classifications', 'defect_category_gut_options'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
        foreach (['defects', 'defect_code_sequences', 'inspection_location_maps'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'category'));
            $this->assertFalse(Schema::hasColumn($table, 'defect_category_id'));
        }
        $this->assertFalse(Schema::hasColumn('defect_assessments', 'defect_classification_id'));
        $this->assertTrue(Schema::hasColumn('defect_assessments', 'classification_snapshot'));
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_09_17_000049_replace_defect_taxonomy_with_native_catalog.php');
    }

    private function onPreviousSchema(callable $assertions): void
    {
        $original = DB::getDefaultConnection();
        config(['database.connections.native_catalog_migration_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection('native_catalog_migration_test');
        Schema::clearResolvedInstance('db.schema');

        try {
            $paths = glob(database_path('migrations/*.php'));
            sort($paths);
            foreach ($paths as $path) {
                if (basename($path) >= '2026_09_17_000049_replace_defect_taxonomy_with_native_catalog.php') {
                    break;
                }
                if (str_contains(basename($path), '000037_')) {
                    $organization = Organization::factory()->create();
                }
                (require $path)->up();
            }
            $assertions($organization);
        } finally {
            DB::disconnect('native_catalog_migration_test');
            DB::purge('native_catalog_migration_test');
            DB::setDefaultConnection($original);
            Schema::clearResolvedInstance('db.schema');
        }
    }
}
