<?php

namespace App\Actions\Settings;

use App\Enums\UserAccountType;
use App\Enums\OperationalRole;
use App\Enums\InspectionResponsibility;
use App\Models\Client;
use App\Models\InspectionResponsible;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateOrganizationUser
{
    public function handle(User $actor, User $user, array $data): User
    {
        return DB::transaction(function () use ($actor, $user, $data): User {
            $actor->organization()->lockForUpdate()->firstOrFail();
            $user = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if ($user->isCompanyAdmin() && $actor->getKey() !== $user->getKey()) {
                throw ValidationException::withMessages(['account_type' => 'Você não pode alterar outra conta de administrador.']);
            }

            if ($actor->getKey() === $user->getKey() && $data['account_type'] !== $user->account_type->value) {
                throw ValidationException::withMessages(['account_type' => 'Você não pode alterar o próprio perfil.']);
            }

            if ($user->isCompanyAdmin() && $data['account_type'] !== UserAccountType::CompanyAdmin->value) {
                $this->assertNotLastActiveAdmin($user);
            }

            if ($data['account_type'] === UserAccountType::Client->value
                && ! Client::query()->forOrganization($user->organization_id)->exists()) {
                throw ValidationException::withMessages(['account_type' => 'Cadastre o cliente antes de criar seus usuários.']);
            }

            if ($data['account_type'] === UserAccountType::Client->value && ! $user->isClient()
                && InspectionResponsible::query()->where('user_id', $user->getKey())
                    ->whereHas('inspection', fn ($query) => $query->whereNotIn('status', ['released', 'canceled']))
                    ->exists()) {
                throw ValidationException::withMessages([
                    'account_type' => 'Substitua as responsabilidades em inspeções abertas antes de alterar para Cliente.',
                ]);
            }

            $roles = $data['operational_roles'] ?? [];
            $removed = array_diff($user->operationalRoleValues(), $roles);
            if ($removed !== []) {
                $responsibilities = array_map(
                    fn (string $role): string => match (OperationalRole::from($role)) {
                        OperationalRole::Planner => InspectionResponsibility::Preparer->value,
                        OperationalRole::Inspector => InspectionResponsibility::Reviewer->value,
                        OperationalRole::Reviewer => InspectionResponsibility::Approver->value,
                        OperationalRole::Releaser => InspectionResponsibility::Releaser->value,
                    },
                    $removed,
                );
                if (InspectionResponsible::query()->where('user_id', $user->getKey())
                    ->whereIn('responsibility', $responsibilities)
                    ->whereHas('inspection', fn ($query) => $query->whereNotIn('status', ['released', 'canceled']))
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'operational_roles' => 'Substitua as responsabilidades em inspeções abertas antes de retirar esta função.',
                    ]);
                }
            }

            $user->update([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'account_type' => $data['account_type'],
                'operational_role' => $roles[0] ?? null,
            ]);
            $user->syncOperationalRoles($roles);

            return $user->refresh();
        });
    }

    private function assertNotLastActiveAdmin(User $user): void
    {
        $count = User::query()
            ->where('organization_id', $user->organization_id)
            ->where('account_type', UserAccountType::CompanyAdmin->value)
            ->where('status', 'active')
            ->whereKeyNot($user->getKey())
            ->count();

        if ($count === 0) {
            throw ValidationException::withMessages(['account_type' => 'A organização precisa manter um administrador ativo.']);
        }
    }
}
