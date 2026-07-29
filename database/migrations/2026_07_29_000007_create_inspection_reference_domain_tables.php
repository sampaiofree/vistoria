<?php

use App\Enums\InspectionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('tag');
            $table->string('name');
            $table->timestamps();
            $table->unique(['organization_id', 'id']);
        });

        Schema::create('equipment_documents', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('equipment_id');
            $table->ulid('document_group');
            $table->string('title');
            $table->string('revision', 50)->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();
            $table->foreign(['organization_id', 'equipment_id'])
                ->references(['organization_id', 'id'])->on('equipments')->restrictOnDelete();
            $table->unique(['organization_id', 'id']);
        });

        Schema::create('inspections', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('equipment_id');
            $table->string('status', 40)->default(InspectionStatus::Planned->value);
            $table->json('context_snapshot')->default('{}');
            $table->timestamps();
            $table->unique(['organization_id', 'id']);
            $table->foreign(['organization_id', 'equipment_id'])
                ->references(['organization_id', 'id'])->on('equipments')->restrictOnDelete();
        });

        Schema::create('inspection_reference_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('inspection_id');
            $table->unsignedBigInteger('equipment_document_id');
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->foreign(['organization_id', 'inspection_id'])
                ->references(['organization_id', 'id'])->on('inspections')->restrictOnDelete();
            $table->foreign(['organization_id', 'equipment_document_id'])
                ->references(['organization_id', 'id'])->on('equipment_documents')->restrictOnDelete();
            $table->unique(['inspection_id', 'equipment_document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_reference_documents');
        Schema::dropIfExists('inspections');
        Schema::dropIfExists('equipment_documents');
        Schema::dropIfExists('equipments');
    }
};
