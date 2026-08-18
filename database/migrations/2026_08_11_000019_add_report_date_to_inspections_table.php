<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table): void {
            $table->date('report_date')->nullable()->after('report_generated_at');
        });

        DB::table('inspections')
            ->whereNull('report_date')
            ->where(function ($query): void {
                $query
                    ->whereNotNull('report_generated_at')
                    ->orWhereNotNull('released_at');
            })
            ->orderBy('id')
            ->chunkById(100, function ($inspections): void {
                foreach ($inspections as $inspection) {
                    $sourceDate = $inspection->report_generated_at ?? $inspection->released_at;

                    DB::table('inspections')
                        ->where('id', $inspection->id)
                        ->whereNull('report_date')
                        ->update([
                            'report_date' => CarbonImmutable::parse($sourceDate)->toDateString(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table): void {
            $table->dropColumn('report_date');
        });
    }
};
