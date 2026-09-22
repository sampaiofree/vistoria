<?php

declare(strict_types=1);

namespace App\Actions\Defects;

use App\Enums\DefectAssessmentStatus;
use App\Enums\DefectCategory;
use App\Enums\GutCriterion;
use App\Enums\InspectionStatus;
use App\Models\DefectAssessment;
use App\Models\Inspection;
use App\Models\User;
use App\Services\Classification\GutClassificationResolver;
use App\Services\Defects\DefectAssessmentCompletionValidator;
use App\Services\Defects\DefectAssessmentQuantitySnapshot;
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
        private readonly DefectAssessmentQuantitySnapshot $quantitySnapshot,
    ) {}

    public function handle(User $actor, DefectAssessment $assessment, array $data = []): DefectAssessment
    {
        return DB::transaction(function () use ($actor, $assessment, $data): DefectAssessment {
            $assessment = DefectAssessment::query()
                ->forOrganization($this->tenant->id())
                ->with([
                    'defect',
                    'inspection',
                    'photos',
                    'quantities',
                    'locationMapVersion',
                    'location',
                ])
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
                'quantity_snapshot' => $assessment->condition->requiresEvidence()
                    && $assessment->defect->category !== DefectCategory::RoofCladding
                    ? $this->quantitySnapshot->build($assessment->defect->category, $assessment->quantities)
                    : null,
                'snapshot_version' => DefectSnapshotBuilder::VERSION,
                'updated_by' => $actor->getKey(),
            ]);

            if (! $assessment->condition->requiresGut()) {
                $assessment->fill([
                    'gravity' => null,
                    'urgency' => null,
                    'trend' => null,
                    'gut_score' => null,
                    'gut_snapshot' => null,
                    'gut_classified_at' => null,
                    'gut_classified_by' => null,
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
                ]);
            }

            $this->validator->ensureConditionAllowed(
                $assessment->defect,
                $assessment->inspection,
                $assessment->condition,
            );

            $this->validator->ensureCanComplete($assessment);

            if ($assessment->condition->requiresGut()) {
                if ($assessment->defect->category === DefectCategory::RoofCladding) {
                    $this->ensureConfiguredTelSelected($assessment);
                } else {
                    $this->ensureConfiguredGutSelected($assessment);
                }
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

        if (! $actor->can('manageFieldContent', $inspection)) {
            throw ValidationException::withMessages([
                'actor' => 'O usuário não está autorizado a concluir avaliações nesta inspeção.',
            ]);
        }

        if (! in_array($inspection->status, [
            InspectionStatus::InProgress,
            InspectionStatus::InCorrection,
            InspectionStatus::InReview,
        ], true)) {
            throw ValidationException::withMessages([
                'inspection' => 'A inspeção não está em estado editável.',
            ]);
        }
    }

    private function ensureConfiguredGutSelected(DefectAssessment $assessment): void
    {
        $category = $assessment->defect->category;

        $this->gutResolver->resolve($category, [
            GutCriterion::Gravity->value => $assessment->gravity,
            GutCriterion::Urgency->value => $assessment->urgency,
            GutCriterion::Trend->value => $assessment->trend,
        ]);

        if ($assessment->gut_score === null) {
            throw ValidationException::withMessages([
                'gut' => 'Salve a avaliação GUT antes de publicar a avaliação.',
            ]);
        }
    }

    private function ensureConfiguredTelSelected(DefectAssessment $assessment): void
    {
        if ($assessment->tel_score === null || ! is_array($assessment->tel_snapshot)
            || $assessment->classification_code === null || ! is_array($assessment->classification_snapshot)) {
            throw ValidationException::withMessages([
                'tel' => 'Salve a classificação TEL antes de publicar a avaliação.',
            ]);
        }
    }
}
