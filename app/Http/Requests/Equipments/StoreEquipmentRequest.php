<?php

namespace App\Http\Requests\Equipments;

use App\Enums\RegistrationStatus;
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
            'tag' => TextNormalizer::equipmentTag((string) $this->input('tag')),
            'defect_code_prefix' => TextNormalizer::technicalCode($this->input('defect_code_prefix')),
            'name' => TextNormalizer::text((string) $this->input('name')),
            'description' => TextNormalizer::nullableText($this->input('description')),
            'manufacturer' => TextNormalizer::nullableText($this->input('manufacturer')),
            'model' => TextNormalizer::nullableText($this->input('model')),
            'serial_number' => TextNormalizer::nullableText($this->input('serial_number')),
            'asset_code' => TextNormalizer::technicalCode($this->input('asset_code')),
            'abc_code' => TextNormalizer::technicalCode($this->input('abc_code')),
            'installation_location' => TextNormalizer::nullableText($this->input('installation_location')),
            'notes' => TextNormalizer::nullableText($this->input('notes')),
        ]);
    }

    public function rules(): array
    {
        $organizationId = $this->user()?->organization_id;

        return [
            'client_id' => [
                'required',
                'integer',
                Rule::exists('clients', 'id')
                    ->where(fn ($query) => $query
                        ->where('organization_id', $organizationId)
                        ->where('status', RegistrationStatus::Active->value)),
            ],
            'client_unit_id' => [
                'required',
                'integer',
                Rule::exists('client_units', 'id')
                    ->where(fn ($query) => $query
                        ->where('organization_id', $organizationId)
                        ->where('status', RegistrationStatus::Active->value)
                        ->where('client_id', $this->input('client_id'))),
            ],
            'area_id' => [
                'required',
                'integer',
                Rule::exists('areas', 'id')
                    ->where(fn ($query) => $query
                        ->where('organization_id', $organizationId)
                        ->where('status', RegistrationStatus::Active->value)
                        ->where('client_unit_id', $this->input('client_unit_id'))),
            ],
            'subarea_id' => [
                'nullable',
                'integer',
                Rule::exists('subareas', 'id')
                    ->where(fn ($query) => $query
                        ->where('organization_id', $organizationId)
                        ->where('status', RegistrationStatus::Active->value)
                        ->where('area_id', $this->input('area_id'))),
            ],
            'tag' => [
                'required',
                'string',
                'max:120',
                Rule::unique('equipments', 'normalized_tag')
                    ->where(fn ($query) => $query
                        ->where('organization_id', $organizationId)
                        ->where('client_id', $this->input('client_id'))
                        ->where('client_unit_id', $this->input('client_unit_id'))),
            ],
            'defect_code_prefix' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('equipments', 'defect_code_prefix')
                    ->where(fn ($query) => $query
                        ->where('organization_id', $organizationId)),
            ],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'manufacturer' => ['nullable', 'string', 'max:150'],
            'model' => ['nullable', 'string', 'max:150'],
            'serial_number' => ['nullable', 'string', 'max:150'],
            'asset_code' => ['nullable', 'string', 'max:120'],
            'abc_code' => ['nullable', 'string', 'max:20'],
            'installation_location' => ['nullable', 'string', 'max:255'],
            'commissioned_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
