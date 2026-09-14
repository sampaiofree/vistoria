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
            $photos = $assessment->photos;
            $requiresEvidence = $assessment->condition->requiresEvidence();

            $hasMinimumPhotos = $photos->count() >= 2;
            $hasReadyAllPhotos = $photos->every(fn ($photo): bool => $photo->processing_status === PhotoProcessingStatus::Ready);

            if ($requiresEvidence && ! $hasMinimumPhotos) {
                return [($assessment->defect?->code ?? (string) $assessment->getKey()).' (mínimo de 2 fotografias)'];
            }

            if (! $hasReadyAllPhotos) {
                return [$assessment->defect?->code ?? (string) $assessment->getKey()];
            }

            return [];
        });

        if ($pending->isNotEmpty()) {
            throw ValidationException::withMessages([
                'inspection' => 'Existem avaliações sem cobertura fotográfica pronta: '.$pending->implode(', ').'.',
            ]);
        }
    }
}
