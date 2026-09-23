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
use App\Services\Defects\AssessmentPhotoCoverageValidator;
use App\Services\Defects\ReinspectionCoverageValidator;
use App\Services\Inspections\GeneralAspectsCoverageValidator;
use App\Services\Inspections\InspectionClassificationM2CoverageValidator;
use App\Services\Inspections\InspectionOverviewCoverageValidator;
use Illuminate\Validation\ValidationException;

final class SubmitInspectionForReview
{
    use ValidatesInspectionTransition;

    public function __construct(
        private readonly TransitionInspection $transition,
        private readonly ReinspectionCoverageValidator $coverageValidator,
        private readonly AssessmentPhotoCoverageValidator $photoCoverageValidator,
        private readonly InspectionOverviewCoverageValidator $overviewCoverageValidator,
        private readonly GeneralAspectsCoverageValidator $generalAspectsCoverageValidator,
        private readonly InspectionClassificationM2CoverageValidator $classificationM2CoverageValidator,
    ) {}

    public function handle(Inspection $inspection, User $actor): Inspection
    {
        $this->validateTenant($inspection, $actor);
        if (in_array($inspection->status, [InspectionStatus::InProgress, InspectionStatus::InCorrection], true)) {
            if ($actor->operational_role !== OperationalRole::Inspector
                || ! $inspection->hasAnyResponsibilityForUser($actor, ...InspectionResponsibility::cases())) {
                throw ValidationException::withMessages([
                    'actor' => 'Somente o Inspetor vinculado pode enviar a inspeção para revisão.',
                ]);
            }
        }

        if (! in_array($inspection->status, [
            InspectionStatus::InProgress,
            InspectionStatus::InCorrection,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'A inspeção não está pronta para envio à revisão.',
            ]);
        }

        if ($inspection->status === InspectionStatus::InCorrection) {
            $pending = InspectionCorrectionRequest::query()
                ->forOrganization($inspection->organization_id)
                ->where('inspection_id', $inspection->id)
                ->where('flow', InspectionCorrectionRequestFlow::ReviewerToInspector->value)
                ->where('status', InspectionCorrectionRequestStatus::Requested)
                ->count();

            if ($pending > 0) {
                throw ValidationException::withMessages([
                    'inspection' => sprintf('Existem %d solicitação(ões) de correção ainda não atendida(s).', $pending),
                ]);
            }
        }

        $this->coverageValidator->validate($inspection);
        $this->photoCoverageValidator->validate($inspection);
        $this->overviewCoverageValidator->validate($inspection);
        $this->generalAspectsCoverageValidator->validate($inspection);
        $this->classificationM2CoverageValidator->validate($inspection);

        $attributes = [];

        if ($inspection->status === InspectionStatus::InProgress && $inspection->field_completed_at === null) {
            $attributes['field_completed_at'] = now();
        }

        return $this->transition->handle(
            $actor,
            $inspection,
            [InspectionStatus::InProgress, InspectionStatus::InCorrection],
            InspectionStatus::AwaitingReview,
            $attributes,
            $inspection->status === InspectionStatus::InCorrection
                ? 'Inspeção reenviada para revisão.'
                : 'Inspeção enviada para revisão.',
        );
    }
}
