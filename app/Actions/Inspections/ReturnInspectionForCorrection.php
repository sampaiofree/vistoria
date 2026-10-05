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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReturnInspectionForCorrection
{
    use ValidatesInspectionTransition;

    public function __construct(
        private readonly TransitionInspection $transition,
    ) {}

    public function handle(Inspection $inspection, User $actor, ?string $reason, string $target = 'inspector'): Inspection
    {
        return $this->withLockedInspection($inspection, $actor, 'returnForCorrection',
            fn (Inspection $locked): Inspection => $this->perform($locked, $actor, $reason, $target));
    }

    private function perform(Inspection $inspection, User $actor, ?string $reason, string $target): Inspection
    {
        $this->validateTenant($inspection, $actor);

        if (! in_array($inspection->status, [InspectionStatus::AwaitingM2, InspectionStatus::InReview], true)) {
            throw ValidationException::withMessages([
                'status' => 'A inspeção não está em revisão.',
            ]);
        }
        if (! $actor->can('returnForCorrection', $inspection)) {
            throw ValidationException::withMessages(['actor' => 'Somente o responsável pela etapa pode devolver a inspeção para correção.']);
        }
        if (! in_array($target, ['inspector', 'planner'], true)
            || ($target === 'planner' && $inspection->status !== InspectionStatus::InReview)) {
            throw ValidationException::withMessages(['correction_target' => 'Selecione um destino válido para esta etapa.']);
        }

        $flow = $inspection->status === InspectionStatus::AwaitingM2
            ? InspectionCorrectionRequestFlow::PlannerToInspector
            : ($target === 'planner'
                ? InspectionCorrectionRequestFlow::ReviewerToPlanner
                : InspectionCorrectionRequestFlow::ReviewerToInspector);
        $reviewerFlow = $inspection->status === InspectionStatus::InReview;

        return DB::transaction(function () use ($inspection, $actor, $reason, $flow, $reviewerFlow): Inspection {
            $requests = InspectionCorrectionRequest::query()
                ->forOrganization($inspection->organization_id)
                ->with('assessment.defect')
                ->where('inspection_id', $inspection->id)
                ->whereIn('flow', $reviewerFlow
                    ? [InspectionCorrectionRequestFlow::ReviewerToInspector->value, InspectionCorrectionRequestFlow::ReviewerToPlanner->value]
                    : [$flow->value])
                ->lockForUpdate()
                ->get();

            if ($requests->contains('status', InspectionCorrectionRequestStatus::Addressed)) {
                throw ValidationException::withMessages([
                    'inspection' => 'Confira e encerre ou substitua todas as solicitações atendidas antes de devolver a inspeção novamente.',
                ]);
            }
            if ($requests->contains('status', InspectionCorrectionRequestStatus::Requested)) {
                throw ValidationException::withMessages([
                    'inspection' => 'Aguarde a resposta das solicitações já enviadas antes de devolver a inspeção novamente.',
                ]);
            }
            if ($requests->contains(fn (InspectionCorrectionRequest $request): bool => $request->status === InspectionCorrectionRequestStatus::Marked
                && $request->flow !== $flow)) {
                throw ValidationException::withMessages([
                    'correction_target' => 'Existem solicitações marcadas para outro responsável. Envie ao responsável indicado ou remova as marcações.',
                ]);
            }

            $marked = $requests
                ->where('flow', $flow)
                ->where('status', InspectionCorrectionRequestStatus::Marked)
                ->values();
            if ($flow === InspectionCorrectionRequestFlow::ReviewerToPlanner
                && $marked->contains(fn (InspectionCorrectionRequest $request): bool => $request->defect_assessment_id !== null)) {
                throw ValidationException::withMessages([
                    'correction_target' => 'Apontamentos de avarias devem ser enviados ao Inspetor.',
                ]);
            }

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
                    'flow' => $flow,
                    'request_message' => $reason,
                    'created_by' => $actor->id,
                    'sent_by' => $actor->id,
                    'sent_at' => now(),
                    'updated_by' => $actor->id,
                ]);
            }

            $summary = $reason;
            if (blank($summary)) {
                if ($flow === InspectionCorrectionRequestFlow::ReviewerToPlanner) {
                    $summary = $marked->pluck('request_message')->implode(' ');
                } else {
                    $codes = $marked
                        ->map(fn (InspectionCorrectionRequest $request): string => $request->assessment?->defect?->code ?? 'Avaria')
                        ->implode(', ');
                    $summary = 'Correções solicitadas em: '.$codes.'.';
                }
            }

            return $this->transition->handle(
                $actor,
                $inspection,
                [$inspection->status],
                $flow === InspectionCorrectionRequestFlow::ReviewerToPlanner
                    ? InspectionStatus::AwaitingM2
                    : InspectionStatus::InCorrection,
                [],
                $summary,
            );
        });
    }
}
