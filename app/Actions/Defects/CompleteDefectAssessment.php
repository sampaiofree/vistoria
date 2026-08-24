<?php

declare(strict_types=1);

namespace App\Actions\Defects;

use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectAssessmentStatus;
use App\Enums\GutCriterion;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Models\DefectAssessment;
use App\Models\Inspection;
use App\Models\User;
use App\Services\Classification\GutClassificationResolver;
use App\Services\Defects\DefectAssessmentCompletionValidator;
use App\Services\Defects\DefectSnapshotBuilder;
use App\Services\Defects\DefectStatusSynchronizer;
use App\Services\Tenancy\TenantContext;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CompleteDefectAssessment
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly DefectAssessmentCompletionValidator $validator,
        private readonly DefectStatusSynchronizer $statusSynchronizer,
        private readonly DefectSnapshotBuilder $snapshotBuilder,
        private readonly GutClassificationResolver $gutResolver,
    ) {}

    public function handle(User $actor, DefectAssessment $assessment, array $data = []): DefectAssessment
    {
        return DB::transaction(function () use ($actor, $assessment, $data): DefectAssessment {
            $assessment = DefectAssessment::query()
                ->forOrganization($this->tenant->id())
                ->with(['defect.categoryDefinition.gutOptions', 'defect.categoryDefinition.classifications', 'inspection'])
                ->lockForUpdate()
                ->findOrFail($assessment->getKey());

            $this->validateActor($actor, $assessment->inspection);

            if ($assessment->isComplete() && $data === []) {
                return $assessment->refresh();
            }

            if ($data !== []) {
                $assessment->fill([
                    'condition' => $data['condition'] ?? $assessment->condition,
                    'location_description' => TextNormalizer::nullableText($data['location_description'] ?? $assessment->location_description),
                    'comment' => TextNormalizer::nullableText($data['comment'] ?? $assessment->comment),
                    'recommendation' => TextNormalizer::nullableText($data['recommendation'] ?? $assessment->recommendation),
                    'reason' => TextNormalizer::nullableText($data['reason'] ?? $assessment->reason),
                    'internal_notes' => TextNormalizer::nullableText($data['internal_notes'] ?? $assessment->internal_notes),
                    'item_description' => TextNormalizer::nullableText($data['item_description'] ?? $assessment->item_description),
                    'project_reference' => TextNormalizer::nullableText($data['project_reference'] ?? $assessment->project_reference),
                    'impacts_activity' => $data['impacts_activity'] ?? $assessment->impacts_activity,
                    'updated_by' => $actor->getKey(),
                ]);
            }

            $assessment->fill([
                'status' => DefectAssessmentStatus::Complete,
                'assessed_at' => now(),
                'defect_snapshot' => $this->snapshotBuilder->build($assessment->defect),
                'snapshot_version' => DefectSnapshotBuilder::VERSION,
                'updated_by' => $actor->getKey(),
            ]);

            if (in_array($assessment->condition, [
                DefectAssessmentCondition::Repaired,
                DefectAssessmentCondition::NotLocated,
                DefectAssessmentCondition::NotInspected,
            ], true)) {
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
                ]);
            }

            if (in_array($assessment->condition, [
                DefectAssessmentCondition::NotLocated,
                DefectAssessmentCondition::NotInspected,
            ], true) && $assessment->locationMarkers()->exists()) {
                throw ValidationException::withMessages([
                    'condition' => 'Remova as marcações desta avaliação antes de publicar uma condição sem localização no mapa.',
                ]);
            }

            $this->validator->ensureConditionAllowed(
                $assessment->defect,
                $assessment->inspection,
                $assessment->condition,
                $assessment->inspection_id === $assessment->defect->first_inspection_id,
            );

            $this->validator->ensureCanComplete($assessment);

            if (in_array($assessment->condition, [
                DefectAssessmentCondition::New,
                DefectAssessmentCondition::Unchanged,
                DefectAssessmentCondition::Worsened,
                DefectAssessmentCondition::Improved,
            ], true)) {
                $this->ensureConfiguredGutSelected($assessment);
            }

            $assessment->save();

            $this->statusSynchronizer->handle($assessment->defect, $actor);

            return $assessment->refresh();
        });
    }

    private function validateActor(User $actor, Inspection $inspection): void
    {
        if (! $actor->isActive() || $actor->isSuperAdmin() || ! $actor->belongsToOrganization($this->tenant->id())) {
            throw ValidationException::withMessages([
                'actor' => 'O usuário não pode concluir avaliações na organização atual.',
            ]);
        }

        if (! $inspection->hasAnyResponsibilityForUser(
            $actor,
            InspectionResponsibility::Preparer,
        )) {
            throw ValidationException::withMessages([
                'actor' => 'O usuário não está autorizado a concluir avaliações nesta inspeção.',
            ]);
        }

        if (! in_array($inspection->status, [
            InspectionStatus::InProgress,
            InspectionStatus::InCorrection,
        ], true)) {
            throw ValidationException::withMessages([
                'inspection' => 'A inspeção não está em estado editável.',
            ]);
        }
    }

    private function ensureConfiguredGutSelected(DefectAssessment $assessment): void
    {
        $category = $assessment->defect->categoryDefinition;

        if ($category === null) {
            throw ValidationException::withMessages([
                'gut' => 'A avaria precisa possuir uma categoria configurada para publicar a avaliação.',
            ]);
        }

        $this->gutResolver->resolve($category, [
            GutCriterion::Gravity->value => $assessment->gravity,
            GutCriterion::Urgency->value => $assessment->urgency,
            GutCriterion::Trend->value => $assessment->trend,
        ]);

        if ($assessment->gut_score === null || $assessment->defect_classification_id === null) {
            throw ValidationException::withMessages([
                'gut' => 'Salve a classificação GUT antes de publicar a avaliação.',
            ]);
        }
    }
}
