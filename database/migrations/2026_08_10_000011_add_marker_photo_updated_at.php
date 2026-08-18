<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inspection_location_marker_photos', 'updated_at')) {
            Schema::table('inspection_location_marker_photos', function (Blueprint $table): void {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('inspection_location_marker_photos', 'updated_at')) {
            Schema::table('inspection_location_marker_photos', function (Blueprint $table): void {
                $table->dropColumn('updated_at');
            });
        }
    }
};
