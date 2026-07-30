<?php

declare(strict_types=1);

namespace App\Actions\Defects;

use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectAssessmentStatus;
use App\Enums\DefectStatus;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Inspection;
use App\Models\User;
use App\Services\Defects\DefectAssessmentCompletionValidator;
use App\Services\Defects\ResolvePreviousDefectAssessment;
use App\Services\Tenancy\TenantContext;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AssessExistingDefect
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly ResolvePreviousDefectAssessment $previousAssessmentResolver,
        private readonly DefectAssessmentCompletionValidator $validator,
        private readonly CompleteDefectAssessment $completeAssessment,
    ) {}

    public function handle(User $actor, Inspection $inspection, Defect $defect, array $data): DefectAssessment
    {
        return DB::transaction(function () use ($actor, $inspection, $defect, $data): DefectAssessment {
            $inspection = Inspection::query()
                ->forOrganization($this->tenant->id())
                ->with(['equipment', 'previousInspection'])
                ->lockForUpdate()
                ->findOrFail($inspection->getKey());

            $defect = Defect::query()
                ->forOrganization($this->tenant->id())
                ->lockForUpdate()
                ->findOrFail($defect->getKey());

            $this->validateActor($actor, $inspection);

            if ($defect->equipment_id !== $inspection->equipment_id) {
                throw ValidationException::withMessages([
                    'defect' => 'A avaria não pertence ao equipamento desta inspeção.',
                ]);
            }

            if ($defect->status !== DefectStatus::Active) {
                throw ValidationException::withMessages([
                    'defect' => 'A avaria já foi finalizada. Registre a recorrência como uma nova avaria.',
                ]);
            }

            if (DefectAssessment::query()
                ->forOrganization($this->tenant->id())
                ->where('defect_id', $defect->getKey())
                ->where('inspection_id', $inspection->getKey())
                ->exists()) {
                throw ValidationException::withMessages([
                    'inspection' => 'Já existe uma avaliação desta avaria nesta inspeção.',
                ]);
            }

            $condition = DefectAssessmentCondition::from($data['condition']);

            $this->validator->ensureConditionAllowed($defect, $inspection, $condition);

            $previousAssessment = $this->previousAssessmentResolver->handle($defect, $inspection);

            $assessment = DefectAssessment::query()->create([
                'organization_id' => $this->tenant->id(),
                'equipment_id' => $inspection->equipment_id,
                'defect_id' => $defect->getKey(),
                'inspection_id' => $inspection->getKey(),
                'previous_assessment_id' => $previousAssessment?->getKey(),
                'condition' => $condition,
                'status' => DefectAssessmentStatus::Draft,
                'location_description' => TextNormalizer::nullableText($data['location_description'] ?? null),
                'comment' => TextNormalizer::nullableText($data['comment'] ?? null),
                'recommendation' => TextNormalizer::nullableText($data['recommendation'] ?? null),
                'reason' => TextNormalizer::nullableText($data['reason'] ?? null),
                'internal_notes' => TextNormalizer::nullableText($data['internal_notes'] ?? null),
                'defect_snapshot' => null,
                'snapshot_version' => 1,
                'assessed_at' => null,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);

            if (($data['assessment_action'] ?? DefectAssessmentStatus::Draft->value) === DefectAssessmentStatus::Complete->value) {
                return $this->completeAssessment->handle($actor, $assessment, $data);
            }

            return $assessment->refresh();
        });
    }

    private function validateActor(User $actor, Inspection $inspection): void
    {
        if (! $actor->isActive() || $actor->isSuperAdmin() || ! $actor->belongsToOrganization($this->tenant->id())) {
            throw ValidationException::withMessages([
                'actor' => 'O usuário não pode avaliar avarias na organização atual.',
            ]);
        }

        if (! $inspection->hasAnyResponsibilityForUser(
            $actor,
            InspectionResponsibility::Inspector,
            InspectionResponsibility::Preparer,
        )) {
            throw ValidationException::withMessages([
                'actor' => 'O usuário não está autorizado a avaliar avarias nesta inspeção.',
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
