<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Actions\Inspections\Concerns\ValidatesInspectionResponsibility;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Models\Inspection;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class ReturnInspectionForCorrection
{
    use ValidatesInspectionResponsibility;

    public function __construct(private readonly TransitionInspection $transition) {}

    public function handle(Inspection $inspection, User $actor, string $reason): Inspection
    {
        return $this->transition->handle(
            $inspection, $actor, [InspectionStatus::AwaitingReview, InspectionStatus::AwaitingApproval], InspectionStatus::InCorrection, null,
            function (Inspection $locked) use ($actor, $reason): void {
                if (blank(trim($reason))) {
                    throw ValidationException::withMessages(['reason' => 'A justificativa é obrigatória.']);
                }
                $role = $locked->status === InspectionStatus::AwaitingReview
                    ? InspectionResponsibility::Reviewer
                    : InspectionResponsibility::Approver;
                $this->requireResponsible($locked, $role, $actor);
            },
            trim($reason),
        );
    }
}
