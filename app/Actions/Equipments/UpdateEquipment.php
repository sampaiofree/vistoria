<?php

namespace App\Actions\Equipments;

use App\Models\Defect;
use App\Models\Equipment;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateEquipment
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    public function handle(User $actor, Equipment $equipment, array $data): Equipment
    {
        return DB::transaction(function () use ($actor, $equipment, $data): Equipment {
            $equipment = Equipment::query()
                ->forOrganization($this->tenant->id())
                ->lockForUpdate()
                ->findOrFail($equipment->getKey());

            if (! $equipment->isRegistrationEditable()) {
                throw ValidationException::withMessages([
                    'equipment' => 'Não é possível editar um equipamento que já possui inspeções ou avarias.',
                ]);
            }

            $maintenanceItemCode = TextNormalizer::technicalCode($data['maintenance_item_code'] ?? null);

            if ($maintenanceItemCode === null) {
                throw ValidationException::withMessages(['maintenance_item_code' => 'Informe o item de manutenção.']);
            }

            $tag = TextNormalizer::equipmentTag($data['tag']);
            $defectCodePrefix = TextNormalizer::technicalCode($data['defect_code_prefix'] ?? null);

            if ($defectCodePrefix === null) {
                throw ValidationException::withMessages(['defect_code_prefix' => 'Informe o prefixo de avaria.']);
            }

            if (Equipment::withTrashed()->forOrganization($this->tenant->id())
                ->where('maintenance_item_code', $maintenanceItemCode)
                ->whereKeyNot($equipment->getKey())->exists()) {
                throw ValidationException::withMessages(['maintenance_item_code' => 'Já existe um equipamento com este item de manutenção na organização.']);
            }

            if (Equipment::withTrashed()->forOrganization($this->tenant->id())
                ->where('defect_code_prefix', $defectCodePrefix)
                ->whereKeyNot($equipment->getKey())
                ->exists()) {
                throw ValidationException::withMessages(['defect_code_prefix' => 'Já existe um equipamento com este prefixo de avaria na organização.']);
            }

            if (
                $equipment->defect_code_prefix !== $defectCodePrefix
                && Defect::query()
                    ->forOrganization($this->tenant->id())
                    ->where('equipment_id', $equipment->getKey())
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'defect_code_prefix' => 'Não é possível alterar o prefixo depois que a avaria já foi usada.',
                ]);
            }

            $equipment->update([
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
                'updated_by' => $actor->id,
            ]);

            return $equipment->refresh();
        });
    }
}
