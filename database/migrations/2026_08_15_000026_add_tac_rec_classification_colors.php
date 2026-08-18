<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $sets = [
            'TAC' => ['prefix' => 'TA', 'name' => 'TAC'],
            'REC' => ['prefix' => 'IE', 'name' => 'REC'],
        ];
        $colors = [
            1 => '#FF0000',
            2 => '#FFC000',
            3 => '#FFFF00',
            4 => '#92D050',
            5 => '#0070C0',
        ];

        DB::table('defect_categories')
            ->whereIn('code', array_keys($sets))
            ->orderBy('id')
            ->each(function (object $category) use ($sets, $colors): void {
                $set = $sets[$category->code];

                foreach ($colors as $position => $color) {
                    $code = $set['prefix'].'-'.$position;
                    $classification = DB::table('defect_classifications')
                        ->where('organization_id', $category->organization_id)
                        ->where('defect_category_id', $category->id)
                        ->where('code', $code)
                        ->first();

                    if ($classification === null) {
                        DB::table('defect_classifications')->insert([
                            'public_id' => (string) Str::ulid(),
                            'organization_id' => $category->organization_id,
                            'defect_category_id' => $category->id,
                            'code' => $code,
                            'name' => $code,
                            'description' => null,
                            'color' => $color,
                            'status' => 'active',
                            'position' => $position,
                            'severity_rank' => $position,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        DB::table('defect_classifications')
                            ->where('id', $classification->id)
                            ->update(['color' => $color]);
                    }
                }
            });
    }

    public function down(): void
    {
        // The previous values are not recoverable without restoring a backup.
    }
};
