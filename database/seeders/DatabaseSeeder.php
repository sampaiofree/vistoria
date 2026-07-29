<?php

namespace Database\Seeders;

use App\Enums\OrganizationStatus;
use App\Enums\UserAccountType;
use App\Enums\UserStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $organization = Organization::query()->firstOrCreate(
            ['document' => '00000000000000'],
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
                'name' => 'Administrador',
                'password' => Hash::make('password'),
                'account_type' => UserAccountType::CompanyAdmin->value,
                'status' => UserStatus::Active->value,
            ],
        );
    }
}
