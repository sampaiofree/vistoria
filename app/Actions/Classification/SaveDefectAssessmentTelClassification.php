<?php

declare(strict_types=1);

namespace App\Actions\Classification;

use App\Models\DefectAssessment;
use App\Models\User;
use App\Services\Classification\NativeDefectCatalog;
use App\Services\Classification\TelClassificationResolver;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

final class SaveDefectAssessmentTelClassification
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly TelClassificationResolver $resolver,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, DefectAssessment $assessment, array $data): DefectAssessment
    {
        return DB::transaction(function () use ($actor, $assessment, $data): DefectAssessment {
            $assessment = DefectAssessment::query()
                ->forOrganization($this->tenant->id())
                ->with('defect')
                ->lockForUpdate()
                ->findOrFail($assessment->getKey());

            if (! $assessment->condition->requiresGut()) {
                return $this->clear($assessment, $actor);
            }

            $resolved = $this->resolver->resolveTechnical($assessment, $data);
            $classification = $resolved['classification'];

            $assessment->fill([
                'tel_score' => $resolved['tel_score'],
                'tel_snapshot' => [
                    'source' => 'native_tel_catalog',
                    'catalog_version' => NativeDefectCatalog::TEL_CATALOG_VERSION,
                    'category_code' => $assessment->defect->category->value,
                    'category_name' => $assessment->defect->category->label(),
                    'height_m' => $resolved['height_m'],
                    'impact' => $resolved['impact'],
                    'damage_group' => $resolved['damage_group'],
                    'damage_option' => $resolved['damage_option'],
                    'fall_risk' => $resolved['risk'],
                    'score' => $resolved['tel_score'],
                    'classification' => $classification?->toArray(),
                    'recommendation' => $classification?->description,
                ],
                'tel_classified_at' => now(),
                'tel_classified_by' => $actor->getKey(),
                'classification_code' => $classification?->code,
                'classification_priority' => $classification?->severity_rank,
                'deadline_months' => null,
                'recommended_due_date' => null,
                'classification_snapshot' => $classification === null ? null : [
                    'source' => 'native_tel_catalog',
                    'catalog_version' => NativeDefectCatalog::TEL_CATALOG_VERSION,
                    'category_code' => $assessment->defect->category->value,
                    'category_name' => $assessment->defect->category->label(),
                    ...$classification->toArray(),
                    'tel_score' => $resolved['tel_score'],
                ],
                'classified_at' => $classification === null ? null : now(),
                'classified_by' => $classification === null ? null : $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ])->save();

            return $assessment->refresh();
        });
    }

    private function clear(DefectAssessment $assessment, User $actor): DefectAssessment
    {
        $assessment->fill([
            'tel_score' => null,
            'tel_snapshot' => null,
            'tel_classified_at' => null,
            'tel_classified_by' => null,
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
