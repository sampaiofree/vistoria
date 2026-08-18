<?php

declare(strict_types=1);

namespace App\Actions\InspectionLocations;

use App\Enums\InspectionLocationMapProcessingStatus;
use App\Exceptions\StaleInspectionLocationMapException;
use App\Exceptions\StaleInspectionLocationMarkerException;
use App\Models\InspectionLocationMap;
use App\Models\InspectionLocationMarker;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeleteInspectionLocationMarker
{
    public function handle(User $actor, InspectionLocationMarker $marker, int $markerVersion, int $mapVersion): void
    {
        if ($actor->organization_id !== $marker->organization_id || $marker->map->processing_status !== InspectionLocationMapProcessingStatus::Ready) {
            throw ValidationException::withMessages(['map' => 'O mapa não está disponível para marcação.']);
        }

        DB::transaction(function () use ($actor, $marker, $markerVersion, $mapVersion): void {
            $updated = InspectionLocationMarker::query()->whereKey($marker->id)->where('lock_version', $markerVersion)->update([
                'active_slot' => null,
                'updated_by' => $actor->id,
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
            if ($updated !== 1) {
                throw new StaleInspectionLocationMarkerException('A marcação foi alterada por outro usuário. Recarregue o editor.');
            }
            $advanced = InspectionLocationMap::query()->whereKey($marker->inspection_location_map_id)->where('lock_version', $mapVersion)->update([
                'lock_version' => $mapVersion + 1,
                'updated_by' => $actor->id,
                'updated_at' => now(),
            ]);
            if ($advanced !== 1) {
                throw new StaleInspectionLocationMapException('O mapa foi alterado por outro usuário. Recarregue o editor.');
            }
        });
    }
}
