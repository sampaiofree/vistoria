<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defect_code_sequences', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedBigInteger('equipment_id');
            $table->string('category', 30);
            $table->unsignedInteger('last_number')->default(0);

            $table->timestamps();

            $table->foreign(
                ['organization_id', 'equipment_id'],
                'defect_sequences_org_equipment_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('equipments')
                ->restrictOnDelete();

            $table->unique(
                ['organization_id', 'equipment_id', 'category'],
                'defect_sequences_scope_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defect_code_sequences');
    }
};
