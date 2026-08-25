<?php

declare(strict_types=1);

namespace App\Http\Requests\EquipmentRevisions;

use App\Enums\EquipmentRevisionEmissionType;
use App\Models\EquipmentRevision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateEquipmentRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $revision = $this->route('equipmentRevision');

        return $revision instanceof EquipmentRevision
            && ($this->user()?->can('update', $revision) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'emission_type' => is_string($this->input('emission_type'))
                ? mb_strtoupper(trim($this->input('emission_type')))
                : $this->input('emission_type'),
            'revision_date' => blank($this->input('revision_date'))
                ? null
                : $this->input('revision_date'),
            ...collect(['preparer_name', 'reviewer_name', 'approver_name', 'releaser_name'])
                ->mapWithKeys(fn (string $field): array => [
                    $field => is_string($this->input($field)) ? trim($this->input($field)) : $this->input($field),
                ])
                ->all(),
        ]);
    }

    public function rules(): array
    {
        return [
            'emission_type' => ['required', Rule::enum(EquipmentRevisionEmissionType::class)],
            'revision_date' => ['required', 'date'],
            'preparer_name' => ['required', 'string', 'max:180'],
            'reviewer_name' => ['required', 'string', 'max:180'],
            'approver_name' => ['required', 'string', 'max:180'],
            'releaser_name' => ['required', 'string', 'max:180'],
        ];
    }
}
