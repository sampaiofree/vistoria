<?php

declare(strict_types=1);

namespace App\Http\Requests\Inspections;

use App\Models\Inspection;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateInspectionClassificationHeaderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $inspection = $this->route('inspection');

        return $inspection instanceof Inspection
            && ($this->user()?->can('manageClassificationM2', $inspection) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'general_drawing' => TextNormalizer::nullableText($this->input('general_drawing')),
            'procedure_number' => TextNormalizer::nullableText($this->input('procedure_number')),
            'inspected_on' => blank($this->input('inspected_on')) ? null : $this->input('inspected_on'),
        ]);
    }

    public function rules(): array
    {
        return [
            'general_drawing' => ['nullable', 'string', 'max:150'],
            'procedure_number' => ['nullable', 'string', 'max:150'],
            'inspected_on' => ['nullable', 'date'],
        ];
    }
}
