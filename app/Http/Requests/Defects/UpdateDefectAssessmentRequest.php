<?php

declare(strict_types=1);

namespace App\Http\Requests\Defects;

use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectAssessmentStatus;
use App\Models\DefectAssessment;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateDefectAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assessment = $this->route('defectAssessment');

        return $assessment instanceof DefectAssessment
            && ($this->user()?->can('update', $assessment) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'condition' => strtolower(trim((string) $this->input('condition'))),
            'location_description' => TextNormalizer::nullableText($this->input('location_description')),
            'comment' => TextNormalizer::nullableText($this->input('comment')),
            'recommendation' => TextNormalizer::nullableText($this->input('recommendation')),
            'reason' => TextNormalizer::nullableText($this->input('reason')),
            'internal_notes' => TextNormalizer::nullableText($this->input('internal_notes')),
            'item_description' => TextNormalizer::nullableText($this->input('item_description')),
            'project_reference' => TextNormalizer::nullableText($this->input('project_reference')),
            'status' => strtolower(trim((string) $this->input('status'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'condition' => ['required', 'string', Rule::in(array_map(
                fn (DefectAssessmentCondition $condition): string => $condition->value,
                DefectAssessmentCondition::cases(),
            ))],
            'location_description' => ['nullable', 'string', 'max:500'],
            'comment' => ['nullable', 'string', 'max:10000'],
            'recommendation' => ['nullable', 'string', 'max:10000'],
            'reason' => [
                Rule::requiredIf(fn (): bool => in_array(
                    $this->input('condition'),
                    [
                        DefectAssessmentCondition::NotLocated->value,
                        DefectAssessmentCondition::NotInspected->value,
                    ],
                    true,
                )),
                'nullable',
                'string',
                'max:10000',
            ],
            'internal_notes' => ['nullable', 'string', 'max:10000'],
            'item_description' => ['nullable', 'string', 'max:180'],
            'project_reference' => ['nullable', 'string', 'max:180'],
            'impacts_activity' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in([
                DefectAssessmentStatus::Draft->value,
                DefectAssessmentStatus::Complete->value,
            ])],
        ];
    }
}
