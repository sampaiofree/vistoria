<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'CV' => ['CV-1' => 'Grave', 'CV-2' => 'Alta', 'CV-3' => 'Média', 'CV-4' => 'Baixa', 'CV-5' => 'Muito baixa'],
            'TAC' => ['TA-1' => 'Grave', 'TA-2' => 'Alta', 'TA-3' => 'Média'],
            'REC' => ['IE-1' => 'Grave', 'IE-2' => 'Alta', 'IE-3' => 'Média', 'IE-4' => 'Baixa', 'IE-5' => 'Muito baixa'],
        ] as $categoryCode => $classifications) {
            foreach ($classifications as $code => $name) {
                DB::table('defect_classifications')
                    ->where('code', $code)
                    ->where('name', $code)
                    ->whereIn('defect_category_id', DB::table('defect_categories')->where('code', $categoryCode)->select('id'))
                    ->update(['name' => $name, 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // Existing custom names cannot be distinguished after a rollback.
    }
};
