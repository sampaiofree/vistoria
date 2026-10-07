<?php

declare(strict_types=1);

namespace App\Http\Requests\Inspections;

use App\Enums\EquipmentRevisionEmissionType;
use App\Enums\OperationalRole;
use App\Models\Inspection;
use App\Rules\ValidServiceOrder;
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
        $data = [
            'emission_type' => blank($this->input('emission_type')) ? null : strtoupper(trim((string) $this->input('emission_type'))),
            'report_date' => blank($this->input('report_date')) ? null : $this->input('report_date'),
            'service_order' => TextNormalizer::nullableText($this->input('service_order')),
            'external_report_number' => TextNormalizer::nullableText($this->input('external_report_number')),
            'report_equipment_name' => is_string($this->input('report_equipment_name'))
                ? trim($this->input('report_equipment_name'))
                : $this->input('report_equipment_name'),
            'first_page_text_template' => blank($this->input('first_page_text_template'))
                ? null
                : trim((string) $this->input('first_page_text_template')),
        ];

        if ($this->has('designer_i_report_number')) {
            $data['designer_i_report_number'] = TextNormalizer::nullableText($this->input('designer_i_report_number'));
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        $inspection = $this->route('inspection');
        $serviceOrderChanged = $this->input('service_order') !== TextNormalizer::nullableText($inspection?->service_order);

        return [
            'emission_type' => [
                'required',
                'nullable',
                Rule::enum(EquipmentRevisionEmissionType::class),
            ],
            'report_date' => ['nullable', 'date'],
            'service_order' => $serviceOrderChanged
                ? ['required', 'string', new ValidServiceOrder]
                : ['nullable', 'string', 'max:100'],
            'external_report_number' => ['nullable', 'string', 'max:150'],
            'report_equipment_name' => ['required', 'string', 'max:150'],
            'report_designer' => ['prohibited'],
            'designer_i_report_number' => $this->user()?->operational_role === OperationalRole::Reviewer
                ? ['required', 'string', 'max:100']
                : ['prohibited'],
            'first_page_text_template' => ['required', 'nullable', 'string', 'max:5000'],
        ];
    }
}
