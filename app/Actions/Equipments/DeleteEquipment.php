<?php

declare(strict_types=1);

namespace App\Actions\Equipments;

use App\Models\Equipment;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class DeleteEquipment
{
    /** @var array<int, string> */
    private const RELATED_TABLES = [
        'inspections',
        'defects',
        'equipment_documents',
        'defect_code_sequences',
        'defect_assessments',
        'defect_relations',
        'defect_location_maps',
        'defect_location_map_versions',
        'defect_assessment_locations',
        'sap_m2_notes',
    ];

    /**
     * Returns false when a technical record is linked to the equipment.
     */
    public function handle(Equipment $equipment): bool
    {
        return DB::transaction(function () use ($equipment): bool {
            $lockedEquipment = Equipment::withTrashed()
                ->lockForUpdate()
                ->findOrFail($equipment->getKey());

            if ($this->hasRelatedRecords($lockedEquipment)) {
                return false;
            }

            $lockedEquipment->forceDelete();

            return true;
        });
    }

    public function hasRelatedRecords(Equipment $equipment): bool
    {
        return self::relatedRecordsQuery((int) $equipment->getKey())->exists();
    }

    public static function relatedRecordsExistsQuery(): Builder
    {
        return self::relatedRecordsQuery()->limit(1);
    }

    private static function relatedRecordsQuery(?int $equipmentId = null): Builder
    {
        $tables = self::RELATED_TABLES;
        $query = self::relatedTableQuery(array_shift($tables), $equipmentId);

        foreach ($tables as $table) {
            $query->unionAll(self::relatedTableQuery($table, $equipmentId));
        }

        return $query;
    }

    private static function relatedTableQuery(string $table, ?int $equipmentId): Builder
    {
        $query = DB::table($table)->selectRaw('1');

        if ($equipmentId === null) {
            return $query->whereColumn("{$table}.equipment_id", 'equipments.id');
        }

        return $query->where('equipment_id', $equipmentId);
    }
}
