<?php

declare(strict_types=1);

namespace App\Http\Requests\Defects;

use App\Enums\DefectAssessmentStatus;
use App\Models\DefectAssessment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeDefectAssessmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assessment = $this->route('defectAssessment');

        return $assessment instanceof DefectAssessment
            && ($this->user()?->can('changeStatus', $assessment) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => strtolower(trim((string) $this->input('status'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(DefectAssessmentStatus::class)],
        ];
    }
}
