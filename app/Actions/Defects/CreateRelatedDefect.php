<?php

declare(strict_types=1);

namespace App\Actions\Defects;

use App\Actions\Classification\ProvisionDefaultDefectTaxonomy;
use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectAssessmentStatus;
use App\Enums\DefectCategory;
use App\Enums\DefectRelationType;
use App\Enums\DefectStatus;
use App\Enums\InspectionStatus;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\DefectRelation;
use App\Models\Inspection;
use App\Models\User;
use App\Services\Defects\DefectCodeGenerator;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateRelatedDefect
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly DefectCodeGenerator $codeGenerator,
        private readonly ProvisionDefaultDefectTaxonomy $taxonomy,
    ) {}

    public function handle(User $actor, Inspection $inspection, Defect $source, array $data): Defect
    {
        return DB::transaction(function () use ($actor, $inspection, $source, $data): Defect {
            $inspection = Inspection::query()->forOrganization($this->tenant->id())->with(['equipment', 'responsibles'])->lockForUpdate()->findOrFail($inspection->getKey());
            $source = Defect::query()->forOrganization($this->tenant->id())->lockForUpdate()->findOrFail($source->getKey());
            $this->validate($inspection, $source, $data);

            $category = $source->categoryDefinition
                ?? $this->taxonomy->handle($this->tenant->id());
            $generated = $this->codeGenerator->nextForCategory($inspection->equipment, $category);
            $defect = Defect::query()->create([
                'organization_id' => $this->tenant->id(),
                'equipment_id' => $inspection->equipment_id,
                'first_inspection_id' => $inspection->getKey(),
                'defect_category_id' => $category->getKey(),
                'code' => $generated['code'],
                // Legacy compatibility until the enum column is removed. The relation is canonical.
                'category' => DefectCategory::Civil,
                'sequence_number' => $generated['number'],
                'title' => $data['title'],
                'origin_description' => $data['origin_description'] ?? null,
                'status' => DefectStatus::Active,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);

            DefectAssessment::query()->create([
                'organization_id' => $this->tenant->id(),
                'equipment_id' => $inspection->equipment_id,
                'defect_id' => $defect->getKey(),
                'inspection_id' => $inspection->getKey(),
                'condition' => DefectAssessmentCondition::New,
                'status' => DefectAssessmentStatus::Draft,
                'location_description' => $data['location_description'] ?? null,
                'comment' => $data['comment'] ?? null,
                'recommendation' => $data['recommendation'] ?? null,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
                'snapshot_version' => 1,
            ]);

            DefectRelation::query()->create([
                'organization_id' => $this->tenant->id(),
                'equipment_id' => $inspection->equipment_id,
                'source_defect_id' => $source->getKey(),
                'target_defect_id' => $defect->getKey(),
                'relation_type' => DefectRelationType::from($data['relation_type']),
                'created_by' => $actor->getKey(),
                'created_at' => now(),
            ]);

            return $defect->refresh();
        });
    }

    private function validate(Inspection $inspection, Defect $source, array $data): void
    {
        if ($source->equipment_id !== $inspection->equipment_id) {
            throw ValidationException::withMessages(['defect' => 'A avaria não pertence ao equipamento desta inspeção.']);
        }

        if (! in_array($inspection->status, [
            InspectionStatus::InProgress,
            InspectionStatus::InCorrection,
        ], true)) {
            throw ValidationException::withMessages(['inspection' => 'A inspeção não está em estado editável.']);
        }

        if ($data['relation_type'] === DefectRelationType::Recurrence->value && ! $source->isRepaired()) {
            throw ValidationException::withMessages(['relation_type' => 'Recorrência exige que a avaria de origem esteja reparada.']);
        }

        if (DefectRelation::query()->where('source_defect_id', $source->getKey())->where('relation_type', $data['relation_type'])->whereHas('targetDefect', fn ($query) => $query->where('first_inspection_id', $inspection->getKey()))->exists()) {
            throw ValidationException::withMessages(['relation_type' => 'Já existe uma relação deste tipo para esta inspeção.']);
        }
    }
}
