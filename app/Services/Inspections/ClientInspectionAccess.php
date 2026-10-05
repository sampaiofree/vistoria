<?php

namespace App\Services\Inspections;

use App\Enums\DefectAssessmentStatus;
use App\Enums\InspectionStatus;
use App\Models\Client;
use App\Models\DefectAssessment;
use App\Models\Inspection;
use App\Models\User;

final class ClientInspectionAccess
{
    public function clientId(User $user): ?int
    {
        if (! $user->isClient() || ! $user->isActive() || $user->organization_id === null) {
            return null;
        }

        $key = 'client-inspection-client-id-'.$user->getKey();
        if (request()->attributes->has($key)) {
            return request()->attributes->get($key);
        }

        $id = Client::query()->forOrganization($user->organization_id)->value('id');
        request()->attributes->set($key, $id);

        return $id;
    }

    public function canView(User $user, Inspection $inspection): bool
    {
        $key = 'client-inspection-view-'.$user->getKey().'-'.$inspection->getKey();
        if (request()->attributes->has($key)) {
            return request()->attributes->get($key);
        }

        $clientId = $this->clientId($user);

        $allowed = $clientId !== null
            && $inspection->organization_id === $user->organization_id
            && $inspection->status === InspectionStatus::Released
            && $inspection->equipment()->where('client_id', $clientId)->exists();
        request()->attributes->set($key, $allowed);

        return $allowed;
    }

    public function canViewAssessment(User $user, DefectAssessment $assessment): bool
    {
        return $assessment->organization_id === $user->organization_id
            && $assessment->equipment_id === $assessment->inspection->equipment_id
            && $assessment->status === DefectAssessmentStatus::Complete
            && $this->canView($user, $assessment->inspection);
    }
}
