<?php

namespace App\Http\Requests\Equipments;

use App\Models\Equipment;
use App\Services\Equipments\EquipmentUpdateRules;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $equipment = $this->route('equipment');

        return $equipment instanceof Equipment
            && ($this->user()?->can('update', $equipment) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'numero_cliente' => TextNormalizer::technicalCode($this->input('numero_cliente')),
            'numero_interno' => TextNormalizer::technicalCode($this->input('numero_interno')),
            'maintenance_plan_code' => TextNormalizer::technicalCode($this->input('maintenance_plan_code')),
            'maintenance_item_code' => TextNormalizer::technicalCode($this->input('maintenance_item_code')),
            'area_code' => TextNormalizer::technicalCode($this->input('area_code')),
            'subarea_code' => TextNormalizer::technicalCode($this->input('subarea_code')),
            'task_list_group' => TextNormalizer::technicalCode($this->input('task_list_group')),
            'task_list_group_counter' => TextNormalizer::technicalCode($this->input('task_list_group_counter')),
            'area_name' => TextNormalizer::nullableText($this->input('area_name')),
            'subarea_name' => TextNormalizer::nullableText($this->input('subarea_name')),
            'tag' => TextNormalizer::equipmentTag((string) $this->input('tag')),
            'defect_code_prefix' => TextNormalizer::technicalCode($this->input('defect_code_prefix')),
            'name' => TextNormalizer::text((string) $this->input('name')),
            'description' => TextNormalizer::nullableText($this->input('description')),
            'abc_code' => TextNormalizer::technicalCode($this->input('abc_code')),
            'installation_location' => TextNormalizer::nullableText($this->input('installation_location')),
            'confirm_related_records_edit' => $this->boolean('confirm_related_records_edit'),
        ]);
    }

    public function rules(): array
    {
        /** @var Equipment|null $equipment */
        $equipment = $this->route('equipment');
        $organizationId = $this->user()?->organization_id;

        return EquipmentUpdateRules::for((int) $organizationId, (int) $equipment?->getKey());
    }
}
