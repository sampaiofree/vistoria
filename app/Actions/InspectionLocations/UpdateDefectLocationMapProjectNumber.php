<?php

declare(strict_types=1);

namespace App\Actions\InspectionLocations;

use App\Models\DefectAssessment;
use App\Models\DefectLocationMap;
use App\Models\DefectLocationMapVersion;
use App\Models\User;
use App\Services\InspectionLocations\InspectionLocationAssetGuard;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class UpdateDefectLocationMapProjectNumber
{
    public function __construct(private readonly InspectionLocationAssetGuard $assetGuard) {}

    public function handle(User $actor, DefectAssessment $assessment, string $projectNumber): DefectLocationMapVersion
    {
        $projectNumber = TextNormalizer::nullableText($projectNumber);
        if ($projectNumber === null) {
            throw ValidationException::withMessages([
                'project_number' => 'Informe o número do projeto.',
            ]);
        }

        $copiedAssets = [];

        try {
            return DB::transaction(function () use ($actor, $assessment, $projectNumber, &$copiedAssets): DefectLocationMapVersion {
            $assessment = DefectAssessment::query()
                ->with(['defect', 'locationMapVersion.map'])
                ->lockForUpdate()
                ->findOrFail($assessment->getKey());

            if ($actor->organization_id !== $assessment->organization_id || ! $actor->can('update', $assessment)) {
                throw ValidationException::withMessages([
                    'project_number' => 'A avaliação não está disponível para alterar o número do projeto.',
                ]);
            }

            if (! $assessment->defect->category->requiresLocationMap() || $assessment->locationMapVersion === null) {
                throw ValidationException::withMessages([
                    'project_number' => 'Envie um mapa de localização antes de informar o número do projeto.',
                ]);
            }

            $version = DefectLocationMapVersion::query()
                ->with('map')
                ->lockForUpdate()
                ->findOrFail($assessment->defect_location_map_version_id);
            $map = DefectLocationMap::query()->lockForUpdate()->findOrFail($version->defect_location_map_id);
            $hasOtherAssessments = $version->assessments()->whereKeyNot($assessment->getKey())->exists();

            if ($version->created_for_assessment_id === $assessment->getKey() && ! $hasOtherAssessments) {
                $version->update(['project_number' => $projectNumber]);
                $map->update(['updated_by' => $actor->getKey()]);

                return $version->refresh();
            }

            $newVersionPublicId = (string) Str::ulid();
            $assetPaths = $this->copyAssets($version, $assessment, $map, $newVersionPublicId, $copiedAssets);

            $newVersion = DefectLocationMapVersion::query()->create([
                'public_id' => $newVersionPublicId,
                'organization_id' => $assessment->organization_id,
                'equipment_id' => $assessment->equipment_id,
                'defect_location_map_id' => $map->getKey(),
                'created_for_assessment_id' => $assessment->getKey(),
                'version' => ((int) $map->versions()->max('version')) + 1,
                'project_number' => $projectNumber,
                'source_disk' => $version->source_disk,
                'source_path' => $assetPaths['source_path'],
                'source_mime_type' => $version->source_mime_type,
                'source_size' => $version->source_size,
                'source_checksum' => $version->source_checksum,
                'source_uploaded_by' => $version->source_uploaded_by,
                'background_disk' => $version->background_disk,
                'background_path' => $assetPaths['background_path'],
                'background_mime_type' => $version->background_mime_type,
                'background_size' => $version->background_size,
                'background_width' => $version->background_width,
                'background_height' => $version->background_height,
                'background_checksum' => $version->background_checksum,
                'processing_status' => $version->processing_status,
                'processing_error' => $version->processing_error,
                'processed_at' => $version->processed_at,
                'lock_version' => 1,
            ]);

            $assessment->forceFill([
                'defect_location_map_version_id' => $newVersion->getKey(),
                'updated_by' => $actor->getKey(),
            ])->save();
            $map->update(['updated_by' => $actor->getKey()]);

                return $newVersion->refresh();
            });
        } catch (Throwable $exception) {
            foreach ($copiedAssets as $asset) {
                Storage::disk($asset['disk'])->delete($asset['path']);
            }

            throw $exception;
        }
    }

    /**
     * @param array<int, array{disk:string,path:string}> $copiedAssets
     * @return array{source_path:?string,background_path:?string}
     */
    private function copyAssets(
        DefectLocationMapVersion $version,
        DefectAssessment $assessment,
        DefectLocationMap $map,
        string $newVersionPublicId,
        array &$copiedAssets,
    ): array {
        $directory = sprintf(
            'organizations/%d/defects/%s/maps/%s/versions/%s',
            $assessment->organization_id,
            $assessment->defect->public_id,
            $map->public_id,
            $newVersionPublicId,
        );

        if ($version->source_path !== null) {
            $this->assetGuard->source($version);
        }
        if ($version->background_path !== null) {
            $this->assetGuard->background($version);
        }

        $sourcePath = $this->copyAsset(
            $version->source_disk,
            $version->source_path,
            $directory.'/source',
            $copiedAssets,
        );
        $backgroundPath = $this->copyAsset(
            $version->background_disk,
            $version->background_path,
            $directory.'/derivatives',
            $copiedAssets,
        );

        if ($backgroundPath !== null) {
            $sourceThumbnail = dirname($version->background_path).'/thumbnail.webp';
            $targetThumbnail = dirname($backgroundPath).'/thumbnail.webp';
            $disk = Storage::disk((string) $version->background_disk);
            if ($disk->exists($sourceThumbnail)) {
                if (! $disk->copy($sourceThumbnail, $targetThumbnail)) {
                    throw new \RuntimeException('Não foi possível copiar a miniatura do mapa.');
                }
                $copiedAssets[] = ['disk' => (string) $version->background_disk, 'path' => $targetThumbnail];
            }
        }

        return ['source_path' => $sourcePath, 'background_path' => $backgroundPath];
    }

    /** @param array<int, array{disk:string,path:string}> $copiedAssets */
    private function copyAsset(?string $diskName, ?string $path, string $directory, array &$copiedAssets): ?string
    {
        if ($diskName === null || $path === null) {
            return null;
        }

        $disk = Storage::disk($diskName);
        $targetPath = $directory.'/'.basename($path);
        if (! $disk->copy($path, $targetPath)) {
            throw new \RuntimeException('Não foi possível copiar os arquivos do mapa.');
        }

        $copiedAssets[] = ['disk' => $diskName, 'path' => $targetPath];

        return $targetPath;
    }
}
