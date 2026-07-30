<?php

declare(strict_types=1);

namespace App\Services\Defects;

use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectAssessmentStatus;
use App\Enums\DefectStatus;
use App\Enums\InspectionStatus;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class DefectStatusSynchronizer
{
    public function handle(Defect $defect, ?User $actor = null): Defect
    {
        return DB::transaction(function () use ($defect, $actor): Defect {
            $defect = Defect::query()
                ->forOrganization($defect->organization_id)
                ->lockForUpdate()
                ->findOrFail($defect->getKey());

            if ($defect->status === DefectStatus::Archived) {
                return $defect->refresh();
            }

            $latestAssessment = $this->latestValidAssessment($defect);

            if ($latestAssessment === null) {
                $defect->update([
                    'status' => DefectStatus::Active,
                    'repaired_at' => null,
                    'updated_by' => $actor?->getKey() ?? $defect->updated_by,
                ]);

                return $defect->refresh();
            }

            $attributes = [
                'updated_by' => $actor?->getKey() ?? $defect->updated_by,
            ];

            if ($latestAssessment->condition === DefectAssessmentCondition::Repaired) {
                $attributes['status'] = DefectStatus::Repaired;
                $attributes['repaired_at'] = $latestAssessment->assessed_at;
            } else {
                $attributes['status'] = DefectStatus::Active;
                $attributes['repaired_at'] = null;
            }

            $defect->update($attributes);

            return $defect->refresh();
        });
    }

    private function latestValidAssessment(Defect $defect): ?DefectAssessment
    {
        $assessments = DefectAssessment::query()
            ->where('organization_id', $defect->organization_id)
            ->where('defect_id', $defect->getKey())
            ->where('status', DefectAssessmentStatus::Complete->value)
            ->whereHas('inspection', fn ($query) => $query->where('status', '!=', InspectionStatus::Canceled->value))
            ->with(['inspection'])
            ->get();

        if ($assessments->isEmpty()) {
            return null;
        }

        return $this->sortAssessments($assessments)->last();
    }

    /**
     * @param  Collection<int, DefectAssessment>  $assessments
     */
    private function sortAssessments(Collection $assessments): Collection
    {
        return $assessments
            ->sort(function (DefectAssessment $left, DefectAssessment $right): int {
                return $this->sortKey($left) <=> $this->sortKey($right);
            })
            ->values();
    }

    /**
     * @return array{0:int, 1:int, 2:int}
     */
    private function sortKey(DefectAssessment $assessment): array
    {
        $inspection = $assessment->inspection;

        $inspectionKey = $inspection?->inspected_on?->getTimestamp()
            ?? $inspection?->scheduled_for?->getTimestamp()
            ?? $inspection?->created_at?->getTimestamp()
            ?? $inspection?->getKey()
            ?? 0;

        $assessmentKey = $assessment->assessed_at?->getTimestamp()
            ?? $assessment->created_at?->getTimestamp()
            ?? $assessment->getKey()
            ?? 0;

        return [
            (int) $inspectionKey,
            (int) $assessmentKey,
            (int) $assessment->getKey(),
        ];
    }
}
