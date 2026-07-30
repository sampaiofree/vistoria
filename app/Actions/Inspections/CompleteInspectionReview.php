<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Actions\Inspections\Concerns\ValidatesInspectionTransition;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Models\Inspection;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class CompleteInspectionReview
{
    use ValidatesInspectionTransition;

    public function __construct(
        private readonly TransitionInspection $transition,
    ) {}

    public function handle(Inspection $inspection, User $actor): Inspection
    {
        $this->validateTenant($inspection, $actor);
        $this->ensureActorHasResponsibility($inspection, $actor, InspectionResponsibility::Reviewer);
        $this->ensureResponsibilityPresent(
            $inspection,
            InspectionResponsibility::Reviewer,
            InspectionResponsibility::Approver,
        );

        if ($inspection->status !== InspectionStatus::AwaitingReview) {
            throw ValidationException::withMessages([
                'status' => 'A inspeção não está aguardando revisão.',
            ]);
        }

        return $this->transition->handle(
            $actor,
            $inspection,
            [InspectionStatus::AwaitingReview],
            InspectionStatus::AwaitingApproval,
            [
                'reviewed_at' => now(),
            ],
            'Revisão concluída.',
        );
    }
}
