<?php

declare(strict_types=1);

namespace App\Actions\InspectionLocations;

use App\Enums\DefectAssessmentStatus;
use App\Models\DefectAssessment;
use App\Models\User;
use App\Services\Defects\DefectStatusSynchronizer;
use App\Services\InspectionLocations\DefectLocationMapVersionPruner;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeleteDefectLocationMapVersion
{
    public function __construct(
        private readonly DefectLocationMapVersionPruner $pruner,
        private readonly DefectStatusSynchronizer $statusSynchronizer,
    ) {}

    public function handle(User $actor, DefectAssessment $assessment): void
    {
        $assessment->loadMissing(['inspection', 'defect', 'locationMapVersion']);
        if ($actor->organization_id !== $assessment->organization_id || ! $actor->can('update', $assessment)) {
            throw ValidationException::withMessages(['map' => 'A avaliacao nao esta disponivel para remover o mapa.']);
        }
        if ($assessment->locationMapVersion === null) {
            return;
        }

        $version = $assessment->locationMapVersion;
        $wasComplete = $assessment->isComplete();

        DB::transaction(function () use ($actor, $assessment): void {
            $assessment = DefectAssessment::query()->lockForUpdate()->findOrFail($assessment->id);
            $assessment->location()->delete();
            $assessment->forceFill([
                'defect_location_map_version_id' => null,
                'status' => $assessment->isComplete() ? DefectAssessmentStatus::Draft : $assessment->status,
                'assessed_at' => $assessment->isComplete() ? null : $assessment->assessed_at,
                'defect_snapshot' => $assessment->isComplete() ? null : $assessment->defect_snapshot,
                'quantity_snapshot' => $assessment->isComplete() ? null : $assessment->quantity_snapshot,
                'updated_by' => $actor->id,
            ])->save();
        });

        if ($wasComplete) {
            $this->statusSynchronizer->handle($assessment->defect, $actor);
        }
        $this->pruner->pruneIfUnreferenced($version);
    }
}
