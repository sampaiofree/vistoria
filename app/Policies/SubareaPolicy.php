<?php

namespace App\Policies;

use App\Models\Subarea;
use App\Models\User;

final class SubareaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive()
            && ! $user->isSuperAdmin()
            && $user->organization_id !== null;
    }

    public function view(User $user, Subarea $subarea): bool
    {
        return $user->isActive()
            && $this->sameOrganization($user, $subarea);
    }

    public function create(User $user): bool
    {
        return $user->isActive()
            && $user->isCompanyAdmin()
            && $user->organization_id !== null;
    }

    public function update(User $user, Subarea $subarea): bool
    {
        return $user->isActive()
            && $user->isCompanyAdmin()
            && $this->sameOrganization($user, $subarea);
    }

    public function changeStatus(User $user, Subarea $subarea): bool
    {
        return $this->update($user, $subarea);
    }

    private function sameOrganization(User $user, Subarea $subarea): bool
    {
        return $user->organization_id !== null
            && $subarea->belongsToOrganization($user->organization_id);
    }
}
