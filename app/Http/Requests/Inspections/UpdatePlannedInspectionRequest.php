<?php

declare(strict_types=1);

namespace App\Http\Requests\Inspections;

use App\Models\Inspection;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;

final class UpdatePlannedInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $inspection = $this->route('inspection');

        return $inspection instanceof Inspection
            && ($this->user()?->can('updatePlanned', $inspection) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'service_order' => TextNormalizer::nullableText($this->input('service_order')),
            'external_report_number' => TextNormalizer::nullableText($this->input('external_report_number')),
            'procedure_number' => TextNormalizer::nullableText($this->input('procedure_number')),
            'atmospheric_classification' => TextNormalizer::nullableText($this->input('atmospheric_classification')),
            'scheduled_for' => blank($this->input('scheduled_for'))
                ? (blank($this->input('scheduled_at')) ? null : $this->input('scheduled_at'))
                : $this->input('scheduled_for'),
            'general_notes' => TextNormalizer::nullableText($this->input('general_notes')),
        ]);
    }

    public function rules(): array
    {
        return [
            'service_order' => ['nullable', 'string', 'max:100'],
            'external_report_number' => ['nullable', 'string', 'max:150'],
            'procedure_number' => ['nullable', 'string', 'max:150'],
            'atmospheric_classification' => ['nullable', 'string', 'max:50'],
            'scheduled_for' => ['nullable', 'date'],
            'general_notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
