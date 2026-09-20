<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InspectionLocationMapProcessingStatus;
use App\Models\DefectAssessment;
use App\Models\DefectLocationMap;
use App\Models\DefectLocationMapVersion;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DefectLocationMapVersion> */
final class DefectLocationMapVersionFactory extends Factory
{
    protected $model = DefectLocationMapVersion::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'equipment_id' => null,
            'defect_location_map_id' => null,
            'created_for_assessment_id' => null,
            'version' => 1,
            'processing_status' => InspectionLocationMapProcessingStatus::Pending,
            'lock_version' => 1,
        ];
    }

    public function forMapAndAssessment(DefectLocationMap $map, DefectAssessment $assessment): static
    {
        return $this->state([
            'organization_id' => $assessment->organization_id,
            'equipment_id' => $assessment->equipment_id,
            'defect_location_map_id' => $map->id,
            'created_for_assessment_id' => $assessment->id,
        ]);
    }

    public function ready(string $path = 'organizations/test/defects/test/maps/test/versions/test/derivatives/test/background.webp'): static
    {
        return $this->state([
            'background_disk' => 'inspection_maps',
            'background_path' => $path,
            'background_mime_type' => 'image/webp',
            'background_size' => 100,
            'background_width' => 1600,
            'background_height' => 900,
            'background_checksum' => str_repeat('a', 64),
            'processing_status' => InspectionLocationMapProcessingStatus::Ready,
            'processed_at' => now(),
        ]);
    }
}
