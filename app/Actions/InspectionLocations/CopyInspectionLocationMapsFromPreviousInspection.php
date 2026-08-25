<?php

declare(strict_types=1);

namespace App\Actions\InspectionLocations;

use App\Enums\InspectionLocationMapProcessingStatus;
use App\Enums\InspectionLocationMapSourceKind;
use App\Enums\InspectionStatus;
use App\Jobs\ProcessInspectionLocationMap;
use App\Models\DefectAssessment;
use App\Models\Inspection;
use App\Models\InspectionLocationMap;
use App\Models\InspectionLocationMarker;
use App\Models\User;
use App\Services\InspectionLocations\InspectionLocationAssetGuard;
use App\Services\InspectionLocations\InspectionLocationCapacity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class CopyInspectionLocationMapsFromPreviousInspection
{
    public function __construct(
        private readonly InspectionLocationAssetGuard $assetGuard,
        private readonly InspectionLocationCapacity $capacity,
    ) {}

    /** @return array{maps:int,markers:int,pending_markers:int} */
    public function handle(User $actor, Inspection $inspection): array
    {
        $inspection->loadMissing('previousInspection');
        if ($inspection->previousInspection === null
            || $actor->organization_id !== $inspection->organization_id
            || $inspection->previousInspection->organization_id !== $inspection->organization_id
            || $inspection->previousInspection->equipment_id !== $inspection->equipment_id
            || ! in_array($inspection->status, [InspectionStatus::InProgress, InspectionStatus::InCorrection], true)) {
            throw ValidationException::withMessages(['inspection' => 'A inspeção não está disponível para copiar mapas anteriores.']);
        }
        $previousMaps = $inspection->previousInspection->locationMaps()->with(['markers.assessment', 'equipmentDocument'])->get();
        if ($previousMaps->isEmpty()) {
            throw ValidationException::withMessages(['maps' => 'A inspeção anterior não possui mapas para copiar.']);
        }

        $summary = ['maps' => 0, 'markers' => 0, 'pending_markers' => 0];
        $processMaps = [];
        $copiedPaths = [];

        try {
            DB::transaction(function () use ($actor, $inspection, $previousMaps, &$summary, &$processMaps, &$copiedPaths): void {
                Inspection::query()->whereKey($inspection->id)->lockForUpdate()->firstOrFail();
                if ($inspection->locationMaps()->exists()) {
                    throw ValidationException::withMessages(['maps' => 'A inspeção atual já possui mapas de localização.']);
                }

                foreach ($previousMaps as $previousMap) {
                    $this->capacity->assertCanCreateMap($inspection, $previousMap->defect_category_id);
                    if ($previousMap->markers->count() > (int) config('inspection_locations.limits.markers_per_map')) {
                        throw ValidationException::withMessages(['markers' => 'Um mapa anterior excede o limite de marcações.']);
                    }
                    $map = InspectionLocationMap::query()->create([
                        'organization_id' => $inspection->organization_id,
                        'equipment_id' => $inspection->equipment_id,
                        'inspection_id' => $inspection->id,
                        'defect_category_id' => $previousMap->defect_category_id,
                        'equipment_document_id' => null,
                        'title' => $previousMap->title,
                        'description' => $previousMap->description,
                        'source_kind' => InspectionLocationMapSourceKind::Upload,
                        'source_page' => null,
                        'source_crop' => null,
                        'reference_snapshot' => null,
                        'source_uploaded_by' => $actor->id,
                        'processing_status' => InspectionLocationMapProcessingStatus::Pending,
                        'geometry_schema_version' => $previousMap->geometry_schema_version,
                        'position' => $previousMap->position,
                        'created_by' => $actor->id,
                        'updated_by' => $actor->id,
                    ]);

                    $copiedPath = $this->copyProcessedBackground($previousMap, $map, $inspection);
                    if ($copiedPath !== null) {
                        $copiedPaths[] = $copiedPath;
                    }

                    foreach ($previousMap->markers as $previousMarker) {
                        $currentAssessment = $previousMarker->assessment === null
                            ? null
                            : DefectAssessment::query()
                                ->forOrganization($inspection->organization_id)
                                ->where('inspection_id', $inspection->id)
                                ->where('defect_id', $previousMarker->assessment->defect_id)
                                ->first();

                        InspectionLocationMarker::query()->create([
                            'organization_id' => $inspection->organization_id,
                            'equipment_id' => $inspection->equipment_id,
                            'inspection_id' => $inspection->id,
                            'inspection_location_map_id' => $map->id,
                            'defect_assessment_id' => $currentAssessment?->id,
                            'label' => $previousMarker->label,
                            'geometry' => $previousMarker->geometry,
                            'style' => $previousMarker->style,
                            'position' => $previousMarker->position,
                            'created_by' => $actor->id,
                            'updated_by' => $actor->id,
                        ]);
                        $summary['markers']++;
                        if ($currentAssessment === null) {
                            $summary['pending_markers']++;
                        }
                    }

                    if ($map->source_path !== null) {
                        $processMaps[] = ['id' => $map->id, 'checksum' => $map->source_checksum];
                    }
                    $summary['maps']++;
                }
            });
        } catch (\Throwable $exception) {
            if ($copiedPaths !== []) {
                Storage::disk('inspection_maps')->delete($copiedPaths);
            }

            throw $exception;
        }

        foreach ($processMaps as $processMap) {
            ProcessInspectionLocationMap::dispatch($processMap['id'], $processMap['checksum'])->afterCommit();
        }

        return $summary;
    }

    private function copyProcessedBackground(InspectionLocationMap $source, InspectionLocationMap $target, Inspection $inspection): ?string
    {
        try {
            $asset = $this->assetGuard->background($source);
        } catch (\RuntimeException) {
            return null;
        }

        if (! $asset['disk']->exists($asset['path'])) {
            return null;
        }

        $path = sprintf('organizations/%d/inspections/%s/maps/%s/source.webp', $inspection->organization_id, $inspection->public_id, $target->public_id);
        $stream = $asset['disk']->readStream($asset['path']);
        if (! is_resource($stream)) {
            return null;
        }

        try {
            Storage::disk('inspection_maps')->put($path, $stream);
        } finally {
            fclose($stream);
        }

        $target->update([
            'source_disk' => 'inspection_maps',
            'source_path' => $path,
            'source_mime_type' => 'image/webp',
            'source_size' => Storage::disk('inspection_maps')->size($path),
            'source_checksum' => hash('sha256', Storage::disk('inspection_maps')->get($path)),
        ]);

        return $path;
    }
}
