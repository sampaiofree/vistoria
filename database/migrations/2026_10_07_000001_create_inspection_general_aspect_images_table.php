<?php

declare(strict_types=1);

use App\Enums\PhotoProcessingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_general_aspect_images', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('inspection_id');
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
            $table->timestamp('uploaded_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('unreferenced_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('processing_error')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'inspection_id', 'id'], 'general_aspect_images_org_inspection_id_unique');
            $table->index(['unreferenced_at', 'processing_status'], 'general_aspect_images_cleanup_index');
            $table->foreign(['organization_id', 'inspection_id'], 'general_aspect_images_inspection_foreign')
                ->references(['organization_id', 'id'])->on('inspections')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_general_aspect_images');
    }
};
