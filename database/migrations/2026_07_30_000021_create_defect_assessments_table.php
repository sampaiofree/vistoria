<?php

declare(strict_types=1);

use App\Enums\DefectAssessmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defect_assessments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();

            $table->foreignId('organization_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedBigInteger('equipment_id');
            $table->unsignedBigInteger('defect_id');
            $table->unsignedBigInteger('inspection_id');
            $table->unsignedBigInteger('previous_assessment_id')->nullable();

            $table->string('condition', 30);
            $table->string('status', 20)
                ->default(DefectAssessmentStatus::Draft->value);

            $table->string('location_description', 500)->nullable();
            $table->text('comment')->nullable();
            $table->text('recommendation')->nullable();
            $table->text('reason')->nullable();
            $table->text('internal_notes')->nullable();

            $table->json('defect_snapshot')->nullable();
            $table->unsignedSmallInteger('snapshot_version')->default(1);
            $table->timestamp('assessed_at')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['organization_id', 'equipment_id', 'id'],
                'assessments_org_equipment_id_unique',
            );

            $table->foreign(
                ['organization_id', 'equipment_id', 'defect_id'],
                'assessments_org_equipment_defect_foreign',
            )
                ->references(['organization_id', 'equipment_id', 'id'])
                ->on('defects')
                ->restrictOnDelete();

            $table->foreign(
                ['organization_id', 'equipment_id', 'inspection_id'],
                'assessments_org_equipment_inspection_foreign',
            )
                ->references(['organization_id', 'equipment_id', 'id'])
                ->on('inspections')
                ->restrictOnDelete();

            $table->foreign(
                ['organization_id', 'equipment_id', 'previous_assessment_id'],
                'assessments_org_equipment_previous_assessment_foreign',
            )
                ->references(['organization_id', 'equipment_id', 'id'])
                ->on('defect_assessments')
                ->restrictOnDelete();

            $table->unique(
                ['organization_id', 'defect_id', 'inspection_id'],
                'defect_assessments_defect_inspection_unique',
            );

            $table->index(
                ['organization_id', 'inspection_id', 'status'],
                'assessments_org_inspection_status_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defect_assessments');
    }
};
