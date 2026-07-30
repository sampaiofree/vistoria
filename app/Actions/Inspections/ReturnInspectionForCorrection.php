<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Actions\Inspections\Concerns\ValidatesInspectionTransition;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Models\Inspection;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class ReturnInspectionForCorrection
{
    use ValidatesInspectionTransition;

    public function __construct(
        private readonly TransitionInspection $transition,
    ) {}

    public function handle(Inspection $inspection, User $actor, string $reason): Inspection
    {
        $this->validateTenant($inspection, $actor);

        if ($inspection->status === InspectionStatus::AwaitingReview) {
            $this->ensureActorHasResponsibility($inspection, $actor, InspectionResponsibility::Reviewer);
        } elseif ($inspection->status === InspectionStatus::AwaitingApproval) {
            $this->ensureActorHasResponsibility($inspection, $actor, InspectionResponsibility::Approver);
        } else {
            throw ValidationException::withMessages([
                'status' => 'A inspeção não está em revisão nem em aprovação.',
            ]);
        }

        return $this->transition->handle(
            $actor,
            $inspection,
            [
                InspectionStatus::AwaitingReview,
                InspectionStatus::AwaitingApproval,
            ],
            InspectionStatus::InCorrection,
            [],
            $reason,
        );
    }
}
