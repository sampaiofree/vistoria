<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /** @var Collection<int, Collection<int, object>> $groups */
        $groups = DB::table('defect_assessment_quantities')
            ->orderBy('defect_assessment_id')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy('defect_assessment_id');

        foreach ($groups as $assessmentId => $rows) {
            if ($rows->pluck('measurement_unit')->unique()->count() > 1) {
                throw new RuntimeException(
                    "A avaliação {$assessmentId} possui quantitativos em unidades diferentes e precisa ser consolidada antes da migration.",
                );
            }
        }

        foreach ($groups as $rows) {
            $primary = $rows->first();
            $value = round($rows->sum(
                fn (object $row): float => (float) $row->quantity * (float) $row->measurement_value,
            ), 4);

            DB::table('defect_assessment_quantities')
                ->where('id', $primary->id)
                ->update([
                    'quantity' => 1,
                    'measurement_value' => $value,
                    'position' => 1,
                ]);

            DB::table('defect_assessment_quantities')
                ->where('defect_assessment_id', $primary->defect_assessment_id)
                ->where('id', '!=', $primary->id)
                ->delete();
        }

        Schema::table('defect_assessment_quantities', function (Blueprint $table): void {
            $table->unique(
                ['organization_id', 'defect_assessment_id'],
                'assessment_quantities_org_assessment_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('defect_assessment_quantities', function (Blueprint $table): void {
            $table->dropUnique('assessment_quantities_org_assessment_unique');
        });
    }
};
