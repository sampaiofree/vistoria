<?php

declare(strict_types=1);

namespace App\Http\Requests\Inspections;

use Illuminate\Foundation\Http\FormRequest;

final class AddressCorrectionRequestRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'response_message' => is_string($this->input('response_message'))
                ? (trim($this->input('response_message')) ?: null)
                : $this->input('response_message'),
        ]);
    }

    public function rules(): array
    {
        return ['response_message' => ['nullable', 'string', 'max:5000']];
    }
}
