<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('defect_assessment_quantities', function (Blueprint $table): void {
            $table->decimal('length', 14, 4)->nullable();
            $table->decimal('height', 14, 4)->nullable();
            $table->decimal('width', 14, 4)->nullable();
            $table->decimal('unit_volume', 42, 12)->nullable();
            $table->decimal('measurement_value', 28, 16)->change();
        });
    }

    public function down(): void
    {
        Schema::table('defect_assessment_quantities', function (Blueprint $table): void {
            $table->dropColumn(['length', 'height', 'width', 'unit_volume']);
            $table->decimal('measurement_value', 16, 4)->change();
        });
    }
};
