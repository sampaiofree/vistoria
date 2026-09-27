<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table): void {
            $table->unsignedSmallInteger('reinspection_scope_version')->nullable();
        });

        Schema::create('inspection_defect_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('defect_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_assessment_id')->nullable()->constrained('defect_assessments')->restrictOnDelete();
            $table->boolean('requires_reinspection');
            $table->date('historical_due_date')->nullable();
            $table->timestamps();
            $table->unique(['inspection_id', 'defect_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_defect_scopes');
        Schema::table('inspections', fn (Blueprint $table) => $table->dropColumn('reinspection_scope_version'));
    }
};
