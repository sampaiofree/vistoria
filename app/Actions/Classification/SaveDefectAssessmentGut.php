<?php

declare(strict_types=1);

namespace App\Actions\Classification;

use App\Enums\DefectAssessmentCondition;
use App\Enums\GutCriterion;
use App\Models\DefectAssessment;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveDefectAssessmentGut
{
    public function __construct(private readonly TenantContext $tenant) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, DefectAssessment $assessment, array $data): DefectAssessment
    {
        return DB::transaction(function () use ($actor, $assessment, $data): DefectAssessment {
            $assessment = DefectAssessment::query()
                ->forOrganization($this->tenant->id())
                ->with(['defect.categoryDefinition.gutOptions', 'inspection'])
                ->lockForUpdate()
                ->findOrFail($assessment->getKey());

            $condition = DefectAssessmentCondition::from($data['condition']);

            if (in_array($condition, [
                DefectAssessmentCondition::Repaired,
                DefectAssessmentCondition::NotLocated,
                DefectAssessmentCondition::NotInspected,
            ], true)) {
                return $this->clear($assessment, $actor);
            }

            $category = $assessment->defect->categoryDefinition;

            if ($category === null) {
                throw ValidationException::withMessages([
                    'gut' => 'A avaria ainda não possui categoria configurável.',
                ]);
            }

            $options = $category->gutOptions->groupBy(fn ($option): string => $option->criterion->value);
            $values = [
                GutCriterion::Gravity->value => $data['gravity'] ?? null,
                GutCriterion::Urgency->value => $data['urgency'] ?? null,
                GutCriterion::Trend->value => $data['trend'] ?? null,
            ];
            $selected = [];

            foreach ($values as $criterion => $score) {
                $criterionOptions = $options->get($criterion, collect());

                if ($criterionOptions->isEmpty()) {
                    $values[$criterion] = null;

                    continue;
                }

                if ($score === null) {
                    throw ValidationException::withMessages([
                        $criterion => 'Escolha uma nota configurada para este critério.',
                    ]);
                }

                $option = $criterionOptions->firstWhere('score', (int) $score);

                if ($option === null) {
                    throw ValidationException::withMessages([
                        $criterion => 'A nota escolhida não pertence à configuração desta categoria.',
                    ]);
                }

                $selected[$criterion] = [
                    'score' => $option->score,
                    'color' => $option->color,
                ];
            }

            $assessment->fill([
                'gravity' => $values[GutCriterion::Gravity->value],
                'urgency' => $values[GutCriterion::Urgency->value],
                'trend' => $values[GutCriterion::Trend->value],
                'gut_score' => null,
                'gut_snapshot' => [
                    'source' => 'defect_category',
                    'category_id' => $category->public_id,
                    'category_code' => $category->code,
                    'category_name' => $category->name,
                    'criteria' => $selected,
                ],
                'gut_classified_at' => now(),
                'gut_classified_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ])->save();

            return $assessment->refresh();
        });
    }

    private function clear(DefectAssessment $assessment, User $actor): DefectAssessment
    {
        $assessment->fill([
            'gravity' => null,
            'urgency' => null,
            'trend' => null,
            'gut_score' => null,
            'gut_snapshot' => null,
            'gut_classified_at' => null,
            'gut_classified_by' => null,
            'updated_by' => $actor->getKey(),
        ])->save();

        return $assessment->refresh();
    }
}
