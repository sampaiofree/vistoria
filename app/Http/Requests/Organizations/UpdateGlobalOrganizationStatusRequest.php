<?php

namespace App\Http\Requests\Organizations;

use App\Enums\OrganizationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateGlobalOrganizationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->isSuperAdmin() && $user->organization_id === null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => is_string($this->input('status'))
                ? mb_strtolower(trim($this->input('status')))
                : $this->input('status'),
        ]);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                OrganizationStatus::Active->value,
                OrganizationStatus::Suspended->value,
            ])],
        ];
    }
}
