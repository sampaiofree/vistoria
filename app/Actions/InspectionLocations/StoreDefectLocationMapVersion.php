<?php

declare(strict_types=1);

namespace App\Actions\InspectionLocations;

use App\Enums\DefectAssessmentStatus;
use App\Enums\InspectionLocationMapProcessingStatus;
use App\Jobs\ProcessInspectionLocationMap;
use App\Models\DefectAssessment;
use App\Models\DefectLocationMap;
use App\Models\DefectLocationMapVersion;
use App\Models\User;
use App\Services\Defects\DefectStatusSynchronizer;
use App\Services\InspectionLocations\DefectLocationMapVersionPruner;
use App\Services\InspectionLocations\InspectionLocationSourceValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class StoreDefectLocationMapVersion
{
    public function __construct(
        private readonly InspectionLocationSourceValidator $sourceValidator,
        private readonly DefectStatusSynchronizer $statusSynchronizer,
        private readonly DefectLocationMapVersionPruner $pruner,
    ) {}

    public function handle(User $actor, DefectAssessment $assessment, UploadedFile $file): DefectLocationMapVersion
    {
        $assessment->loadMissing(['defect.locationMap', 'inspection']);
        if ($actor->organization_id !== $assessment->organization_id || ! $actor->can('update', $assessment)) {
            throw ValidationException::withMessages(['file' => 'A avaliacao nao esta disponivel para receber um mapa.']);
        }

        $this->sourceValidator->validate($file, []);
        $mapPublicId = $assessment->defect->locationMap?->public_id ?? (string) Str::ulid();
        $versionPublicId = (string) Str::ulid();
        $directory = sprintf(
            'organizations/%d/defects/%s/maps/%s/versions/%s',
            $assessment->organization_id,
            $assessment->defect->public_id,
            $mapPublicId,
            $versionPublicId,
        );
        $path = $file->storeAs($directory, (string) Str::ulid().'.'.strtolower($file->extension()), 'inspection_maps');
        if (! is_string($path)) {
            throw ValidationException::withMessages(['file' => 'Nao foi possivel armazenar a imagem do mapa.']);
        }

        $wasComplete = $assessment->isComplete();
        $previousVersion = $assessment->locationMapVersion;

        try {
            $version = DB::transaction(function () use ($actor, $assessment, $file, $path, $mapPublicId, $versionPublicId): DefectLocationMapVersion {
                $assessment = DefectAssessment::query()
                    ->forOrganization($assessment->organization_id)
                    ->with(['defect', 'location'])
                    ->lockForUpdate()
                    ->findOrFail($assessment->id);
                $map = DefectLocationMap::query()
                    ->where('organization_id', $assessment->organization_id)
                    ->where('defect_id', $assessment->defect_id)
                    ->lockForUpdate()
                    ->first();

                if ($map === null) {
                    $map = DefectLocationMap::query()->create([
                        'public_id' => $mapPublicId,
                        'organization_id' => $assessment->organization_id,
                        'equipment_id' => $assessment->equipment_id,
                        'defect_id' => $assessment->defect_id,
                        'created_by' => $actor->id,
                        'updated_by' => $actor->id,
                    ]);
                }

                $version = DefectLocationMapVersion::query()->create([
                    'public_id' => $versionPublicId,
                    'organization_id' => $assessment->organization_id,
                    'equipment_id' => $assessment->equipment_id,
                    'defect_location_map_id' => $map->id,
                    'created_for_assessment_id' => $assessment->id,
                    'version' => ((int) $map->versions()->max('version')) + 1,
                    'source_disk' => 'inspection_maps',
                    'source_path' => $path,
                    'source_mime_type' => $file->getMimeType(),
                    'source_size' => $file->getSize(),
                    'source_checksum' => (string) hash_file('sha256', $file->getRealPath()),
                    'source_uploaded_by' => $actor->id,
                    'processing_status' => InspectionLocationMapProcessingStatus::Pending,
                ]);

                $complete = $assessment->isComplete();
                $assessment->forceFill([
                    'defect_location_map_version_id' => $version->id,
                    'status' => $complete ? DefectAssessmentStatus::Draft : $assessment->status,
                    'assessed_at' => $complete ? null : $assessment->assessed_at,
                    'defect_snapshot' => $complete ? null : $assessment->defect_snapshot,
                    'quantity_snapshot' => $complete ? null : $assessment->quantity_snapshot,
                    'updated_by' => $actor->id,
                ])->save();

                if ($assessment->location !== null) {
                    $assessment->location->update([
                        'confirmed_at' => null,
                        'confirmed_by' => null,
                        'lock_version' => $assessment->location->lock_version + 1,
                        'updated_by' => $actor->id,
                    ]);
                }

                return $version;
            });
        } catch (Throwable $exception) {
            Storage::disk('inspection_maps')->delete($path);
            throw $exception;
        }

        ProcessInspectionLocationMap::dispatch($version->id, $version->source_checksum)->afterCommit();
        if ($wasComplete) {
            $this->statusSynchronizer->handle($assessment->defect, $actor);
        }
        if ($previousVersion !== null && $previousVersion->isNot($version)) {
            $this->pruner->pruneIfUnreferenced($previousVersion);
        }

        return $version->refresh();
    }
}
