<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectAssessmentStatus;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\Organization;
use App\Services\Defects\DefectSnapshotBuilder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DefectAssessment>
 */
final class DefectAssessmentFactory extends Factory
{
    protected $model = DefectAssessment::class;

    public function definition(): array
    {
        return [
            'public_id' => null,
            'organization_id' => Organization::factory(),
            'equipment_id' => null,
            'defect_id' => null,
            'inspection_id' => null,
            'previous_assessment_id' => null,
            'condition' => DefectAssessmentCondition::New,
            'status' => DefectAssessmentStatus::Draft,
            'location_description' => null,
            'comment' => null,
            'recommendation' => null,
            'reason' => null,
            'internal_notes' => null,
            'defect_snapshot' => null,
            'snapshot_version' => DefectSnapshotBuilder::VERSION,
            'assessed_at' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (DefectAssessment $assessment): void {
            if ($assessment->defect_id === null) {
                $defect = Defect::factory()->create([
                    'organization_id' => $this->organizationId($assessment->organization_id),
                ]);

                $assessment->defect_id = $defect->getKey();
                $assessment->equipment_id = $defect->equipment_id;
            }

            if ($assessment->inspection_id === null) {
                $inspection = Inspection::factory()
                    ->forEquipment(Equipment::query()->findOrFail($assessment->equipment_id))
                    ->create();

                $assessment->inspection_id = $inspection->getKey();
            }

            if ($assessment->defect_snapshot === []) {
                $assessment->defect_snapshot = [];
            }
        });
    }

    public function forDefect(Defect $defect, Inspection $inspection): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $defect->organization_id,
            'equipment_id' => $defect->equipment_id,
            'defect_id' => $defect->getKey(),
            'inspection_id' => $inspection->getKey(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => DefectAssessmentStatus::Draft,
            'assessed_at' => null,
            'defect_snapshot' => null,
        ]);
    }

    public function complete(): static
    {
        return $this->state(fn (): array => [
            'status' => DefectAssessmentStatus::Complete,
            'assessed_at' => now(),
        ]);
    }

    public function repaired(): static
    {
        return $this->state(fn (): array => [
            'condition' => DefectAssessmentCondition::Repaired,
            'status' => DefectAssessmentStatus::Complete,
            'assessed_at' => now(),
        ]);
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
