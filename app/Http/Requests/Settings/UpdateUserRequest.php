<?php

namespace App\Http\Requests\Settings;

use App\Enums\UserAccountType;
use App\Models\User;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => TextNormalizer::text((string) $this->input('name')),
            'email' => TextNormalizer::email($this->input('email')),
        ]);
    }

    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:254', Rule::unique('users', 'email')->ignore($user?->getKey())],
            'account_type' => ['required', Rule::in([
                UserAccountType::Member->value,
                UserAccountType::CompanyAdmin->value,
            ])],
        ];
    }
}
