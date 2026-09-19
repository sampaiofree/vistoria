<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Inspection;
use App\Models\InspectionLocationMap;
use App\Models\User;

final class InspectionLocationMapPolicy
{
    public function view(User $user, InspectionLocationMap $map): bool
    {
        return $this->activeInOrganization($user) && $map->belongsToOrganization($user->organization_id);
    }

    public function create(User $user, Inspection $inspection): bool
    {
        return $this->canEditInspection($user, $inspection);
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
        return $user->can('manageFieldContent', $inspection);
    }
}
