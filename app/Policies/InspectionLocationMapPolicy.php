<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Models\DefectCategory;
use App\Models\Inspection;
use App\Models\InspectionLocationMap;
use App\Models\User;

final class InspectionLocationMapPolicy
{
    public function view(User $user, InspectionLocationMap $map): bool
    {
        return $this->activeInOrganization($user) && $map->belongsToOrganization($user->organization_id);
    }

    public function create(User $user, Inspection $inspection, DefectCategory $category): bool
    {
        return $this->canEditInspection($user, $inspection)
            && $inspection->organization_id === $category->organization_id;
    }

    public function update(User $user, InspectionLocationMap $map): bool
    {
        return $this->view($user, $map) && $this->canEditInspection($user, $map->inspection);
    }

    public function delete(User $user, InspectionLocationMap $map): bool
    {
        return $this->update($user, $map);
    }

    public function copyPrevious(User $user, Inspection $inspection): bool
    {
        return $this->canEditInspection($user, $inspection) && $inspection->previous_inspection_id !== null;
    }

    public function reorder(User $user, Inspection $inspection): bool
    {
        return $this->canEditInspection($user, $inspection);
    }

    private function activeInOrganization(User $user): bool
    {
        return $user->isActive() && ! $user->isSuperAdmin() && $user->organization_id !== null;
    }

    private function canEditInspection(User $user, Inspection $inspection): bool
    {
        return $this->activeInOrganization($user)
            && $inspection->belongsToOrganization($user->organization_id)
            && in_array($inspection->status, [InspectionStatus::InProgress, InspectionStatus::InCorrection], true)
            && $inspection->hasAnyResponsibilityForUser($user, InspectionResponsibility::Preparer);
    }
}
