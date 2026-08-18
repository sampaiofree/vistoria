<?php

declare(strict_types=1);

namespace App\Http\Requests\EquipmentRevisions;

use App\Enums\EquipmentRevisionEmissionType;
use App\Models\Equipment;
use App\Models\EquipmentRevision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreEquipmentRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $equipment = $this->route('equipment');

        return $equipment instanceof Equipment
            && ($this->user()?->can('create', [EquipmentRevision::class, $equipment]) ?? false);
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
        ]);
    }

    public function rules(): array
    {
        $organizationId = $this->user()?->organization_id;

        return [
            'emission_type' => ['required', Rule::enum(EquipmentRevisionEmissionType::class)],
            'revision_date' => ['required', 'date'],
            'preparer_id' => $this->activeUserRule($organizationId),
            'reviewer_id' => $this->activeUserRule($organizationId),
            'approver_id' => $this->activeUserRule($organizationId),
            'releaser_id' => $this->activeUserRule($organizationId),
        ];
    }

    /** @return array<int, mixed> */
    private function activeUserRule(?int $organizationId): array
    {
        return [
            'required',
            'integer',
            Rule::exists('users', 'id')->where(fn ($query) => $query
                ->where('organization_id', $organizationId)
                ->where('status', 'active')),
        ];
    }
}
