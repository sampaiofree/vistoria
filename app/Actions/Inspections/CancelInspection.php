<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Actions\Inspections\Concerns\ValidatesInspectionTransition;
use App\Enums\InspectionStatus;
use App\Models\Inspection;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class CancelInspection
{
    use ValidatesInspectionTransition;

    public function __construct(
        private readonly TransitionInspection $transition,
    ) {}

    public function handle(Inspection $inspection, User $actor, string $reason): Inspection
    {
        return $this->withLockedInspection($inspection, $actor, 'cancel',
            fn (Inspection $locked): Inspection => $this->perform($locked, $actor, $reason));
    }

    private function perform(Inspection $inspection, User $actor, string $reason): Inspection
    {
        $this->validateTenant($inspection, $actor);

        if (! $actor->can('cancel', $inspection)) {
            throw ValidationException::withMessages([
                'actor' => 'Somente o Liberador vinculado pode cancelar esta inspeção.',
            ]);
        }

        if ($inspection->status->isFinal()) {
            throw ValidationException::withMessages([
                'status' => 'A inspeção já foi finalizada.',
            ]);
        }

        return $this->transition->handle(
            $actor,
            $inspection,
            [
                InspectionStatus::Planned,
                InspectionStatus::InProgress,
                InspectionStatus::AwaitingM2,
                InspectionStatus::AwaitingReview,
                InspectionStatus::InCorrection,
                InspectionStatus::InReview,
                InspectionStatus::AwaitingRelease,
            ],
            InspectionStatus::Canceled,
            [
                'canceled_at' => now(),
            ],
            $reason,
        );
    }
}
