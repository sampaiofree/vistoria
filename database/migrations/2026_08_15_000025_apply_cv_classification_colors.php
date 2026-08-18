<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'CV-1' => '#FF0000',
            'CV-2' => '#FFC000',
            'CV-3' => '#FFFF00',
            'CV-4' => '#92D050',
            'CV-5' => '#0070C0',
        ] as $code => $color) {
            DB::table('defect_classifications')
                ->where('code', $code)
                ->update(['color' => $color]);
        }
    }

    public function down(): void
    {
        // The previous values are not recoverable without restoring a backup.
    }
};
