<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Enums\EquipmentRevisionEmissionType;
use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionStatusHistory;
use App\Models\User;
use App\Services\Inspections\InspectionSnapshotBuilder;
use App\Services\Inspections\PreviousInspectionContentCopier;
use App\Services\Inspections\ReinspectionScopePlanner;
use App\Services\Tenancy\TenantContext;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CreateInspection
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly InspectionSnapshotBuilder $snapshotBuilder,
        private readonly PreviousInspectionContentCopier $contentCopier,
    ) {}

    /** @param ?array<int, array{disk:string,path:string}> $copiedFiles */
    public function handle(User $actor, Equipment $equipment, array $data, ?array &$copiedFiles = null): Inspection
    {
        if (! $actor->isActive() || $actor->isSuperAdmin() || ! $actor->belongsToOrganization($this->tenant->id())) {
            throw ValidationException::withMessages([
                'actor' => 'O usuário não pode criar inspeções na organização atual.',
            ]);
        }

        $newFiles = [];

        try {
            $inspection = DB::transaction(function () use ($actor, $equipment, $data, &$newFiles): Inspection {
                // The equipment row is the serialization point. Locking the inspections
                // found by the query is not sufficient when no inspection exists yet.
                $equipment = Equipment::query()
                    ->where('organization_id', $this->tenant->id())
                    ->whereKey($equipment->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $equipment->canReceiveInspection()) {
                    throw ValidationException::withMessages([
                        'equipment_id' => 'O equipamento não pode receber nova inspeção.',
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
                        'equipment_id' => 'O equipamento já possui uma inspeção aberta.',
                    ]);
                }

                $previousInspection = $this->resolvePreviousInspection($equipment);
                $type = $previousInspection === null
                    ? InspectionType::Initial
                    : InspectionType::Reinspection;

                $inspection = Inspection::query()->create([
                    'organization_id' => $this->tenant->id(),
                    'equipment_id' => $equipment->getKey(),
                    'previous_inspection_id' => $previousInspection?->getKey(),
                    'inspection_type' => $type,
                    'status' => InspectionStatus::Planned,
                    'report_revision' => $this->nextReportRevision($equipment),
                    'emission_type' => EquipmentRevisionEmissionType::ForKnowledge,
                    'first_page_text_template' => implode("\n", [
                        'UBU - '.($equipment->area_name ?? ''),
                        $equipment->subarea_name ?? '',
                        $equipment->description ?? '',
                        'INSPEÇÃO DE INTEGRIDADE ESTRUTURAL',
                        'RELATÓRIO DE INSPEÇÃO',
                    ]),
                    'service_order' => TextNormalizer::nullableText($data['service_order'] ?? null),
                    'external_report_number' => $equipment->numero_cliente,
                    'report_designer' => 'PROJETISTA II',
                    'designer_i_report_number' => $equipment->numero_interno,
                    'procedure_number' => TextNormalizer::nullableText($data['procedure_number'] ?? null),
                    'atmospheric_classification' => TextNormalizer::nullableText($data['atmospheric_classification'] ?? null),
                    'planned_start_on' => $data['planned_start_on'],
                    'planned_end_on' => $data['planned_end_on'],
                    'context_snapshot' => [
                        ...$this->snapshotBuilder->build($equipment),
                        'previous_content_version' => 1,
                    ],
                    'snapshot_version' => InspectionSnapshotBuilder::VERSION,
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

                app(ReinspectionScopePlanner::class)->save($inspection, $actor, $data);
                $this->contentCopier->copy($inspection, $previousInspection, $actor, $newFiles);

                return $inspection->refresh();
            });

            if ($copiedFiles !== null) {
                array_push($copiedFiles, ...$newFiles);
            }

            return $inspection;
        } catch (Throwable $exception) {
            $this->contentCopier->deleteFiles($newFiles);

            throw $exception;
        }
    }

    private function resolvePreviousInspection(Equipment $equipment): ?Inspection
    {
        return Inspection::query()
            ->where('organization_id', $this->tenant->id())
            ->where('equipment_id', $equipment->getKey())
            ->where('status', InspectionStatus::Released->value)
            ->orderByDesc('released_at')
            ->orderByDesc('id')
            ->first();
    }

    private function nextReportRevision(Equipment $equipment): int
    {
        return ((int) (Inspection::query()
            ->where('organization_id', $this->tenant->id())
            ->where('equipment_id', $equipment->getKey())
            ->max('report_revision') ?? -1)) + 1;
    }
}
