<?php

namespace App\Policies;

use App\Models\Inspection;
use App\Models\User;

final class InspectionPolicy
{
    public function manageReferenceDocuments(User $user, Inspection $inspection): bool
    {
        return $user->isActive()
            && $user->isCompanyAdmin()
            && $user->organization_id !== null
            && $inspection->belongsToOrganization($user->organization_id);
    }
}
