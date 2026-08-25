<?php

namespace App\Http\Requests\Organizations;

use App\Rules\ValidCnpj;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreGlobalOrganizationRequest extends FormRequest
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
            'admin_name' => TextNormalizer::text((string) $this->input('admin_name')),
            'admin_email' => TextNormalizer::email($this->input('admin_email')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'document' => ['nullable', 'digits:14', new ValidCnpj, Rule::unique('organizations', 'document')],
            'admin_name' => ['required', 'string', 'max:150'],
            'admin_email' => ['required', 'email:rfc', 'max:254', Rule::unique('users', 'email')],
        ];
    }
}
