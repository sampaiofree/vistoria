<?php

use App\Enums\DocumentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_documents', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedBigInteger('equipment_id');
            $table->ulid('document_group');

            $table->string('document_type', 50);
            $table->string('title', 200);
            $table->string('document_number', 150)->nullable();
            $table->string('revision', 50)->nullable();
            $table->text('description')->nullable();

            $table->string('disk', 50);
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('size');
            $table->char('checksum', 64);

            $table->boolean('is_current')->default(true);

            $table->string('status', 20)
                ->default(DocumentStatus::Active->value);

            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->date('issued_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign(
                ['organization_id', 'equipment_id'],
                'equipment_documents_org_equipment_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('equipments')
                ->restrictOnDelete();

            $table->index(
                ['organization_id', 'equipment_id', 'document_type'],
                'equipment_documents_org_equipment_type_index',
            );

            $table->index(
                ['organization_id', 'equipment_id', 'document_group'],
                'equipment_documents_org_group_index',
            );

            $table->index(
                ['organization_id', 'equipment_id', 'is_current'],
                'equipment_documents_org_current_index',
            );

            $table->unique(
                ['organization_id', 'equipment_id', 'path'],
                'equipment_documents_org_equipment_path_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_documents');
    }
};
