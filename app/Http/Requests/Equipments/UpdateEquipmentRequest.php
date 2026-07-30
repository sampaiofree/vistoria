<?php

namespace App\Http\Requests\Equipments;

use App\Models\Equipment;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'tag' => TextNormalizer::equipmentTag((string) $this->input('tag')),
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
        /** @var Equipment|null $equipment */
        $equipment = $this->route('equipment');
        $organizationId = $this->user()?->organization_id;

        return [
            'client_id' => [
                'required',
                'integer',
                Rule::exists('clients', 'id')
                    ->where(fn ($query) => $query
                        ->where('organization_id', $organizationId)),
            ],
            'client_unit_id' => [
                'required',
                'integer',
                Rule::exists('client_units', 'id')
                    ->where(fn ($query) => $query
                        ->where('organization_id', $organizationId)
                        ->where('client_id', $this->input('client_id'))),
            ],
            'area_id' => [
                'required',
                'integer',
                Rule::exists('areas', 'id')
                    ->where(fn ($query) => $query
                        ->where('organization_id', $organizationId)
                        ->where('client_unit_id', $this->input('client_unit_id'))),
            ],
            'subarea_id' => [
                'nullable',
                'integer',
                Rule::exists('subareas', 'id')
                    ->where(fn ($query) => $query
                        ->where('organization_id', $organizationId)
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
                        ->where('client_unit_id', $this->input('client_unit_id')))
                    ->ignore($equipment?->getKey()),
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
