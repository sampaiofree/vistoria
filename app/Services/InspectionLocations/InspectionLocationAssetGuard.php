<?php

declare(strict_types=1);

namespace App\Services\InspectionLocations;

use App\Enums\InspectionLocationMapSourceKind;
use App\Models\InspectionLocationMap;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class InspectionLocationAssetGuard
{
    /** @return array{disk:FilesystemAdapter,path:string} */
    public function source(InspectionLocationMap $map): array
    {
        $map->loadMissing(['inspection', 'equipmentDocument']);
        $diskName = $map->source_disk;
        $path = $map->source_path;

        $this->assertRelativePath($diskName, $path);

        if ($map->source_kind === InspectionLocationMapSourceKind::ReferenceDocument) {
            $document = $map->equipmentDocument;
            if ($document === null
                || $document->organization_id !== $map->organization_id
                || $document->equipment_id !== $map->equipment_id
                || $document->disk !== $diskName
                || $document->path !== $path
                || $document->checksum !== $map->source_checksum) {
                throw new RuntimeException('Origem documental inconsistente.');
            }
        } else {
            $this->assertInspectionMapPath($map, $diskName, $path);
        }

        return ['disk' => Storage::disk($diskName), 'path' => $path];
    }

    /** @return array{disk:FilesystemAdapter,path:string} */
    public function background(InspectionLocationMap $map, string $variant = 'background'): array
    {
        $map->loadMissing('inspection');
        $diskName = $map->background_disk;
        $path = $map->background_path;
        $this->assertRelativePath($diskName, $path);
        $this->assertInspectionMapPath($map, $diskName, $path);

        if ($variant === 'thumbnail') {
            $thumbnailPath = dirname($path).'/thumbnail.webp';
            $this->assertInspectionMapPath($map, $diskName, $thumbnailPath);
            if (Storage::disk($diskName)->exists($thumbnailPath)) {
                $path = $thumbnailPath;
            }
        } elseif ($variant !== 'background') {
            throw new RuntimeException('Variante de imagem inválida.');
        }

        return ['disk' => Storage::disk($diskName), 'path' => $path];
    }

    private function assertRelativePath(?string $disk, ?string $path): void
    {
        if (! in_array($disk, ['inspection_maps', 'equipment_documents'], true)
            || $path === null
            || $path === ''
            || str_contains($path, "\0")
            || str_contains($path, '\\')
            || str_starts_with($path, '/')
            || collect(explode('/', $path))->contains(fn (string $part): bool => $part === '' || $part === '.' || $part === '..')) {
            throw new RuntimeException('Caminho de asset inválido.');
        }
    }

    private function assertInspectionMapPath(InspectionLocationMap $map, ?string $disk, ?string $path): void
    {
        $prefix = sprintf(
            'organizations/%d/inspections/%s/maps/%s/',
            $map->organization_id,
            $map->inspection->public_id,
            $map->public_id,
        );

        if ($disk !== 'inspection_maps' || $path === null || ! str_starts_with($path, $prefix)) {
            throw new RuntimeException('Asset fora do diretório privado do mapa.');
        }
    }
}
