<?php

declare(strict_types=1);

namespace App\Http\Requests\Defects;

use App\Enums\DefectAssessmentCondition;
use App\Models\DefectAssessment;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CompleteDefectAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assessment = $this->route('defectAssessment');

        return $assessment instanceof DefectAssessment
            && ($this->user()?->can('complete', $assessment) ?? false);
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
            'defect_classification_id' => [
                'nullable',
                'integer',
                Rule::exists('defect_classifications', 'id')->where('organization_id', $this->user()?->organization_id),
            ],
            'impacts_activity' => ['nullable', 'boolean'],
            'gravity' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'urgency' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'trend' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
