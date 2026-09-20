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
        DB::table('defect_assessment_quantities')
            ->select(['id', 'defect_assessment_id', 'position'])
            ->orderBy('defect_assessment_id')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy('defect_assessment_id')
            ->each(function ($rows): void {
                foreach ($rows->values() as $index => $row) {
                    DB::table('defect_assessment_quantities')
                        ->where('id', $row->id)
                        ->update(['position' => $index + 1]);
                }
            });

        DB::table('defect_assessments')
            ->whereNotNull('quantity_snapshot')
            ->select(['id', 'quantity_snapshot'])
            ->orderBy('id')
            ->each(function (object $row): void {
                $snapshot = json_decode((string) $row->quantity_snapshot, true, flags: JSON_THROW_ON_ERROR);
                if (! is_array($snapshot) || is_array($snapshot['items'] ?? null)) {
                    return;
                }

                $item = $snapshot;
                $item['position'] ??= 1;
                $item['description'] ??= null;

                DB::table('defect_assessments')
                    ->where('id', $row->id)
                    ->update(['quantity_snapshot' => json_encode([
                        'source' => 'quantity_snapshot',
                        'snapshot_version' => 2,
                        'category' => $snapshot['category'] ?? null,
                        'measurement_unit' => $snapshot['measurement_unit'] ?? null,
                        'item_count' => 1,
                        'total' => (string) ($snapshot['total'] ?? '0'),
                        'items' => [$item],
                    ], JSON_THROW_ON_ERROR)]);
            });

        Schema::table('defect_assessment_quantities', function (Blueprint $table): void {
            $table->dropUnique('assessment_quantities_org_assessment_unique');
            $table->dropIndex('assessment_quantities_org_assessment_position_index');
            $table->unique(
                ['organization_id', 'defect_assessment_id', 'position'],
                'assessment_quantities_org_assessment_position_unique',
            );
        });
    }

    public function down(): void
    {
        throw new RuntimeException('A composição de quantitativos por múltiplos itens é irreversível.');
    }
};
