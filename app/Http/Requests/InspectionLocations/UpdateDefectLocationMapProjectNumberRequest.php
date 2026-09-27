<?php

declare(strict_types=1);

namespace App\Http\Requests\InspectionLocations;

use App\Models\DefectAssessment;
use App\Support\TextNormalizer;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateDefectLocationMapProjectNumberRequest extends FormRequest
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
            'project_number' => TextNormalizer::nullableText($this->input('project_number')),
        ]);
    }

    public function rules(): array
    {
        return [
            'project_number' => ['required', 'string', 'max:150'],
        ];
    }
}
