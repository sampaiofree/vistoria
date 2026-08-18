<?php

declare(strict_types=1);

use App\Enums\AssessmentPhotoType;
use App\Enums\PhotoProcessingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_photos', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('inspection_id');
            $table->unsignedBigInteger('defect_assessment_id');
            $table->string('photo_type', 30)->default(AssessmentPhotoType::Detail->value);
            $table->string('caption', 500)->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->boolean('is_primary')->default(false);
            $table->string('processing_status', 30)->default(PhotoProcessingStatus::Pending->value);
            $table->string('disk', 50)->default('inspection_photos');
            $table->string('original_path', 700)->nullable();
            $table->string('optimized_path', 700)->nullable();
            $table->string('thumbnail_path', 700)->nullable();
            $table->string('original_name', 255);
            $table->string('original_mime_type', 120);
            $table->string('original_extension', 20)->nullable();
            $table->unsignedBigInteger('original_size');
            $table->unsignedInteger('original_width')->nullable();
            $table->unsignedInteger('original_height')->nullable();
            $table->unsignedBigInteger('optimized_size')->nullable();
            $table->unsignedInteger('optimized_width')->nullable();
            $table->unsignedInteger('optimized_height')->nullable();
            $table->unsignedBigInteger('thumbnail_size')->nullable();
            $table->unsignedInteger('thumbnail_width')->nullable();
            $table->unsignedInteger('thumbnail_height')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('uploaded_at');
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('processing_error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign(['organization_id', 'inspection_id'], 'assessment_photos_org_inspection_foreign')
                ->references(['organization_id', 'id'])->on('inspections')->restrictOnDelete();
            $table->foreign(['organization_id', 'inspection_id', 'defect_assessment_id'], 'assessment_photos_org_assessment_foreign')
                ->references(['organization_id', 'inspection_id', 'id'])->on('defect_assessments')->restrictOnDelete();
            $table->index(['organization_id', 'defect_assessment_id', 'processing_status'], 'assessment_photos_org_assessment_status_index');
            $table->index(['organization_id', 'inspection_id', 'position'], 'assessment_photos_org_inspection_position_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_photos');
    }
};
