<?php

declare(strict_types=1);

namespace App\Actions\Classification;

use App\Enums\RegistrationStatus;
use App\Models\DefectClassification;
use App\Models\User;

final class ChangeDefectClassificationStatus
{
    public function handle(User $actor, DefectClassification $classification, RegistrationStatus $status): DefectClassification
    {
        $classification->update([
            'status' => $status,
            'updated_by' => $actor->getKey(),
        ]);

        return $classification->refresh();
    }
}
