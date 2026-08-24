<?php

declare(strict_types=1);

namespace App\Actions\Defects;

use App\Actions\Classification\ProvisionDefaultDefectTaxonomy;
use App\Actions\Classification\SaveDefectAssessmentGut;
use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectAssessmentStatus;
use App\Enums\DefectCategory;
use App\Enums\DefectStatus;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\DefectCategory as DefectCategoryModel;
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
        private readonly SaveDefectAssessmentGut $saveGut,
        private readonly ProvisionDefaultDefectTaxonomy $taxonomy,
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

            $category = isset($data['defect_category_id']) && $data['defect_category_id'] !== null
                ? DefectCategoryModel::query()
                    ->forOrganization($this->tenant->id())
                    ->whereKey((int) $data['defect_category_id'])
                    ->where('status', 'active')
                    ->first()
                : $this->taxonomy->handle($this->tenant->id());

            if ($category === null) {
                throw ValidationException::withMessages(['defect_category_id' => 'A categoria selecionada não está ativa na organização atual.']);
            }

            $generated = $this->codeGenerator->nextForCategory($equipment, $category);

            $defect = Defect::query()->create([
                'organization_id' => $this->tenant->id(),
                'equipment_id' => $equipment->getKey(),
                'first_inspection_id' => $inspection->getKey(),
                'defect_category_id' => $category->getKey(),
                'code' => $generated['code'],
                // Legacy compatibility until the enum column is removed. The relation is canonical.
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
                $assessment = $this->saveGut->handle($actor, $assessment, [
                    'condition' => $assessment->condition->value,
                    'gravity' => $data['gravity'] ?? null,
                    'urgency' => $data['urgency'] ?? null,
                    'trend' => $data['trend'] ?? null,
                ]);

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
            InspectionResponsibility::Preparer,
        )) {
            throw ValidationException::withMessages([
                'actor' => 'O usuário não está autorizado a criar avarias nesta inspeção.',
            ]);
        }
    }
}
