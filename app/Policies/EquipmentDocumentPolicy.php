<?php

namespace App\Policies;

use App\Models\Equipment;
use App\Models\EquipmentDocument;
use App\Models\User;

final class EquipmentDocumentPolicy
{
    public function view(User $user, EquipmentDocument $document): bool
    {
        return $user->isActive()
            && $this->sameOrganization($user, $document);
    }

    public function create(User $user, Equipment $equipment): bool
    {
        return $user->isActive()
            && $user->isCompanyAdmin()
            && $equipment->belongsToOrganization($user->organization_id ?? 0);
    }

    public function download(User $user, EquipmentDocument $document): bool
    {
        return $this->view($user, $document);
    }

    public function updateStatus(User $user, EquipmentDocument $document): bool
    {
        return $user->isActive()
            && $user->isCompanyAdmin()
            && $this->sameOrganization($user, $document);
    }

    public function setCurrent(User $user, EquipmentDocument $document): bool
    {
        return $this->updateStatus($user, $document);
    }

    private function sameOrganization(User $user, EquipmentDocument $document): bool
    {
        return $user->organization_id !== null
            && $document->belongsToOrganization($user->organization_id);
    }
}
