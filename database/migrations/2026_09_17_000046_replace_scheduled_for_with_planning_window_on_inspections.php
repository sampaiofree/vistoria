<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table): void {
            $table->date('planned_start_on')->nullable()->after('atmospheric_classification');
            $table->date('planned_end_on')->nullable()->after('planned_start_on');
        });

        DB::table('inspections')
            ->whereNotNull('scheduled_for')
            ->update([
                'planned_start_on' => new Expression('scheduled_for'),
                'planned_end_on' => new Expression('scheduled_for'),
            ]);

        Schema::table('inspections', function (Blueprint $table): void {
            $table->dropIndex('inspections_dashboard_priority_index');
            $table->dropIndex('inspections_org_schedule_index');
            $table->dropColumn('scheduled_for');

            $table->index(
                ['organization_id', 'planned_start_on'],
                'inspections_org_planned_start_index',
            );
            $table->index(
                ['organization_id', 'status', 'planned_end_on'],
                'inspections_dashboard_planned_end_index',
            );
        });
    }

    public function down(): void
    {
        throw new RuntimeException(
            'A janela de planejamento não pode ser revertida para uma única data sem perda de informação.',
        );
    }
};
