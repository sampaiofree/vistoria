<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('defect_location_map_versions', function (Blueprint $table): void {
            $table->string('project_number', 150)->nullable()->after('version');
        });
    }

    public function down(): void
    {
        Schema::table('defect_location_map_versions', function (Blueprint $table): void {
            $table->dropColumn('project_number');
        });
    }
};
