<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Models\Organization;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FileAttributes;

final class OrganizationStorageScanner
{
    /** @return array{photos: array{bytes: int, file_count: int}, maps: array{bytes: int, file_count: int}, branding: array{bytes: int, file_count: int}, total_bytes: int, total_file_count: int, measured_at: string} */
    public function scan(int $organizationId): array
    {
        $prefix = 'organizations/'.$organizationId;
        $result = [
            'photos' => ['bytes' => 0, 'file_count' => 0],
            'maps' => ['bytes' => 0, 'file_count' => 0],
            'branding' => ['bytes' => 0, 'file_count' => 0],
        ];

        foreach (['inspection_photos' => 'photos', 'inspection_maps' => 'maps'] as $diskName => $category) {
            $disk = Storage::disk($diskName);

            foreach ($disk->getDriver()->listContents($prefix, true) as $file) {
                if (! $file instanceof FileAttributes || ! preg_match('/\.(?:jpe?g|png|webp)$/i', $file->path())) {
                    continue;
                }

                $size = $file->fileSize() ?? $disk->size($file->path());
                $result[$category]['bytes'] += $size;
                $result[$category]['file_count']++;
            }
        }

        $organization = Organization::query()->findOrFail($organizationId);
        $brandingDisk = Storage::disk('branding_images');
        foreach ([
            $prefix.'/clients',
            'organizations/'.$organization->public_id.'/branding',
        ] as $brandingPrefix) {
            foreach ($brandingDisk->getDriver()->listContents($brandingPrefix, true) as $file) {
                if (! $file instanceof FileAttributes || ! preg_match('/\.(?:jpe?g|png|webp)$/i', $file->path())) {
                    continue;
                }

                $result['branding']['bytes'] += $file->fileSize() ?? $brandingDisk->size($file->path());
                $result['branding']['file_count']++;
            }
        }

        return [
            ...$result,
            'total_bytes' => $result['photos']['bytes'] + $result['maps']['bytes'] + $result['branding']['bytes'],
            'total_file_count' => $result['photos']['file_count'] + $result['maps']['file_count'] + $result['branding']['file_count'],
            'measured_at' => now()->toIso8601String(),
        ];
    }
}
