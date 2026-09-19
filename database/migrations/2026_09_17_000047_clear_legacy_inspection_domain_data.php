<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Development data intentionally has no compatibility path for the retired workflow.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('inspection_location_marker_photos')->delete();
            DB::table('inspection_location_markers')->delete();
            DB::table('inspection_location_maps')->delete();

            DB::table('inspection_overview_photos')->delete();
            DB::table('inspection_overview_blocks')->delete();

            DB::table('defect_assessment_quantities')->delete();
            DB::table('assessment_photos')->delete();
            DB::table('defect_assessments')->update(['previous_assessment_id' => null]);
            DB::table('defect_assessments')->delete();
            DB::table('defect_relations')->delete();
            DB::table('defects')->delete();
            DB::table('defect_code_sequences')->delete();

            DB::table('inspection_reference_documents')->delete();
            DB::table('inspection_status_histories')->delete();
            DB::table('inspection_responsibles')->delete();
            DB::table('inspections')->update(['previous_inspection_id' => null]);
            DB::table('inspections')->delete();
        });
    }

    public function down(): void
    {
        // The development-data cleanup is intentionally irreversible.
    }
};
