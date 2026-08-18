<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DefectAssessment;
use App\Models\InspectionLocationMap;
use App\Models\InspectionLocationMarker;
use App\Models\User;

final class InspectionLocationMarkerPolicy
{
    public function view(User $user, InspectionLocationMarker $marker): bool
    {
        return $user->isActive()
            && ! $user->isSuperAdmin()
            && $marker->belongsToOrganization((int) $user->organization_id);
    }

    public function create(User $user, InspectionLocationMap $map, DefectAssessment $assessment): bool
    {
        return $user->can('update', $map)
            && $user->can('update', $assessment)
            && $map->organization_id === $assessment->organization_id
            && $map->inspection_id === $assessment->inspection_id
            && $map->equipment_id === $assessment->equipment_id;
    }

    public function update(User $user, InspectionLocationMarker $marker): bool
    {
        return $this->view($user, $marker) && $user->can('update', $marker->map);
    }

    public function delete(User $user, InspectionLocationMarker $marker): bool
    {
        return $this->update($user, $marker);
    }
}
