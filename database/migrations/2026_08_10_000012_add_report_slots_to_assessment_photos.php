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
            $table->unsignedTinyInteger('report_slot')->nullable()->after('is_primary');
            $table->unique(['defect_assessment_id', 'report_slot'], 'assessment_photos_assessment_report_slot_unique');
        });

        DB::table('assessment_photos')
            ->where('is_primary', true)
            ->whereNull('deleted_at')
            ->update(['report_slot' => 1]);

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
                ->get();

            $slotOne = $photos->firstWhere('report_slot', 1)
                ?? $photos->firstWhere('processing_status', 'ready');

            if ($slotOne !== null) {
                DB::table('assessment_photos')
                    ->where('defect_assessment_id', $assessmentId)
                    ->where('id', $slotOne->id)
                    ->update(['report_slot' => 1, 'is_primary' => true]);
            }

            $slotTwo = $photos
                ->first(fn (object $photo): bool => $photo->id !== $slotOne?->id
                    && $photo->report_slot !== 1
                    && $photo->processing_status === 'ready');

            if ($slotTwo !== null) {
                DB::table('assessment_photos')
                    ->where('defect_assessment_id', $assessmentId)
                    ->where('id', $slotTwo->id)
                    ->update(['report_slot' => 2, 'is_primary' => false]);
            }
        }

        $obsoleteMarkerPhotoIds = DB::table('inspection_location_marker_photos as links')
            ->join('assessment_photos as photos', 'photos.id', '=', 'links.assessment_photo_id')
            ->where(function ($query): void {
                $query->whereNull('photos.report_slot')->orWhereNotIn('photos.report_slot', [1, 2]);
            })
            ->pluck('links.id');

        if ($obsoleteMarkerPhotoIds->isNotEmpty()) {
            DB::table('inspection_location_marker_photos')->whereIn('id', $obsoleteMarkerPhotoIds)->delete();
        }
    }

    public function down(): void
    {
        Schema::table('assessment_photos', function (Blueprint $table): void {
            $table->dropUnique('assessment_photos_assessment_report_slot_unique');
            $table->dropColumn('report_slot');
        });
    }
};
