<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\DefectStatus;
use App\Enums\InspectionStatus;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Inspection;
use App\Models\User;

final class DefectAssessmentPolicy
{
    public function view(User $user, DefectAssessment $assessment): bool
    {
        return $this->activeInOrganization($user)
            && $this->sameOrganizationAssessment($user, $assessment);
    }

    public function create(User $user, Inspection $inspection, Defect $defect): bool
    {
        if (in_array($inspection->status, [
            InspectionStatus::InProgress,
            InspectionStatus::InCorrection,
            InspectionStatus::InReview,
        ], true)) {
            return $user->can('manageFieldContent', $inspection)
                && $this->sameOrganizationDefect($user, $defect)
                && $inspection->equipment_id === $defect->equipment_id
                && $defect->status === DefectStatus::Active;
        }

        return false;
    }

    public function update(User $user, DefectAssessment $assessment): bool
    {
        return $assessment->isDraft()
            && $user->can('manageFieldContent', $assessment->inspection);
    }

    /**
     * Allows an authorized field user to move an assessment between draft and published.
     * Published assessments remain read-only until they are explicitly returned to draft.
     */
    public function changeStatus(User $user, DefectAssessment $assessment): bool
    {
        return $user->can('manageFieldContent', $assessment->inspection);
    }

    public function complete(User $user, DefectAssessment $assessment): bool
    {
        return $this->update($user, $assessment);
    }

    public function uploadPhoto(User $user, DefectAssessment $assessment): bool
    {
        return $this->update($user, $assessment);
    }

    private function activeInOrganization(User $user): bool
    {
        return $user->isActive()
            && ! $user->isSuperAdmin()
            && $user->organization_id !== null;
    }

    private function sameOrganizationAssessment(User $user, DefectAssessment $assessment): bool
    {
        return $user->organization_id !== null
            && $assessment->belongsToOrganization($user->organization_id);
    }

    private function sameOrganizationInspection(User $user, Inspection $inspection): bool
    {
        return $user->organization_id !== null
            && $inspection->belongsToOrganization($user->organization_id);
    }

    private function sameOrganizationDefect(User $user, Defect $defect): bool
    {
        return $user->organization_id !== null
            && $defect->belongsToOrganization($user->organization_id);
    }
}
