<?php

declare(strict_types=1);

namespace App\Services\Defects;

use App\Enums\DefectAssessmentCondition;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Inspection;
use Illuminate\Validation\ValidationException;

final class DefectAssessmentCompletionValidator
{
    public function ensureConditionAllowed(
        Defect $defect,
        Inspection $inspection,
        DefectAssessmentCondition $condition,
        bool $allowNewCondition = false,
    ): void {
        if ($condition !== DefectAssessmentCondition::New) {
            return;
        }

        if ($allowNewCondition && $inspection->getKey() === $defect->first_inspection_id) {
            return;
        }

        throw ValidationException::withMessages([
            'condition' => 'A condição "nova" só pode ser usada na primeira avaliação da avaria.',
        ]);
    }

    public function ensureCanComplete(DefectAssessment $assessment): void
    {
        $errors = [];

        if ($assessment->comment === null) {
            $errors['comment'] = 'Informe um comentário para concluir a avaliação.';
        }

        if ($assessment->condition->requiresReason() && $assessment->reason === null) {
            $errors['reason'] = 'Informe a justificativa para esta condição.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
