<?php

declare(strict_types=1);

namespace App\Services\Pwa;

use App\Models\Organization;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickException;

final class OrganizationAppBranding
{
    public const ICON_SIZES = [192, 512];

    public function manifest(Organization $organization): array
    {
        $version = $this->version($this->uploadedIcon($organization));

        return [
            'id' => '/pwa/'.$organization->public_id,
            'name' => $organization->name,
            'short_name' => $organization->name,
            'lang' => 'pt-BR',
            'start_url' => route('dashboard', absolute: false),
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#FFFFFF',
            'theme_color' => $organization->primary_color ?? '#0F172A',
            'prefer_related_applications' => false,
            'icons' => array_map(fn (int $size): array => [
                'src' => $this->iconUrl($organization, $size, $version),
                'sizes' => $size.'x'.$size,
                'type' => 'image/png',
                'purpose' => 'any',
            ], self::ICON_SIZES),
        ];
    }

    public function iconUrl(Organization $organization, int $size = 192, ?string $version = null): string
    {
        return route('pwa.icon', [
            'organization' => $organization,
            'size' => $size,
            'v' => $version ?? $this->version($this->uploadedIcon($organization)),
        ], absolute: false);
    }

    public function icon(Organization $organization, int $size): string
    {
        $uploadedIcon = $this->uploadedIcon($organization);
        $key = 'pwa:icon:base64:'.$organization->public_id.':'.$this->version($uploadedIcon).':'.$size;

        // Database caches may use a UTF-8 TEXT column, which cannot store raw PNG bytes.
        // A new namespace prevents treating legacy binary entries as Base64.
        $encoded = Cache::remember($key, now()->addDays(30), fn (): string => base64_encode($this->render($uploadedIcon, $size)));

        return base64_decode($encoded, strict: true);
    }

    private function uploadedIcon(Organization $organization): ?string
    {
        $disk = Storage::disk('branding_images');
        $path = $organization->pwa_icon_path;

        if ($path === null || ! $disk->exists($path) || $disk->size($path) > 2 * 1024 * 1024) {
            return null;
        }

        return $disk->get($path);
    }

    private function version(?string $uploadedIcon): string
    {
        // Include the renderer version so future visual changes invalidate old derivatives.
        return hash('sha256', 'v2|'.($uploadedIcon ?? file_get_contents(resource_path('images/pwa-icon.svg'))));
    }

    private function render(?string $uploadedIcon, int $size): string
    {
        $image = new Imagick;
        $canvas = new Imagick;
        $customIconLoaded = false;
        $limits = [
            Imagick::RESOURCETYPE_MEMORY => (int) config('photos.processing.memory_megabytes') * 1024 * 1024,
            Imagick::RESOURCETYPE_MAP => (int) config('photos.processing.map_megabytes') * 1024 * 1024,
            Imagick::RESOURCETYPE_DISK => (int) config('photos.processing.disk_megabytes') * 1024 * 1024,
            Imagick::RESOURCETYPE_THREAD => (int) config('photos.processing.threads'),
        ];
        $previousLimits = [];

        try {
            foreach ($limits as $resource => $limit) {
                $previousLimits[$resource] = $image->getResourceLimit($resource);
                $image->setResourceLimit($resource, $limit);
            }

            if ($uploadedIcon !== null && $this->safeRaster($uploadedIcon)) {
                try {
                    $image->readImageBlob($uploadedIcon);
                    $image->setIteratorIndex(0);
                    foreach (['autoOrient', 'autoOrientImage', 'autoOrientate'] as $method) {
                        if (method_exists($image, $method)) {
                            $image->{$method}();
                            break;
                        }
                    }
                    $customIconLoaded = true;
                } catch (ImagickException) {
                    $image->clear();
                }
            }

            if ($image->getNumberImages() === 0) {
                // Only the bundled SVG is decoded here; uploaded SVGs are never accepted.
                $image->setResolution(192, 192);
                $image->readImage(resource_path('images/pwa-icon.svg'));
            }

            if ($customIconLoaded) {
                $image->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1);
            } else {
                $image->thumbnailImage((int) floor($size * 0.8), (int) floor($size * 0.8), true);
            }
            $image->setImagePage(0, 0, 0, 0);
            $canvas->newImage($size, $size, 'white', 'png');
            $canvas->compositeImage(
                $image,
                Imagick::COMPOSITE_OVER,
                (int) floor(($size - $image->getImageWidth()) / 2),
                (int) floor(($size - $image->getImageHeight()) / 2),
            );
            $canvas->stripImage();

            return $canvas->getImageBlob();
        } finally {
            $image->clear();
            $canvas->clear();
            foreach ($previousLimits as $resource => $limit) {
                Imagick::setResourceLimit($resource, $limit);
            }
        }
    }

    private function safeRaster(string $contents): bool
    {
        $dimensions = @getimagesizefromstring($contents);

        return $dimensions !== false
            && in_array($dimensions['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)
            && $dimensions[0] > 0 && $dimensions[0] === $dimensions[1]
            && max($dimensions[0], $dimensions[1]) <= (int) config('photos.limits.max_dimension')
            && (int) config('photos.limits.max_pixels') >= $dimensions[0] * $dimensions[1];
    }
}
