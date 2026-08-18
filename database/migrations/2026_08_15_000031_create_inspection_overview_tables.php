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
        Schema::create('inspection_overview_blocks', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('inspection_id');
            $table->unsignedTinyInteger('position');
            $table->text('comment')->nullable();
            $table->text('recommendation')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'inspection_id', 'position'], 'inspection_overview_blocks_org_inspection_position_unique');
            $table->unique(['organization_id', 'inspection_id', 'id'], 'inspection_overview_blocks_org_inspection_id_unique');
            $table->foreign(['organization_id', 'inspection_id'], 'inspection_overview_blocks_org_inspection_foreign')
                ->references(['organization_id', 'id'])
                ->on('inspections')
                ->restrictOnDelete();
        });

        Schema::create('inspection_overview_photos', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('inspection_id');
            $table->unsignedBigInteger('inspection_overview_block_id');
            $table->unsignedTinyInteger('slot')->nullable();
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
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('processing_error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['inspection_overview_block_id', 'slot'], 'inspection_overview_photos_block_slot_unique');
            $table->index(['organization_id', 'inspection_id', 'processing_status'], 'inspection_overview_photos_org_inspection_status_index');
            $table->foreign(
                ['organization_id', 'inspection_id', 'inspection_overview_block_id'],
                'inspection_overview_photos_org_inspection_block_foreign',
            )
                ->references(['organization_id', 'inspection_id', 'id'])
                ->on('inspection_overview_blocks')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_overview_photos');
        Schema::dropIfExists('inspection_overview_blocks');
    }
};
