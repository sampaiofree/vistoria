<?php

declare(strict_types=1);

namespace App\Http\Requests\Classification;

use App\Models\DefectAssessment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssignDefectClassificationRequest extends FormRequest
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
            'defect_classification_id' => ['required', 'integer', Rule::exists('defect_classifications', 'id')->where('organization_id', $this->user()?->organization_id)],
        ];
    }
}
