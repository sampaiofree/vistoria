<?php

declare(strict_types=1);

namespace App\Services\InspectionLocations;

use App\Models\DefectLocationMapVersion;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class InspectionLocationAssetGuard
{
    /** @return array{disk:FilesystemAdapter,path:string} */
    public function source(DefectLocationMapVersion $version): array
    {
        return $this->asset($version, $version->source_disk, $version->source_path);
    }

    /** @return array{disk:FilesystemAdapter,path:string} */
    public function background(DefectLocationMapVersion $version, string $variant = 'background'): array
    {
        $asset = $this->asset($version, $version->background_disk, $version->background_path);

        if ($variant === 'thumbnail') {
            $thumbnail = dirname($asset['path']).'/thumbnail.webp';
            $this->assertVersionPath($version, $version->background_disk, $thumbnail);
            if ($asset['disk']->exists($thumbnail)) {
                $asset['path'] = $thumbnail;
            }
        } elseif ($variant !== 'background') {
            throw new RuntimeException('Variante de imagem invalida.');
        }

        return $asset;
    }

    /** @return array{disk:FilesystemAdapter,path:string} */
    private function asset(DefectLocationMapVersion $version, ?string $disk, ?string $path): array
    {
        $this->assertRelativePath($disk, $path);
        $this->assertVersionPath($version, $disk, $path);

        return ['disk' => Storage::disk((string) $disk), 'path' => (string) $path];
    }

    private function assertRelativePath(?string $disk, ?string $path): void
    {
        if ($disk !== 'inspection_maps'
            || $path === null
            || $path === ''
            || str_contains($path, "\0")
            || str_contains($path, '\\')
            || str_starts_with($path, '/')
            || collect(explode('/', $path))->contains(fn (string $part): bool => $part === '' || $part === '.' || $part === '..')) {
            throw new RuntimeException('Caminho de asset invalido.');
        }
    }

    private function assertVersionPath(DefectLocationMapVersion $version, ?string $disk, ?string $path): void
    {
        $version->loadMissing('map.defect');
        $prefix = sprintf(
            'organizations/%d/defects/%s/maps/%s/versions/%s/',
            $version->organization_id,
            $version->map->defect->public_id,
            $version->map->public_id,
            $version->public_id,
        );

        if ($disk !== 'inspection_maps' || $path === null || ! str_starts_with($path, $prefix)) {
            throw new RuntimeException('Asset fora do diretorio privado da versao do mapa.');
        }
    }
}
