<?php

namespace Database\Seeders;

use App\Enums\OrganizationStatus;
use App\Enums\RegistrationStatus;
use App\Enums\UserAccountType;
use App\Enums\UserStatus;
use App\Models\Area;
use App\Models\Client;
use App\Models\ClientUnit;
use App\Models\Organization;
use App\Models\Subarea;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

        $client = Client::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'document' => '11222333000144',
            ],
            [
                'public_id' => (string) Str::ulid(),
                'name' => 'Cliente Piloto',
                'legal_name' => 'Cliente Piloto LTDA',
                'email' => 'contato@clientepiloto.test',
                'phone' => '(11) 99999-9999',
                'status' => RegistrationStatus::Active->value,
                'notes' => 'Cliente base para desenvolvimento local.',
            ],
        );

        $unit = ClientUnit::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'client_id' => $client->id,
                'normalized_code' => 'UN-001',
            ],
            [
                'public_id' => (string) Str::ulid(),
                'name' => 'Unidade Central',
                'code' => 'UN-001',
                'timezone' => 'America/Sao_Paulo',
                'address_line' => 'Rua Principal',
                'address_number' => '100',
                'district' => 'Centro',
                'postal_code' => '01000-000',
                'city' => 'Sao Paulo',
                'state' => 'SP',
                'country_code' => 'BR',
                'status' => RegistrationStatus::Active->value,
                'notes' => null,
            ],
        );

        $area = Area::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'client_unit_id' => $unit->id,
                'normalized_code' => 'AR-001',
            ],
            [
                'public_id' => (string) Str::ulid(),
                'name' => 'Area Operacional',
                'code' => 'AR-001',
                'status' => RegistrationStatus::Active->value,
                'description' => null,
            ],
        );

        Subarea::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'area_id' => $area->id,
                'normalized_code' => 'SA-001',
            ],
            [
                'public_id' => (string) Str::ulid(),
                'name' => 'Subarea Principal',
                'code' => 'SA-001',
                'status' => RegistrationStatus::Active->value,
                'description' => null,
            ],
        );
    }
}
