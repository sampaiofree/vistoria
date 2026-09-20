<?php

declare(strict_types=1);

namespace App\Actions\Classification;

use App\Enums\GutCriterion;
use App\Models\DefectAssessment;
use App\Models\User;
use App\Services\Classification\GutClassificationResolver;
use App\Services\Classification\NativeDefectCatalog;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

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

            if (! $condition->requiresGut()) {
                return $this->clear($assessment, $actor);
            }

            $category = $assessment->defect->category;
            $resolved = $this->resolver->resolveTechnical($assessment, $data);
            $classification = $resolved['classification'];

            $classificationValues = $classification === null
                ? [
                    'classification_code' => null,
                    'classification_priority' => null,
                    'deadline_months' => null,
                    'recommended_due_date' => null,
                    'classification_snapshot' => null,
                    'classified_at' => null,
                    'classified_by' => null,
                ]
                : [
                    'classification_code' => $classification->code,
                    'classification_priority' => $classification->severity_rank,
                    'deadline_months' => null,
                    'recommended_due_date' => null,
                    'classification_snapshot' => [
                        'source' => 'native_catalog',
                        'catalog_version' => NativeDefectCatalog::VERSION,
                        'category_code' => $category->value,
                        'category_name' => $category->label(),
                        ...$classification->toArray(),
                        'gut_score' => $resolved['gut_score'],
                    ],
                    'classified_at' => now(),
                    'classified_by' => $actor->getKey(),
                ];

            $assessment->fill([
                'gravity' => $resolved['criteria'][GutCriterion::Gravity->value]['score'],
                'urgency' => $resolved['criteria'][GutCriterion::Urgency->value]['score'],
                'trend' => $resolved['criteria'][GutCriterion::Trend->value]['score'],
                'gut_score' => $resolved['gut_score'],
                'gut_snapshot' => [
                    'source' => 'native_catalog',
                    'catalog_version' => NativeDefectCatalog::VERSION,
                    'category_code' => $category->value,
                    'category_name' => $category->label(),
                    'criteria' => $resolved['criteria'],
                    'score' => $resolved['gut_score'],
                    'classification' => $classification?->toArray(),
                ],
                'gut_classified_at' => now(),
                'gut_classified_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ] + $classificationValues)->save();

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
