<?php

declare(strict_types=1);

namespace App\Http\Requests\Inspections;

use Illuminate\Foundation\Http\FormRequest;

final class CorrectionRequestMessageRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'request_message' => is_string($this->input('request_message'))
                ? trim($this->input('request_message'))
                : $this->input('request_message'),
        ]);
    }

    public function rules(): array
    {
        return ['request_message' => ['required', 'string', 'min:10', 'max:5000']];
    }
}
