<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Actions\Inspections\Concerns\ValidatesInspectionTransition;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
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

        if ($inspection->status !== InspectionStatus::InReview) {
            throw ValidationException::withMessages([
                'status' => 'A inspeção não está em revisão.',
            ]);
        }
        if ($actor->operational_role !== OperationalRole::Reviewer || ! $inspection->hasAnyResponsibilityForUser($actor, ...\App\Enums\InspectionResponsibility::cases())) {
            throw ValidationException::withMessages(['actor' => 'Somente o Revisor vinculado pode devolver a inspeção para correção.']);
        }

        return $this->transition->handle(
            $actor,
            $inspection,
            [InspectionStatus::InReview],
            InspectionStatus::InCorrection,
            [],
            $reason,
        );
    }
}
