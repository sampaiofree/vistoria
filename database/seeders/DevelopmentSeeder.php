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
use App\Support\TextNormalizer;
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
                'public_id' => (string) Str::ulid(),
                'name' => 'Empresa de Inspecao',
                'legal_name' => 'Empresa de Inspecao LTDA',
                'timezone' => 'America/Sao_Paulo',
                'status' => OrganizationStatus::Active->value,
            ],
        );

        $this->updateOrCreateUser(
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

        $this->updateOrCreateUser(
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

        $this->updateOrCreateUser(
            ['email' => 'member@vistoria.test'],
            [
                'organization_id' => $organization->id,
                'name' => 'Membro da Empresa',
                'password' => Hash::make('password'),
                'account_type' => UserAccountType::Member->value,
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
                'name' => 'Samarco Mineracao S.A.',
                'legal_name' => 'Samarco Mineracao S.A.',
                'email' => 'contato@samarco.test',
                'phone' => '(11) 99999-9999',
                'status' => RegistrationStatus::Active->value,
                'notes' => 'Cliente base para desenvolvimento local.',
            ],
        );

        $unit = ClientUnit::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'client_id' => $client->id,
                'normalized_code' => TextNormalizer::technicalCode('UBU'),
            ],
            [
                'public_id' => (string) Str::ulid(),
                'name' => 'Unidade de Ubu',
                'code' => 'UBU',
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
                'normalized_code' => TextNormalizer::technicalCode('USINA III'),
            ],
            [
                'public_id' => (string) Str::ulid(),
                'name' => 'Usina III',
                'code' => 'USINA III',
                'status' => RegistrationStatus::Active->value,
                'description' => null,
            ],
        );

        Subarea::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'area_id' => $area->id,
                'normalized_code' => TextNormalizer::technicalCode('FORNO DE ENDURECIMENTO'),
            ],
            [
                'public_id' => (string) Str::ulid(),
                'name' => 'Forno de Endurecimento',
                'code' => 'FORNO DE ENDURECIMENTO',
                'status' => RegistrationStatus::Active->value,
                'description' => null,
            ],
        );
    }

    private function updateOrCreateUser(array $identity, array $attributes): User
    {
        $user = User::query()->firstOrNew($identity);

        if (! $user->exists) {
            $user->public_id = (string) Str::ulid();
        }

        $user->fill($attributes)->save();

        return $user;
    }
}
