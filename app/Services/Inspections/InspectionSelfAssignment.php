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
    /** @return list<InspectionResponsibility> */
    public function rolesFor(User $user): array
    {
        if (! $user->isActive()
            || ! in_array($user->account_type, [UserAccountType::Member, UserAccountType::CompanyAdmin], true)
            || $user->organization_id === null) {
            return [];
        }

        $roles = [];
        if ($user->hasOperationalRole(OperationalRole::Reviewer)) {
            $roles[] = InspectionResponsibility::Approver;
        }
        if ($user->hasOperationalRole(OperationalRole::Releaser)) {
            $roles[] = InspectionResponsibility::Releaser;
        }

        return $roles;
    }

    public function roleFor(User $user): ?InspectionResponsibility
    {
        return $this->rolesFor($user)[0] ?? null;
    }

    /** @param Builder<Inspection> $query @return Builder<Inspection> */
    public function availableQuery(Builder $query, User $user): Builder
    {
        $roles = $this->rolesFor($user);
        if ($roles === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->forOrganization($user->organization_id)
            ->whereNotIn('status', [InspectionStatus::Released, InspectionStatus::Canceled])
            ->where(function (Builder $vacancies) use ($roles): void {
                foreach ($roles as $role) {
                    $vacancies->orWhereDoesntHave('responsibles', fn (Builder $responsibles) => $responsibles->where('responsibility', $role->value));
                }
            });
    }

    public function isAvailable(User $user, Inspection $inspection): bool
    {
        return $this->availableQuery(Inspection::query()->whereKey($inspection->id), $user)->exists();
    }

    /** @return array{action:string,label:string,options:list<array{value:string,label:string}>,requires_selection:bool}|null */
    public function capability(User $user, Inspection $inspection): ?array
    {
        if ($inspection->status->isFinal() || ! $inspection->belongsToOrganization($user->organization_id ?? 0)) {
            return null;
        }
        $options = [];
        foreach ($this->rolesFor($user) as $role) {
            if (! $inspection->responsibles()->where('responsibility', $role->value)->exists()) {
                $options[] = ['value' => $role->value, 'label' => $role->label()];
            }
        }
        if ($options === []) {
            return null;
        }

        return [
            'action' => route('inspections.self-assign', $inspection),
            'label' => count($options) === 1 ? 'Assumir como '.$options[0]['label'] : 'Assumir funções',
            'options' => $options,
            'requires_selection' => count($this->rolesFor($user)) > 1,
        ];
    }
}
