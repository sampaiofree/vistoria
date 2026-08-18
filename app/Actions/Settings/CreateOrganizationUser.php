<?php

namespace App\Actions\Settings;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateOrganizationUser
{
    /** @return array{user: User, temporary_password: string} */
    public function handle(Organization $organization, array $data): array
    {
        $temporaryPassword = Str::random(16);

        $user = DB::transaction(fn (): User => User::query()->create([
            'organization_id' => $organization->getKey(),
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => $temporaryPassword,
            'must_change_password' => true,
            'account_type' => $data['account_type'],
            'status' => 'active',
        ]));

        return compact('user', 'temporaryPassword') + ['temporary_password' => $temporaryPassword];
    }
}
