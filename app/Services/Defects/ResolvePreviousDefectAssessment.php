<?php

declare(strict_types=1);

namespace App\Services\Defects;

use App\Enums\DefectAssessmentStatus;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Inspection;
use App\Enums\InspectionStatus;

final class ResolvePreviousDefectAssessment
{
    public function handle(Defect $defect, Inspection $inspection): ?DefectAssessment
    {
        $cursor = $inspection->previousInspection;

        while ($cursor !== null) {
            if ($cursor->status !== InspectionStatus::Canceled) {
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
