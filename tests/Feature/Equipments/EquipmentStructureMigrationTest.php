<?php

namespace Tests\Feature\Equipments;

use App\Models\Client;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class EquipmentStructureMigrationTest extends TestCase
{
    public function test_pending_migrations_preserve_existing_equipment_with_repeated_tags_and_related_data(): void
    {
        $originalConnection = DB::getDefaultConnection();
        config(['database.connections.equipment_migration_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('equipment_migration_test');
        Schema::clearResolvedInstance('db.schema');

        try {
            $paths = glob(database_path('migrations/*.php'));
            sort($paths);
            foreach ($paths as $path) {
                if (! str_contains(basename($path), '2026_09_16_')) {
                    (require $path)->up();
                }
            }
            $organization = Organization::factory()->create();
            $client = Client::factory()->for($organization)->create();
            $equipments = [];
            for ($i = 1; $i <= 2; $i++) {
                $unitId = DB::table('client_units')->insertGetId(['public_id' => (string) Str::ulid(), 'organization_id' => $organization->id, 'client_id' => $client->id, 'name' => 'Unidade '.$i]);
                $areaId = DB::table('areas')->insertGetId(['public_id' => (string) Str::ulid(), 'organization_id' => $organization->id, 'client_unit_id' => $unitId, 'name' => 'Área '.$i]);
                $subareaId = DB::table('subareas')->insertGetId(['public_id' => (string) Str::ulid(), 'organization_id' => $organization->id, 'area_id' => $areaId, 'name' => 'Subárea '.$i]);
                $id = DB::table('equipments')->insertGetId(['public_id' => (string) Str::ulid(), 'organization_id' => $organization->id, 'client_id' => $client->id, 'numero_cliente' => 'SAM-'.$i, 'numero_interno' => 'SEND-'.$i, 'client_unit_id' => $unitId, 'area_id' => $areaId, 'subarea_id' => $subareaId, 'tag' => 'PRÉDIO', 'normalized_tag' => 'PRÉDIO', 'defect_code_prefix' => 'LEGADO'.$i, 'name' => 'Prédio '.$i]);
                $equipments[] = Equipment::findOrFail($id);
            }
            $inspection = Inspection::factory()->forEquipment($equipments[0])->create(['context_snapshot' => ['organization' => ['name' => 'Empresa'], 'client' => ['name' => 'Cliente'], 'unit' => ['name' => 'Unidade'], 'area' => ['name' => 'Área'], 'subarea' => ['name' => 'Subárea'], 'equipment' => ['tag' => 'PRÉDIO']]]);
            foreach ($paths as $path) {
                if (str_contains(basename($path), '2026_09_16_')) {
                    (require $path)->up();
                }
            }
            $this->assertSame(2, Equipment::count());
            $this->assertSame('LEGADO1', $equipments[0]->refresh()->defect_code_prefix);
            $this->assertNull($equipments[0]->maintenance_item_code);
            $this->assertSame(['organization', 'client', 'equipment'], array_keys($inspection->refresh()->context_snapshot));
            foreach (['client_units', 'areas', 'subareas'] as $table) {
                $this->assertFalse(Schema::hasTable($table));
            }
            $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        } finally {
            DB::disconnect('equipment_migration_test');
            DB::setDefaultConnection($originalConnection);
            Schema::clearResolvedInstance('db.schema');
        }
    }
}
