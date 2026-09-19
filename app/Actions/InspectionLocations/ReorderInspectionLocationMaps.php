<?php

declare(strict_types=1);

namespace App\Actions\InspectionLocations;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Exceptions\StaleInspectionLocationMapException;
use App\Models\Inspection;
use App\Models\InspectionLocationMap;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReorderInspectionLocationMaps
{
    /** @param array<int, array{public_id:string,lock_version:int}> $orderedMaps */
    public function handle(User $actor, Inspection $inspection, array $orderedMaps): void
    {
        $publicIds = array_column($orderedMaps, 'public_id');
        DB::transaction(function () use ($actor, $inspection, $orderedMaps, $publicIds): void {
            $lockedInspection = Inspection::query()->whereKey($inspection->id)->lockForUpdate()->firstOrFail();
            if ($actor->organization_id !== $lockedInspection->organization_id
                || ! $actor->can('manageFieldContent', $lockedInspection)) {
                throw ValidationException::withMessages(['maps' => 'A inspeção não está disponível para reordenar mapas.']);
            }

            $maps = $lockedInspection->locationMaps()->lockForUpdate()->get()->keyBy('public_id');
            if ($maps->count() !== count($orderedMaps)
                || count($publicIds) !== count(array_unique($publicIds))
                || array_diff($publicIds, $maps->keys()->all()) !== []) {
                throw ValidationException::withMessages(['maps' => 'A ordem contém mapas inválidos.']);
            }

            foreach ($orderedMaps as $index => $item) {
                $map = $maps->get($item['public_id']);
                $updated = InspectionLocationMap::query()->whereKey($map->id)->where('lock_version', $item['lock_version'])->update([
                    'position' => $index + 1,
                    'lock_version' => $item['lock_version'] + 1,
                    'updated_by' => $actor->id,
                    'updated_at' => now(),
                ]);
                if ($updated !== 1) {
                    throw new StaleInspectionLocationMapException('Um mapa foi alterado por outro usuário. Recarregue a página.');
                }
            }
        });
    }
}
