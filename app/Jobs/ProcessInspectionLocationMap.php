<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\InspectionLocationMapProcessingStatus;
use App\Enums\InspectionLocationMapSourceKind;
use App\Models\InspectionLocationMap;
use App\Services\InspectionLocations\InspectionLocationAssetGuard;
use App\Services\Notifications\NotifyInspectionImageFailure;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Imagick;
use RuntimeException;
use Throwable;

final class ProcessInspectionLocationMap implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(
        public readonly int $mapId,
        public readonly ?string $expectedSourceChecksum = null,
    ) {
        $this->onQueue('images');
    }

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(InspectionLocationAssetGuard $assetGuard): void
    {
        $map = InspectionLocationMap::query()->with(['inspection', 'equipmentDocument'])->find($this->mapId);
        if ($map === null || $map->trashed() || ! $this->matchesExpectedSource($map)) {
            return;
        }

        $claimed = InspectionLocationMap::query()
            ->whereKey($map->id)
            ->where('processing_status', InspectionLocationMapProcessingStatus::Pending->value)
            ->when($this->expectedSourceChecksum !== null, fn ($query) => $query->where('source_checksum', $this->expectedSourceChecksum))
            ->update([
                'processing_status' => InspectionLocationMapProcessingStatus::Processing,
                'processing_error' => null,
                'updated_at' => now(),
            ]);
        if ($claimed !== 1) {
            return;
        }

        $map->refresh();
        $image = null;
        $thumbnail = null;
        $outputDisk = null;
        $outputPaths = [];

        try {
            $source = $assetGuard->source($map);
            if (! $source['disk']->exists($source['path'])) {
                throw new RuntimeException('Origem não encontrada.');
            }

            $maxBytes = (int) config('inspection_locations.limits.source_size_kilobytes') * 1024;
            if ((int) $source['disk']->size($source['path']) > $maxBytes) {
                throw new RuntimeException('Origem acima do limite permitido.');
            }

            $image = new Imagick;
            $this->configureResources($image);
            $image->setOption('pdf:use-cropbox', 'true');
            $path = $source['disk']->path($source['path']);
            if ($map->source_mime_type === 'application/pdf') {
                $page = max(1, (int) ($map->source_page ?? 1));
                if ($page > (int) config('inspection_locations.limits.pdf_pages')) {
                    throw new RuntimeException('Página acima do limite permitido.');
                }
                $path .= '['.($page - 1).']';
            }

            $image->readImage($path);
            $image->setIteratorIndex(0);
            $image->setImageFormat('png');
            $this->assertSafeDimensions($image);
            $this->applyCrop($image, $map->source_crop);
            $image->thumbnailImage(
                (int) config('inspection_locations.processing.max_output_dimension'),
                (int) config('inspection_locations.processing.max_output_dimension'),
                true,
                true,
            );
            $image->setImageFormat('webp');
            $image->setImageCompressionQuality(88);

            $thumbnail = clone $image;
            $thumbnail->thumbnailImage(
                (int) config('inspection_locations.processing.thumbnail_dimension'),
                (int) config('inspection_locations.processing.thumbnail_dimension'),
                true,
                true,
            );
            $thumbnail->setImageFormat('webp');
            $thumbnail->setImageCompressionQuality(78);

            $disk = 'inspection_maps';
            $directory = sprintf(
                'organizations/%d/inspections/%s/maps/%s',
                $map->organization_id,
                $map->inspection->public_id,
                $map->public_id,
            );
            $derivativeDirectory = $directory.'/derivatives/'.hash('sha256', (string) $map->source_checksum);
            $backgroundPath = $derivativeDirectory.'/background.webp';
            $thumbnailPath = $derivativeDirectory.'/thumbnail.webp';
            $backgroundBlob = $image->getImageBlob();
            $thumbnailBlob = $thumbnail->getImageBlob();
            $outputDisk = $disk;
            $outputPaths = [$backgroundPath, $thumbnailPath];
            Storage::disk($disk)->put($backgroundPath, $backgroundBlob);
            Storage::disk($disk)->put($thumbnailPath, $thumbnailBlob);

            $published = InspectionLocationMap::query()
                ->whereKey($map->id)
                ->where('source_checksum', $map->source_checksum)
                ->where('processing_status', InspectionLocationMapProcessingStatus::Processing->value)
                ->update([
                    'background_disk' => $disk,
                    'background_path' => $backgroundPath,
                    'background_mime_type' => 'image/webp',
                    'background_size' => strlen($backgroundBlob),
                    'background_width' => $image->getImageWidth(),
                    'background_height' => $image->getImageHeight(),
                    'background_checksum' => hash('sha256', $backgroundBlob),
                    'processing_status' => InspectionLocationMapProcessingStatus::Ready,
                    'processed_at' => now(),
                    'processing_error' => null,
                    'updated_at' => now(),
                ]);
            if ($published !== 1) {
                $this->removeGeneratedOutputs($outputDisk, $outputPaths, $map->public_id);
                $outputDisk = null;
                $outputPaths = [];
            } else {
                $this->removeUploadedSource($map->refresh(), $assetGuard);
            }
        } catch (Throwable $exception) {
            $this->removeGeneratedOutputs($outputDisk, $outputPaths, $map->public_id);
            $this->markPendingForRetry($map);
            Log::warning('Falha ao processar mapa de localização.', [
                'map_public_id' => $map->public_id,
                'exception' => $exception::class,
            ]);

            throw $exception;
        } finally {
            $thumbnail?->clear();
            $thumbnail?->destroy();
            $image?->clear();
            $image?->destroy();
        }
    }

    public function failed(Throwable $exception): void
    {
        $map = InspectionLocationMap::query()->with(['inspection', 'equipmentDocument'])->find($this->mapId);
        if ($map === null || ! $this->matchesExpectedSource($map) || $map->processing_status === InspectionLocationMapProcessingStatus::Ready || $map->processing_status === InspectionLocationMapProcessingStatus::Failed) {
            return;
        }

        $this->removeFailedUploadedSource($map, app(InspectionLocationAssetGuard::class));
        $this->markFailed($map);

        app(NotifyInspectionImageFailure::class)->handle(
            $map->inspection,
            $map->source_uploaded_by ?? $map->updated_by,
            'Falha no processamento do mapa',
            sprintf('A imagem do mapa “%s” não pôde ser processada. Escolha outra imagem.', $map->title),
            route('inspection-location-maps.edit', $map),
        );
    }

    private function configureResources(Imagick $image): void
    {
        $image->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, (int) config('inspection_locations.processing.memory_megabytes') * 1024 * 1024);
        $image->setResourceLimit(Imagick::RESOURCETYPE_MAP, (int) config('inspection_locations.processing.map_megabytes') * 1024 * 1024);
        $image->setResourceLimit(Imagick::RESOURCETYPE_DISK, (int) config('inspection_locations.processing.disk_megabytes') * 1024 * 1024);
        $image->setResourceLimit(Imagick::RESOURCETYPE_THREAD, 1);
    }

    private function assertSafeDimensions(Imagick $image): void
    {
        $width = $image->getImageWidth();
        $height = $image->getImageHeight();
        $maxDimension = (int) config('inspection_locations.limits.image_dimension');
        $maxPixels = (int) config('inspection_locations.limits.image_pixels');

        if ($width < 1 || $height < 1 || $width > $maxDimension || $height > $maxDimension || ($width * $height) > $maxPixels) {
            throw new RuntimeException('A imagem excede o limite seguro de dimensões.');
        }
    }

    private function matchesExpectedSource(InspectionLocationMap $map): bool
    {
        return $this->expectedSourceChecksum === null
            || ($map->source_checksum !== null && hash_equals($this->expectedSourceChecksum, $map->source_checksum));
    }

    private function markFailed(InspectionLocationMap $map): void
    {
        InspectionLocationMap::query()
            ->whereKey($map->id)
            ->where('source_checksum', $map->source_checksum)
            ->whereIn('processing_status', [
                InspectionLocationMapProcessingStatus::Pending->value,
                InspectionLocationMapProcessingStatus::Processing->value,
            ])
            ->update([
                'processing_status' => InspectionLocationMapProcessingStatus::Failed,
                'processing_error' => (string) config('inspection_locations.processing.error_message'),
                'processed_at' => null,
                'updated_at' => now(),
            ]);
    }

    private function markPendingForRetry(InspectionLocationMap $map): void
    {
        InspectionLocationMap::query()
            ->whereKey($map->id)
            ->where('source_checksum', $map->source_checksum)
            ->where('processing_status', InspectionLocationMapProcessingStatus::Processing->value)
            ->update([
                'processing_status' => InspectionLocationMapProcessingStatus::Pending,
                'processing_error' => (string) config('inspection_locations.processing.error_message'),
                'updated_at' => now(),
            ]);
    }

    private function removeUploadedSource(InspectionLocationMap $map, InspectionLocationAssetGuard $assetGuard): void
    {
        if ($map->source_kind !== InspectionLocationMapSourceKind::Upload || $map->source_path === null) {
            return;
        }

        try {
            $source = $assetGuard->source($map);
            if (! $source['disk']->exists($source['path']) || $source['disk']->delete($source['path'])) {
                $map->update(['source_path' => null]);
            } else {
                Log::warning('Não foi possível remover o upload temporário de um mapa processado.', ['map_public_id' => $map->public_id]);
            }
        } catch (Throwable $exception) {
            Log::warning('Não foi possível remover o upload temporário de um mapa processado.', [
                'map_public_id' => $map->public_id,
                'exception' => $exception::class,
            ]);
        }
    }

    private function removeFailedUploadedSource(InspectionLocationMap $map, InspectionLocationAssetGuard $assetGuard): void
    {
        if ($map->source_kind !== InspectionLocationMapSourceKind::Upload || $map->source_path === null) {
            return;
        }

        try {
            $source = $assetGuard->source($map);
            if (! $source['disk']->exists($source['path']) || $source['disk']->delete($source['path'])) {
                $map->update(['source_path' => null]);
            }
        } catch (Throwable $exception) {
            Log::warning('Não foi possível remover o upload temporário de um mapa após falha definitiva.', [
                'map_public_id' => $map->public_id,
                'exception' => $exception::class,
            ]);
        }
    }

    /** @param array<int, string> $paths */
    private function removeGeneratedOutputs(?string $disk, array $paths, string $mapPublicId): void
    {
        if ($disk === null || $paths === []) {
            return;
        }

        try {
            Storage::disk($disk)->delete($paths);
        } catch (Throwable) {
            Log::warning('Não foi possível limpar derivados descartados de um mapa de localização.', [
                'map_public_id' => $mapPublicId,
            ]);
        }
    }

    private function applyCrop(Imagick $image, ?array $crop): void
    {
        if ($crop === null) {
            return;
        }

        if ((float) $crop['x'] + (float) $crop['width'] > 1 || (float) $crop['y'] + (float) $crop['height'] > 1) {
            throw new RuntimeException('O recorte ultrapassa os limites da imagem.');
        }

        $width = $image->getImageWidth();
        $height = $image->getImageHeight();
        $image->cropImage(
            (int) round($width * $crop['width']),
            (int) round($height * $crop['height']),
            (int) round($width * $crop['x']),
            (int) round($height * $crop['y']),
        );
        $image->setImagePage(0, 0, 0, 0);
    }
}
