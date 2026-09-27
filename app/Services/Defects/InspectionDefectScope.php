<?php

declare(strict_types=1);

namespace App\Services\Defects;

use App\Enums\DefectStatus;
use App\Enums\InspectionStatus;
use App\Models\Defect;
use App\Models\Inspection;
use Illuminate\Support\Collection;

final class InspectionDefectScope
{
    /**
     * Return the defects that belong to the inspection's operational history.
     *
     * A current assessment always keeps the defect visible for audit (including
     * repaired defects). An inherited defect without a current assessment is
     * eligible only when it is active and originated in the inspection chain.
     *
     * @return Collection<int, Defect>
     */
    public function handle(Inspection $inspection): Collection
    {
        $inspection->loadMissing([
            'previousInspection',
            'equipment.defects.firstInspection',
            'equipment.defects.assessments',
        ]);

        if ($inspection->reinspection_scope_version !== null) {
            $inspection->loadMissing('defectScopes');
            $ids = $inspection->defectScopes->pluck('defect_id')->all();

            return $inspection->equipment->defects
                ->filter(fn (Defect $defect): bool => in_array($defect->id, $ids, true)
                    || $defect->first_inspection_id === $inspection->id
                    || $defect->assessments->contains('inspection_id', $inspection->id))
                ->sortBy('sequence_number')->values();
        }

        $ancestorIds = $this->ancestorInspectionIds($inspection);

        return $inspection->equipment->defects
            ->filter(function (Defect $defect) use ($inspection, $ancestorIds): bool {
                if ($defect->assessments->contains('inspection_id', $inspection->getKey())) {
                    return true;
                }

                if ($defect->first_inspection_id === $inspection->getKey()) {
                    return true;
                }

                return $defect->status === DefectStatus::Active
                    && in_array($defect->first_inspection_id, $ancestorIds, true);
            })
            ->sortBy('sequence_number')
            ->values();
    }

    /** @return array<int, int> */
    public function ancestorInspectionIds(Inspection $inspection): array
    {
        $ids = [];
        $cursor = $inspection->previousInspection;

        while ($cursor !== null) {
            if ($cursor->status !== InspectionStatus::Canceled) {
                $ids[] = (int) $cursor->getKey();
            }

            $cursor->loadMissing('previousInspection');
            $cursor = $cursor->previousInspection;
        }

        return $ids;
    }
}
