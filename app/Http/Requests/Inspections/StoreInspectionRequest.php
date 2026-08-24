<?php

namespace App\Http\Requests\Inspections;

use App\Models\Inspection;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Inspection::class) ?? false;
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
        ]);
    }

    public function rules(): array
    {
        $organizationId = $this->user()?->organization_id;

        return [
            'equipment_id' => [
                'required',
                'integer',
                Rule::exists('equipments', 'id')
                    ->where(fn ($query) => $query
                        ->where('organization_id', $organizationId)),
            ],
            'service_order' => ['nullable', 'string', 'max:100'],
            'external_report_number' => ['nullable', 'string', 'max:150'],
            'procedure_number' => ['nullable', 'string', 'max:150'],
            'atmospheric_classification' => ['nullable', 'string', 'max:50'],
            'scheduled_for' => ['nullable', 'date'],
        ];
    }
}
