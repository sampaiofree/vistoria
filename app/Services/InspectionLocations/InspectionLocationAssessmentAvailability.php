<?php

declare(strict_types=1);

namespace App\Services\InspectionLocations;

use App\Models\DefectAssessment;
use App\Models\InspectionLocationMarker;
use Illuminate\Validation\ValidationException;

final class InspectionLocationAssessmentAvailability
{
    public function assertAvailable(DefectAssessment $assessment, ?InspectionLocationMarker $except = null): void
    {
        if (! $this->isAvailable($assessment, $except)) {
            throw ValidationException::withMessages([
                'defect_assessment_id' => 'Esta avaria já está vinculada a um mapa desta inspeção.',
            ]);
        }
    }

    public function isAvailable(DefectAssessment $assessment, ?InspectionLocationMarker $except = null): bool
    {
        return ! InspectionLocationMarker::query()
            ->where('organization_id', $assessment->organization_id)
            ->where('inspection_id', $assessment->inspection_id)
            ->where('defect_assessment_id', $assessment->id)
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->id))
            ->exists();
    }
}
