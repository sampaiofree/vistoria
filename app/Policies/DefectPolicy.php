<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Models\Defect;
use App\Models\Inspection;
use App\Models\User;

final class DefectPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->activeInOrganization($user);
    }

    public function view(User $user, Defect $defect): bool
    {
        return $this->activeInOrganization($user)
            && $this->sameOrganization($user, $defect);
    }

    public function create(User $user, Inspection $inspection): bool
    {
        return $this->activeInOrganization($user)
            && $this->sameOrganizationInspection($user, $inspection)
            && in_array($inspection->status, [
                InspectionStatus::InProgress,
                InspectionStatus::InCorrection,
            ], true)
            && $inspection->hasAnyResponsibilityForUser(
                $user,
                InspectionResponsibility::Inspector,
                InspectionResponsibility::Preparer,
            );
    }

    private function activeInOrganization(User $user): bool
    {
        return $user->isActive()
            && ! $user->isSuperAdmin()
            && $user->organization_id !== null;
    }

    private function sameOrganization(User $user, Defect $defect): bool
    {
        return $user->organization_id !== null
            && $defect->belongsToOrganization($user->organization_id);
    }

    private function sameOrganizationInspection(User $user, Inspection $inspection): bool
    {
        return $user->organization_id !== null
            && $inspection->belongsToOrganization($user->organization_id);
    }
}
