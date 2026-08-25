<?php

declare(strict_types=1);

namespace App\Services\Classification;

use App\Enums\GutCriterion;
use App\Models\DefectCategory;
use App\Models\DefectClassification;
use Illuminate\Validation\ValidationException;

final class GutClassificationResolver
{
    /**
     * @param  array{gravity:?int,urgency:?int,trend:?int}  $scores
     * @return array{classification:?DefectClassification,gut_score:int,criteria:array<string, array{score:int,color:string}>}
     */
    public function resolve(DefectCategory $category, array $scores): array
    {
        $optionsByCriterion = $category->gutOptions
            ->groupBy(fn ($option): string => $option->criterion->value);
        $criteria = [];
        $errors = [];

        foreach ([GutCriterion::Gravity, GutCriterion::Urgency, GutCriterion::Trend] as $criterion) {
            $options = $optionsByCriterion->get($criterion->value, collect());
            $score = $scores[$criterion->value] ?? null;

            if ($options->isEmpty()) {
                $errors[$criterion->value] = 'Configure ao menos uma nota GUT para este critério na categoria.';

                continue;
            }

            $option = $score === null ? null : $options->firstWhere('score', (int) $score);

            if ($option === null) {
                $errors[$criterion->value] = 'Escolha uma nota GUT configurada para este critério.';

                continue;
            }

            $criteria[$criterion->value] = [
                'score' => (int) $option->score,
                'color' => (string) $option->color,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $gutScore = $criteria[GutCriterion::Gravity->value]['score']
            * $criteria[GutCriterion::Urgency->value]['score']
            * $criteria[GutCriterion::Trend->value]['score'];
        $matches = $category->classifications
            ->filter(fn (DefectClassification $classification): bool => $classification->isActive()
                && $classification->lower_limit !== null
                && $classification->upper_limit !== null
                && $classification->lower_limit <= $gutScore
                && $classification->upper_limit >= $gutScore)
            ->values();

        if ($matches->count() > 1) {
            throw ValidationException::withMessages([
                'gut' => "Mais de uma classificação ativa possui faixa para o resultado GUT {$gutScore}.",
            ]);
        }

        return [
            'classification' => $matches->first(),
            'gut_score' => $gutScore,
            'criteria' => $criteria,
        ];
    }
}
