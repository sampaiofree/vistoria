<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

final class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return $user->isActive() && $this->sameOrganization($user, $organization);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->view($user, $organization) && $user->isCompanyAdmin();
    }

    private function sameOrganization(User $user, Organization $organization): bool
    {
        return $user->organization_id !== null
            && $user->organization_id === $organization->getKey();
    }
}
