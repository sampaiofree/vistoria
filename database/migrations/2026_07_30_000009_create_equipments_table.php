<?php

declare(strict_types=1);

use App\Enums\EquipmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('client_unit_id');
            $table->unsignedBigInteger('area_id');
            $table->unsignedBigInteger('subarea_id')->nullable();

            $table->string('tag', 120);
            $table->string('normalized_tag', 120);

            $table->string('name', 180);
            $table->text('description')->nullable();

            $table->string('manufacturer', 150)->nullable();
            $table->string('model', 150)->nullable();
            $table->string('serial_number', 150)->nullable();
            $table->string('asset_code', 120)->nullable();
            $table->string('abc_code', 20)->nullable();

            $table->string('installation_location', 255)->nullable();
            $table->date('commissioned_at')->nullable();

            $table->string('status', 30)
                ->default(EquipmentStatus::Active->value);

            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign(
                ['organization_id', 'client_id'],
                'equipments_org_client_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('clients')
                ->restrictOnDelete();

            $table->foreign(
                ['organization_id', 'client_id', 'client_unit_id'],
                'equipments_org_client_unit_foreign',
            )
                ->references(['organization_id', 'client_id', 'id'])
                ->on('client_units')
                ->restrictOnDelete();

            $table->foreign(
                ['organization_id', 'client_unit_id', 'area_id'],
                'equipments_org_unit_area_foreign',
            )
                ->references(['organization_id', 'client_unit_id', 'id'])
                ->on('areas')
                ->restrictOnDelete();

            $table->foreign(
                ['organization_id', 'area_id', 'subarea_id'],
                'equipments_org_area_subarea_foreign',
            )
                ->references(['organization_id', 'area_id', 'id'])
                ->on('subareas')
                ->restrictOnDelete();

            $table->unique(
                ['organization_id', 'client_unit_id', 'normalized_tag'],
                'equipments_org_unit_tag_unique',
            );

            $table->unique(
                ['organization_id', 'id'],
                'equipments_org_id_unique',
            );

            $table->index(
                ['organization_id', 'status', 'name'],
                'equipments_org_status_name_index',
            );

            $table->index(
                ['organization_id', 'normalized_tag'],
                'equipments_org_tag_index',
            );

            $table->index(
                ['organization_id', 'client_unit_id', 'area_id'],
                'equipments_org_unit_area_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipments');
    }
};
