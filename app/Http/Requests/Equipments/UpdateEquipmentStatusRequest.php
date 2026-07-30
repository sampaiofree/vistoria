<?php

namespace App\Http\Requests\Equipments;

use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateEquipmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $equipment = $this->route('equipment');

        return $equipment instanceof Equipment
            && ($this->user()?->can('changeStatus', $equipment) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => is_string($this->input('status'))
                ? mb_strtolower(trim($this->input('status')))
                : $this->input('status'),
            'reason' => is_string($this->input('reason'))
                ? trim($this->input('reason'))
                : $this->input('reason'),
        ]);
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::enum(EquipmentStatus::class),
            ],
            'reason' => [
                Rule::requiredIf($this->input('status') === EquipmentStatus::Decommissioned->value),
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
