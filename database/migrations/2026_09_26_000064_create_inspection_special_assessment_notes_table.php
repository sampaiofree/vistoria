<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_special_assessment_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('defect_assessment_id')->constrained()->cascadeOnDelete();
            $table->string('service', 100)->nullable();
            $table->string('priority', 100)->nullable();
            $table->string('note', 100)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['inspection_id', 'defect_assessment_id'], 'inspection_special_assessment_note_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_special_assessment_notes');
    }
};
