<?php

declare(strict_types=1);

namespace App\Actions\InspectionLocations;

use App\Enums\InspectionLocationMapSourceKind;
use App\Models\InspectionLocationMap;
use App\Models\User;
use App\Services\InspectionLocations\InspectionLocationAssetGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class DeleteInspectionLocationMap
{
    public function __construct(private readonly InspectionLocationAssetGuard $assetGuard) {}

    public function handle(User $actor, InspectionLocationMap $map): void
    {
        $paths = $this->ownedAssets($map);

        DB::transaction(function () use ($actor, $map): void {
            $map->markers()->update([
                'active_slot' => null,
                'deleted_at' => now(),
                'updated_by' => $actor->id,
                'updated_at' => now(),
            ]);
            $map->update(['updated_by' => $actor->id]);
            $map->delete();
        });

        foreach ($paths as $asset) {
            try {
                $asset['disk']->delete($asset['paths']);
            } catch (Throwable $exception) {
                Log::warning('Não foi possível remover imediatamente os arquivos de um mapa excluído.', [
                    'map_public_id' => $map->public_id,
                    'exception' => $exception::class,
                ]);
            }
        }
    }

    /** @return array<int, array{disk:mixed,paths:array<int,string>}> */
    private function ownedAssets(InspectionLocationMap $map): array
    {
        $assets = [];

        if ($map->source_kind === InspectionLocationMapSourceKind::Upload && $map->source_path !== null) {
            try {
                $source = $this->assetGuard->source($map);
                $assets[] = ['disk' => $source['disk'], 'paths' => [$source['path']]];
            } catch (RuntimeException) {
                // Caminhos inconsistentes ou documentos referenciados nunca são apagados.
            }
        }

        if ($map->background_path !== null) {
            try {
                $background = $this->assetGuard->background($map);
                $assets[] = [
                    'disk' => $background['disk'],
                    'paths' => [$background['path'], dirname($background['path']).'/thumbnail.webp'],
                ];
            } catch (RuntimeException) {
                // Caminhos inconsistentes nunca são usados em operações destrutivas.
            }
        }

        return $assets;
    }
}
