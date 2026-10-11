<?php

namespace App\Http\Requests\Settings;

use App\Enums\OperationalRole;
use App\Enums\UserAccountType;
use App\Models\User;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');
        if ($target instanceof User && $target->organization_id !== $this->user()?->organization_id) {
            abort(404);
        }

        return $target instanceof User && ($this->user()?->can('update', $target) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('operational_roles') && filled($this->input('operational_role'))) {
            $this->merge(['operational_roles' => [$this->input('operational_role')]]);
        }
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
                UserAccountType::Client->value,
            ])],
            'operational_roles' => $this->input('account_type') === UserAccountType::Client->value
                ? ['prohibited']
                : ['required', 'array', 'min:1', 'max:4'],
            'operational_roles.*' => ['distinct', Rule::enum(OperationalRole::class)],
        ];
    }
}
