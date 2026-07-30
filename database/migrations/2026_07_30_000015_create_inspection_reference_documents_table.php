<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_reference_documents', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedBigInteger('inspection_id');
            $table->unsignedBigInteger('equipment_document_id');

            $table->foreignId('added_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('created_at');

            $table->foreign(
                ['organization_id', 'inspection_id'],
                'inspection_ref_docs_org_inspection_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('inspections')
                ->restrictOnDelete();

            $table->foreign(
                ['organization_id', 'equipment_document_id'],
                'inspection_ref_docs_org_document_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('equipment_documents')
                ->restrictOnDelete();

            $table->unique(
                ['inspection_id', 'equipment_document_id'],
                'inspection_ref_docs_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_reference_documents');
    }
};
