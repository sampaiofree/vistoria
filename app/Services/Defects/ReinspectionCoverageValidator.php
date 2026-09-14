<?php

declare(strict_types=1);

namespace App\Services\Defects;

use App\Models\Defect;
use App\Models\Inspection;
use Illuminate\Validation\ValidationException;

final class ReinspectionCoverageValidator
{
    public function __construct(private readonly InspectionDefectScope $scope) {}

    public function validate(Inspection $inspection): void
    {
        $pending = $this->scope->handle($inspection)
            ->filter(function (Defect $defect) use ($inspection): bool {
                $assessment = $defect->assessments->firstWhere('inspection_id', $inspection->getKey());

                return $assessment === null || ! $assessment->isComplete();
            })
            ->count();

        if ($pending === 0) {
            return;
        }

        throw ValidationException::withMessages([
            'inspection' => sprintf('Ainda existem %d avaria(s) sem avaliação completa.', $pending),
        ]);
    }
}
