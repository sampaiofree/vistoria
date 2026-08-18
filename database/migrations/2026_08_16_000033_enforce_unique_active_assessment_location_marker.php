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
        $duplicates = DB::table('inspection_location_markers')
            ->select(['organization_id', 'inspection_id', 'defect_assessment_id'])
            ->selectRaw('COUNT(*) AS marker_count')
            ->whereNull('deleted_at')
            ->whereNotNull('defect_assessment_id')
            ->groupBy('organization_id', 'inspection_id', 'defect_assessment_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $summary = $duplicates
                ->map(fn (object $row): string => sprintf(
                    'inspeção %d / avaliação %d (%d marcações)',
                    $row->inspection_id,
                    $row->defect_assessment_id,
                    $row->marker_count,
                ))
                ->implode('; ');

            throw new RuntimeException('Existem avarias vinculadas mais de uma vez. Corrija antes de executar a migração: '.$summary);
        }

        Schema::table('inspection_location_markers', function (Blueprint $table): void {
            $table->unsignedTinyInteger('active_slot')->nullable()->default(1)->after('defect_assessment_id');
        });

        DB::table('inspection_location_markers')
            ->whereNotNull('deleted_at')
            ->update(['active_slot' => null]);

        Schema::table('inspection_location_markers', function (Blueprint $table): void {
            $table->unique(
                ['organization_id', 'inspection_id', 'defect_assessment_id', 'active_slot'],
                'location_markers_active_assessment_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('inspection_location_markers', function (Blueprint $table): void {
            $table->dropUnique('location_markers_active_assessment_unique');
            $table->dropColumn('active_slot');
        });
    }
};
