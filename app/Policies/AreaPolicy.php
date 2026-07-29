<?php

namespace App\Policies;

use App\Models\Area;
use App\Models\User;

final class AreaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive()
            && ! $user->isSuperAdmin()
            && $user->organization_id !== null;
    }

    public function view(User $user, Area $area): bool
    {
        return $user->isActive()
            && $this->sameOrganization($user, $area);
    }

    public function create(User $user): bool
    {
        return $user->isActive()
            && $user->isCompanyAdmin()
            && $user->organization_id !== null;
    }

    public function update(User $user, Area $area): bool
    {
        return $user->isActive()
            && $user->isCompanyAdmin()
            && $this->sameOrganization($user, $area);
    }

    public function changeStatus(User $user, Area $area): bool
    {
        return $this->update($user, $area);
    }

    private function sameOrganization(User $user, Area $area): bool
    {
        return $user->organization_id !== null
            && $area->belongsToOrganization($user->organization_id);
    }
}
