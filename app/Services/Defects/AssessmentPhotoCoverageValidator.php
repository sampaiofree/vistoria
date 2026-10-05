<?php

declare(strict_types=1);

namespace App\Services\Defects;

use App\Enums\PhotoProcessingStatus;
use App\Models\Inspection;
use Illuminate\Validation\ValidationException;

final class AssessmentPhotoCoverageValidator
{
    public function validate(Inspection $inspection): void
    {
        $assessments = $inspection->defectAssessments()->with(['photos', 'defect'])->get();
        $pending = $assessments->flatMap(function ($assessment): array {
            if ($assessment->condition->isCanceled()) {
                return [];
            }

            $photos = $assessment->photos;
            $requiresEvidence = $assessment->condition->requiresEvidence();

            $photoCount = $photos->count();
            $hasReadyAllPhotos = $photos->every(fn ($photo): bool => $photo->processing_status === PhotoProcessingStatus::Ready);

            if ($requiresEvidence && $photoCount < 2) {
                return [($assessment->defect?->code ?? (string) $assessment->getKey()).' (mínimo de 2 fotografias)'];
            }

            if ($requiresEvidence && $photoCount % 2 !== 0) {
                return [($assessment->defect?->code ?? (string) $assessment->getKey()).' (fotografias em número ímpar; adicione mais uma)'];
            }

            if (! $hasReadyAllPhotos) {
                return [$assessment->defect?->code ?? (string) $assessment->getKey()];
            }

            return [];
        });

        if ($pending->isNotEmpty()) {
            throw ValidationException::withMessages([
                'inspection' => 'Existem avaliações com cobertura fotográfica incompleta: '.$pending->implode(', ').'.',
            ]);
        }
    }
}
