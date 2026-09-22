<?php

declare(strict_types=1);

namespace App\Http\Requests\Defects;

use App\Enums\DefectAssessmentStatus;
use App\Enums\DefectCategory;
use App\Models\Defect;
use App\Models\Inspection;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreDefectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $inspection = $this->route('inspection');

        return $inspection instanceof Inspection
            && ($this->user()?->can('create', [Defect::class, $inspection]) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $normalized = [
            'title' => TextNormalizer::text((string) $this->input('title')),
            'category' => $this->input('category', DefectCategory::Civil->value),
            'origin_description' => TextNormalizer::nullableText($this->input('origin_description')),
            'location_description' => TextNormalizer::nullableText($this->input('location_description')),
            'comment' => TextNormalizer::nullableText($this->input('comment')),
            'recommendation' => TextNormalizer::nullableText($this->input('recommendation')),
            'internal_notes' => TextNormalizer::nullableText($this->input('internal_notes')),
            'assessment_action' => strtolower(trim((string) $this->input('assessment_action', DefectAssessmentStatus::Draft->value))),
        ];

        foreach (UpdateDefectAssessmentGutRequest::technicalCodeFields() as $field) {
            if ($this->exists($field)) {
                $normalized[$field] = TextNormalizer::nullableText($this->input($field));
            }
        }
        foreach (['damage_group_code', 'damage_option_code'] as $field) {
            if ($this->exists($field)) {
                $normalized[$field] = TextNormalizer::nullableText($this->input($field));
            }
        }
        $this->merge($normalized);
    }

    public function rules(): array
    {
        return UpdateDefectAssessmentGutRequest::technicalRules()
            + UpdateDefectAssessmentTelRequest::technicalRules() + [
            'category' => ['nullable', Rule::enum(DefectCategory::class)],
            'title' => ['required', 'string', 'max:200'],
            'origin_description' => ['nullable', 'string', 'max:10000'],
            'location_description' => ['nullable', 'string', 'max:500'],
            'comment' => [
                Rule::requiredIf(fn (): bool => $this->input('assessment_action', DefectAssessmentStatus::Draft->value) === DefectAssessmentStatus::Complete->value),
                'nullable',
                'string',
                'max:10000',
            ],
            'recommendation' => [
                Rule::requiredIf(fn (): bool => $this->input('assessment_action', DefectAssessmentStatus::Draft->value) === DefectAssessmentStatus::Complete->value),
                'nullable',
                'string',
                'max:10000',
            ],
            'internal_notes' => ['nullable', 'string', 'max:10000'],
            'assessment_action' => ['nullable', Rule::in([
                DefectAssessmentStatus::Draft->value,
                DefectAssessmentStatus::Complete->value,
            ])],
        ];
    }
}
