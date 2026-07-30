<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionStatusHistory;
use App\Models\User;
use App\Services\Inspections\InspectionSnapshotBuilder;
use App\Services\Tenancy\TenantContext;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateInspection
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly InspectionSnapshotBuilder $snapshotBuilder,
    ) {}

    public function handle(User $actor, Equipment $equipment, array $data): Inspection
    {
        if (! $actor->isActive() || $actor->isSuperAdmin() || ! $actor->belongsToOrganization($this->tenant->id())) {
            throw ValidationException::withMessages([
                'actor' => 'O usuário não pode criar inspeções na organização atual.',
            ]);
        }

        return DB::transaction(function () use ($actor, $equipment, $data): Inspection {
            // The equipment row is the serialization point. Locking the inspections
            // found by the query is not sufficient when no inspection exists yet.
            $equipment = Equipment::query()
                ->where('organization_id', $this->tenant->id())
                ->whereKey($equipment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $equipment->canReceiveInspection()) {
                throw ValidationException::withMessages([
                    'equipment' => 'O equipamento não pode receber nova inspeção.',
                ]);
            }

            $hasOpenInspection = Inspection::query()
                ->where('organization_id', $this->tenant->id())
                ->where('equipment_id', $equipment->getKey())
                ->whereNotIn('status', [
                    InspectionStatus::Released->value,
                    InspectionStatus::Canceled->value,
                ])
                ->exists();

            if ($hasOpenInspection) {
                throw ValidationException::withMessages([
                    'equipment' => 'O equipamento já possui uma inspeção aberta.',
                ]);
            }

            $type = InspectionType::from($data['inspection_type']);
            $previousInspection = $this->resolvePreviousInspection(
                $equipment,
                $type,
                $data['previous_inspection_id'] ?? null,
            );

            $inspection = Inspection::query()->create([
                'organization_id' => $this->tenant->id(),
                'equipment_id' => $equipment->getKey(),
                'previous_inspection_id' => $previousInspection?->getKey(),
                'inspection_type' => $type,
                'status' => InspectionStatus::Planned,
                'service_order' => TextNormalizer::nullableText($data['service_order'] ?? null),
                'external_report_number' => TextNormalizer::nullableText($data['external_report_number'] ?? null),
                'procedure_number' => TextNormalizer::nullableText($data['procedure_number'] ?? null),
                'atmospheric_classification' => TextNormalizer::nullableText($data['atmospheric_classification'] ?? null),
                'scheduled_for' => $data['scheduled_for'] ?? $data['scheduled_at'] ?? null,
                'context_snapshot' => $this->snapshotBuilder->build($equipment),
                'snapshot_version' => InspectionSnapshotBuilder::VERSION,
                'general_notes' => TextNormalizer::nullableText($data['general_notes'] ?? null),
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);

            $inspection->update([
                'number' => sprintf('INS-%s-%06d', now()->format('Y'), $inspection->getKey()),
            ]);

            InspectionStatusHistory::query()->create([
                'organization_id' => $this->tenant->id(),
                'inspection_id' => $inspection->getKey(),
                'from_status' => null,
                'to_status' => InspectionStatus::Planned,
                'changed_by' => $actor->getKey(),
                'reason' => 'Inspeção criada.',
                'created_at' => now(),
            ]);

            return $inspection->refresh();
        });
    }

    private function resolvePreviousInspection(
        Equipment $equipment,
        InspectionType $type,
        mixed $previousInspectionId,
    ): ?Inspection {
        if ($type === InspectionType::Initial) {
            if ($previousInspectionId !== null) {
                throw ValidationException::withMessages([
                    'previous_inspection_id' => 'Inspeção inicial não pode possuir inspeção anterior.',
                ]);
            }

            return null;
        }

        if ($previousInspectionId === null) {
            throw ValidationException::withMessages([
                'previous_inspection_id' => 'Selecione a inspeção anterior.',
            ]);
        }

        return Inspection::query()
            ->where('organization_id', $this->tenant->id())
            ->where('equipment_id', $equipment->getKey())
            ->where('status', InspectionStatus::Released->value)
            ->findOrFail($previousInspectionId);
    }
}
