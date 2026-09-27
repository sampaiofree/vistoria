<?php

declare(strict_types=1);

namespace App\Services\InspectionLocations;

use App\Models\DefectLocationMapVersion;
use Illuminate\Support\Facades\Log;
use Throwable;

final class DefectLocationMapVersionPruner
{
    public function __construct(private readonly InspectionLocationAssetGuard $assetGuard) {}

    public function pruneIfUnreferenced(DefectLocationMapVersion $version): void
    {
        $version->refresh();
        if ($version->assessments()->exists()) {
            return;
        }

        $assets = [];
        foreach (['source', 'background'] as $kind) {
            try {
                if ($this->isReferencedByAnotherVersion($version, $kind)) {
                    continue;
                }

                $asset = $kind === 'source'
                    ? $this->assetGuard->source($version)
                    : $this->assetGuard->background($version);
                $assets[] = $kind === 'background'
                    ? ['disk' => $asset['disk'], 'paths' => [$asset['path'], dirname($asset['path']).'/thumbnail.webp']]
                    : ['disk' => $asset['disk'], 'paths' => [$asset['path']]];
            } catch (\RuntimeException) {
                // Missing or already-cleaned assets are safe to ignore.
            }
        }

        $map = $version->map;
        $version->delete();

        foreach ($assets as $asset) {
            try {
                $asset['disk']->delete($asset['paths']);
            } catch (Throwable $exception) {
                Log::warning('Nao foi possivel remover os arquivos de uma versao de mapa sem referencia.', [
                    'map_version_public_id' => $version->public_id,
                    'exception' => $exception::class,
                ]);
            }
        }

        if (! $map->versions()->exists()) {
            $map->delete();
        }
    }

    private function isReferencedByAnotherVersion(DefectLocationMapVersion $version, string $kind): bool
    {
        $pathColumn = $kind === 'source' ? 'source_path' : 'background_path';
        $diskColumn = $kind === 'source' ? 'source_disk' : 'background_disk';
        $path = $version->getAttribute($pathColumn);
        $disk = $version->getAttribute($diskColumn);

        if ($path === null || $disk === null) {
            return false;
        }

        return DefectLocationMapVersion::query()
            ->whereKeyNot($version->getKey())
            ->where($diskColumn, $disk)
            ->where($pathColumn, $path)
            ->exists();
    }
}
