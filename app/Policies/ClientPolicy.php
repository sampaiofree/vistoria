<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

final class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive()
            && ! $user->isSuperAdmin()
            && $user->organization_id !== null;
    }

    public function view(User $user, Client $client): bool
    {
        return $user->isActive()
            && $this->sameOrganization($user, $client);
    }

    public function create(User $user): bool
    {
        return $user->isActive()
            && $user->isCompanyAdmin()
            && $user->organization_id !== null;
    }

    public function update(User $user, Client $client): bool
    {
        return $user->isActive()
            && $user->isCompanyAdmin()
            && $this->sameOrganization($user, $client);
    }

    public function changeStatus(User $user, Client $client): bool
    {
        return $this->update($user, $client);
    }

    private function sameOrganization(User $user, Client $client): bool
    {
        return $user->organization_id !== null
            && $client->belongsToOrganization($user->organization_id);
    }
}
