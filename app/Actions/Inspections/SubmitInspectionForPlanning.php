<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Actions\Inspections\Concerns\ValidatesInspectionTransition;
use App\Enums\InspectionCorrectionRequestFlow;
use App\Enums\InspectionCorrectionRequestStatus;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Models\Inspection;
use App\Models\InspectionCorrectionRequest;
use App\Models\User;
use App\Services\Defects\AssessmentPhotoCoverageValidator;
use App\Services\Defects\ReinspectionCoverageValidator;
use App\Services\Inspections\GeneralAspectsCoverageValidator;
use App\Services\Inspections\InspectionOverviewCoverageValidator;
use App\Services\Inspections\InspectionTechnicalReferencesCoverageValidator;
use App\Services\Inspections\ReportEquipmentCoverageValidator;
use Illuminate\Validation\ValidationException;

final class SubmitInspectionForPlanning
{
    use ValidatesInspectionTransition;

    public function __construct(
        private readonly TransitionInspection $transition,
        private readonly ReinspectionCoverageValidator $coverageValidator,
        private readonly AssessmentPhotoCoverageValidator $photoCoverageValidator,
        private readonly InspectionOverviewCoverageValidator $overviewCoverageValidator,
        private readonly GeneralAspectsCoverageValidator $generalAspectsCoverageValidator,
    ) {}

    public function handle(Inspection $inspection, User $actor): Inspection
    {
        return $this->withLockedInspection($inspection, $actor, 'submitForPlanning',
            fn (Inspection $locked): Inspection => $this->perform($locked, $actor));
    }

    private function perform(Inspection $inspection, User $actor): Inspection
    {
        $this->validateTenant($inspection, $actor);
        if (in_array($inspection->status, [InspectionStatus::InProgress, InspectionStatus::InCorrection], true)) {
            if ($actor->operational_role !== OperationalRole::Inspector
                || ! $inspection->hasAnyResponsibilityForUser($actor, ...InspectionResponsibility::cases())) {
                throw ValidationException::withMessages([
                    'actor' => 'Somente o Inspetor vinculado pode enviar a inspeção para preenchimento de notas.',
                ]);
            }
        }

        if (! in_array($inspection->status, [
            InspectionStatus::InProgress,
            InspectionStatus::InCorrection,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'A inspeção não está pronta para envio ao planejador.',
            ]);
        }

        if ($inspection->status === InspectionStatus::InCorrection) {
            $pending = InspectionCorrectionRequest::query()
                ->forOrganization($inspection->organization_id)
                ->where('inspection_id', $inspection->id)
                ->whereIn('flow', [InspectionCorrectionRequestFlow::ReviewerToInspector->value, InspectionCorrectionRequestFlow::PlannerToInspector->value])
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
        app(InspectionTechnicalReferencesCoverageValidator::class)->validate($inspection);
        app(ReportEquipmentCoverageValidator::class)->validate($inspection);

        $attributes = [];

        if ($inspection->inspected_on === null) {
            $attributes['inspected_on'] = today();
        }

        if ($inspection->status === InspectionStatus::InProgress && $inspection->field_completed_at === null) {
            $attributes['field_completed_at'] = now();
        }

        return $this->transition->handle(
            $actor,
            $inspection,
            [InspectionStatus::InProgress, InspectionStatus::InCorrection],
            InspectionStatus::AwaitingM2,
            $attributes,
            $inspection->status === InspectionStatus::InCorrection
                ? 'Inspeção reenviada para preenchimento de notas.'
                : 'Inspeção enviada para preenchimento de notas.',
        );
    }
}
