<?php

declare(strict_types=1);

use App\Enums\InspectionLocationMapProcessingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('defect_categories', function (Blueprint $table): void {
            $table->boolean('requires_location_map')->default(false)->after('status');
        });

        Schema::table('assessment_photos', function (Blueprint $table): void {
            $table->unique(
                ['organization_id', 'inspection_id', 'id'],
                'assessment_photos_org_inspection_id_unique',
            );
        });

        Schema::create('inspection_location_maps', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('equipment_id');
            $table->unsignedBigInteger('inspection_id');
            $table->unsignedBigInteger('defect_category_id');
            $table->unsignedBigInteger('equipment_document_id')->nullable();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('source_kind', 30)->default('upload');
            $table->unsignedInteger('source_page')->nullable();
            $table->json('source_crop')->nullable();
            $table->json('reference_snapshot')->nullable();
            $table->string('source_disk', 50)->nullable();
            $table->string('source_path', 700)->nullable();
            $table->string('source_mime_type', 120)->nullable();
            $table->unsignedBigInteger('source_size')->nullable();
            $table->char('source_checksum', 64)->nullable();
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
            $table->unsignedSmallInteger('geometry_schema_version')->default(1);
            $table->unsignedInteger('position')->default(1);
            $table->unsignedInteger('lock_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign(['organization_id', 'equipment_id', 'inspection_id'], 'location_maps_org_equipment_inspection_foreign')
                ->references(['organization_id', 'equipment_id', 'id'])->on('inspections')->restrictOnDelete();
            $table->foreign(['organization_id', 'defect_category_id'], 'location_maps_org_category_foreign')
                ->references(['organization_id', 'id'])->on('defect_categories')->restrictOnDelete();
            $table->foreign(['organization_id', 'equipment_document_id'], 'location_maps_org_document_foreign')
                ->references(['organization_id', 'id'])->on('equipment_documents')->restrictOnDelete();

            $table->unique(['organization_id', 'equipment_id', 'inspection_id', 'id'], 'location_maps_scope_id_unique');
            $table->index(['organization_id', 'inspection_id', 'defect_category_id', 'position'], 'location_maps_scope_position_index');
            $table->index(['organization_id', 'inspection_id', 'processing_status'], 'location_maps_processing_status_index');
        });

        Schema::create('inspection_location_markers', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('equipment_id');
            $table->unsignedBigInteger('inspection_id');
            $table->unsignedBigInteger('inspection_location_map_id');
            $table->unsignedBigInteger('defect_assessment_id');
            $table->string('label', 120)->nullable();
            $table->json('geometry');
            $table->json('style')->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->unsignedInteger('lock_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign(['organization_id', 'equipment_id', 'inspection_id', 'inspection_location_map_id'], 'location_markers_map_scope_foreign')
                ->references(['organization_id', 'equipment_id', 'inspection_id', 'id'])->on('inspection_location_maps')->restrictOnDelete();
            $table->foreign(['organization_id', 'inspection_id', 'defect_assessment_id'], 'location_markers_assessment_scope_foreign')
                ->references(['organization_id', 'inspection_id', 'id'])->on('defect_assessments')->restrictOnDelete();

            $table->unique(['organization_id', 'inspection_id', 'id'], 'location_markers_scope_id_unique');
            $table->index(['organization_id', 'inspection_location_map_id', 'position'], 'location_markers_map_position_index');
            $table->index(['organization_id', 'defect_assessment_id'], 'location_markers_assessment_index');
        });

        Schema::create('inspection_location_marker_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('inspection_id');
            $table->unsignedBigInteger('inspection_location_marker_id');
            $table->unsignedBigInteger('assessment_photo_id');
            $table->unsignedInteger('position')->default(1);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['organization_id', 'inspection_id', 'inspection_location_marker_id'], 'marker_photos_marker_scope_foreign')
                ->references(['organization_id', 'inspection_id', 'id'])->on('inspection_location_markers')->cascadeOnDelete();
            $table->foreign(['organization_id', 'inspection_id', 'assessment_photo_id'], 'marker_photos_photo_scope_foreign')
                ->references(['organization_id', 'inspection_id', 'id'])->on('assessment_photos')->cascadeOnDelete();

            $table->unique(['inspection_location_marker_id', 'assessment_photo_id'], 'marker_photos_marker_photo_unique');
            $table->index(['organization_id', 'inspection_id', 'assessment_photo_id'], 'marker_photos_photo_scope_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_location_marker_photos');
        Schema::dropIfExists('inspection_location_markers');
        Schema::dropIfExists('inspection_location_maps');

        Schema::table('assessment_photos', function (Blueprint $table): void {
            $table->dropUnique('assessment_photos_org_inspection_id_unique');
        });

        Schema::table('defect_categories', function (Blueprint $table): void {
            $table->dropColumn('requires_location_map');
        });
    }
};
