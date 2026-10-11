<?php

declare(strict_types=1);

namespace App\Http\Requests\Defects;

use App\Enums\RecoveryClassification;
use App\Models\DefectAssessment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRecoveryClassificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assessment = $this->route('defectAssessment');

        return $assessment instanceof DefectAssessment
            && ($this->user()?->can('update', $assessment) ?? false);
    }

    public function rules(): array
    {
        return ['recovery_classification_code' => ['required', Rule::enum(RecoveryClassification::class)]];
    }
}
