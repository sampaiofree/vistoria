<?php

declare(strict_types=1);

namespace App\Http\Requests\Inspections;

use App\Enums\AtmosphericCorrosivity;
use App\Enums\OperationalRole;
use App\Enums\UserStatus;
use App\Models\Inspection;
use App\Support\TextNormalizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PreviewInspectionBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Inspection::class) ?? false;
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Somente usuários ativos com papel Planejador podem criar inspeções.');
    }

    protected function prepareForValidation(): void
    {
        $inspections = collect($this->input('inspections', []))
            ->map(function (mixed $inspection): array {
                $inspection = is_array($inspection) ? $inspection : [];

                return [
                    'equipment_id' => blank($inspection['equipment_id'] ?? null) ? null : (int) $inspection['equipment_id'],
                    'service_order' => TextNormalizer::nullableText($inspection['service_order'] ?? null),
                    'atmospheric_classification' => TextNormalizer::technicalCode($inspection['atmospheric_classification'] ?? null),
                    'planned_start_on' => $inspection['planned_start_on'] ?? null,
                    'planned_end_on' => $inspection['planned_end_on'] ?? null,
                    'inspector_id' => blank($inspection['inspector_id'] ?? null) ? null : (int) $inspection['inspector_id'],
                ];
            })
            ->all();

        $this->merge(['inspections' => $inspections]);
    }

    public function rules(): array
    {
        $organizationId = $this->user()?->organization_id;

        return [
            'inspections' => ['required', 'array', 'min:1', 'max:100'],
            'inspections.*.equipment_id' => [
                'required',
                'integer',
                Rule::exists('equipments', 'id')->where(
                    fn ($query) => $query->where('organization_id', $organizationId),
                ),
            ],
            'inspections.*.service_order' => ['nullable', 'string', 'max:100'],
            'inspections.*.atmospheric_classification' => ['nullable', Rule::enum(AtmosphericCorrosivity::class)],
            'inspections.*.planned_start_on' => ['required', 'date'],
            'inspections.*.planned_end_on' => ['required', 'date', 'after_or_equal:inspections.*.planned_start_on'],
            'inspections.*.inspector_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('organization_id', $organizationId)
                    ->where('status', UserStatus::Active->value)
                    ->where('operational_role', OperationalRole::Inspector->value)),
            ],
        ];
    }
}
