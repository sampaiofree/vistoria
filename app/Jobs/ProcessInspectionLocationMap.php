<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\InspectionLocationMapProcessingStatus;
use App\Models\DefectLocationMapVersion;
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
        public readonly int $mapVersionId,
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
        $version = DefectLocationMapVersion::query()->with('map.defect')->find($this->mapVersionId);
        if ($version === null || ! $this->matchesExpectedSource($version)) {
            return;
        }

        $claimed = DefectLocationMapVersion::query()
            ->whereKey($version->id)
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

        $version->refresh();
        $image = null;
        $thumbnail = null;
        $outputPaths = [];

        try {
            $source = $assetGuard->source($version);
            if (! $source['disk']->exists($source['path'])) {
                throw new RuntimeException('Origem nao encontrada.');
            }
            if ((int) $source['disk']->size($source['path']) > (int) config('inspection_locations.limits.source_size_kilobytes') * 1024) {
                throw new RuntimeException('Origem acima do limite permitido.');
            }

            $image = new Imagick;
            $this->configureResources($image);
            $image->readImage($source['disk']->path($source['path']));
            $image->setIteratorIndex(0);
            $this->assertSafeDimensions($image);
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

            $directory = dirname((string) $version->source_path).'/derivatives/'.hash('sha256', (string) $version->source_checksum);
            $backgroundPath = $directory.'/background.webp';
            $thumbnailPath = $directory.'/thumbnail.webp';
            $backgroundBlob = $image->getImageBlob();
            $thumbnailBlob = $thumbnail->getImageBlob();
            $outputPaths = [$backgroundPath, $thumbnailPath];
            Storage::disk('inspection_maps')->put($backgroundPath, $backgroundBlob);
            Storage::disk('inspection_maps')->put($thumbnailPath, $thumbnailBlob);

            $published = DefectLocationMapVersion::query()
                ->whereKey($version->id)
                ->where('source_checksum', $version->source_checksum)
                ->where('processing_status', InspectionLocationMapProcessingStatus::Processing->value)
                ->update([
                    'background_disk' => 'inspection_maps',
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
                Storage::disk('inspection_maps')->delete($outputPaths);
            } else {
                $this->removeUploadedSource($version->refresh(), $assetGuard);
            }
        } catch (Throwable $exception) {
            Storage::disk('inspection_maps')->delete($outputPaths);
            $this->markPendingForRetry($version);
            Log::warning('Falha ao processar versao do mapa de localizacao.', [
                'map_version_public_id' => $version->public_id,
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
        $version = DefectLocationMapVersion::query()->with(['createdForAssessment.inspection', 'map.defect'])->find($this->mapVersionId);
        if ($version === null || ! $this->matchesExpectedSource($version) || in_array($version->processing_status, [
            InspectionLocationMapProcessingStatus::Ready,
            InspectionLocationMapProcessingStatus::Failed,
        ], true)) {
            return;
        }

        $this->removeUploadedSource($version, app(InspectionLocationAssetGuard::class));
        $this->markFailed($version);
        $assessment = $version->createdForAssessment;
        if ($assessment !== null) {
            app(NotifyInspectionImageFailure::class)->handle(
                $assessment->inspection,
                $version->source_uploaded_by,
                'Falha no processamento do mapa',
                sprintf('A imagem do mapa da avaria %s nao pode ser processada. Escolha outra imagem.', $version->map->defect->code),
                route('defect-assessments.show', $assessment),
            );
        }
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
        if ($width < 1 || $height < 1
            || $width > (int) config('inspection_locations.limits.image_dimension')
            || $height > (int) config('inspection_locations.limits.image_dimension')
            || ($width * $height) > (int) config('inspection_locations.limits.image_pixels')) {
            throw new RuntimeException('A imagem excede o limite seguro de dimensoes.');
        }
    }

    private function matchesExpectedSource(DefectLocationMapVersion $version): bool
    {
        return $this->expectedSourceChecksum === null
            || ($version->source_checksum !== null && hash_equals($this->expectedSourceChecksum, $version->source_checksum));
    }

    private function markFailed(DefectLocationMapVersion $version): void
    {
        DefectLocationMapVersion::query()
            ->whereKey($version->id)
            ->where('source_checksum', $version->source_checksum)
            ->whereIn('processing_status', [
                InspectionLocationMapProcessingStatus::Pending->value,
                InspectionLocationMapProcessingStatus::Processing->value,
            ])->update([
                'processing_status' => InspectionLocationMapProcessingStatus::Failed,
                'processing_error' => (string) config('inspection_locations.processing.error_message'),
                'processed_at' => null,
                'updated_at' => now(),
            ]);
    }

    private function markPendingForRetry(DefectLocationMapVersion $version): void
    {
        DefectLocationMapVersion::query()
            ->whereKey($version->id)
            ->where('source_checksum', $version->source_checksum)
            ->where('processing_status', InspectionLocationMapProcessingStatus::Processing->value)
            ->update([
                'processing_status' => InspectionLocationMapProcessingStatus::Pending,
                'processing_error' => (string) config('inspection_locations.processing.error_message'),
                'updated_at' => now(),
            ]);
    }

    private function removeUploadedSource(DefectLocationMapVersion $version, InspectionLocationAssetGuard $assetGuard): void
    {
        if ($version->source_path === null) {
            return;
        }
        try {
            $source = $assetGuard->source($version);
            if (! $source['disk']->exists($source['path']) || $source['disk']->delete($source['path'])) {
                $version->update(['source_path' => null]);
            }
        } catch (Throwable $exception) {
            Log::warning('Nao foi possivel remover o upload temporario de uma versao de mapa.', [
                'map_version_public_id' => $version->public_id,
                'exception' => $exception::class,
            ]);
        }
    }
}
