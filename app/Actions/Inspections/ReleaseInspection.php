<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Actions\Inspections\Concerns\ValidatesInspectionTransition;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Models\Inspection;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class ReleaseInspection
{
    use ValidatesInspectionTransition;

    public function __construct(
        private readonly TransitionInspection $transition,
    ) {}

    public function handle(Inspection $inspection, User $actor): Inspection
    {
        $this->validateTenant($inspection, $actor);
        if ($actor->operational_role !== OperationalRole::Releaser || ! $inspection->hasAnyResponsibilityForUser($actor, ...\App\Enums\InspectionResponsibility::cases())) {
            throw ValidationException::withMessages(['actor' => 'Somente o Liberador vinculado pode liberar a inspeção.']);
        }

        if ($inspection->status !== InspectionStatus::AwaitingRelease) {
            throw ValidationException::withMessages([
                'status' => 'A inspeção não está aguardando liberação.',
            ]);
        }

        return $this->transition->handle(
            $actor,
            $inspection,
            [InspectionStatus::AwaitingRelease],
            InspectionStatus::Released,
            [
                'released_at' => now(),
            ],
            'Inspeção liberada.',
        );
    }
}
