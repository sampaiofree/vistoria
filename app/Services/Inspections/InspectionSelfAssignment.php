<?php

declare(strict_types=1);

namespace App\Services\Inspections;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Enums\UserAccountType;
use App\Models\Inspection;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class InspectionSelfAssignment
{
    public function roleFor(User $user): ?InspectionResponsibility
    {
        if (! $user->isActive()
            || ! in_array($user->account_type, [UserAccountType::Member, UserAccountType::CompanyAdmin], true)
            || $user->organization_id === null) {
            return null;
        }

        return match ($user->operational_role) {
            OperationalRole::Reviewer => InspectionResponsibility::Approver,
            OperationalRole::Releaser => InspectionResponsibility::Releaser,
            default => null,
        };
    }

    /** @param Builder<Inspection> $query @return Builder<Inspection> */
    public function availableQuery(Builder $query, User $user): Builder
    {
        $role = $this->roleFor($user);
        if ($role === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->forOrganization($user->organization_id)
            ->whereNotIn('status', [InspectionStatus::Released, InspectionStatus::Canceled])
            ->whereDoesntHave('responsibles', fn (Builder $responsibles) => $responsibles->where('responsibility', $role->value));
    }

    public function isAvailable(User $user, Inspection $inspection): bool
    {
        return $this->availableQuery(Inspection::query()->whereKey($inspection->id), $user)->exists();
    }

    /** @return array{action:string,label:string}|null */
    public function capability(User $user, Inspection $inspection): ?array
    {
        return $this->isAvailable($user, $inspection) ? [
            'action' => route('inspections.self-assign', $inspection),
            'label' => 'Assumir como '.$this->roleFor($user)->label(),
        ] : null;
    }
}
