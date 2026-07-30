<?php

declare(strict_types=1);

namespace App\Http\Requests\Defects;

use App\Enums\DefectAssessmentStatus;
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
        $this->merge([
            'title' => TextNormalizer::text((string) $this->input('title')),
            'origin_description' => TextNormalizer::nullableText($this->input('origin_description')),
            'location_description' => TextNormalizer::nullableText($this->input('location_description')),
            'comment' => TextNormalizer::nullableText($this->input('comment')),
            'recommendation' => TextNormalizer::nullableText($this->input('recommendation')),
            'internal_notes' => TextNormalizer::nullableText($this->input('internal_notes')),
            'assessment_action' => strtolower(trim((string) $this->input('assessment_action', DefectAssessmentStatus::Draft->value))),
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'origin_description' => ['nullable', 'string', 'max:10000'],
            'location_description' => ['nullable', 'string', 'max:500'],
            'comment' => [
                Rule::requiredIf(fn (): bool => $this->input('assessment_action', DefectAssessmentStatus::Draft->value) === DefectAssessmentStatus::Complete->value),
                'nullable',
                'string',
                'max:10000',
            ],
            'recommendation' => ['nullable', 'string', 'max:10000'],
            'internal_notes' => ['nullable', 'string', 'max:10000'],
            'assessment_action' => ['nullable', Rule::in([
                DefectAssessmentStatus::Draft->value,
                DefectAssessmentStatus::Complete->value,
            ])],
        ];
    }
}
