<?php

namespace App\Policies;

use App\Models\Equipment;
use App\Models\User;

final class EquipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive()
            && ! $user->isSuperAdmin()
            && $user->organization_id !== null;
    }

    public function view(User $user, Equipment $equipment): bool
    {
        return $user->isActive()
            && $this->sameOrganization($user, $equipment);
    }

    public function create(User $user): bool
    {
        return $user->isActive()
            && $user->isCompanyAdmin()
            && $user->organization_id !== null;
    }

    public function update(User $user, Equipment $equipment): bool
    {
        return $user->isActive()
            && $user->isCompanyAdmin()
            && $this->sameOrganization($user, $equipment);
    }

    public function changeStatus(User $user, Equipment $equipment): bool
    {
        return $this->update($user, $equipment);
    }

    private function sameOrganization(User $user, Equipment $equipment): bool
    {
        return $user->organization_id !== null
            && $equipment->belongsToOrganization($user->organization_id);
    }
}
