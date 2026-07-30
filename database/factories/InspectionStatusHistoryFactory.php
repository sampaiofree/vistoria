<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InspectionStatus;
use App\Models\Inspection;
use App\Models\InspectionStatusHistory;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspectionStatusHistory>
 */
class InspectionStatusHistoryFactory extends Factory
{
    protected $model = InspectionStatusHistory::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'inspection_id' => null,
            'from_status' => null,
            'to_status' => InspectionStatus::Planned,
            'changed_by' => null,
            'reason' => fake()->sentence(),
            'metadata' => [],
            'created_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (InspectionStatusHistory $history): void {
            if (blank($history->inspection_id)) {
                $inspection = Inspection::factory()->create([
                    'organization_id' => $this->organizationId($history->organization_id),
                ]);

                $history->inspection_id = $inspection->getKey();
                $history->organization_id = $inspection->organization_id;
            }

            $inspection = Inspection::query()
                ->findOrFail($history->inspection_id);

            $history->organization_id = $inspection->organization_id;

            if (blank($history->changed_by)) {
                $history->changed_by = User::factory()->create([
                    'organization_id' => $inspection->organization_id,
                ])->getKey();
            }
        });
    }

    public function forInspection(
        Inspection $inspection,
        ?User $actor = null,
        InspectionStatus $toStatus = InspectionStatus::Planned,
    ): static {
        return $this->state(fn (): array => [
            'organization_id' => $inspection->organization_id,
            'inspection_id' => $inspection->getKey(),
            'to_status' => $toStatus,
            'changed_by' => $actor?->getKey() ?? User::factory()->create([
                'organization_id' => $inspection->organization_id,
            ])->getKey(),
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
