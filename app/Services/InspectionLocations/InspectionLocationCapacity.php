<?php

declare(strict_types=1);

namespace App\Services\InspectionLocations;

use App\Enums\DefectCategory;
use App\Models\Inspection;
use App\Models\InspectionLocationMap;
use Illuminate\Validation\ValidationException;

final class InspectionLocationCapacity
{
    public function assertCanCreateMap(Inspection $inspection, DefectCategory $category): void
    {
        $query = $inspection->locationMaps();

        if ((clone $query)->count() >= (int) config('inspection_locations.limits.maps_per_inspection')) {
            throw ValidationException::withMessages([
                'maps' => 'A inspeção atingiu o limite de mapas de localização.',
            ]);
        }

        if ((clone $query)->where('category', $category->value)->count() >= (int) config('inspection_locations.limits.maps_per_category')) {
            throw ValidationException::withMessages([
                'category' => 'A categoria atingiu o limite de mapas nesta inspeção.',
            ]);
        }
    }

    public function assertCanCreateMarker(InspectionLocationMap $map): void
    {
        if ($map->markers()->count() >= (int) config('inspection_locations.limits.markers_per_map')) {
            throw ValidationException::withMessages([
                'markers' => 'O mapa atingiu o limite de marcações.',
            ]);
        }
    }
}
