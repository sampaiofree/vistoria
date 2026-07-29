<?php

namespace Database\Seeders;

use App\Enums\OrganizationStatus;
use App\Enums\UserAccountType;
use App\Enums\UserStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->firstOrCreate(
            ['document' => '21798932000100'],
            [
                'name' => 'Empresa de Inspecao',
                'legal_name' => 'Empresa de Inspecao LTDA',
                'status' => OrganizationStatus::Active->value,
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'admin@vistoria.test'],
            [
                'organization_id' => $organization->id,
                'name' => 'Administrador da Empresa',
                'password' => Hash::make('password'),
                'account_type' => UserAccountType::CompanyAdmin->value,
                'status' => UserStatus::Active->value,
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'superadmin@vistoria.test'],
            [
                'organization_id' => null,
                'name' => 'Superadministrador',
                'password' => Hash::make('password'),
                'account_type' => UserAccountType::SuperAdmin->value,
                'status' => UserStatus::Active->value,
                'email_verified_at' => now(),
            ],
        );
    }
}
