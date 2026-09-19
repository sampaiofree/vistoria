<?php

namespace App\Actions\Settings;

use App\Enums\UserAccountType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateOrganizationUser
{
    public function handle(User $actor, User $user, array $data): User
    {
        return DB::transaction(function () use ($actor, $user, $data): User {
            $actor->organization()->lockForUpdate()->firstOrFail();
            $user->refresh();

            if ($actor->getKey() === $user->getKey() && $data['account_type'] !== $user->account_type->value) {
                throw ValidationException::withMessages(['account_type' => 'Você não pode alterar o próprio perfil.']);
            }

            if ($user->isCompanyAdmin() && $data['account_type'] === UserAccountType::Member->value) {
                $this->assertNotLastActiveAdmin($user);
            }

            $user->update([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'account_type' => $data['account_type'],
                'operational_role' => $data['operational_role'],
            ]);

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
