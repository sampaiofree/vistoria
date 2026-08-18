<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DefectCategory;
use App\Models\User;

final class DefectCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->isCompanyAdmin() && $user->organization_id !== null;
    }

    public function view(User $user, DefectCategory $category): bool
    {
        return $this->viewAny($user) && $category->belongsToOrganization((int) $user->organization_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, DefectCategory $category): bool
    {
        return $this->create($user) && $this->view($user, $category);
    }

    public function changeStatus(User $user, DefectCategory $category): bool
    {
        return $this->update($user, $category);
    }
}
