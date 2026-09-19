<?php

declare(strict_types=1);

namespace App\Actions\InspectionLocations;

use App\Enums\InspectionLocationMapProcessingStatus;
use App\Exceptions\StaleInspectionLocationMapException;
use App\Models\InspectionLocationMap;
use App\Models\InspectionLocationMarker;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReorderInspectionLocationMarkers
{
    /** @param array<int, string> $markerPublicIds */
    public function handle(User $actor, InspectionLocationMap $map, array $markerPublicIds, int $mapVersion): void
    {
        if ($actor->organization_id !== $map->organization_id
            || ! $actor->can('manageFieldContent', $map->inspection)
            || $map->processing_status !== InspectionLocationMapProcessingStatus::Ready) {
            throw ValidationException::withMessages(['map' => 'O mapa não está disponível para marcação.']);
        }

        $markers = $map->markers()->get()->keyBy('public_id');
        if ($markers->count() !== count($markerPublicIds)
            || count($markerPublicIds) !== count(array_unique($markerPublicIds))
            || array_diff($markerPublicIds, $markers->keys()->all()) !== []) {
            throw ValidationException::withMessages(['markers' => 'A ordem contém marcações inválidas.']);
        }

        DB::transaction(function () use ($actor, $map, $markerPublicIds, $markers, $mapVersion): void {
            $advanced = InspectionLocationMap::query()->whereKey($map->id)->where('lock_version', $mapVersion)->update([
                'lock_version' => $mapVersion + 1, 'updated_by' => $actor->id, 'updated_at' => now(),
            ]);
            if ($advanced !== 1) {
                throw new StaleInspectionLocationMapException('O mapa foi alterado por outro usuário. Recarregue o editor.');
            }
            foreach ($markerPublicIds as $index => $publicId) {
                InspectionLocationMarker::query()->whereKey($markers->get($publicId)->id)->update([
                    'position' => $index + 1,
                    'updated_by' => $actor->id,
                    'updated_at' => now(),
                ]);
            }
        });
    }
}
