<?php

declare(strict_types=1);

namespace App\Services\Defects;

use App\Enums\DefectAssessmentStatus;
use App\Enums\InspectionStatus;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Inspection;

final class ResolvePreviousDefectAssessment
{
    public function handle(Defect $defect, Inspection $inspection): ?DefectAssessment
    {
        $entry = app(InspectionAssessmentResolver::class)->entry($inspection, $defect->id);
        if ($entry !== null) {
            return $entry->sourceAssessment;
        }

        $cursor = $inspection->previousInspection;

        while ($cursor !== null) {
            if ($cursor->status !== InspectionStatus::Canceled) {
                $entry = app(InspectionAssessmentResolver::class)->entry($cursor, $defect->id);
                if ($entry?->requires_reinspection === false) {
                    return $entry->sourceAssessment;
                }
                $assessment = DefectAssessment::query()
                    ->forOrganization($defect->organization_id)
                    ->where('defect_id', $defect->getKey())
                    ->where('inspection_id', $cursor->getKey())
                    ->where('status', DefectAssessmentStatus::Complete->value)
                    ->first();

                if ($assessment !== null) {
                    return $assessment;
                }
            }

            $cursor = $cursor->previousInspection;
        }

        return null;
    }
}
