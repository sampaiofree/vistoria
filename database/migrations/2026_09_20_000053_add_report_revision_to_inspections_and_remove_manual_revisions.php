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
            // Existing development databases are reset before this migration is used.
            $table->unsignedInteger('report_revision')->nullable()->after('report_date');
            $table->unique(
                ['organization_id', 'equipment_id', 'report_revision'],
                'inspections_org_equipment_report_revision_unique',
            );
        });

        Schema::dropIfExists('equipment_revisions');
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table): void {
            $table->dropUnique('inspections_org_equipment_report_revision_unique');
            $table->dropColumn('report_revision');
        });
    }
};
