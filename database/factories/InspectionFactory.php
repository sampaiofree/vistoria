<?php

namespace Database\Factories;

use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Organization;
use App\Services\Inspections\InspectionSnapshotBuilder;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Inspection> */
class InspectionFactory extends Factory
{
    protected $model = Inspection::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'equipment_id' => null,
            'previous_inspection_id' => null,
            'number' => null,
            'inspection_type' => InspectionType::Initial,
            'status' => InspectionStatus::Planned,
            'service_order' => null,
            'external_report_number' => null,
            'procedure_number' => null,
            'atmospheric_classification' => null,
            'scheduled_for' => null,
            'inspected_on' => null,
            'context_snapshot' => [],
            'snapshot_version' => InspectionSnapshotBuilder::VERSION,
            'general_notes' => null,
            'started_at' => null,
            'field_completed_at' => null,
            'reviewed_at' => null,
            'approved_at' => null,
            'report_generated_at' => null,
            'released_at' => null,
            'canceled_at' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Inspection $inspection): void {
            if (blank($inspection->equipment_id)) {
                $equipment = Equipment::factory()->create([
                    'organization_id' => $this->organizationId($inspection->organization_id),
                ]);

                $inspection->equipment_id = $equipment->getKey();

                if (blank($inspection->context_snapshot)) {
                    $inspection->context_snapshot = app(InspectionSnapshotBuilder::class)->build($equipment);
                }

                return;
            }

            if (blank($inspection->context_snapshot)) {
                $equipment = Equipment::query()
                    ->with(['organization', 'client', 'unit', 'area', 'subarea'])
                    ->findOrFail($inspection->equipment_id);

                $inspection->context_snapshot = app(InspectionSnapshotBuilder::class)->build($equipment);
            }
        });
    }

    public function forEquipment(Equipment $equipment, ?Inspection $previousInspection = null): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $equipment->organization_id,
            'equipment_id' => $equipment->getKey(),
            'previous_inspection_id' => $previousInspection?->getKey(),
            'inspection_type' => $previousInspection === null
                ? InspectionType::Initial
                : InspectionType::Reinspection,
            'context_snapshot' => [],
        ]);
    }

    public function reinspection(Inspection $previousInspection): static
    {
        return $this->forEquipment($previousInspection->equipment, $previousInspection);
    }

    private function organizationId(mixed $organization): int
    {
        if ($organization instanceof Organization) {
            return (int) $organization->getKey();
        }

        if ($organization instanceof Factory) {
            return (int) $organization->create()->getKey();
        }

        return (int) $organization;
    }
}
