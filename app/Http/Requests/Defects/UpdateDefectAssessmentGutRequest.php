<?php

declare(strict_types=1);

namespace App\Http\Requests\Defects;

use App\Enums\DefectAssessmentCondition;
use App\Models\DefectAssessment;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateDefectAssessmentGutRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assessment = $this->route('defectAssessment');

        return $assessment instanceof DefectAssessment
            && ($this->user()?->can('update', $assessment) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (self::technicalCodeFields() as $field) {
            if ($this->exists($field)) {
                $normalized[$field] = TextNormalizer::nullableText($this->input($field));
            }
        }

        $this->merge($normalized);
    }

    public function rules(): array
    {
        return self::technicalRules() + [
            'condition' => ['required', Rule::enum(DefectAssessmentCondition::class)],
        ];
    }

    /** @return array<string, mixed> */
    public static function technicalRules(): array
    {
        return [
            'gravity' => ['prohibited'],
            'urgency' => ['prohibited'],
            'trend' => ['prohibited'],
            'gut_score' => ['prohibited'],
            'classification_code' => ['prohibited'],
            'safety_impact_code' => ['nullable', 'string', 'max:120'],
            'asset_impact_code' => ['nullable', 'string', 'max:120'],
            'urgency_context_code' => ['nullable', 'string', 'max:120'],
            'urgency_matrix_code' => ['nullable', 'string', 'max:120'],
            'transporter_type_code' => ['nullable', 'string', 'max:120'],
            'urgency_option_code' => ['nullable', 'string', 'max:120'],
            'urgency_manual_description' => ['prohibited'],
            'urgency_manual_score' => ['prohibited'],
            'trend_group_code' => ['nullable', 'string', 'max:120'],
            'trend_option_code' => ['nullable', 'string', 'max:120'],
            'trend_manual_description' => ['prohibited'],
            'trend_manual_score' => ['prohibited'],
        ];
    }

    /** @return list<string> */
    public static function technicalFieldNames(): array
    {
        return [
            'safety_impact_code',
            'asset_impact_code',
            'urgency_context_code',
            'urgency_matrix_code',
            'transporter_type_code',
            'urgency_option_code',
            'trend_group_code',
            'trend_option_code',
        ];
    }

    /** @return list<string> */
    public static function technicalCodeFields(): array
    {
        return [
            'safety_impact_code',
            'asset_impact_code',
            'urgency_context_code',
            'urgency_matrix_code',
            'transporter_type_code',
            'urgency_option_code',
            'trend_group_code',
            'trend_option_code',
        ];
    }
}
