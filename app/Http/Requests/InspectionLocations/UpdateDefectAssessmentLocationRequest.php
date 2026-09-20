<?php

declare(strict_types=1);

namespace App\Http\Requests\InspectionLocations;

use App\Models\DefectAssessment;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateDefectAssessmentLocationRequest extends FormRequest
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
            'geometry' => ['required', 'array'],
            'label' => ['nullable', 'string', 'max:240'],
            'lock_version' => ['nullable', 'integer', 'min:1'],
            'style' => ['prohibited'],
            'color' => ['prohibited'],
            'defect_assessment_id' => ['prohibited'],
        ];
    }
}
