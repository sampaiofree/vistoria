<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Enums\InspectionStatus;
use App\Models\Inspection;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class CancelInspection
{
    public function __construct(private readonly TransitionInspection $transition) {}

    public function handle(Inspection $inspection, User $actor, string $reason): Inspection
    {
        return $this->transition->handle(
            $inspection,
            $actor,
            [InspectionStatus::Planned, InspectionStatus::InProgress, InspectionStatus::AwaitingReview, InspectionStatus::InCorrection, InspectionStatus::AwaitingApproval],
            InspectionStatus::Canceled,
            'canceled_at',
            function (Inspection $locked) use ($actor, $reason): void {
                if (blank(trim($reason))) {
                    throw ValidationException::withMessages(['reason' => 'A justificativa é obrigatória.']);
                }

                $isAssigned = $locked->responsibles()->where('user_id', $actor->getKey())->exists();
                if (! $actor->isCompanyAdmin() && ! $isAssigned) {
                    throw ValidationException::withMessages(['actor' => 'O usuário não pode cancelar esta inspeção.']);
                }
            },
            trim($reason),
        );
    }
}
