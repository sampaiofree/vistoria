<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('inspections')
            ->select(['id', 'organization_id'])
            ->orderBy('id')
            ->chunkById(250, function ($inspections) use ($now): void {
                $rows = [];

                foreach ($inspections as $inspection) {
                    foreach ([1, 2] as $position) {
                        $rows[] = [
                            'public_id' => (string) Str::ulid(),
                            'organization_id' => $inspection->organization_id,
                            'inspection_id' => $inspection->id,
                            'position' => $position,
                            'comment' => null,
                            'recommendation' => null,
                            'created_by' => null,
                            'updated_by' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                DB::table('inspection_overview_blocks')->insertOrIgnore($rows);
            });
    }

    public function down(): void
    {
        // Os blocos podem receber conteúdo após a implantação. Eles não são
        // apagados isoladamente; a migration anterior remove as tabelas.
    }
};
