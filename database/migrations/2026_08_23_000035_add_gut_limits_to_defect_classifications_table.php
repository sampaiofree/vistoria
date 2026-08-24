<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('defect_classifications', function (Blueprint $table): void {
            $table->unsignedBigInteger('lower_limit')->nullable()->after('severity_rank');
            $table->unsignedBigInteger('upper_limit')->nullable()->after('lower_limit');
            $table->index(
                ['organization_id', 'defect_category_id', 'status', 'lower_limit', 'upper_limit'],
                'defect_classifications_gut_range_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('defect_classifications', function (Blueprint $table): void {
            $table->dropIndex('defect_classifications_gut_range_index');
            $table->dropColumn(['lower_limit', 'upper_limit']);
        });
    }
};
