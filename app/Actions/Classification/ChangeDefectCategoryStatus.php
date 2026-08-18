<?php

declare(strict_types=1);

namespace App\Actions\Classification;

use App\Enums\RegistrationStatus;
use App\Models\DefectCategory;
use App\Models\User;

final class ChangeDefectCategoryStatus
{
    public function handle(User $actor, DefectCategory $category, RegistrationStatus $status): DefectCategory
    {
        $category->update([
            'status' => $status,
            'updated_by' => $actor->getKey(),
        ]);

        return $category->refresh();
    }
}
