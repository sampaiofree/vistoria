<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // SQLite must disable foreign keys before starting the table rebuild transaction.
    public $withinTransaction = false;

    public function up(): void
    {
        DB::table('inspections')
            ->select(['id', 'context_snapshot'])
            ->orderBy('id')
            ->chunkById(250, function ($inspections): void {
                foreach ($inspections as $inspection) {
                    $snapshot = json_decode((string) $inspection->context_snapshot, true);

                    if (! is_array($snapshot)) {
                        continue;
                    }

                    unset($snapshot['area'], $snapshot['subarea']);

                    DB::table('inspections')->where('id', $inspection->id)->update([
                        'context_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                    ]);
                }
            });

        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::withoutForeignKeyConstraints(function (): void {
                DB::transaction(fn () => $this->rebuildSqliteEquipmentsTable());
            });
        } else {
            Schema::table('equipments', function (Blueprint $table): void {
                $table->dropForeign('equipments_org_area_subarea_foreign');
                $table->dropForeign('equipments_org_unit_area_foreign');
                $table->dropIndex('equipments_org_unit_area_index');
                $table->dropColumn(['area_id', 'subarea_id']);
            });
        }

        Schema::dropIfExists('subareas');
        Schema::dropIfExists('areas');
    }

    public function down(): void
    {
        throw new RuntimeException('A remoção de áreas e subáreas é irreversível.');
    }

    private function rebuildSqliteEquipmentsTable(): void
    {
        Schema::create('equipments_without_areas', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('client_unit_id');
            $table->string('tag', 120);
            $table->string('normalized_tag', 120);
            $table->string('defect_code_prefix', 80)->nullable();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->string('manufacturer', 150)->nullable();
            $table->string('model', 150)->nullable();
            $table->string('serial_number', 150)->nullable();
            $table->string('asset_code', 120)->nullable();
            $table->string('abc_code', 20)->nullable();
            $table->string('installation_location', 255)->nullable();
            $table->date('commissioned_at')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamp('decommissioned_at')->nullable();
            $table->foreignId('decommissioned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decommission_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign(['organization_id', 'client_id'])->references(['organization_id', 'id'])->on('clients')->restrictOnDelete();
            $table->foreign(['organization_id', 'client_id', 'client_unit_id'])->references(['organization_id', 'client_id', 'id'])->on('client_units')->restrictOnDelete();
            $table->unique(['organization_id', 'client_unit_id', 'normalized_tag']);
            $table->unique(['organization_id', 'defect_code_prefix']);
            $table->unique(['organization_id', 'id']);
            $table->index(['organization_id', 'status', 'name']);
            $table->index(['organization_id', 'normalized_tag']);
        });

        DB::statement('INSERT INTO equipments_without_areas (id, public_id, organization_id, client_id, client_unit_id, tag, normalized_tag, defect_code_prefix, name, description, manufacturer, model, serial_number, asset_code, abc_code, installation_location, commissioned_at, status, decommissioned_at, decommissioned_by, decommission_reason, notes, created_by, updated_by, created_at, updated_at, deleted_at) SELECT id, public_id, organization_id, client_id, client_unit_id, tag, normalized_tag, defect_code_prefix, name, description, manufacturer, model, serial_number, asset_code, abc_code, installation_location, commissioned_at, status, decommissioned_at, decommissioned_by, decommission_reason, notes, created_by, updated_by, created_at, updated_at, deleted_at FROM equipments');
        Schema::drop('equipments');
        Schema::rename('equipments_without_areas', 'equipments');
    }
};
