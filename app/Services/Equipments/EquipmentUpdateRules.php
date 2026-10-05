<?php

declare(strict_types=1);

namespace App\Services\Equipments;

use App\Enums\AssetAbcClass;
use Illuminate\Validation\Rule;

final class EquipmentUpdateRules
{
    /** @return array<string, array<int, mixed>> */
    public static function for(int $organizationId, int $equipmentId): array
    {
        $unique = fn (string $field) => Rule::unique('equipments', $field)
            ->where(fn ($query) => $query->where('organization_id', $organizationId))
            ->ignore($equipmentId);

        return [
            'numero_cliente' => ['required', 'string', 'max:50', $unique('numero_cliente')],
            'numero_interno' => ['required', 'string', 'max:50', $unique('numero_interno')],
            'tag' => ['required', 'string', 'max:120'],
            'maintenance_item_code' => ['required', 'string', 'max:80', $unique('maintenance_item_code')],
            'defect_code_prefix' => ['required', 'string', 'max:80', $unique('defect_code_prefix')],
            'maintenance_plan_code' => ['nullable', 'string', 'max:80'],
            'area_code' => ['nullable', 'string', 'max:80'],
            'subarea_code' => ['nullable', 'string', 'max:80'],
            'task_list_group' => ['nullable', 'string', 'max:80'],
            'task_list_group_counter' => ['nullable', 'string', 'max:80'],
            'area_name' => ['nullable', 'string', 'max:180'],
            'subarea_name' => ['nullable', 'string', 'max:180'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'abc_code' => ['nullable', Rule::enum(AssetAbcClass::class)],
            'installation_location' => ['nullable', 'string', 'max:255'],
            'confirm_related_records_edit' => ['nullable', 'boolean'],
        ];
    }
}
