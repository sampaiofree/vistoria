<?php

declare(strict_types=1);

namespace App\Http\Requests\Inspections;

use App\Enums\AtmosphericCorrosivity;
use App\Enums\OperationalRole;
use App\Enums\UserStatus;
use App\Models\Inspection;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $normalized = [
            'equipment_id' => blank($this->input('equipment_id')) ? null : (int) $this->input('equipment_id'),
            'inspector_id' => blank($this->input('inspector_id')) ? null : (int) $this->input('inspector_id'),
            'service_order' => TextNormalizer::nullableText($this->input('service_order')),
        ];

        if ($this->exists('atmospheric_classification')) {
            $normalized['atmospheric_classification'] = TextNormalizer::technicalCode($this->input('atmospheric_classification'));
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        $organizationId = $this->user()?->organization_id;

        return [
            'equipment_id' => [
                'required',
                'integer',
                Rule::exists('equipments', 'id')->where(
                    fn ($query) => $query->where('organization_id', $organizationId),
                ),
            ],
            'inspector_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('organization_id', $organizationId)
                    ->where('status', UserStatus::Active->value)
                    ->where('operational_role', OperationalRole::Inspector->value)),
            ],
            'service_order' => ['nullable', 'string', 'max:100'],
            'planned_start_on' => ['required', 'date'],
            'planned_end_on' => ['required', 'date', 'after_or_equal:planned_start_on'],
            'external_report_number' => ['prohibited'],
            'procedure_number' => ['prohibited'],
            'atmospheric_classification' => ['nullable', Rule::enum(AtmosphericCorrosivity::class)],
        ];
    }
}
