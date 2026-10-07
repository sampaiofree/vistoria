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
            $table->string('report_equipment_name', 150)->nullable()->after('external_report_number');
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table): void {
            $table->dropColumn('report_equipment_name');
        });
    }
};
