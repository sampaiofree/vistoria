<?php

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ChangePassword
{
    public function handle(User $user, string $password): void
    {
        DB::transaction(function () use ($user, $password): void {
            $user->update(['password' => $password, 'must_change_password' => false]);
            $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
        });
    }
}
