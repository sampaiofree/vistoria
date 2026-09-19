<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Actions\Inspections\Concerns\ValidatesInspectionTransition;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Models\Inspection;
use App\Models\User;
use App\Services\Defects\AssessmentPhotoCoverageValidator;
use App\Services\Defects\ReinspectionCoverageValidator;
use Illuminate\Validation\ValidationException;

final class SubmitInspectionForReview
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

        $this->coverageValidator->validate($inspection);
        $this->photoCoverageValidator->validate($inspection);

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
