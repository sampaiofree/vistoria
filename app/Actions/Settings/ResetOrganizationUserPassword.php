<?php

namespace App\Actions\Settings;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ResetOrganizationUserPassword
{
    /** @return array{user: User, temporary_password: string} */
    public function handle(User $user): array
    {
        $temporaryPassword = Str::random(16);

        DB::transaction(function () use ($user, $temporaryPassword): void {
            $user->update([
                'password' => $temporaryPassword,
                'must_change_password' => true,
            ]);
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->getKey())->delete();
            $user->forceFill(['remember_token' => null])->saveQuietly();
        });

        return ['user' => $user->refresh(), 'temporary_password' => $temporaryPassword];
    }
}
