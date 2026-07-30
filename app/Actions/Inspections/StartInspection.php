<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Actions\Inspections\Concerns\ValidatesInspectionTransition;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Models\Inspection;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class StartInspection
{
    use ValidatesInspectionTransition;

    public function __construct(
        private readonly TransitionInspection $transition,
    ) {}

    public function handle(Inspection $inspection, User $actor): Inspection
    {
        $this->validateTenant($inspection, $actor);
        $this->ensureActorHasResponsibility($inspection, $actor, InspectionResponsibility::Inspector);

        if (! $inspection->equipment->canReceiveInspection()) {
            throw ValidationException::withMessages([
                'equipment' => 'O equipamento não pode iniciar uma inspeção neste momento.',
            ]);
        }

        $this->ensureResponsibilityPresent($inspection, InspectionResponsibility::Inspector);

        return $this->transition->handle(
            $actor,
            $inspection,
            [InspectionStatus::Planned],
            InspectionStatus::InProgress,
            [
                'started_at' => now(),
                'inspected_on' => $inspection->inspected_on ?? today(),
            ],
            'Inspeção iniciada.',
        );
    }
}
