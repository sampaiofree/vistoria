<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\InspectionOverviewPhoto;
use App\Models\User;

final class InspectionOverviewPhotoPolicy
{
    public function view(User $user, InspectionOverviewPhoto $photo): bool
    {
        return $user->isActive()
            && ! $user->isSuperAdmin()
            && $user->organization_id !== null
            && $photo->belongsToOrganization($user->organization_id);
    }

    public function update(User $user, InspectionOverviewPhoto $photo): bool
    {
        return $this->view($user, $photo)
            && $photo->inspection !== null
            && $user->can('manageReportOverview', $photo->inspection);
    }

    public function delete(User $user, InspectionOverviewPhoto $photo): bool
    {
        return $this->update($user, $photo);
    }
}
