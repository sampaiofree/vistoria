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
        Schema::table('defect_assessments', function (Blueprint $table): void {
            $table->text('map_observations')->nullable()->after('location_description');
        });

        // Preserve the text already shown in the map observations of existing reports.
        DB::table('defect_assessment_locations')->orderBy('id')->chunkById(500, function ($locations): void {
            $descriptions = DB::table('defect_assessments')
                ->whereIn('id', $locations->pluck('defect_assessment_id'))
                ->pluck('location_description', 'id');

            foreach ($locations as $location) {
                $observations = $location->label ?? $descriptions->get($location->defect_assessment_id);

                if ($observations !== null) {
                    DB::table('defect_assessments')->where('id', $location->defect_assessment_id)
                        ->update(['map_observations' => $observations]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('defect_assessments', function (Blueprint $table): void {
            $table->dropColumn('map_observations');
        });
    }
};
