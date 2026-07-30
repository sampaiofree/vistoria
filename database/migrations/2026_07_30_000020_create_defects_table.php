<?php

declare(strict_types=1);

use App\Enums\DefectStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defects', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedBigInteger('equipment_id');
            $table->unsignedBigInteger('first_inspection_id');

            $table->string('code', 150);
            $table->string('category', 30);
            $table->unsignedInteger('sequence_number');

            $table->string('title', 200);
            $table->text('origin_description')->nullable();

            $table->string('status', 30)
                ->default(DefectStatus::Active->value);

            $table->timestamp('repaired_at')->nullable();
            $table->timestamp('archived_at')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->foreign(
                ['organization_id', 'equipment_id'],
                'defects_org_equipment_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('equipments')
                ->restrictOnDelete();

            $table->foreign(
                ['organization_id', 'equipment_id', 'first_inspection_id'],
                'defects_org_equipment_first_inspection_foreign',
            )
                ->references(['organization_id', 'equipment_id', 'id'])
                ->on('inspections')
                ->restrictOnDelete();

            $table->unique(
                ['organization_id', 'code'],
                'defects_org_code_unique',
            );

            $table->unique(
                ['organization_id', 'equipment_id', 'id'],
                'defects_org_equipment_id_unique',
            );

            $table->unique(
                ['organization_id', 'equipment_id', 'category', 'sequence_number'],
                'defects_sequence_unique',
            );

            $table->index(
                ['organization_id', 'equipment_id', 'status'],
                'defects_org_equipment_status_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defects');
    }
};
