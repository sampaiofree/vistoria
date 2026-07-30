<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Actions\Inspections\Concerns\ValidatesInspectionTransition;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Models\Inspection;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class ApproveInspection
{
    use ValidatesInspectionTransition;

    public function __construct(
        private readonly TransitionInspection $transition,
    ) {}

    public function handle(Inspection $inspection, User $actor): Inspection
    {
        $this->validateTenant($inspection, $actor);
        $this->ensureActorHasResponsibility($inspection, $actor, InspectionResponsibility::Approver);
        $this->ensureResponsibilityPresent($inspection, InspectionResponsibility::Approver);

        if ($inspection->status !== InspectionStatus::AwaitingApproval) {
            throw ValidationException::withMessages([
                'status' => 'A inspeção não está aguardando aprovação.',
            ]);
        }

        return $this->transition->handle(
            $actor,
            $inspection,
            [InspectionStatus::AwaitingApproval],
            InspectionStatus::Approved,
            [
                'approved_at' => now(),
            ],
            'Inspeção aprovada.',
        );
    }
}
