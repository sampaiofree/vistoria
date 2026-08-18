<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AssessmentPhoto;
use App\Models\User;

final class AssessmentPhotoPolicy
{
    public function view(User $user, AssessmentPhoto $photo): bool
    {
        return $user->isActive() && ! $user->isSuperAdmin() && $photo->belongsToOrganization((int) $user->organization_id);
    }

    public function update(User $user, AssessmentPhoto $photo): bool
    {
        return $this->view($user, $photo)
            && $photo->assessment !== null
            && $user->can('update', $photo->assessment);
    }

    public function delete(User $user, AssessmentPhoto $photo): bool
    {
        return $this->update($user, $photo);
    }
}
