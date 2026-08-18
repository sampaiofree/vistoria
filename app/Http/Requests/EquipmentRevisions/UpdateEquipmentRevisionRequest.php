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
        ]);
    }

    public function rules(): array
    {
        $organizationId = $this->user()?->organization_id;
        $revision = $this->route('equipmentRevision');

        return [
            'emission_type' => ['required', Rule::enum(EquipmentRevisionEmissionType::class)],
            'revision_date' => ['required', 'date'],
            'preparer_id' => $this->userRule($organizationId, (int) $revision->preparer_id),
            'reviewer_id' => $this->userRule($organizationId, (int) $revision->reviewer_id),
            'approver_id' => $this->userRule($organizationId, (int) $revision->approver_id),
            'releaser_id' => $this->userRule($organizationId, (int) $revision->releaser_id),
        ];
    }

    /** @return array<int, mixed> */
    private function userRule(?int $organizationId, int $currentUserId): array
    {
        return [
            'required',
            'integer',
            Rule::exists('users', 'id')->where(fn ($query) => $query
                ->where('organization_id', $organizationId)
                ->where(function ($query) use ($currentUserId): void {
                    $query
                        ->where('status', 'active')
                        ->orWhere('id', $currentUserId);
                })),
        ];
    }
}
