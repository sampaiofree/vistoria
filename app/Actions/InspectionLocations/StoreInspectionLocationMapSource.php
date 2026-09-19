<?php

declare(strict_types=1);

namespace App\Actions\InspectionLocations;

use App\Enums\InspectionLocationMapProcessingStatus;
use App\Enums\InspectionLocationMapSourceKind;
use App\Exceptions\StaleInspectionLocationMapException;
use App\Jobs\ProcessInspectionLocationMap;
use App\Models\InspectionLocationMap;
use App\Models\User;
use App\Services\InspectionLocations\InspectionLocationAssetGuard;
use App\Services\InspectionLocations\InspectionLocationSourceValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class StoreInspectionLocationMapSource
{
    public function __construct(
        private readonly InspectionLocationSourceValidator $sourceValidator,
        private readonly InspectionLocationAssetGuard $assetGuard,
    ) {}

    public function handle(User $actor, InspectionLocationMap $map, UploadedFile $file, array $data): InspectionLocationMap
    {
        if ($actor->organization_id !== $map->organization_id || ! $actor->can('manageFieldContent', $map->inspection)) {
            throw ValidationException::withMessages(['file' => 'O mapa não pertence à organização atual.']);
        }

        $this->sourceValidator->validate($file, $data);
        $previousUpload = null;
        if ($map->source_kind === InspectionLocationMapSourceKind::Upload && $map->source_path !== null) {
            try {
                $previousUpload = $this->assetGuard->source($map);
            } catch (RuntimeException) {
                // Um caminho inconsistente nunca será usado para excluir arquivos.
            }
        }
        $previousBackground = null;
        if ($map->background_path !== null) {
            try {
                $previousBackground = $this->assetGuard->background($map);
            } catch (RuntimeException) {
                // Um caminho inconsistente nunca será usado para excluir arquivos.
            }
        }

        $disk = 'inspection_maps';
        $directory = sprintf('organizations/%d/inspections/%s/maps/%s', $map->organization_id, $map->inspection->public_id, $map->public_id);
        $path = $file->storeAs($directory, (string) Str::ulid().'.'.strtolower($file->extension()), $disk);
        if (! is_string($path)) {
            throw ValidationException::withMessages(['file' => 'Não foi possível armazenar a origem do mapa.']);
        }

        try {
            $map = DB::transaction(function () use ($actor, $map, $file, $data, $disk, $path): InspectionLocationMap {
                $locked = InspectionLocationMap::query()->lockForUpdate()->findOrFail($map->id);
                $expectedVersion = (int) $data['lock_version'];
                if ($locked->lock_version !== $expectedVersion) {
                    throw new StaleInspectionLocationMapException('O mapa foi alterado por outro usuário. Recarregue a página.');
                }

                $locked->update([
                    'source_kind' => InspectionLocationMapSourceKind::Upload,
                    'equipment_document_id' => null,
                    'reference_snapshot' => null,
                    'source_disk' => $disk,
                    'source_path' => $path,
                    'source_mime_type' => $file->getMimeType(),
                    'source_size' => $file->getSize(),
                    'source_checksum' => (string) hash_file('sha256', $file->getRealPath()),
                    'source_uploaded_by' => $actor->id,
                    'source_page' => null,
                    'source_crop' => null,
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
                    'lock_version' => $expectedVersion + 1,
                    'updated_by' => $actor->id,
                ]);

                return $locked->refresh();
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }

        if ($previousUpload !== null && $previousUpload['path'] !== $path) {
            try {
                $previousUpload['disk']->delete($previousUpload['path']);
            } catch (Throwable) {
                Log::warning('Não foi possível remover a origem substituída de um mapa de localização.', [
                    'map_public_id' => $map->public_id,
                ]);
            }
        }
        if ($previousBackground !== null) {
            try {
                $previousBackground['disk']->delete([
                    $previousBackground['path'],
                    dirname($previousBackground['path']).'/thumbnail.webp',
                ]);
            } catch (Throwable) {
                Log::warning('Não foi possível remover derivados substituídos de um mapa de localização.', [
                    'map_public_id' => $map->public_id,
                ]);
            }
        }

        ProcessInspectionLocationMap::dispatch($map->id, $map->source_checksum)->afterCommit();

        return $map;
    }
}
