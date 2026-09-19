<?php

declare(strict_types=1);

namespace App\Actions\Defects;

use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectAssessmentStatus;
use App\Enums\InspectionStatus;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Inspection;
use App\Models\User;
use App\Services\Defects\DefectAssessmentCompletionValidator;
use App\Services\Defects\DefectStatusSynchronizer;
use App\Services\Tenancy\TenantContext;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateDefectAssessment
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly DefectAssessmentCompletionValidator $validator,
        private readonly DefectStatusSynchronizer $statusSynchronizer,
    ) {}

    public function handle(User $actor, DefectAssessment $assessment, array $data): DefectAssessment
    {
        return DB::transaction(function () use ($actor, $assessment, $data): DefectAssessment {
            $assessment = DefectAssessment::query()
                ->forOrganization($this->tenant->id())
                ->with(['defect', 'inspection'])
                ->lockForUpdate()
                ->findOrFail($assessment->getKey());

            $this->validateActor($actor, $assessment->inspection);

            $defect = Defect::query()
                ->forOrganization($this->tenant->id())
                ->lockForUpdate()
                ->findOrFail($assessment->defect_id);

            if ($assessment->inspection->status === InspectionStatus::Canceled) {
                throw ValidationException::withMessages([
                    'inspection' => 'Não é possível editar avaliações de uma inspeção cancelada.',
                ]);
            }

            $wasComplete = $assessment->isComplete();

            if ($wasComplete) {
                $markerCount = $assessment->locationMarkers()->count();

                if ($markerCount > 0) {
                    throw ValidationException::withMessages([
                        'status' => "Esta avaliação possui {$markerCount} marcação(ões). Remova os vínculos na aba Localização antes de movê-la para rascunho.",
                    ]);
                }
            }

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

            $this->validator->ensureConditionAllowed(
                $defect,
                $assessment->inspection,
                $assessment->condition,
            );

            if ($wasComplete) {
                $assessment->fill([
                    'status' => DefectAssessmentStatus::Draft,
                    'assessed_at' => null,
                    'defect_snapshot' => null,
                ]);
            }

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
                    'classification_code' => null,
                    'classification_priority' => null,
                    'deadline_months' => null,
                    'recommended_due_date' => null,
                    'classification_snapshot' => null,
                    'classified_at' => null,
                    'classified_by' => null,
                ]);
            }

            $assessment->save();

            if ($wasComplete) {
                $this->statusSynchronizer->handle($defect, $actor);
            }

            return $assessment->refresh();
        });
    }

    private function validateActor(User $actor, Inspection $inspection): void
    {
        if (! $actor->isActive() || $actor->isSuperAdmin() || ! $actor->belongsToOrganization($this->tenant->id())) {
            throw ValidationException::withMessages([
                'actor' => 'O usuário não pode editar avaliações na organização atual.',
            ]);
        }

        if (! $actor->can('manageFieldContent', $inspection)) {
            throw ValidationException::withMessages([
                'actor' => 'O usuário não está autorizado a editar avaliações nesta inspeção.',
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
}
