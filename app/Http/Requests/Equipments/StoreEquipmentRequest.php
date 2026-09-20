<?php

namespace App\Http\Requests\Equipments;

use App\Enums\AssetAbcClass;
use App\Models\Equipment;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Equipment::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
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
        ]);
    }

    public function rules(): array
    {
        $organizationId = $this->user()?->organization_id;

        return [
            'tag' => ['required', 'string', 'max:120'],
            'maintenance_item_code' => [
                'required', 'string', 'max:80',
                Rule::unique('equipments', 'maintenance_item_code')
                    ->where(fn ($query) => $query->where('organization_id', $organizationId)),
            ],
            'defect_code_prefix' => [
                'required',
                'string',
                'max:80',
                Rule::unique('equipments', 'defect_code_prefix')
                    ->where(fn ($query) => $query
                        ->where('organization_id', $organizationId)),
            ],
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
        ];
    }
}
