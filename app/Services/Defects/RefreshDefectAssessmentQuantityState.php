<?php

declare(strict_types=1);

namespace App\Services\Defects;

use App\Enums\DefectAssessmentStatus;
use App\Models\DefectAssessment;
use App\Models\User;

final class RefreshDefectAssessmentQuantityState
{
    public function __construct(
        private readonly DefectAssessmentQuantitySnapshot $snapshot,
        private readonly DefectStatusSynchronizer $statusSynchronizer,
    ) {}

    public function handle(DefectAssessment $assessment, User $actor, bool $wasComplete): DefectAssessment
    {
        $assessment->load(['defect', 'quantities', 'locationMapVersion', 'location']);

        if ($wasComplete) {
            $keepPublished = $assessment->quantities->isNotEmpty()
                && $assessment->locationMapVersion?->isReady()
                && $assessment->location?->isConfirmed();

            if ($keepPublished) {
                $assessment->assessed_at = now();
                $assessment->quantity_snapshot = $this->snapshot->build(
                    $assessment->defect->category,
                    $assessment->quantities,
                );
            } else {
                $assessment->fill([
                    'status' => DefectAssessmentStatus::Draft,
                    'assessed_at' => null,
                    'defect_snapshot' => null,
                    'quantity_snapshot' => null,
                ]);
            }
        }

        $assessment->updated_by = $actor->getKey();
        $assessment->save();

        if ($wasComplete && $assessment->status === DefectAssessmentStatus::Draft) {
            $this->statusSynchronizer->handle($assessment->defect, $actor);
        }

        return $assessment->refresh()->load('quantities');
    }
}
