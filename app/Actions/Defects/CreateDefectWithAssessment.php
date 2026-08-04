<?php

declare(strict_types=1);

namespace App\Actions\Defects;

use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectAssessmentStatus;
use App\Enums\DefectCategory;
use App\Enums\DefectStatus;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\User;
use App\Services\Defects\DefectCodeGenerator;
use App\Services\Tenancy\TenantContext;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateDefectWithAssessment
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly DefectCodeGenerator $codeGenerator,
        private readonly CompleteDefectAssessment $completeAssessment,
    ) {}

    public function handle(User $actor, Inspection $inspection, array $data): Defect
    {
        return DB::transaction(function () use ($actor, $inspection, $data): Defect {
            $inspection = Inspection::query()
                ->forOrganization($this->tenant->id())
                ->with(['equipment', 'responsibles'])
                ->lockForUpdate()
                ->findOrFail($inspection->getKey());

            $this->validateActor($actor, $inspection);

            if (! in_array($inspection->status, [
                InspectionStatus::InProgress,
                InspectionStatus::InCorrection,
            ], true)) {
                throw ValidationException::withMessages([
                    'inspection' => 'A inspeção não está em estado editável para criar avarias.',
                ]);
            }

            $equipment = Equipment::query()
                ->forOrganization($this->tenant->id())
                ->whereKey($inspection->equipment_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $equipment->defect_code_prefix) {
                throw ValidationException::withMessages([
                    'defect_code_prefix' => 'Configure o prefixo de avaria do equipamento antes de criar avarias.',
                ]);
            }

            $generated = $this->codeGenerator->next($equipment, DefectCategory::Civil);

            $defect = Defect::query()->create([
                'organization_id' => $this->tenant->id(),
                'equipment_id' => $equipment->getKey(),
                'first_inspection_id' => $inspection->getKey(),
                'code' => $generated['code'],
                'category' => DefectCategory::Civil,
                'sequence_number' => $generated['number'],
                'title' => TextNormalizer::text((string) $data['title']),
                'origin_description' => TextNormalizer::nullableText($data['origin_description'] ?? null),
                'status' => DefectStatus::Active,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);

            $assessment = DefectAssessment::query()->create([
                'organization_id' => $this->tenant->id(),
                'equipment_id' => $equipment->getKey(),
                'defect_id' => $defect->getKey(),
                'inspection_id' => $inspection->getKey(),
                'previous_assessment_id' => null,
                'condition' => DefectAssessmentCondition::New,
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
                $assessment = $this->completeAssessment->handle($actor, $assessment, $data);
            }

            return $defect->refresh();
        });
    }

    private function validateActor(User $actor, Inspection $inspection): void
    {
        if (! $actor->isActive() || $actor->isSuperAdmin() || ! $actor->belongsToOrganization($this->tenant->id())) {
            throw ValidationException::withMessages([
                'actor' => 'O usuário não pode criar avarias na organização atual.',
            ]);
        }

        if (! $inspection->hasAnyResponsibilityForUser(
            $actor,
            InspectionResponsibility::Inspector,
            InspectionResponsibility::Preparer,
        )) {
            throw ValidationException::withMessages([
                'actor' => 'O usuário não está autorizado a criar avarias nesta inspeção.',
            ]);
        }
    }
}
