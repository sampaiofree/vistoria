<?php

declare(strict_types=1);

namespace App\Actions\InspectionLocations;

use App\Enums\InspectionLocationMapProcessingStatus;
use App\Exceptions\StaleInspectionLocationMapException;
use App\Exceptions\StaleInspectionLocationMarkerException;
use App\Models\AssessmentPhoto;
use App\Models\InspectionLocationMap;
use App\Models\InspectionLocationMarker;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SyncInspectionLocationMarkerPhotos
{
    public function handle(User $actor, InspectionLocationMarker $marker, array $photoPublicIds, int $markerVersion, int $mapVersion): InspectionLocationMarker
    {
        $map = $marker->map;
        if ($actor->organization_id !== $marker->organization_id
            || $marker->defect_assessment_id === null
            || $map->processing_status !== InspectionLocationMapProcessingStatus::Ready) {
            throw ValidationException::withMessages(['photos' => 'A marcação não está disponível para vincular fotografias.']);
        }

        $photoPublicIds = array_values(array_unique($photoPublicIds));
        $photos = AssessmentPhoto::query()
            ->forOrganization($marker->organization_id)
            ->where('inspection_id', $marker->inspection_id)
            ->where('defect_assessment_id', $marker->defect_assessment_id)
            ->whereIn('public_id', $photoPublicIds)
            ->get()
            ->keyBy('public_id');

        if ($photos->count() !== count($photoPublicIds)) {
            throw ValidationException::withMessages(['photo_ids' => 'Uma ou mais fotografias não pertencem à avaliação da marcação.']);
        }

        DB::transaction(function () use ($actor, $marker, $map, $photoPublicIds, $photos, $markerVersion, $mapVersion): void {
            $markerUpdated = InspectionLocationMarker::query()->whereKey($marker->id)->where('lock_version', $markerVersion)->update([
                'lock_version' => $markerVersion + 1, 'updated_by' => $actor->id, 'updated_at' => now(),
            ]);
            if ($markerUpdated !== 1) {
                throw new StaleInspectionLocationMarkerException('A marcação foi alterada por outro usuário. Recarregue o editor.');
            }

            $mapUpdated = InspectionLocationMap::query()->whereKey($map->id)->where('lock_version', $mapVersion)->update([
                'lock_version' => $mapVersion + 1, 'updated_by' => $actor->id, 'updated_at' => now(),
            ]);
            if ($mapUpdated !== 1) {
                throw new StaleInspectionLocationMapException('O mapa foi alterado por outro usuário. Recarregue o editor.');
            }

            DB::table('inspection_location_marker_photos')->where('inspection_location_marker_id', $marker->id)->delete();
            foreach ($photoPublicIds as $index => $publicId) {
                DB::table('inspection_location_marker_photos')->insert([
                    'organization_id' => $marker->organization_id,
                    'inspection_id' => $marker->inspection_id,
                    'inspection_location_marker_id' => $marker->id,
                    'assessment_photo_id' => $photos->get($publicId)->id,
                    'position' => $index + 1,
                    'created_at' => now(),
                ]);
            }
        });

        return $marker->refresh()->load('photos');
    }
}
