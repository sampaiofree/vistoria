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
        Schema::table('assessment_photos', function (Blueprint $table): void {
            $table->dropUnique('assessment_photos_assessment_report_slot_unique');
        });

        Schema::table('assessment_photos', function (Blueprint $table): void {
            $table->dropColumn(['report_slot', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::table('assessment_photos', function (Blueprint $table): void {
            $table->boolean('is_primary')->default(false)->after('position');
            $table->unsignedTinyInteger('report_slot')->nullable()->after('is_primary');
            $table->unique(['defect_assessment_id', 'report_slot'], 'assessment_photos_assessment_report_slot_unique');
        });

        $assessmentIds = DB::table('assessment_photos')
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('defect_assessment_id');

        foreach ($assessmentIds as $assessmentId) {
            $photos = DB::table('assessment_photos')
                ->where('defect_assessment_id', $assessmentId)
                ->whereNull('deleted_at')
                ->orderBy('position')
                ->orderBy('id')
                ->limit(2)
                ->get();

            foreach ($photos as $index => $photo) {
                DB::table('assessment_photos')->where('id', $photo->id)->update([
                    'report_slot' => $index + 1,
                    'is_primary' => $index === 0,
                ]);
            }
        }
    }
};
