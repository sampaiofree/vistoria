<?php

declare(strict_types=1);

namespace App\Actions\InspectionLocations;

use App\Enums\DefectAssessmentStatus;
use App\Exceptions\StaleDefectAssessmentLocationException;
use App\Models\DefectAssessment;
use App\Models\DefectAssessmentLocation;
use App\Models\User;
use App\Services\Defects\DefectStatusSynchronizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeleteDefectAssessmentLocation
{
    public function __construct(private readonly DefectStatusSynchronizer $statusSynchronizer) {}

    public function handle(User $actor, DefectAssessment $assessment, int $lockVersion): void
    {
        $assessment->loadMissing(['inspection', 'defect', 'location']);
        if ($actor->organization_id !== $assessment->organization_id || ! $actor->can('update', $assessment)) {
            throw ValidationException::withMessages(['location' => 'A avaliacao nao esta disponivel para remover a localizacao.']);
        }
        if ($assessment->location === null) {
            return;
        }

        $wasComplete = $assessment->isComplete();

        DB::transaction(function () use ($actor, $assessment, $lockVersion): void {
            $deleted = DefectAssessmentLocation::query()
                ->whereKey($assessment->location->id)
                ->where('lock_version', $lockVersion)
                ->delete();
            if ($deleted !== 1) {
                throw new StaleDefectAssessmentLocationException('A localizacao foi alterada por outro usuario. Recarregue o editor.');
            }

            if ($assessment->isComplete()) {
                $assessment->forceFill([
                    'status' => DefectAssessmentStatus::Draft,
                    'assessed_at' => null,
                    'defect_snapshot' => null,
                    'quantity_snapshot' => null,
                    'updated_by' => $actor->id,
                ])->save();
            }
        });

        if ($wasComplete) {
            $this->statusSynchronizer->handle($assessment->defect, $actor);
        }
    }
}
