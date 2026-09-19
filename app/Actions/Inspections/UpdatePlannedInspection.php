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
use App\Services\Tenancy\TenantContext;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdatePlannedInspection
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly InspectionSnapshotBuilder $snapshotBuilder,
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

        return DB::transaction(function () use ($inspection, $actor, $data): Inspection {
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

            $inspection->update([
                'equipment_id' => $equipment->getKey(),
                'previous_inspection_id' => $previousInspection?->getKey(),
                'inspection_type' => $previousInspection === null ? InspectionType::Initial : InspectionType::Reinspection,
                'service_order' => TextNormalizer::nullableText($data['service_order'] ?? null),
                'planned_start_on' => $data['planned_start_on'],
                'planned_end_on' => $data['planned_end_on'],
                'context_snapshot' => $this->snapshotBuilder->build($equipment),
                'snapshot_version' => InspectionSnapshotBuilder::VERSION,
                'updated_by' => $actor->getKey(),
            ]);

            return $inspection->refresh();
        });
    }
}
