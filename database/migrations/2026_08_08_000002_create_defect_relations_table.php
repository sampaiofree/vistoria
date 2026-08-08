<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defect_relations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('equipment_id');
            $table->unsignedBigInteger('source_defect_id');
            $table->unsignedBigInteger('target_defect_id');
            $table->string('relation_type', 30);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->foreign(['organization_id', 'equipment_id', 'source_defect_id'], 'defect_relations_source_foreign')
                ->references(['organization_id', 'equipment_id', 'id'])->on('defects')->restrictOnDelete();
            $table->foreign(['organization_id', 'equipment_id', 'target_defect_id'], 'defect_relations_target_foreign')
                ->references(['organization_id', 'equipment_id', 'id'])->on('defects')->restrictOnDelete();
            $table->unique(['source_defect_id', 'target_defect_id', 'relation_type'], 'defect_relations_unique');
            $table->index(['organization_id', 'equipment_id', 'relation_type'], 'defect_relations_scope_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defect_relations');
    }
};
