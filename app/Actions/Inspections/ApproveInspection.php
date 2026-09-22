<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Actions\Inspections\Concerns\ValidatesInspectionTransition;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionCorrectionRequestStatus;
use App\Enums\InspectionCorrectionRequestFlow;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Models\Inspection;
use App\Models\InspectionCorrectionRequest;
use App\Models\User;
use App\Services\Defects\AssessmentPhotoCoverageValidator;
use App\Services\Defects\ReinspectionCoverageValidator;
use Illuminate\Validation\ValidationException;

final class ApproveInspection
{
    use ValidatesInspectionTransition;

    public function __construct(
        private readonly TransitionInspection $transition,
        private readonly ReinspectionCoverageValidator $coverageValidator,
        private readonly AssessmentPhotoCoverageValidator $photoCoverageValidator,
    ) {}

    public function handle(Inspection $inspection, User $actor): Inspection
    {
        $this->validateTenant($inspection, $actor);
        if ($actor->operational_role !== OperationalRole::Reviewer || ! $inspection->hasAnyResponsibilityForUser($actor, ...InspectionResponsibility::cases())) {
            throw ValidationException::withMessages(['actor' => 'Somente o Revisor vinculado pode enviar a inspeção para liberação.']);
        }

        if ($inspection->status !== InspectionStatus::InReview) {
            throw ValidationException::withMessages([
                'status' => 'A inspeção não está em revisão.',
            ]);
        }

        $openReviewerRequests = InspectionCorrectionRequest::query()
            ->forOrganization($inspection->organization_id)
            ->where('inspection_id', $inspection->id)
            ->where('flow', InspectionCorrectionRequestFlow::ReviewerToInspector->value)
            ->whereIn('status', [
                InspectionCorrectionRequestStatus::Marked,
                InspectionCorrectionRequestStatus::Requested,
                InspectionCorrectionRequestStatus::Addressed,
            ])
            ->count();

        if ($openReviewerRequests > 0) {
            throw ValidationException::withMessages([
                'inspection' => sprintf('Existem %d solicitação(ões) ao Inspetor que precisam ser encerradas antes da liberação.', $openReviewerRequests),
            ]);
        }

        $pendingReleaseRequests = InspectionCorrectionRequest::query()
            ->forOrganization($inspection->organization_id)
            ->where('inspection_id', $inspection->id)
            ->where('flow', InspectionCorrectionRequestFlow::ReleaserToReviewer->value)
            ->whereIn('status', [
                InspectionCorrectionRequestStatus::Marked,
                InspectionCorrectionRequestStatus::Requested,
            ])
            ->count();

        if ($pendingReleaseRequests > 0) {
            throw ValidationException::withMessages([
                'inspection' => sprintf('Existem %d apontamento(s) do Liberador ainda sem resposta.', $pendingReleaseRequests),
            ]);
        }

        $this->coverageValidator->validate($inspection);
        $this->photoCoverageValidator->validate($inspection);

        return $this->transition->handle(
            $actor,
            $inspection,
            [InspectionStatus::InReview],
            InspectionStatus::AwaitingRelease,
            [
                'approved_at' => now(),
            ],
            'Inspeção enviada para liberação.',
        );
    }
}
