<?php

declare(strict_types=1);

namespace App\Actions\Classification;

use App\Enums\DefectAssessmentCondition;
use App\Enums\GutCriterion;
use App\Models\DefectAssessment;
use App\Models\DefectCategory;
use App\Models\User;
use App\Services\Classification\GutClassificationResolver;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveDefectAssessmentGut
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly GutClassificationResolver $resolver,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, DefectAssessment $assessment, array $data): DefectAssessment
    {
        return DB::transaction(function () use ($actor, $assessment, $data): DefectAssessment {
            $assessment = DefectAssessment::query()
                ->forOrganization($this->tenant->id())
                ->with(['defect', 'inspection'])
                ->lockForUpdate()
                ->findOrFail($assessment->getKey());

            $condition = $assessment->condition;

            if (in_array($condition, [
                DefectAssessmentCondition::Repaired,
                DefectAssessmentCondition::NotLocated,
                DefectAssessmentCondition::NotInspected,
            ], true)) {
                return $this->clear($assessment, $actor);
            }

            $categoryId = $assessment->defect->defect_category_id;

            if ($categoryId === null) {
                throw ValidationException::withMessages([
                    'gut' => 'A avaria ainda não possui categoria configurável.',
                ]);
            }

            $category = DefectCategory::query()
                ->forOrganization($this->tenant->id())
                ->with(['gutOptions', 'classifications'])
                ->lockForUpdate()
                ->findOrFail($categoryId);
            $values = [
                GutCriterion::Gravity->value => $data['gravity'] ?? null,
                GutCriterion::Urgency->value => $data['urgency'] ?? null,
                GutCriterion::Trend->value => $data['trend'] ?? null,
            ];
            $resolved = $this->resolver->resolve($category, $values);
            $classification = $resolved['classification'];

            $assessment->fill([
                'gravity' => $resolved['criteria'][GutCriterion::Gravity->value]['score'],
                'urgency' => $resolved['criteria'][GutCriterion::Urgency->value]['score'],
                'trend' => $resolved['criteria'][GutCriterion::Trend->value]['score'],
                'gut_score' => $resolved['gut_score'],
                'gut_snapshot' => [
                    'source' => 'defect_category',
                    'category_id' => $category->public_id,
                    'category_code' => $category->code,
                    'category_name' => $category->name,
                    'criteria' => $resolved['criteria'],
                    'score' => $resolved['gut_score'],
                ],
                'gut_classified_at' => now(),
                'gut_classified_by' => $actor->getKey(),
                'defect_classification_id' => $classification->getKey(),
                'classification_code' => $classification->code,
                'classification_priority' => $classification->severity_rank,
                'deadline_months' => null,
                'recommended_due_date' => null,
                'classification_snapshot' => [
                    'source' => 'gut_range',
                    'classification_id' => $classification->public_id,
                    'category_id' => $category->public_id,
                    'category_code' => $category->code,
                    'category_name' => $category->name,
                    'code' => $classification->code,
                    'name' => $classification->name,
                    'description' => $classification->description,
                    'position' => $classification->position,
                    'severity_rank' => $classification->severity_rank,
                    'lower_limit' => $classification->lower_limit,
                    'upper_limit' => $classification->upper_limit,
                    'gut_score' => $resolved['gut_score'],
                ],
                'classified_at' => now(),
                'classified_by' => $actor->getKey(),
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
            'defect_classification_id' => null,
            'classification_code' => null,
            'classification_priority' => null,
            'deadline_months' => null,
            'recommended_due_date' => null,
            'classification_snapshot' => null,
            'classified_at' => null,
            'classified_by' => null,
            'updated_by' => $actor->getKey(),
        ])->save();

        return $assessment->refresh();
    }
}
