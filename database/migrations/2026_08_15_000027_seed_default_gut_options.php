<?php

declare(strict_types=1);

use App\Enums\GutCriterion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $colors = [
            1 => '#00AEEF',
            2 => '#92D050',
            3 => '#FFFF00',
            4 => '#FFC000',
            5 => '#FF0000',
        ];

        DB::table('defect_categories')
            ->orderBy('id')
            ->each(function (object $category) use ($colors): void {
                foreach (GutCriterion::cases() as $criterion) {
                    foreach ($colors as $score => $color) {
                        $option = DB::table('defect_category_gut_options')
                            ->where('organization_id', $category->organization_id)
                            ->where('defect_category_id', $category->id)
                            ->where('criterion', $criterion->value)
                            ->where('score', $score)
                            ->first();

                        if ($option === null) {
                            DB::table('defect_category_gut_options')->insert([
                                'organization_id' => $category->organization_id,
                                'defect_category_id' => $category->id,
                                'criterion' => $criterion->value,
                                'score' => $score,
                                'color' => $color,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        } else {
                            DB::table('defect_category_gut_options')
                                ->where('id', $option->id)
                                ->update(['color' => $color]);
                        }
                    }
                }
            });
    }

    public function down(): void
    {
        // These defaults may have been edited after deployment; do not delete user data.
    }
};
