<?php

declare(strict_types=1);

namespace App\Services\Photos;

use Illuminate\Support\Facades\Storage;
use Imagick;

final class PhotoVariantProcessor
{
    private const OPTIMIZED_MAX_DIMENSION = 2000;

    private const THUMBNAIL_MAX_DIMENSION = 480;

    /** @return array<string, int|string> */
    public function process(string $diskName, string $originalPath): array
    {
        $disk = Storage::disk($diskName);

        if (! $disk->exists($originalPath)) {
            throw new \RuntimeException('Arquivo original não encontrado.');
        }

        $original = $disk->get($originalPath);
        $image = new Imagick;
        $image->readImageBlob($original);
        $image->setIteratorIndex(0);
        $this->autoOrient($image);
        $optimized = clone $image;
        $thumbnail = clone $image;

        try {
            $base = dirname($originalPath);
            $optimizedPath = $base.'/optimized.webp';
            $thumbnailPath = $base.'/thumbnail.webp';

            $this->resizeDown($optimized, self::OPTIMIZED_MAX_DIMENSION);
            $optimized->setImageFormat('webp');
            $optimized->setImageCompressionQuality(82);
            $disk->put($optimizedPath, $optimized->getImagesBlob());

            $this->resizeDown($thumbnail, self::THUMBNAIL_MAX_DIMENSION);
            $thumbnail->setImageFormat('webp');
            $thumbnail->setImageCompressionQuality(78);
            $disk->put($thumbnailPath, $thumbnail->getImagesBlob());

            return [
                'original_width' => $image->getImageWidth(),
                'original_height' => $image->getImageHeight(),
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
        } finally {
            $image->clear();
            $optimized->clear();
            $thumbnail->clear();
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
