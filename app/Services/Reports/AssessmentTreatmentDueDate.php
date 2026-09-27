<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\DefectCategory;
use Carbon\CarbonInterface;

final class AssessmentTreatmentDueDate
{
    public static function forClassification(?CarbonInterface $inspectionDate, DefectCategory $category, ?string $classificationCode): ?CarbonInterface
    {
        $years = match ($category) {
            DefectCategory::StructuralRecovery, DefectCategory::Civil => match ($classificationCode) {
                'IE-1', 'CV-1' => 1,
                'IE-2', 'CV-2' => 2,
                'IE-3', 'CV-3' => 3,
                default => null,
            },
            DefectCategory::AnticorrosiveTreatment => match ($classificationCode) {
                'TA-1' => 1,
                'TA-2' => 3,
                'TA-3' => 5,
                default => null,
            },
            default => null,
        };

        return $inspectionDate === null || $years === null ? null : $inspectionDate->copy()->addYears($years);
    }
}
