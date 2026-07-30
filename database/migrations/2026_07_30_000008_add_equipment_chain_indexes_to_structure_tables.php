<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_units', function (Blueprint $table): void {
            $table->unique(
                ['organization_id', 'client_id', 'id'],
                'client_units_org_client_id_unique',
            );
        });

        Schema::table('areas', function (Blueprint $table): void {
            $table->unique(
                ['organization_id', 'client_unit_id', 'id'],
                'areas_org_unit_id_unique',
            );
        });

        Schema::table('subareas', function (Blueprint $table): void {
            $table->unique(
                ['organization_id', 'area_id', 'id'],
                'subareas_org_area_id_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('subareas', function (Blueprint $table): void {
            $table->dropUnique('subareas_org_area_id_unique');
        });

        Schema::table('areas', function (Blueprint $table): void {
            $table->dropUnique('areas_org_unit_id_unique');
        });

        Schema::table('client_units', function (Blueprint $table): void {
            $table->dropUnique('client_units_org_client_id_unique');
        });
    }
};
