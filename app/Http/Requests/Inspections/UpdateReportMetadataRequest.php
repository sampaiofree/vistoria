<?php

declare(strict_types=1);

namespace App\Http\Requests\Inspections;

use App\Enums\EquipmentRevisionEmissionType;
use App\Models\Inspection;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateReportMetadataRequest extends FormRequest
{
    public function authorize(): bool
    {
        $inspection = $this->route('inspection');

        return $inspection instanceof Inspection
            && ($this->user()?->can('manageReportMetadata', $inspection) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'emission_type' => blank($this->input('emission_type')) ? null : strtoupper(trim((string) $this->input('emission_type'))),
            'report_date' => blank($this->input('report_date')) ? null : $this->input('report_date'),
            'service_order' => TextNormalizer::nullableText($this->input('service_order')),
            'external_report_number' => TextNormalizer::nullableText($this->input('external_report_number')),
            'first_page_text_template' => blank($this->input('first_page_text_template'))
                ? null
                : trim((string) $this->input('first_page_text_template')),
        ]);

        if ($this->exists('report_designer')) {
            $this->merge([
                'report_designer' => TextNormalizer::text((string) $this->input('report_designer')),
            ]);
        }

        if ($this->exists('designer_i_report_number')) {
            $this->merge([
                'designer_i_report_number' => TextNormalizer::nullableText($this->input('designer_i_report_number')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'emission_type' => [
                'required',
                'nullable',
                Rule::enum(EquipmentRevisionEmissionType::class),
            ],
            'report_date' => ['required', 'nullable', 'date'],
            'service_order' => ['required', 'nullable', 'string', 'max:100'],
            'external_report_number' => ['nullable', 'string', 'max:150'],
            'report_designer' => ['sometimes', 'required', 'string', 'max:100'],
            'designer_i_report_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'first_page_text_template' => ['required', 'nullable', 'string', 'max:5000'],
        ];
    }
}
