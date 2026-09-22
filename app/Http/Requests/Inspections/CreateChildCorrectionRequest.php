<?php

declare(strict_types=1);

namespace App\Http\Requests\Inspections;

use Illuminate\Foundation\Http\FormRequest;

final class CreateChildCorrectionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'request_message' => is_string($this->input('request_message'))
                ? trim($this->input('request_message'))
                : $this->input('request_message'),
            'defect_assessment_id' => blank($this->input('defect_assessment_id'))
                ? null
                : (int) $this->input('defect_assessment_id'),
        ]);
    }

    public function rules(): array
    {
        return [
            'request_message' => ['required', 'string', 'min:10', 'max:5000'],
            'defect_assessment_id' => ['nullable', 'integer'],
        ];
    }
}
