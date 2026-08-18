<?php

namespace App\Policies;

use App\Models\Equipment;
use App\Models\EquipmentRevision;
use App\Models\User;

final class EquipmentRevisionPolicy
{
    public function view(User $user, EquipmentRevision $revision): bool
    {
        return $user->isActive()
            && $this->sameOrganization($user, $revision);
    }

    public function create(User $user, Equipment $equipment): bool
    {
        return $user->isActive()
            && $user->isCompanyAdmin()
            && $equipment->belongsToOrganization($user->organization_id ?? 0);
    }

    public function update(User $user, EquipmentRevision $revision): bool
    {
        return $user->isActive()
            && $user->isCompanyAdmin()
            && $this->sameOrganization($user, $revision);
    }

    public function delete(User $user, EquipmentRevision $revision): bool
    {
        return $this->update($user, $revision);
    }

    private function sameOrganization(User $user, EquipmentRevision $revision): bool
    {
        return $user->organization_id !== null
            && $revision->belongsToOrganization($user->organization_id);
    }
}
