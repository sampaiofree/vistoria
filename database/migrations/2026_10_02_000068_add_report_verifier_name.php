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
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('report_verifier_name', 150)->nullable();
        });

        // Keep the name already shown as Verificado on finalized reports.
        DB::table('inspections')->whereIn('status', ['released', 'canceled'])
            ->select('id', 'report_responsibles_snapshot')
            ->chunkById(500, function ($inspections): void {
                $inspectors = DB::table('inspection_responsibles as responsibles')
                    ->leftJoin('users', 'users.id', '=', 'responsibles.user_id')
                    ->whereIn('responsibles.inspection_id', $inspections->pluck('id'))
                    ->where('responsibles.responsibility', 'reviewer')
                    ->orderByDesc('responsibles.is_primary')
                    ->orderBy('responsibles.created_at')
                    ->orderBy('responsibles.id')
                    ->get(['responsibles.inspection_id', 'users.name'])
                    ->groupBy('inspection_id');

                foreach ($inspections as $inspection) {
                    $snapshot = $inspection->report_responsibles_snapshot === null
                        ? []
                        : json_decode($inspection->report_responsibles_snapshot, true, 512, JSON_THROW_ON_ERROR);
                    $snapshot['reviewer'] = $inspectors->get($inspection->id)?->first()?->name;
                    DB::table('inspections')->where('id', $inspection->id)
                        ->update(['report_responsibles_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
                }
            });
    }

    public function down(): void
    {
        DB::table('inspections')->whereNotNull('report_responsibles_snapshot')
            ->select('id', 'report_responsibles_snapshot')
            ->chunkById(500, function ($inspections): void {
                foreach ($inspections as $inspection) {
                    $snapshot = json_decode($inspection->report_responsibles_snapshot, true, 512, JSON_THROW_ON_ERROR);
                    unset($snapshot['reviewer']);
                    DB::table('inspections')->where('id', $inspection->id)
                        ->update(['report_responsibles_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
                }
            });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn('report_verifier_name');
        });
    }
};
