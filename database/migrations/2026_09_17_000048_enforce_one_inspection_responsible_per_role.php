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
        DB::transaction(function (): void {
            $responsibles = DB::table('inspection_responsibles')
                ->orderBy('inspection_id')
                ->orderBy('responsibility')
                ->orderByDesc('is_primary')
                ->orderBy('assigned_at')
                ->orderBy('id')
                ->get(['id', 'inspection_id', 'responsibility']);

            $responsibles
                ->groupBy(fn (object $responsible): string => $responsible->inspection_id.'|'.$responsible->responsibility)
                ->each(function ($group): void {
                    $kept = $group->first();

                    DB::table('inspection_responsibles')
                        ->where('id', $kept->id)
                        ->update(['is_primary' => true]);

                    $duplicates = $group->skip(1)->pluck('id');

                    if ($duplicates->isNotEmpty()) {
                        DB::table('inspection_responsibles')->whereIn('id', $duplicates)->delete();
                    }
                });
        });

        Schema::table('inspection_responsibles', function (Blueprint $table): void {
            $table->unique(['inspection_id', 'responsibility'], 'inspection_responsibles_role_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inspection_responsibles', function (Blueprint $table): void {
            $table->dropUnique('inspection_responsibles_role_unique');
        });
    }
};
