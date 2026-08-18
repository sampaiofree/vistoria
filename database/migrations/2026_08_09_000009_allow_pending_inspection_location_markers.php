<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_location_markers', function (Blueprint $table): void {
            $table->unsignedBigInteger('defect_assessment_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('inspection_location_markers')->whereNull('defect_assessment_id')->delete();

        Schema::table('inspection_location_markers', function (Blueprint $table): void {
            $table->unsignedBigInteger('defect_assessment_id')->nullable(false)->change();
        });
    }
};
