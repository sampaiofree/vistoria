<?php

namespace Database\Factories;

use App\Enums\InspectionResponsibility;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspectionResponsible>
 */
class InspectionResponsibleFactory extends Factory
{
    protected $model = InspectionResponsible::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'inspection_id' => null,
            'user_id' => null,
            'responsibility' => InspectionResponsibility::Inspector,
            'is_primary' => false,
            'assigned_by' => null,
            'assigned_at' => now(),
            'completed_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (InspectionResponsible $responsible): void {
            if (blank($responsible->inspection_id)) {
                $inspection = Inspection::factory()->create([
                    'organization_id' => $this->organizationId($responsible->organization_id),
                ]);

                $responsible->inspection_id = $inspection->getKey();
                $responsible->organization_id = $inspection->organization_id;
            }

            $inspection = Inspection::query()
                ->with('organization')
                ->findOrFail($responsible->inspection_id);

            $responsible->organization_id = $inspection->organization_id;

            if (blank($responsible->user_id)) {
                $responsible->user_id = User::factory()->create([
                    'organization_id' => $inspection->organization_id,
                ])->getKey();
            }
        });
    }

    public function forInspection(Inspection $inspection, ?User $user = null): static
    {
        return $this->state(fn (): array => [
            'organization_id' => $inspection->organization_id,
            'inspection_id' => $inspection->getKey(),
            'user_id' => $user?->getKey() ?? User::factory()->create([
                'organization_id' => $inspection->organization_id,
            ])->getKey(),
        ]);
    }

    public function primary(): static
    {
        return $this->state(fn (): array => [
            'is_primary' => true,
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
