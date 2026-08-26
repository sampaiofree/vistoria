<?php

declare(strict_types=1);

namespace App\Services\Photos;

use Illuminate\Support\Facades\Storage;
use Imagick;
use RuntimeException;

final class PhotoVariantProcessor
{
    private const OPTIMIZED_MAX_DIMENSION = 3000;

    private const THUMBNAIL_MAX_DIMENSION = 480;

    /** @return array<string, int|string> */
    public function process(string $diskName, string $originalPath): array
    {
        $disk = Storage::disk($diskName);

        if (! $disk->exists($originalPath)) {
            throw new RuntimeException('Arquivo original não encontrado.');
        }

        $original = $disk->get($originalPath);
        $image = new Imagick;
        $optimized = null;
        $thumbnail = null;
        $optimizedPath = dirname($originalPath).'/optimized.webp';
        $thumbnailPath = dirname($originalPath).'/thumbnail.webp';

        try {
            $this->configureResources($image);
            $image->pingImageBlob($original);
            $image->setIteratorIndex(0);
            $this->assertSafeDimensions($image);
            $image->clear();

            $image->readImageBlob($original);
            $image->setIteratorIndex(0);
            $this->assertSafeDimensions($image);
            $this->autoOrient($image);

            $originalWidth = $image->getImageWidth();
            $originalHeight = $image->getImageHeight();
            $optimized = clone $image;

            $this->resizeDown($optimized, self::OPTIMIZED_MAX_DIMENSION);
            $optimized->setImageFormat('webp');
            $optimized->setImageCompressionQuality(82);
            $disk->put($optimizedPath, $optimized->getImagesBlob());

            $thumbnail = clone $optimized;
            $this->resizeDown($thumbnail, self::THUMBNAIL_MAX_DIMENSION);
            $thumbnail->setImageFormat('webp');
            $thumbnail->setImageCompressionQuality(78);
            $disk->put($thumbnailPath, $thumbnail->getImagesBlob());

            return [
                'original_width' => $originalWidth,
                'original_height' => $originalHeight,
                'checksum' => hash('sha256', $original),
                'optimized_path' => $optimizedPath,
                'optimized_size' => $disk->size($optimizedPath),
                'optimized_width' => $optimized->getImageWidth(),
                'optimized_height' => $optimized->getImageHeight(),
                'thumbnail_path' => $thumbnailPath,
                'thumbnail_size' => $disk->size($thumbnailPath),
                'thumbnail_width' => $thumbnail->getImageWidth(),
                'thumbnail_height' => $thumbnail->getImageHeight(),
            ];
        } catch (\Throwable $exception) {
            $disk->delete([$optimizedPath, $thumbnailPath]);

            throw $exception;
        } finally {
            $image->clear();
            $image->destroy();
            $optimized?->clear();
            $optimized?->destroy();
            $thumbnail?->clear();
            $thumbnail?->destroy();
        }
    }

    private function configureResources(Imagick $image): void
    {
        $image->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, (int) config('photos.processing.memory_megabytes') * 1024 * 1024);
        $image->setResourceLimit(Imagick::RESOURCETYPE_MAP, (int) config('photos.processing.map_megabytes') * 1024 * 1024);
        $image->setResourceLimit(Imagick::RESOURCETYPE_DISK, (int) config('photos.processing.disk_megabytes') * 1024 * 1024);
        $image->setResourceLimit(Imagick::RESOURCETYPE_THREAD, (int) config('photos.processing.threads'));
    }

    private function assertSafeDimensions(Imagick $image): void
    {
        $width = $image->getImageWidth();
        $height = $image->getImageHeight();
        $maxDimension = (int) config('photos.limits.max_dimension');
        $maxPixels = (int) config('photos.limits.max_pixels');

        if ($width < 1 || $height < 1 || $width > $maxDimension || $height > $maxDimension || ($width * $height) > $maxPixels) {
            throw new RuntimeException((string) config('photos.processing.unsafe_image_message'));
        }
    }

    private function autoOrient(Imagick $image): void
    {
        foreach (['autoOrient', 'autoOrientImage', 'autoOrientate'] as $method) {
            if (method_exists($image, $method)) {
                $image->{$method}();
                break;
            }
        }

        $image->setImagePage(0, 0, 0, 0);
    }

    private function resizeDown(Imagick $image, int $maxDimension): void
    {
        if ($image->getImageWidth() <= $maxDimension && $image->getImageHeight() <= $maxDimension) {
            return;
        }

        $image->thumbnailImage($maxDimension, $maxDimension, true);
        $image->setImagePage(0, 0, 0, 0);
    }
}
