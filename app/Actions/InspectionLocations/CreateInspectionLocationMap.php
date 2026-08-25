<?php

declare(strict_types=1);

namespace App\Actions\InspectionLocations;

use App\Enums\InspectionLocationMapSourceKind;
use App\Models\DefectCategory;
use App\Models\Inspection;
use App\Models\InspectionLocationMap;
use App\Models\User;
use App\Services\InspectionLocations\InspectionLocationCapacity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateInspectionLocationMap
{
    public function __construct(private readonly InspectionLocationCapacity $capacity) {}

    public function handle(User $actor, Inspection $inspection, array $data): InspectionLocationMap
    {
        if ($actor->organization_id !== $inspection->organization_id) {
            throw ValidationException::withMessages(['inspection' => 'A inspeção não pertence à organização atual.']);
        }

        $category = DefectCategory::query()
            ->forOrganization($inspection->organization_id)
            ->whereKey($data['defect_category_id'])
            ->where('status', 'active')
            ->firstOrFail();

        return DB::transaction(function () use ($actor, $inspection, $category, $data): InspectionLocationMap {
            Inspection::query()->whereKey($inspection->id)->lockForUpdate()->firstOrFail();
            $this->capacity->assertCanCreateMap($inspection, $category->id);
            $mapData = [
                ...$data,
                'organization_id' => $inspection->organization_id,
                'equipment_id' => $inspection->equipment_id,
                'inspection_id' => $inspection->id,
                'position' => $data['position'] ?? ((int) $inspection->locationMaps()->max('position') + 1),
                'source_kind' => InspectionLocationMapSourceKind::Upload,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ];

            return InspectionLocationMap::query()->create($mapData);
        });
    }
}
