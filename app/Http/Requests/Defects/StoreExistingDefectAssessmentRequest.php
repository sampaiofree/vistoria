<?php

declare(strict_types=1);

namespace App\Http\Requests\Defects;

use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectAssessmentStatus;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Inspection;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreExistingDefectAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $inspection = $this->route('inspection');
        $defect = $this->route('defect');

        return $inspection instanceof Inspection
            && $defect instanceof Defect
            && ($this->user()?->can('create', [DefectAssessment::class, $inspection, $defect]) ?? false);
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
            'assessment_action' => strtolower(trim((string) $this->input('assessment_action', DefectAssessmentStatus::Draft->value))),
        ]);
    }

    public function rules(): array
    {
        $conditionValues = array_map(
            fn (DefectAssessmentCondition $condition): string => $condition->value,
            array_filter(
                DefectAssessmentCondition::cases(),
                fn (DefectAssessmentCondition $condition): bool => $condition !== DefectAssessmentCondition::New,
            ),
        );

        return [
            'condition' => ['required', 'string', Rule::in($conditionValues)],
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
            'assessment_action' => ['nullable', Rule::in([
                DefectAssessmentStatus::Draft->value,
                DefectAssessmentStatus::Complete->value,
            ])],
        ];
    }
}
