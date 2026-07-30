<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Actions\Inspections\Concerns\ValidatesInspectionTransition;
use App\Enums\InspectionResponsibility;
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
        $this->validateTenant($inspection, $actor);

        if (! $actor->isCompanyAdmin() && ! $inspection->hasAnyResponsibilityForUser($actor, ...InspectionResponsibility::cases())) {
            throw ValidationException::withMessages([
                'actor' => 'O usuário não está autorizado a cancelar esta inspeção.',
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
                InspectionStatus::AwaitingReview,
                InspectionStatus::InCorrection,
                InspectionStatus::AwaitingApproval,
                InspectionStatus::Approved,
                InspectionStatus::ReportGenerated,
            ],
            InspectionStatus::Canceled,
            [
                'canceled_at' => now(),
            ],
            $reason,
        );
    }
}
