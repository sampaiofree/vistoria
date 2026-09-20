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
        Schema::table('defect_assessment_quantities', function (Blueprint $table): void {
            $table->decimal('quantity', 38, 16)->default(1)->change();
            $table->decimal('measurement_value', 38, 16)->change();
            $table->string('category', 12)->nullable();
            $table->string('calculation_type', 40)->nullable();
            $table->string('rec_element', 40)->nullable();
            $table->json('inputs')->nullable();
            $table->decimal('unit_value', 38, 16)->nullable();
            $table->string('mode', 20)->nullable();
            $table->unsignedInteger('formula_version')->nullable();
            $table->json('formula_snapshot')->nullable();
            $table->index(
                ['organization_id', 'category', 'calculation_type'],
                'assessment_quantities_org_category_type_index',
            );
        });

        Schema::table('defect_assessments', function (Blueprint $table): void {
            $table->json('quantity_snapshot')->nullable();
        });

        DB::table('defect_assessment_quantities')
            ->join('defect_assessments', 'defect_assessments.id', '=', 'defect_assessment_quantities.defect_assessment_id')
            ->join('defects', 'defects.id', '=', 'defect_assessments.defect_id')
            ->select('defect_assessment_quantities.id', 'defects.category')
            ->orderBy('defect_assessment_quantities.id')
            ->each(function (object $row): void {
                $category = match (strtolower((string) $row->category)) {
                    'civil', 'cv' => 'CV',
                    'tac' => 'TAC',
                    'rec' => 'REC',
                    default => null,
                };

                if ($category === null) {
                    return;
                }

                DB::table('defect_assessment_quantities')
                    ->where('id', $row->id)
                    ->update([
                        'category' => $category,
                        'calculation_type' => match ($category) {
                            'CV' => 'civil_volume',
                            'TAC' => 'tac_area',
                            'REC' => 'rec_weight',
                        },
                        'mode' => 'manual',
                        'formula_version' => 0,
                        'inputs' => json_encode([], JSON_THROW_ON_ERROR),
                        'formula_snapshot' => json_encode([
                            'source' => 'legacy_quantity',
                            'formula_version' => 0,
                            'category' => $category,
                        ], JSON_THROW_ON_ERROR),
                    ]);
            });
    }

    public function down(): void
    {
        throw new RuntimeException('A estrutura e a precisão dos quantitativos nativos são irreversíveis.');
    }
};
