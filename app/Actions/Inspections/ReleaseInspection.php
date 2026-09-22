<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Actions\Inspections\Concerns\ValidatesInspectionTransition;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionCorrectionRequestFlow;
use App\Enums\InspectionCorrectionRequestStatus;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Models\Inspection;
use App\Models\InspectionCorrectionRequest;
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
        if ($actor->operational_role !== OperationalRole::Releaser || ! $inspection->hasAnyResponsibilityForUser($actor, ...InspectionResponsibility::cases())) {
            throw ValidationException::withMessages(['actor' => 'Somente o Liberador vinculado pode liberar a inspeção.']);
        }

        if ($inspection->status !== InspectionStatus::AwaitingRelease) {
            throw ValidationException::withMessages([
                'status' => 'A inspeção não está aguardando liberação.',
            ]);
        }

        $openRequests = InspectionCorrectionRequest::query()
            ->forOrganization($inspection->organization_id)
            ->where('inspection_id', $inspection->id)
            ->where('flow', InspectionCorrectionRequestFlow::ReleaserToReviewer->value)
            ->whereIn('status', [
                InspectionCorrectionRequestStatus::Marked,
                InspectionCorrectionRequestStatus::Requested,
                InspectionCorrectionRequestStatus::Addressed,
            ])
            ->count();

        if ($openRequests > 0) {
            throw ValidationException::withMessages([
                'inspection' => sprintf('Existem %d apontamento(s) do Liberador que precisam ser encerrados antes da liberação.', $openRequests),
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
