<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationStatus;
use App\Enums\UserAccountType;
use App\Enums\UserStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateOrganizationWithAdmin
{
    /**
     * @param  array{name:string, legal_name:?string, document:?string, admin_name:string, admin_email:string}  $data
     * @return array{organization: Organization, admin: User, temporary_password: string}
     */
    public function handle(array $data): array
    {
        $temporaryPassword = Str::password(16);

        return DB::transaction(function () use ($data, $temporaryPassword): array {
            $organization = Organization::query()->create([
                'name' => $data['name'],
                'legal_name' => $data['legal_name'],
                'document' => $data['document'],
                'timezone' => 'America/Sao_Paulo',
                'status' => OrganizationStatus::Active,
            ]);

            $admin = User::query()->create([
                'organization_id' => $organization->getKey(),
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => $temporaryPassword,
                'must_change_password' => true,
                'account_type' => UserAccountType::CompanyAdmin,
                'status' => UserStatus::Active,
            ]);

            return [
                'organization' => $organization->refresh(),
                'admin' => $admin->refresh(),
                'temporary_password' => $temporaryPassword,
            ];
        });
    }
}
