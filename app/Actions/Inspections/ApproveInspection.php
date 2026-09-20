<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Actions\Inspections\Concerns\ValidatesInspectionTransition;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
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
        if ($actor->operational_role !== OperationalRole::Reviewer || ! $inspection->hasAnyResponsibilityForUser($actor, ...InspectionResponsibility::cases())) {
            throw ValidationException::withMessages(['actor' => 'Somente o Revisor vinculado pode aprovar a inspeção.']);
        }

        if ($inspection->status !== InspectionStatus::InReview) {
            throw ValidationException::withMessages([
                'status' => 'A inspeção não está em revisão.',
            ]);
        }

        return $this->transition->handle(
            $actor,
            $inspection,
            [InspectionStatus::InReview],
            InspectionStatus::AwaitingRelease,
            [
                'approved_at' => now(),
            ],
            'Inspeção aprovada.',
        );
    }
}
