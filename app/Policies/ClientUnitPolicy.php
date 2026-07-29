<?php

namespace App\Policies;

use App\Models\ClientUnit;
use App\Models\User;

final class ClientUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive()
            && ! $user->isSuperAdmin()
            && $user->organization_id !== null;
    }

    public function view(User $user, ClientUnit $unit): bool
    {
        return $user->isActive()
            && $this->sameOrganization($user, $unit);
    }

    public function create(User $user): bool
    {
        return $user->isActive()
            && $user->isCompanyAdmin()
            && $user->organization_id !== null;
    }

    public function update(User $user, ClientUnit $unit): bool
    {
        return $user->isActive()
            && $user->isCompanyAdmin()
            && $this->sameOrganization($user, $unit);
    }

    public function changeStatus(User $user, ClientUnit $unit): bool
    {
        return $this->update($user, $unit);
    }

    private function sameOrganization(User $user, ClientUnit $unit): bool
    {
        return $user->organization_id !== null
            && $unit->belongsToOrganization($user->organization_id);
    }
}
