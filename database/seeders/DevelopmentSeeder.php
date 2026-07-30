<?php

namespace Database\Seeders;

use App\Enums\EquipmentStatus;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use App\Enums\OrganizationStatus;
use App\Enums\RegistrationStatus;
use App\Enums\UserAccountType;
use App\Enums\UserStatus;
use App\Models\Area;
use App\Models\Client;
use App\Models\ClientUnit;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\InspectionStatusHistory;
use App\Models\Organization;
use App\Models\Subarea;
use App\Models\User;
use App\Services\Inspections\InspectionSnapshotBuilder;
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

        $admin = $this->updateOrCreateUser(
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

        Equipment::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'client_id' => $client->id,
                'client_unit_id' => $unit->id,
                'area_id' => $area->id,
                'normalized_tag' => TextNormalizer::equipmentTag('U03-06VT002'),
            ],
            [
                'public_id' => (string) Str::ulid(),
                'subarea_id' => Subarea::query()
                    ->where('organization_id', $organization->id)
                    ->where('area_id', $area->id)
                    ->value('id'),
                'tag' => TextNormalizer::equipmentTag('U03-06VT002'),
                'name' => 'Ventilador',
                'description' => null,
                'manufacturer' => 'Weg',
                'model' => 'VX-200',
                'serial_number' => 'SN-00000001',
                'asset_code' => null,
                'abc_code' => 'A',
                'installation_location' => 'Forno de Endurecimento',
                'commissioned_at' => null,
                'status' => EquipmentStatus::Active->value,
                'notes' => 'Equipamento base para desenvolvimento local.',
            ],
        );

        $this->seedDemoInspection($organization, $admin);
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

    private function seedDemoInspection(
        Organization $organization,
        User $admin,
    ): void {
        $equipment = Equipment::query()
            ->where('organization_id', $organization->id)
            ->where('normalized_tag', TextNormalizer::equipmentTag('U03-06VT002'))
            ->firstOrFail();

        $inspection = Inspection::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'equipment_id' => $equipment->id,
                'service_order' => '3500762191',
            ],
            [
                'public_id' => (string) Str::ulid(),
                'previous_inspection_id' => null,
                'number' => null,
                'inspection_type' => InspectionType::Initial->value,
                'status' => InspectionStatus::Planned->value,
                'external_report_number' => null,
                'procedure_number' => 'T000000-S-2PO006_R-04',
                'atmospheric_classification' => 'C4',
                'scheduled_for' => '2026-05-11',
                'inspected_on' => null,
                'context_snapshot' => app(InspectionSnapshotBuilder::class)->build($equipment),
                'snapshot_version' => InspectionSnapshotBuilder::VERSION,
                'general_notes' => 'Inspeção base para demonstração local.',
                'started_at' => null,
                'field_completed_at' => null,
                'reviewed_at' => null,
                'approved_at' => null,
                'report_generated_at' => null,
                'released_at' => null,
                'canceled_at' => null,
                'created_by' => $admin->id,
                'updated_by' => $admin->id,
            ],
        );

        $inspection->fill([
            'inspection_type' => InspectionType::Initial->value,
            'previous_inspection_id' => null,
            'status' => InspectionStatus::Planned->value,
            'external_report_number' => null,
            'procedure_number' => 'T000000-S-2PO006_R-04',
            'atmospheric_classification' => 'C4',
            'scheduled_for' => '2026-05-11',
            'context_snapshot' => app(InspectionSnapshotBuilder::class)->build($equipment),
            'snapshot_version' => InspectionSnapshotBuilder::VERSION,
            'general_notes' => 'Inspeção base para demonstração local.',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ])->save();

        if (blank($inspection->number)) {
            $inspection->update([
                'number' => sprintf('INS-%s-%06d', now()->format('Y'), $inspection->id),
            ]);
        }

        InspectionStatusHistory::query()->firstOrCreate(
            [
                'organization_id' => $organization->id,
                'inspection_id' => $inspection->id,
                'to_status' => InspectionStatus::Planned->value,
            ],
            [
                'from_status' => null,
                'changed_by' => $admin->id,
                'reason' => 'Inspeção criada.',
                'created_at' => now(),
            ],
        );

        foreach (InspectionResponsibility::cases() as $responsibility) {
            InspectionResponsible::query()->firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'inspection_id' => $inspection->id,
                    'user_id' => $admin->id,
                    'responsibility' => $responsibility->value,
                ],
                [
                    'is_primary' => $responsibility === InspectionResponsibility::Inspector,
                    'assigned_by' => $admin->id,
                    'assigned_at' => now(),
                    'completed_at' => null,
                ],
            );
        }
    }
}
