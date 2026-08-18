<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DefectAssessment;
use App\Models\InspectionLocationMap;
use App\Models\InspectionLocationMarker;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InspectionLocationMarker> */
final class InspectionLocationMarkerFactory extends Factory
{
    protected $model = InspectionLocationMarker::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'equipment_id' => null,
            'inspection_id' => null,
            'inspection_location_map_id' => null,
            'defect_assessment_id' => null,
            'label' => null,
            'geometry' => ['version' => 1, 'shapes' => [['type' => 'point', 'x' => 0.5, 'y' => 0.5]]],
            'style' => null,
            'position' => 1,
            'lock_version' => 1,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (InspectionLocationMarker $marker): void {
            if ($marker->inspection_location_map_id === null) {
                $map = InspectionLocationMap::factory()->create(['organization_id' => $marker->organization_id]);
                $marker->equipment_id = $map->equipment_id;
                $marker->inspection_id = $map->inspection_id;
                $marker->inspection_location_map_id = $map->id;
            }
        });
    }

    public function forMapAndAssessment(InspectionLocationMap $map, DefectAssessment $assessment): static
    {
        return $this->state([
            'organization_id' => $map->organization_id,
            'equipment_id' => $map->equipment_id,
            'inspection_id' => $map->inspection_id,
            'inspection_location_map_id' => $map->id,
            'defect_assessment_id' => $assessment->id,
        ]);
    }
}
