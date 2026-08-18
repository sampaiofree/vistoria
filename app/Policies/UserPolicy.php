<?php

namespace App\Policies;

use App\Models\User;

final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isActive() && $actor->isCompanyAdmin() && $actor->organization_id !== null;
    }

    public function view(User $actor, User $user): bool
    {
        return $this->viewAny($actor) && $this->sameOrganization($actor, $user);
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function update(User $actor, User $user): bool
    {
        return $this->view($actor, $user);
    }

    public function changeStatus(User $actor, User $user): bool
    {
        return $this->update($actor, $user);
    }

    public function resetPassword(User $actor, User $user): bool
    {
        return $this->update($actor, $user) && $actor->getKey() !== $user->getKey();
    }

    private function sameOrganization(User $actor, User $user): bool
    {
        return $actor->organization_id !== null
            && $user->organization_id === $actor->organization_id
            && ! $user->isSuperAdmin();
    }
}
