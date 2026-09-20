<?php

declare(strict_types=1);

namespace App\Services\Defects;

use App\Enums\DefectAssessmentCondition;
use App\Enums\InspectionLocationMapProcessingStatus;
use App\Enums\PhotoProcessingStatus;
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
    ): void {
        if ($inspection->getKey() === $defect->first_inspection_id
            || $condition !== DefectAssessmentCondition::New) {
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

        if ($assessment->condition->requiresEvidence()) {
            if ($assessment->recommendation === null) {
                $errors['recommendation'] = 'Informe uma recomendação para publicar a avaliação.';
            }

            $assessment->loadMissing(['quantities', 'locationMapVersion', 'location']);

            if ($assessment->quantities->isEmpty()) {
                $errors['quantity'] = 'Informe o quantitativo antes de publicar a avaliação.';
            }

            if ($assessment->photos->count() < 2) {
                $errors['photos'] = 'Adicione pelo menos duas fotografias antes de publicar a avaliação.';
            } elseif ($assessment->photos->contains(
                fn ($photo): bool => $photo->processing_status !== PhotoProcessingStatus::Ready,
            )) {
                $errors['photos'] = 'Aguarde o processamento de todas as fotografias antes de publicar a avaliação.';
            }

            if ($assessment->locationMapVersion === null) {
                $errors['location_map'] = 'Envie o mapa de localização antes de publicar a avaliação.';
            } elseif ($assessment->locationMapVersion->processing_status !== InspectionLocationMapProcessingStatus::Ready
                || $assessment->locationMapVersion->background_path === null) {
                $errors['location_map'] = 'Aguarde o processamento do mapa de localização antes de publicar a avaliação.';
            }

            if ($assessment->location === null || ! $assessment->location->isConfirmed()) {
                $errors['location'] = 'Identifique e confirme a localização da avaria no mapa antes de publicar a avaliação.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
