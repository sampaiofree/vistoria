<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DefectClassification;
use App\Models\User;

final class DefectClassificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->isCompanyAdmin() && $user->organization_id !== null;
    }

    public function view(User $user, DefectClassification $classification): bool
    {
        return $this->viewAny($user) && $classification->belongsToOrganization((int) $user->organization_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, DefectClassification $classification): bool
    {
        return $this->create($user) && $this->view($user, $classification);
    }

    public function changeStatus(User $user, DefectClassification $classification): bool
    {
        return $this->update($user, $classification);
    }
}
