<?php

declare(strict_types=1);

namespace App\Actions\InspectionLocations;

use App\Enums\InspectionLocationMapProcessingStatus;
use App\Jobs\ProcessInspectionLocationMap;
use App\Models\InspectionLocationMap;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class RetryInspectionLocationMapProcessing
{
    public function handle(User $actor, InspectionLocationMap $map): InspectionLocationMap
    {
        if ($actor->organization_id !== $map->organization_id || $map->source_path === null || $map->source_checksum === null) {
            throw ValidationException::withMessages(['map' => 'O mapa não possui uma origem válida para reprocessamento.']);
        }

        $updated = InspectionLocationMap::query()
            ->whereKey($map->id)
            ->where('processing_status', InspectionLocationMapProcessingStatus::Failed->value)
            ->update([
                'processing_status' => InspectionLocationMapProcessingStatus::Pending,
                'processing_error' => null,
                'processed_at' => null,
                'updated_by' => $actor->id,
                'updated_at' => now(),
            ]);
        if ($updated !== 1) {
            throw ValidationException::withMessages(['map' => 'O mapa já está processado ou em processamento.']);
        }

        ProcessInspectionLocationMap::dispatch($map->id, $map->source_checksum)->afterCommit();

        return $map->refresh();
    }
}
