<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('report_reviewer_name', 150)->nullable();
            $table->string('report_releaser_name', 150)->nullable();
        });
        Schema::table('inspections', function (Blueprint $table): void {
            $table->json('report_responsibles_snapshot')->nullable();
        });

        // Preserve the names currently displayed on legacy finalized reports,
        // without changing their operational assignments or timestamps.
        DB::table('inspections')->whereIn('status', ['released', 'canceled'])
            ->select('id')->chunkById(500, function ($inspections): void {
                $assignments = DB::table('inspection_responsibles as responsibles')
                    ->leftJoin('users', 'users.id', '=', 'responsibles.user_id')
                    ->whereIn('responsibles.inspection_id', $inspections->pluck('id'))
                    ->whereIn('responsibles.responsibility', ['approver', 'releaser'])
                    ->orderByDesc('responsibles.is_primary')->orderBy('responsibles.created_at')->orderBy('responsibles.id')
                    ->get(['responsibles.inspection_id', 'responsibles.responsibility', 'users.name'])
                    ->groupBy('inspection_id');

                foreach ($inspections as $inspection) {
                    $names = ['approver' => null, 'releaser' => null];
                    $roles = $assignments->get($inspection->id, collect())->groupBy('responsibility');
                    foreach ($names as $role => $_) {
                        $names[$role] = $roles->get($role)?->first()?->name;
                    }
                    DB::table('inspections')->where('id', $inspection->id)
                        ->update(['report_responsibles_snapshot' => json_encode($names, JSON_THROW_ON_ERROR)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('inspections', fn (Blueprint $table) => $table->dropColumn('report_responsibles_snapshot'));
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn(['report_reviewer_name', 'report_releaser_name']));
    }
};
