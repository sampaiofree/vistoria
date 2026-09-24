<?php

namespace App\Actions\Equipments;

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

            $hasRelatedRecords = $equipment->inspections()->exists() || $equipment->defects()->exists();

            if ($hasRelatedRecords && ! filter_var($data['confirm_related_records_edit'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                throw ValidationException::withMessages([
                    'confirm_related_records_edit' => 'Confirme que deseja alterar o cadastro que possui inspeções, relatórios ou avarias vinculados.',
                ]);
            }

            $numeroCliente = TextNormalizer::technicalCode($data['numero_cliente'] ?? null);

            if ($numeroCliente === null) {
                throw ValidationException::withMessages(['numero_cliente' => 'Informe o número do cliente.']);
            }

            $numeroInterno = TextNormalizer::technicalCode($data['numero_interno'] ?? null);

            if ($numeroInterno === null) {
                throw ValidationException::withMessages(['numero_interno' => 'Informe o número interno.']);
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
                ->where('numero_cliente', $numeroCliente)
                ->whereKeyNot($equipment->getKey())->exists()) {
                throw ValidationException::withMessages(['numero_cliente' => 'Já existe um equipamento com este número do cliente na organização.']);
            }

            if (Equipment::withTrashed()->forOrganization($this->tenant->id())
                ->where('numero_interno', $numeroInterno)
                ->whereKeyNot($equipment->getKey())->exists()) {
                throw ValidationException::withMessages(['numero_interno' => 'Já existe um equipamento com este número interno na organização.']);
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

            $equipment->update([
                'numero_cliente' => $numeroCliente,
                'numero_interno' => $numeroInterno,
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
