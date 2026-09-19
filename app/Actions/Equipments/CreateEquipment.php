<?php

declare(strict_types=1);

namespace App\Actions\Equipments;

use App\Enums\EquipmentStatus;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateEquipment
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    public function handle(User $actor, array $data): Equipment
    {
        return DB::transaction(function () use ($actor, $data): Equipment {
            $client = Client::query()
                ->forOrganization($this->tenant->id())
                ->first();

            $this->validateClient($client);

            $maintenanceItemCode = TextNormalizer::technicalCode($data['maintenance_item_code'] ?? null);

            if ($maintenanceItemCode === null) {
                throw ValidationException::withMessages(['maintenance_item_code' => 'Informe o item de manutenção.']);
            }

            $defectCodePrefix = TextNormalizer::technicalCode($data['defect_code_prefix'] ?? null);

            if ($defectCodePrefix === null) {
                throw ValidationException::withMessages(['defect_code_prefix' => 'Informe o prefixo de avaria.']);
            }

            $tag = TextNormalizer::equipmentTag((string) $data['tag']);

            $existing = Equipment::query()
                ->withTrashed()
                ->forOrganization($this->tenant->id())
                ->where('maintenance_item_code', $maintenanceItemCode)
                ->exists();

            if ($existing) {
                throw ValidationException::withMessages([
                    'maintenance_item_code' => 'Já existe um equipamento com este item de manutenção na organização.',
                ]);
            }

            if (Equipment::query()
                ->withTrashed()
                ->forOrganization($this->tenant->id())
                ->where('defect_code_prefix', $defectCodePrefix)
                ->exists()) {
                throw ValidationException::withMessages([
                    'defect_code_prefix' => 'Já existe um equipamento com este prefixo de avaria na organização.',
                ]);
            }

            return Equipment::query()->create([
                'organization_id' => $this->tenant->id(),
                'client_id' => $client->getKey(),
                'maintenance_plan_code' => TextNormalizer::technicalCode($data['maintenance_plan_code'] ?? null),
                'maintenance_item_code' => $maintenanceItemCode,
                'area_code' => TextNormalizer::technicalCode($data['area_code'] ?? null),
                'subarea_code' => TextNormalizer::technicalCode($data['subarea_code'] ?? null),
                'task_list_group' => TextNormalizer::technicalCode($data['task_list_group'] ?? null),
                'task_list_group_counter' => TextNormalizer::technicalCode($data['task_list_group_counter'] ?? null),
                'area_name' => TextNormalizer::nullableText($data['area_name'] ?? null),
                'subarea_name' => TextNormalizer::nullableText($data['subarea_name'] ?? null),
                'tag' => $tag,
                'normalized_tag' => $tag,
                'defect_code_prefix' => $defectCodePrefix,
                'name' => TextNormalizer::text((string) $data['name']),
                'description' => TextNormalizer::nullableText($data['description'] ?? null),
                'abc_code' => TextNormalizer::technicalCode($data['abc_code'] ?? null),
                'installation_location' => TextNormalizer::nullableText($data['installation_location'] ?? null),
                'status' => EquipmentStatus::Active,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);
        });
    }

    private function validateClient(?Client $client): void
    {
        if ($client === null) {
            throw ValidationException::withMessages([
                'client' => 'Cadastre o cliente em Configurações antes de criar um equipamento.',
            ]);
        }

        if (! $client->isActive()) {
            throw ValidationException::withMessages([
                'client' => 'Ative o cliente em Configurações antes de criar um equipamento.',
            ]);
        }
    }
}
