<?php

declare(strict_types=1);

use App\Enums\InspectionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table): void {
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('equipment_id');
            $table->unsignedBigInteger('previous_inspection_id')->nullable();
            $table->string('number', 40)->nullable();
            $table->string('inspection_type', 30)
                ->default(InspectionType::Initial->value);
            $table->string('service_order', 100)->nullable();
            $table->string('external_report_number', 150)->nullable();
            $table->string('procedure_number', 150)->nullable();
            $table->string('atmospheric_classification', 50)->nullable();
            $table->date('scheduled_for')->nullable();
            $table->date('inspected_on')->nullable();
            $table->json('context_snapshot');
            $table->unsignedSmallInteger('snapshot_version')->default(1);
            $table->text('general_notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('field_completed_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('report_generated_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->unique(['organization_id', 'number'], 'inspections_org_number_unique');

            $table->foreign(
                ['organization_id', 'equipment_id'],
                'inspections_org_equipment_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('equipments')
                ->restrictOnDelete();

            $table->foreign(
                ['organization_id', 'previous_inspection_id'],
                'inspections_org_previous_foreign',
            )
                ->references(['organization_id', 'id'])
                ->on('inspections')
                ->restrictOnDelete();

            $table->index(
                ['organization_id', 'equipment_id', 'status'],
                'inspections_org_equipment_status_index',
            );

            $table->index(
                ['organization_id', 'scheduled_for'],
                'inspections_org_schedule_index',
            );

            $table->index(
                ['organization_id', 'inspected_on'],
                'inspections_org_inspected_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table): void {
            if (DB::getDriverName() === 'sqlite') {
                $table->dropForeign(['organization_id', 'previous_inspection_id']);
                $table->dropForeign(['organization_id', 'equipment_id']);
            } else {
                $table->dropForeign('inspections_org_previous_foreign');
                $table->dropForeign('inspections_org_equipment_foreign');
            }

            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('created_by');

            $table->dropIndex('inspections_org_inspected_index');
            $table->dropIndex('inspections_org_schedule_index');
            $table->dropIndex('inspections_org_equipment_status_index');
            $table->dropUnique('inspections_public_id_unique');
            $table->dropUnique('inspections_org_number_unique');
            $table->dropColumn([
                'public_id',
                'equipment_id',
                'previous_inspection_id',
                'number',
                'inspection_type',
                'service_order',
                'external_report_number',
                'procedure_number',
                'atmospheric_classification',
                'scheduled_for',
                'inspected_on',
                'context_snapshot',
                'snapshot_version',
                'general_notes',
                'started_at',
                'field_completed_at',
                'reviewed_at',
                'approved_at',
                'report_generated_at',
                'released_at',
                'canceled_at',
            ]);
        });
    }
};
