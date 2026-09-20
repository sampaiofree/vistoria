<?php

declare(strict_types=1);

use App\Enums\InspectionLocationMapProcessingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::table('inspection_location_marker_photos')->delete();
        DB::table('inspection_location_markers')->delete();
        DB::table('inspection_location_maps')->delete();

        Schema::dropIfExists('inspection_location_marker_photos');
        Schema::dropIfExists('inspection_location_markers');
        Schema::dropIfExists('inspection_location_maps');

        Schema::create('defect_location_maps', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('equipment_id');
            $table->unsignedBigInteger('defect_id');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(
                ['organization_id', 'equipment_id', 'defect_id'],
                'defect_location_maps_scope_foreign',
            )->references(['organization_id', 'equipment_id', 'id'])->on('defects')->restrictOnDelete();
            $table->unique(['organization_id', 'equipment_id', 'id'], 'defect_location_maps_scope_id_unique');
            $table->unique(['organization_id', 'defect_id'], 'defect_location_maps_defect_unique');
        });

        Schema::create('defect_location_map_versions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('equipment_id');
            $table->unsignedBigInteger('defect_location_map_id');
            $table->unsignedBigInteger('created_for_assessment_id');
            $table->unsignedInteger('version');
            $table->string('source_disk', 50)->nullable();
            $table->string('source_path', 700)->nullable();
            $table->string('source_mime_type', 120)->nullable();
            $table->unsignedBigInteger('source_size')->nullable();
            $table->char('source_checksum', 64)->nullable();
            $table->foreignId('source_uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('background_disk', 50)->nullable();
            $table->string('background_path', 700)->nullable();
            $table->string('background_mime_type', 120)->nullable();
            $table->unsignedBigInteger('background_size')->nullable();
            $table->unsignedInteger('background_width')->nullable();
            $table->unsignedInteger('background_height')->nullable();
            $table->char('background_checksum', 64)->nullable();
            $table->string('processing_status', 30)->default(InspectionLocationMapProcessingStatus::Pending->value);
            $table->text('processing_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->foreign(
                ['organization_id', 'equipment_id', 'defect_location_map_id'],
                'defect_location_versions_map_scope_foreign',
            )->references(['organization_id', 'equipment_id', 'id'])->on('defect_location_maps')->restrictOnDelete();
            $table->foreign(
                ['organization_id', 'equipment_id', 'created_for_assessment_id'],
                'defect_location_versions_assessment_scope_foreign',
            )->references(['organization_id', 'equipment_id', 'id'])->on('defect_assessments')->restrictOnDelete();
            $table->unique(['organization_id', 'equipment_id', 'id'], 'defect_location_versions_scope_id_unique');
            $table->unique(['defect_location_map_id', 'version'], 'defect_location_versions_number_unique');
            $table->index(['organization_id', 'processing_status'], 'defect_location_versions_status_index');
        });

        Schema::table('defect_assessments', function (Blueprint $table): void {
            $table->unsignedBigInteger('defect_location_map_version_id')->nullable()->after('previous_assessment_id');
            $table->foreign(
                ['organization_id', 'equipment_id', 'defect_location_map_version_id'],
                'assessments_location_version_scope_foreign',
            )->references(['organization_id', 'equipment_id', 'id'])->on('defect_location_map_versions')->restrictOnDelete();
            $table->index(
                ['organization_id', 'defect_location_map_version_id'],
                'assessments_location_version_index',
            );
        });

        Schema::create('defect_assessment_locations', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('equipment_id');
            $table->unsignedBigInteger('inspection_id');
            $table->unsignedBigInteger('defect_assessment_id');
            $table->json('geometry');
            $table->string('label', 240)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(
                ['organization_id', 'equipment_id', 'defect_assessment_id'],
                'defect_assessment_locations_assessment_foreign',
            )->references(['organization_id', 'equipment_id', 'id'])->on('defect_assessments')->cascadeOnDelete();
            $table->foreign(
                ['organization_id', 'equipment_id', 'inspection_id'],
                'defect_assessment_locations_inspection_foreign',
            )->references(['organization_id', 'equipment_id', 'id'])->on('inspections')->restrictOnDelete();
            $table->unique(['organization_id', 'defect_assessment_id'], 'defect_assessment_locations_assessment_unique');
            $table->index(['organization_id', 'inspection_id'], 'defect_assessment_locations_inspection_index');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('A substituicao do modulo de mapas por avaria e irreversivel.');
    }
};
