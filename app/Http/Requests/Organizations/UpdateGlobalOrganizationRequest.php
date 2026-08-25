<?php

namespace App\Http\Requests\Organizations;

use App\Models\Organization;
use App\Rules\ValidCnpj;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateGlobalOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->isSuperAdmin() && $user->organization_id === null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => TextNormalizer::text((string) $this->input('name')),
            'legal_name' => TextNormalizer::nullableText($this->input('legal_name')),
            'document' => TextNormalizer::document($this->input('document')),
        ]);
    }

    public function rules(): array
    {
        $organization = $this->route('organization');

        return [
            'name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'document' => [
                'nullable',
                'digits:14',
                new ValidCnpj,
                Rule::unique('organizations', 'document')->ignore(
                    $organization instanceof Organization ? $organization->getKey() : null,
                ),
            ],
        ];
    }
}
