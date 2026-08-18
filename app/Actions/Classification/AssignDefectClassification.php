<?php

declare(strict_types=1);

namespace App\Actions\Classification;

use App\Enums\DefectAssessmentCondition;
use App\Enums\InspectionStatus;
use App\Models\DefectAssessment;
use App\Models\DefectClassification;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AssignDefectClassification
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(User $actor, DefectAssessment $assessment, int $classificationId): DefectAssessment
    {
        return DB::transaction(function () use ($actor, $assessment, $classificationId): DefectAssessment {
            $assessment = DefectAssessment::query()
                ->forOrganization($this->tenant->id())
                ->with(['defect', 'defect.categoryDefinition', 'inspection'])
                ->lockForUpdate()
                ->findOrFail($assessment->getKey());

            if (! in_array($assessment->inspection->status, [InspectionStatus::InProgress, InspectionStatus::InCorrection], true)) {
                throw ValidationException::withMessages(['inspection' => 'A avaliação não está em estado editável.']);
            }

            if (in_array($assessment->condition, [
                DefectAssessmentCondition::Repaired,
                DefectAssessmentCondition::NotLocated,
                DefectAssessmentCondition::NotInspected,
            ], true)) {
                throw ValidationException::withMessages(['classification' => 'Esta condição não recebe classificação atual.']);
            }

            $category = $assessment->defect->categoryDefinition;

            if ($category === null) {
                throw ValidationException::withMessages(['classification' => 'A avaria ainda não possui categoria configurável.']);
            }

            $classification = DefectClassification::query()
                ->forOrganization($this->tenant->id())
                ->whereKey($classificationId)
                ->where('defect_category_id', $category->getKey())
                ->where('status', 'active')
                ->first();

            if ($classification === null) {
                throw ValidationException::withMessages(['classification' => 'A classificação não pertence à categoria da avaria ou está inativa.']);
            }

            $assessment->fill([
                'defect_classification_id' => $classification->getKey(),
                'classification_code' => $classification->code,
                'classification_priority' => $classification->severity_rank,
                'classification_snapshot' => [
                    'source' => 'manual',
                    'classification_id' => $classification->public_id,
                    'category_id' => $category->public_id,
                    'category_code' => $category->code,
                    'category_name' => $category->name,
                    'code' => $classification->code,
                    'name' => $classification->name,
                    'description' => $classification->description,
                    'position' => $classification->position,
                    'severity_rank' => $classification->severity_rank,
                ],
                'classified_at' => now(),
                'classified_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ])->save();

            return $assessment->refresh();
        });
    }
}
