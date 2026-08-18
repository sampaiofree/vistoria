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
            $table->foreignId('classification_profile_id')->nullable()->after('equipment_id')->constrained('classification_profiles')->restrictOnDelete();
            $table->json('classification_profile_snapshot')->nullable()->after('classification_profile_id');
        });

        Schema::table('defect_assessments', function (Blueprint $table): void {
            $table->string('item_description', 180)->nullable()->after('location_description');
            $table->string('project_reference', 180)->nullable()->after('item_description');
            $table->boolean('impacts_activity')->nullable()->after('project_reference');
            $table->unsignedTinyInteger('gravity')->nullable()->after('impacts_activity');
            $table->unsignedTinyInteger('urgency')->nullable()->after('gravity');
            $table->unsignedTinyInteger('trend')->nullable()->after('urgency');
            $table->unsignedInteger('gut_score')->nullable()->after('trend');
            $table->string('classification_code', 20)->nullable()->after('gut_score');
            $table->unsignedTinyInteger('classification_priority')->nullable()->after('classification_code');
            $table->unsignedInteger('deadline_months')->nullable()->after('classification_priority');
            $table->date('recommended_due_date')->nullable()->after('deadline_months');
            $table->json('classification_snapshot')->nullable()->after('recommended_due_date');
            $table->timestamp('classified_at')->nullable()->after('classification_snapshot');
            $table->foreignId('classified_by')->nullable()->after('classified_at')->constrained('users')->nullOnDelete();
            $table->index(['organization_id', 'inspection_id', 'classification_code'], 'assessments_org_inspection_classification_index');
        });
    }

    public function down(): void
    {
        Schema::table('defect_assessments', function (Blueprint $table): void {
            $table->dropIndex('assessments_org_inspection_classification_index');
            $table->dropConstrainedForeignId('classified_by');
            $table->dropColumn(['item_description', 'project_reference', 'impacts_activity', 'gravity', 'urgency', 'trend', 'gut_score', 'classification_code', 'classification_priority', 'deadline_months', 'recommended_due_date', 'classification_snapshot', 'classified_at']);
        });
        Schema::table('inspections', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('classification_profile_id');
            $table->dropColumn('classification_profile_snapshot');
        });
    }
};
