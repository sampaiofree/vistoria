<?php

namespace App\Http\Requests\Settings;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) ?? false;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in([UserStatus::Active->value, UserStatus::Inactive->value])]];
    }
}
