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
            $table->index(
                ['organization_id', 'status', 'scheduled_for'],
                'inspections_dashboard_priority_index',
            );
        });

        Schema::table('inspection_responsibles', function (Blueprint $table): void {
            $table->index(
                ['organization_id', 'user_id', 'responsibility', 'inspection_id'],
                'inspection_responsibles_dashboard_index',
            );
        });

        Schema::table('inspection_status_histories', function (Blueprint $table): void {
            $table->index(
                ['organization_id', 'created_at'],
                'inspection_histories_dashboard_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('inspection_status_histories', function (Blueprint $table): void {
            $table->dropIndex('inspection_histories_dashboard_index');
        });

        Schema::table('inspection_responsibles', function (Blueprint $table): void {
            $table->dropIndex('inspection_responsibles_dashboard_index');
        });

        Schema::table('inspections', function (Blueprint $table): void {
            $table->dropIndex('inspections_dashboard_priority_index');
        });
    }
};
