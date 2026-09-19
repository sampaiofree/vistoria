<?php

declare(strict_types=1);

namespace App\Http\Requests\Defects;

use App\Enums\DefectAssessmentCondition;
use App\Models\DefectAssessment;
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

    public function rules(): array
    {
        return [
            'condition' => ['required', Rule::enum(DefectAssessmentCondition::class)],
            'gravity' => ['nullable', 'integer', 'min:1', 'max:5'],
            'urgency' => ['nullable', 'integer', 'min:1', 'max:5'],
            'trend' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }
}
