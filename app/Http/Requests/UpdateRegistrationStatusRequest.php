<?php

namespace App\Http\Requests;

use App\Enums\RegistrationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRegistrationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'status' => [
                'required',
                Rule::enum(RegistrationStatus::class),
            ],
        ];
    }
}
