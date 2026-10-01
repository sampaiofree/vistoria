<?php

declare(strict_types=1);

namespace App\Http\Requests\Inspections;

use App\Models\Inspection;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateInspectionTechnicalReferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $inspection = $this->route('inspection');

        return $inspection instanceof Inspection
            && ($this->user()?->can('manageReportContent', $inspection) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['general_drawing', 'procedure_number'] as $field) {
            $value = $this->input($field);
            $normalized[$field] = is_string($value) || $value === null
                ? TextNormalizer::nullableText($value)
                : $value;
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        return [
            'general_drawing' => ['nullable', 'string', 'max:150'],
            'procedure_number' => ['nullable', 'string', 'max:150'],
        ];
    }
}
