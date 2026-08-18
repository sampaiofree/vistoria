<?php

namespace App\Actions\Settings;

use App\Enums\UserAccountType;
use App\Enums\UserStatus;
use App\Models\InspectionResponsible;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ChangeOrganizationUserStatus
{
    public function handle(User $actor, User $user, UserStatus $status): User
    {
        return DB::transaction(function () use ($actor, $user, $status): User {
            $actor->organization()->lockForUpdate()->firstOrFail();
            $user->refresh();

            if ($actor->getKey() === $user->getKey() && $status !== UserStatus::Active) {
                throw ValidationException::withMessages(['status' => 'Você não pode inativar a própria conta.']);
            }

            if ($status === UserStatus::Inactive) {
                if ($user->isCompanyAdmin()) {
                    $this->assertNotLastActiveAdmin($user);
                }

                $openAssignments = InspectionResponsible::query()
                    ->where('user_id', $user->getKey())
                    ->whereHas('inspection', fn ($query) => $query->whereNotIn('status', ['released', 'canceled']))
                    ->distinct('inspection_id')
                    ->count('inspection_id');

                if ($openAssignments > 0) {
                    throw ValidationException::withMessages([
                        'status' => "O usuário está atribuído a {$openAssignments} inspeção(ões) aberta(s). Substitua os responsáveis antes de inativar.",
                    ]);
                }
            }

            $user->update(['status' => $status]);

            if ($status === UserStatus::Inactive) {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->getKey())->delete();
                $user->forceFill(['remember_token' => null])->saveQuietly();
            }

            return $user->refresh();
        });
    }

    private function assertNotLastActiveAdmin(User $user): void
    {
        $count = User::query()
            ->where('organization_id', $user->organization_id)
            ->where('account_type', UserAccountType::CompanyAdmin->value)
            ->where('status', UserStatus::Active->value)
            ->whereKeyNot($user->getKey())
            ->count();

        if ($count === 0) {
            throw ValidationException::withMessages(['status' => 'A organização precisa manter um administrador ativo.']);
        }
    }
}
