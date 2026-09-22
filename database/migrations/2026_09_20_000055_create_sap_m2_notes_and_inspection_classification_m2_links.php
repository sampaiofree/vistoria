<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sap_m2_notes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('equipment_id')->constrained('equipments')->restrictOnDelete();
            $table->string('sap_number', 100);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'equipment_id', 'sap_number']);
        });

        Schema::create('inspection_classification_m2_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            $table->string('category', 10);
            $table->string('classification_code', 20);
            $table->foreignId('sap_m2_note_id')->constrained('sap_m2_notes')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['inspection_id', 'category', 'classification_code'], 'inspection_classification_m2_group_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_classification_m2_links');
        Schema::dropIfExists('sap_m2_notes');
    }
};
