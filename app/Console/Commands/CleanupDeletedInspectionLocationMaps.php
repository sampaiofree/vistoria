<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\InspectionLocationMapSourceKind;
use App\Models\InspectionLocationMap;
use App\Models\InspectionLocationMarker;
use App\Services\InspectionLocations\InspectionLocationAssetGuard;
use Illuminate\Console\Command;
use RuntimeException;

final class CleanupDeletedInspectionLocationMaps extends Command
{
    protected $signature = 'inspection-maps:cleanup-deleted {--days=30}';

    protected $description = 'Remove arquivos e registros de mapas excluídos após a retenção.';

    public function handle(InspectionLocationAssetGuard $assetGuard): int
    {
        $cutoff = now()->subDays(max(1, (int) $this->option('days')));
        $removed = 0;

        InspectionLocationMap::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->with('inspection')
            ->chunkById(100, function ($maps) use (&$removed, $assetGuard): void {
                foreach ($maps as $map) {
                    if ($map->inspection?->status?->isFinal()) {
                        continue;
                    }

                    if ($map->source_kind === InspectionLocationMapSourceKind::Upload) {
                        try {
                            $source = $assetGuard->source($map);
                            $source['disk']->delete($source['path']);
                        } catch (RuntimeException) {
                            // Caminhos inconsistentes nunca são usados em operações destrutivas.
                        }
                    }

                    try {
                        $background = $assetGuard->background($map);
                        $background['disk']->delete([
                            $background['path'],
                            dirname($background['path']).'/thumbnail.webp',
                        ]);
                    } catch (RuntimeException) {
                        // Caminhos inconsistentes nunca são usados em operações destrutivas.
                    }

                    InspectionLocationMarker::withTrashed()->where('inspection_location_map_id', $map->id)->forceDelete();
                    $map->forceDelete();
                    $removed++;
                }
            });

        $this->info("{$removed} mapa(s) removido(s).");

        return self::SUCCESS;
    }
}
