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
            $table->string('label', 240)->nullable()->change();
        });

        DB::table('inspection_location_markers')
            ->whereNotNull('label')
            ->orderBy('id')
            ->chunkById(200, function ($markers): void {
                $assessmentIds = $markers->pluck('defect_assessment_id')->filter()->unique()->values();
                $codes = DB::table('defect_assessments as assessments')
                    ->join('defects', 'defects.id', '=', 'assessments.defect_id')
                    ->whereIn('assessments.id', $assessmentIds)
                    ->pluck('defects.code', 'assessments.id');

                foreach ($markers as $marker) {
                    $defectCode = $codes->get($marker->defect_assessment_id);

                    if ($defectCode !== null && trim((string) $marker->label) === trim((string) $defectCode)) {
                        DB::table('inspection_location_markers')
                            ->where('id', $marker->id)
                            ->update(['label' => null]);
                    }
                }
            });
    }

    public function down(): void
    {
        DB::table('inspection_location_markers')
            ->whereNotNull('label')
            ->orderBy('id')
            ->chunkById(200, function ($markers): void {
                foreach ($markers as $marker) {
                    DB::table('inspection_location_markers')
                        ->where('id', $marker->id)
                        ->update(['label' => mb_substr((string) $marker->label, 0, 120)]);
                }
            });

        Schema::table('inspection_location_markers', function (Blueprint $table): void {
            $table->string('label', 120)->nullable()->change();
        });
    }
};
