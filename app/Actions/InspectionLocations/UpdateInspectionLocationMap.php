<?php

declare(strict_types=1);

namespace App\Actions\InspectionLocations;

use App\Exceptions\StaleInspectionLocationMapException;
use App\Models\InspectionLocationMap;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class UpdateInspectionLocationMap
{
    public function handle(User $actor, InspectionLocationMap $map, array $data): InspectionLocationMap
    {
        if (! $actor->can('manageFieldContent', $map->inspection)) {
            throw ValidationException::withMessages(['map' => 'O mapa não está disponível para edição.']);
        }

        $updated = InspectionLocationMap::query()
            ->whereKey($map->id)
            ->where('lock_version', $data['lock_version'])
            ->update([
                ...collect($data)->except('lock_version')->all(),
                'lock_version' => $map->lock_version + 1,
                'updated_by' => $actor->id,
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            throw new StaleInspectionLocationMapException('O mapa foi alterado por outro usuário. Recarregue o editor.');
        }

        return $map->refresh();
    }
}
