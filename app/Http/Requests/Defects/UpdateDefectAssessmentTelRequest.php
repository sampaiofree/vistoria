<?php

declare(strict_types=1);

namespace App\Http\Requests\Defects;

use App\Enums\DefectAssessmentCondition;
use App\Models\DefectAssessment;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateDefectAssessmentTelRequest extends FormRequest
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
        foreach (['damage_group_code', 'damage_option_code'] as $field) {
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
            'height_m' => ['nullable', 'numeric', 'min:0', 'decimal:0,16'],
            'damage_group_code' => ['nullable', 'string', 'max:120'],
            'damage_option_code' => ['nullable', 'string', 'max:120'],
            'impact_score' => ['prohibited'],
            'impact' => ['prohibited'],
            'fall_risk_score' => ['prohibited'],
            'risk_score' => ['prohibited'],
            'risk' => ['prohibited'],
            'tel_score' => ['prohibited'],
            'classification_score' => ['prohibited'],
            'classification_code' => ['prohibited'],
            'classification' => ['prohibited'],
        ];
    }

    /** @return list<string> */
    public static function technicalFieldNames(): array
    {
        return ['height_m', 'damage_group_code', 'damage_option_code'];
    }
}
