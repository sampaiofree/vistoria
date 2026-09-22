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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReturnInspectionForCorrection
{
    use ValidatesInspectionTransition;

    public function __construct(
        private readonly TransitionInspection $transition,
    ) {}

    public function handle(Inspection $inspection, User $actor, ?string $reason): Inspection
    {
        $this->validateTenant($inspection, $actor);

        if ($inspection->status !== InspectionStatus::InReview) {
            throw ValidationException::withMessages([
                'status' => 'A inspeção não está em revisão.',
            ]);
        }
        if ($actor->operational_role !== OperationalRole::Reviewer || ! $inspection->hasAnyResponsibilityForUser($actor, ...InspectionResponsibility::cases())) {
            throw ValidationException::withMessages(['actor' => 'Somente o Revisor vinculado pode devolver a inspeção para correção.']);
        }

        return DB::transaction(function () use ($inspection, $actor, $reason): Inspection {
            $requests = InspectionCorrectionRequest::query()
                ->forOrganization($inspection->organization_id)
                ->with('assessment.defect')
                ->where('inspection_id', $inspection->id)
                ->where('flow', InspectionCorrectionRequestFlow::ReviewerToInspector->value)
                ->lockForUpdate()
                ->get();

            if ($requests->contains('status', InspectionCorrectionRequestStatus::Addressed)) {
                throw ValidationException::withMessages([
                    'inspection' => 'Confira e encerre ou substitua todas as solicitações atendidas antes de devolver a inspeção novamente.',
                ]);
            }

            $marked = $requests
                ->where('status', InspectionCorrectionRequestStatus::Marked)
                ->values();

            if ($marked->isEmpty() && blank($reason)) {
                throw ValidationException::withMessages([
                    'justification' => 'Informe uma mensagem geral ou marque ao menos uma avaria para correção.',
                ]);
            }

            $marked->each(fn (InspectionCorrectionRequest $request): bool => $request->update([
                'status' => InspectionCorrectionRequestStatus::Requested,
                'sent_by' => $actor->id,
                'sent_at' => now(),
                'updated_by' => $actor->id,
            ]));

            if (filled($reason)) {
                InspectionCorrectionRequest::query()->create([
                    'organization_id' => $inspection->organization_id,
                    'inspection_id' => $inspection->id,
                    'status' => InspectionCorrectionRequestStatus::Requested,
                    'flow' => InspectionCorrectionRequestFlow::ReviewerToInspector,
                    'request_message' => $reason,
                    'created_by' => $actor->id,
                    'sent_by' => $actor->id,
                    'sent_at' => now(),
                    'updated_by' => $actor->id,
                ]);
            }

            $summary = $reason;
            if (blank($summary)) {
                $codes = $marked
                    ->map(fn (InspectionCorrectionRequest $request): string => $request->assessment?->defect?->code ?? 'Avaria')
                    ->implode(', ');
                $summary = 'Correções solicitadas em: '.$codes.'.';
            }

            return $this->transition->handle(
                $actor,
                $inspection,
                [InspectionStatus::InReview],
                InspectionStatus::InCorrection,
                [],
                $summary,
            );
        });
    }
}
