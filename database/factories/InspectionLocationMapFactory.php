<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InspectionLocationMapProcessingStatus;
use App\Enums\InspectionLocationMapSourceKind;
use App\Models\DefectCategory;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\InspectionLocationMap;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InspectionLocationMap> */
final class InspectionLocationMapFactory extends Factory
{
    protected $model = InspectionLocationMap::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'equipment_id' => null,
            'inspection_id' => null,
            'defect_category_id' => null,
            'equipment_document_id' => null,
            'title' => fake()->sentence(5),
            'description' => null,
            'source_kind' => InspectionLocationMapSourceKind::Upload,
            'source_page' => null,
            'source_crop' => null,
            'reference_snapshot' => null,
            'source_disk' => null,
            'source_path' => null,
            'source_mime_type' => null,
            'source_size' => null,
            'source_checksum' => null,
            'source_uploaded_by' => null,
            'background_disk' => null,
            'background_path' => null,
            'background_mime_type' => null,
            'background_size' => null,
            'background_width' => null,
            'background_height' => null,
            'background_checksum' => null,
            'processing_status' => InspectionLocationMapProcessingStatus::Pending,
            'processing_error' => null,
            'processed_at' => null,
            'geometry_schema_version' => 1,
            'position' => 1,
            'lock_version' => 1,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (InspectionLocationMap $map): void {
            if ($map->equipment_id === null) {
                $equipment = Equipment::factory()->create(['organization_id' => $map->organization_id]);
                $map->equipment_id = $equipment->id;
            }

            if ($map->inspection_id === null) {
                $inspection = Inspection::factory()->forEquipment(Equipment::findOrFail($map->equipment_id))->create();
                $map->inspection_id = $inspection->id;
            }

            if ($map->defect_category_id === null) {
                $map->defect_category_id = DefectCategory::factory()->create(['organization_id' => $map->organization_id])->id;
            }
        });
    }

    public function forInspection(Inspection $inspection, DefectCategory $category): static
    {
        return $this->state([
            'organization_id' => $inspection->organization_id,
            'equipment_id' => $inspection->equipment_id,
            'inspection_id' => $inspection->id,
            'defect_category_id' => $category->id,
        ]);
    }
}
