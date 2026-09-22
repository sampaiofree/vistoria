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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReturnInspectionForReview
{
    use ValidatesInspectionTransition;

    public function __construct(private readonly TransitionInspection $transition) {}

    public function handle(Inspection $inspection, User $actor, ?string $reason): Inspection
    {
        $this->validateTenant($inspection, $actor);
        if ($actor->operational_role !== OperationalRole::Releaser || ! $inspection->hasAnyResponsibilityForUser($actor, ...InspectionResponsibility::cases())) {
            throw ValidationException::withMessages(['actor' => 'Somente o Liberador vinculado pode devolver a inspeção para revisão.']);
        }

        return DB::transaction(function () use ($inspection, $actor, $reason): Inspection {
            $requests = InspectionCorrectionRequest::query()
                ->forOrganization($inspection->organization_id)
                ->with('assessment.defect')
                ->where('inspection_id', $inspection->id)
                ->where('flow', InspectionCorrectionRequestFlow::ReleaserToReviewer->value)
                ->lockForUpdate()
                ->get();

            if ($requests->contains('status', InspectionCorrectionRequestStatus::Addressed)) {
                throw ValidationException::withMessages(['inspection' => 'Encerre ou substitua todos os apontamentos atendidos antes de devolver novamente para revisão.']);
            }

            $marked = $requests->where('status', InspectionCorrectionRequestStatus::Marked)->values();
            if ($marked->isEmpty() && blank($reason)) {
                throw ValidationException::withMessages(['justification' => 'Informe uma mensagem geral ou marque ao menos uma avaria para ajuste.']);
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
                    'flow' => InspectionCorrectionRequestFlow::ReleaserToReviewer,
                    'status' => InspectionCorrectionRequestStatus::Requested,
                    'request_message' => $reason,
                    'created_by' => $actor->id,
                    'sent_by' => $actor->id,
                    'sent_at' => now(),
                    'updated_by' => $actor->id,
                ]);
            }

            $summary = $reason ?: 'Ajustes solicitados em: '.$marked
                ->map(fn (InspectionCorrectionRequest $request): string => $request->assessment?->defect?->code ?? 'Relatório')
                ->implode(', ').'.';

            return $this->transition->handle(
                $actor,
                $inspection,
                [InspectionStatus::AwaitingRelease],
                InspectionStatus::AwaitingReview,
                ['approved_at' => null],
                $summary,
            );
        });
    }
}
