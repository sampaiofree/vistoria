<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use App\Enums\OperationalRole;
use App\Enums\UserStatus;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\User;
use App\Services\Inspections\InspectionSnapshotBuilder;
use App\Services\Inspections\PreviousInspectionContentCopier;
use App\Services\Inspections\ReinspectionScopePlanner;
use App\Services\Tenancy\TenantContext;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class UpdatePlannedInspection
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly InspectionSnapshotBuilder $snapshotBuilder,
        private readonly PreviousInspectionContentCopier $contentCopier,
    ) {}

    public function handle(Inspection $inspection, User $actor, array $data): Inspection
    {
        if (! $actor->isActive()
            || $actor->isSuperAdmin()
            || ! $actor->belongsToOrganization($this->tenant->id())
            || $actor->operational_role !== OperationalRole::Planner) {
            throw ValidationException::withMessages([
                'actor' => 'O usuário não pode editar inspeções na organização atual.',
            ]);
        }

        $newFiles = [];
        $oldFiles = [];

        try {
            $updated = DB::transaction(function () use ($inspection, $actor, $data, &$newFiles, &$oldFiles): Inspection {
                $inspection = Inspection::query()
                    ->forOrganization($this->tenant->id())
                    ->lockForUpdate()
                    ->findOrFail($inspection->getKey());

                if ($inspection->status !== InspectionStatus::Planned
                    || ! $inspection->hasAnyResponsibilityForUser($actor, ...InspectionResponsibility::cases())) {
                    throw ValidationException::withMessages([
                        'actor' => 'Somente o Planejador vinculado pode editar o planejamento.',
                    ]);
                }

                $equipment = Equipment::query()
                    ->forOrganization($this->tenant->id())
                    ->whereKey($data['equipment_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $equipment->canReceiveInspection()) {
                    throw ValidationException::withMessages([
                        'equipment_id' => 'O equipamento não está apto para receber uma inspeção.',
                    ]);
                }

                $hasOtherOpenInspection = Inspection::query()
                    ->forOrganization($this->tenant->id())
                    ->where('equipment_id', $equipment->getKey())
                    ->where('id', '!=', $inspection->getKey())
                    ->whereNotIn('status', [InspectionStatus::Released->value, InspectionStatus::Canceled->value])
                    ->exists();

                if ($hasOtherOpenInspection) {
                    throw ValidationException::withMessages([
                        'equipment_id' => 'O equipamento já possui uma inspeção aberta.',
                    ]);
                }

                $inspector = User::query()
                    ->where('organization_id', $this->tenant->id())
                    ->whereKey($data['inspector_id'])
                    ->where('status', UserStatus::Active->value)
                    ->where('operational_role', OperationalRole::Inspector->value)
                    ->firstOrFail();

                $previousInspection = Inspection::query()
                    ->forOrganization($this->tenant->id())
                    ->where('equipment_id', $equipment->getKey())
                    ->where('status', InspectionStatus::Released->value)
                    ->orderByDesc('released_at')
                    ->orderByDesc('id')
                    ->first();

                InspectionResponsible::query()
                    ->where('inspection_id', $inspection->getKey())
                    ->where('responsibility', InspectionResponsibility::Reviewer->value)
                    ->lockForUpdate()
                    ->get();

                InspectionResponsible::query()
                    ->where('inspection_id', $inspection->getKey())
                    ->where('responsibility', InspectionResponsibility::Reviewer->value)
                    ->delete();

                InspectionResponsible::query()->create([
                    'organization_id' => $this->tenant->id(),
                    'inspection_id' => $inspection->getKey(),
                    'user_id' => $inspector->getKey(),
                    'responsibility' => InspectionResponsibility::Reviewer,
                    'is_primary' => true,
                    'assigned_by' => $actor->getKey(),
                    'assigned_at' => now(),
                ]);

                $equipmentChanged = $inspection->equipment_id !== $equipment->id;
                $inheritsPreviousContent = data_get($inspection->context_snapshot, 'previous_content_version') === 1;
                $contextSnapshot = $this->snapshotBuilder->build($equipment);
                if ($inheritsPreviousContent) {
                    $contextSnapshot['previous_content_version'] = 1;
                }
                $serviceOrder = TextNormalizer::nullableText($data['service_order'] ?? null);
                $inspection->update([
                    'equipment_id' => $equipment->getKey(),
                    'previous_inspection_id' => $previousInspection?->getKey(),
                    'inspection_type' => $previousInspection === null ? InspectionType::Initial : InspectionType::Reinspection,
                    'service_order' => $serviceOrder === TextNormalizer::nullableText($inspection->service_order)
                        ? $inspection->service_order
                        : $serviceOrder,
                    'atmospheric_classification' => array_key_exists('atmospheric_classification', $data)
                        ? TextNormalizer::technicalCode($data['atmospheric_classification'])
                        : $inspection->atmospheric_classification,
                    'planned_start_on' => $data['planned_start_on'],
                    'planned_end_on' => $data['planned_end_on'],
                    'context_snapshot' => $contextSnapshot,
                    'snapshot_version' => InspectionSnapshotBuilder::VERSION,
                    'updated_by' => $actor->getKey(),
                ]);

                $inspection->unsetRelation('previousInspection');
                if ($equipmentChanged || $inspection->reinspection_scope_version !== null || array_key_exists('reinspection_defect_ids', $data)) {
                    app(ReinspectionScopePlanner::class)->save($inspection, $actor, $data, $equipmentChanged);
                }

                if ($equipmentChanged && $inheritsPreviousContent) {
                    $oldFiles = $this->contentCopier->clear($inspection);
                    $this->contentCopier->copy($inspection, $previousInspection, $actor, $newFiles);
                }

                return $inspection->refresh();
            });
        } catch (Throwable $exception) {
            $this->contentCopier->deleteFiles($newFiles);

            throw $exception;
        }

        $this->contentCopier->deleteFiles($oldFiles);

        return $updated;
    }
}
