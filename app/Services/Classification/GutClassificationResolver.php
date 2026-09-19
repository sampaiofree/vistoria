<?php

declare(strict_types=1);

namespace App\Services\Classification;

use App\Enums\DefectCategory;
use App\Enums\GutCriterion;
use Illuminate\Validation\ValidationException;

final class GutClassificationResolver
{
    /**
     * @param  array{gravity:?int,urgency:?int,trend:?int}  $scores
     * @return array{classification:?DefectClassificationDefinition,gut_score:int,criteria:array<string,array{score:int,color:string}>}
     */
    public function resolve(DefectCategory $category, array $scores): array
    {
        $options = NativeDefectCatalog::gutOptions();
        $criteria = [];
        $errors = [];

        foreach (GutCriterion::cases() as $criterion) {
            $score = $scores[$criterion->value] ?? null;
            $note = is_int($score) || is_string($score) || is_float($score)
                ? filter_var($score, FILTER_VALIDATE_INT)
                : false;
            $option = $note === false ? null : collect($options[$criterion->value])->firstWhere('score', $note);
            if ($option === null) {
                $errors[$criterion->value] = 'Escolha uma nota GUT de 1 a 5 para este critério.';

                continue;
            }
            $criteria[$criterion->value] = $option;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $gutScore = $criteria['gravity']['score'] * $criteria['urgency']['score'] * $criteria['trend']['score'];

        return [
            'classification' => NativeDefectCatalog::classifications($category)
                ->first(fn (DefectClassificationDefinition $classification): bool => $classification->contains($gutScore)),
            'gut_score' => $gutScore,
            'criteria' => $criteria,
        ];
    }
}
