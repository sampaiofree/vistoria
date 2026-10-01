<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Actions\Inspections\Concerns\ValidatesInspectionTransition;
use App\Enums\InspectionCorrectionRequestFlow;
use App\Enums\InspectionCorrectionRequestStatus;
use App\Enums\InspectionStatus;
use App\Models\Inspection;
use App\Models\InspectionCorrectionRequest;
use App\Models\User;
use App\Services\Defects\AssessmentPhotoCoverageValidator;
use App\Services\Defects\ReinspectionCoverageValidator;
use App\Services\Inspections\GeneralAspectsCoverageValidator;
use App\Services\Inspections\InspectionClassificationM2CoverageValidator;
use App\Services\Inspections\InspectionOverviewCoverageValidator;
use App\Services\Inspections\InspectionTechnicalReferencesCoverageValidator;
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
        return $this->withLockedInspection($inspection, $actor, 'submitForReview',
            fn (Inspection $locked): Inspection => $this->perform($locked, $actor));
    }

    private function perform(Inspection $inspection, User $actor): Inspection
    {
        $this->validateTenant($inspection, $actor);
        if ($inspection->status !== InspectionStatus::AwaitingM2 || ! $actor->can('submitForReview', $inspection)) {
            throw ValidationException::withMessages(['status' => 'Somente o Planejador vinculado pode encaminhar as notas para revisão nesta etapa.']);
        }

        $pending = InspectionCorrectionRequest::query()
            ->where('inspection_id', $inspection->id)
            ->where('flow', InspectionCorrectionRequestFlow::PlannerToInspector->value)
            ->whereIn('status', [InspectionCorrectionRequestStatus::Marked, InspectionCorrectionRequestStatus::Requested, InspectionCorrectionRequestStatus::Addressed])
            ->count();
        if ($pending > 0) {
            throw ValidationException::withMessages(['inspection' => 'Confira e encerre os apontamentos do Planejador antes de enviar para revisão.']);
        }

        $this->coverageValidator->validate($inspection);
        $this->photoCoverageValidator->validate($inspection);
        $this->overviewCoverageValidator->validate($inspection);
        $this->generalAspectsCoverageValidator->validate($inspection);
        $this->classificationM2CoverageValidator->validate($inspection);
        app(InspectionTechnicalReferencesCoverageValidator::class)->validate($inspection);

        return $this->transition->handle(
            $actor, $inspection, [InspectionStatus::AwaitingM2], InspectionStatus::AwaitingReview,
            [], 'Notas conferidas pelo Planejador. Inspeção enviada para revisão.',
        );
    }
}
