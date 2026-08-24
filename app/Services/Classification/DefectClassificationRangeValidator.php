<?php

declare(strict_types=1);

namespace App\Services\Classification;

use App\Enums\RegistrationStatus;
use App\Models\DefectCategory;
use App\Models\DefectClassification;
use Illuminate\Validation\ValidationException;

final class DefectClassificationRangeValidator
{
    public function ensureValidRange(int $lowerLimit, int $upperLimit): void
    {
        if ($lowerLimit > $upperLimit) {
            throw ValidationException::withMessages([
                'upper_limit' => 'O limite superior deve ser maior ou igual ao limite inferior.',
            ]);
        }
    }

    public function ensureNoActiveOverlap(
        DefectCategory $category,
        int $lowerLimit,
        int $upperLimit,
        ?int $ignoreClassificationId = null,
    ): void {
        $query = DefectClassification::query()
            ->where('organization_id', $category->organization_id)
            ->where('defect_category_id', $category->getKey())
            ->where('status', RegistrationStatus::Active->value)
            ->whereNotNull('lower_limit')
            ->whereNotNull('upper_limit')
            ->where('lower_limit', '<=', $upperLimit)
            ->where('upper_limit', '>=', $lowerLimit);

        if ($ignoreClassificationId !== null) {
            $query->whereKeyNot($ignoreClassificationId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'lower_limit' => 'A faixa informada sobrepõe uma classificação ativa desta categoria.',
            ]);
        }
    }

    public function ensureActivatable(DefectCategory $category, DefectClassification $classification): void
    {
        if ($classification->lower_limit === null || $classification->upper_limit === null) {
            throw ValidationException::withMessages([
                'status' => 'Defina os limites inferior e superior antes de ativar a classificação.',
            ]);
        }

        $this->ensureValidRange($classification->lower_limit, $classification->upper_limit);
        $this->ensureNoActiveOverlap(
            $category,
            $classification->lower_limit,
            $classification->upper_limit,
            $classification->getKey(),
        );
    }
}
