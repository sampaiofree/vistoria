<?php

declare(strict_types=1);

namespace App\Actions\Defects;

use App\Enums\DefectAssessmentStatus;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Models\DefectAssessment;
use App\Models\Inspection;
use App\Models\User;
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
    ) {}

    public function handle(User $actor, DefectAssessment $assessment, array $data = []): DefectAssessment
    {
        return DB::transaction(function () use ($actor, $assessment, $data): DefectAssessment {
            $assessment = DefectAssessment::query()
                ->forOrganization($this->tenant->id())
                ->with(['defect', 'inspection'])
                ->lockForUpdate()
                ->findOrFail($assessment->getKey());

            $this->validateActor($actor, $assessment->inspection);

            if ($assessment->isComplete()) {
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

            $this->validator->ensureConditionAllowed(
                $assessment->defect,
                $assessment->inspection,
                $assessment->condition,
                $assessment->inspection_id === $assessment->defect->first_inspection_id,
            );

            $this->validator->ensureCanComplete($assessment);

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
            InspectionResponsibility::Inspector,
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
}
