<?php

declare(strict_types=1);

namespace App\Actions\InspectionLocations;

use App\Models\InspectionLocationMap;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DeleteInspectionLocationMap
{
    public function handle(User $actor, InspectionLocationMap $map): void
    {
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
    }
}
