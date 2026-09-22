<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Enums\DefectAssessmentStatus;
use App\Enums\InspectionCorrectionRequestFlow;
use App\Enums\InspectionCorrectionRequestStatus;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Models\DefectAssessment;
use App\Models\Inspection;
use App\Models\InspectionCorrectionRequest;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManageInspectionCorrectionRequest
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function create(User $actor, DefectAssessment $assessment, string $message): InspectionCorrectionRequest
    {
        return DB::transaction(function () use ($actor, $assessment, $message): InspectionCorrectionRequest {
            $assessment = $this->assessment($assessment);
            $inspection = $assessment->inspection;
            $flow = $this->rootFlow($actor, $inspection);

            $this->ensureNoOpenRequest($inspection, $flow, null, $assessment->id);

            return InspectionCorrectionRequest::query()->create([
                'organization_id' => $this->tenant->id(),
                'inspection_id' => $inspection->id,
                'defect_assessment_id' => $assessment->id,
                'flow' => $flow,
                'status' => InspectionCorrectionRequestStatus::Marked,
                'request_message' => $message,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        });
    }

    public function createChild(User $actor, InspectionCorrectionRequest $parent, string $message, ?DefectAssessment $assessment = null): InspectionCorrectionRequest
    {
        return DB::transaction(function () use ($actor, $parent, $message, $assessment): InspectionCorrectionRequest {
            $parent = $this->request($parent, ['inspection.responsibles']);

            if (! $actor->can('createChild', $parent)) {
                throw ValidationException::withMessages(['request' => 'Somente o Revisor vinculado pode desdobrar este apontamento para o Inspetor.']);
            }

            $assessment = $assessment === null ? null : $this->assessment($assessment);
            $assessmentId = $assessment?->id ?? $parent->defect_assessment_id;
            if ($assessment !== null && $assessment->inspection_id !== $parent->inspection_id) {
                throw ValidationException::withMessages(['defect_assessment_id' => 'A avaria deve pertencer à mesma inspeção.']);
            }

            $this->ensureNoOpenRequest($parent->inspection, InspectionCorrectionRequestFlow::ReviewerToInspector, $parent->id, $assessmentId);

            return InspectionCorrectionRequest::query()->create([
                'organization_id' => $this->tenant->id(),
                'inspection_id' => $parent->inspection_id,
                'defect_assessment_id' => $assessmentId,
                'parent_request_id' => $parent->id,
                'flow' => InspectionCorrectionRequestFlow::ReviewerToInspector,
                'status' => InspectionCorrectionRequestStatus::Marked,
                'request_message' => $message,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        });
    }

    public function update(User $actor, InspectionCorrectionRequest $request, string $message): InspectionCorrectionRequest
    {
        return DB::transaction(function () use ($actor, $request, $message): InspectionCorrectionRequest {
            $request = $this->request($request);
            $this->authorize($actor, 'update', $request, 'Somente o solicitante vinculado pode alterar este apontamento.');
            $request->update(['request_message' => $message, 'updated_by' => $actor->id]);

            return $request->refresh();
        });
    }

    public function delete(User $actor, InspectionCorrectionRequest $request): void
    {
        DB::transaction(function () use ($actor, $request): void {
            $request = $this->request($request);
            $this->authorize($actor, 'delete', $request, 'Somente o solicitante vinculado pode remover este apontamento.');
            $request->delete();
        });
    }

    public function address(User $actor, InspectionCorrectionRequest $request, ?string $response): InspectionCorrectionRequest
    {
        return DB::transaction(function () use ($actor, $request, $response): InspectionCorrectionRequest {
            $request = $this->request($request);
            $this->authorize($actor, 'address', $request, 'Somente o responsável pelo ajuste pode responder este apontamento.');

            if ($request->flow === InspectionCorrectionRequestFlow::ReviewerToInspector
                && $request->defect_assessment_id !== null
                && $request->assessment?->status !== DefectAssessmentStatus::Complete) {
                throw ValidationException::withMessages(['request' => 'Publique a avaliação da avaria antes de informar que a correção foi atendida.']);
            }

            if ($request->flow === InspectionCorrectionRequestFlow::ReleaserToReviewer && $this->hasOpenChildren($request)) {
                throw ValidationException::withMessages(['request' => 'Encerre ou substitua todos os ajustes enviados ao Inspetor antes de responder ao Liberador.']);
            }

            $request->update([
                'status' => InspectionCorrectionRequestStatus::Addressed,
                'response_message' => $response,
                'addressed_by' => $actor->id,
                'addressed_at' => now(),
                'updated_by' => $actor->id,
            ]);

            return $request->refresh();
        });
    }

    public function markPending(User $actor, InspectionCorrectionRequest $request): InspectionCorrectionRequest
    {
        return DB::transaction(function () use ($actor, $request): InspectionCorrectionRequest {
            $request = $this->request($request);
            $this->authorize($actor, 'markPending', $request, 'Somente o responsável pelo ajuste pode reabrir este apontamento.');
            $request->update([
                'status' => InspectionCorrectionRequestStatus::Requested,
                'addressed_by' => null,
                'addressed_at' => null,
                'updated_by' => $actor->id,
            ]);

            return $request->refresh();
        });
    }

    public function close(User $actor, InspectionCorrectionRequest $request): InspectionCorrectionRequest
    {
        return DB::transaction(function () use ($actor, $request): InspectionCorrectionRequest {
            $request = $this->request($request);
            $this->authorize($actor, 'close', $request, 'Somente o solicitante vinculado pode encerrar este apontamento.');
            $request->update([
                'status' => InspectionCorrectionRequestStatus::Closed,
                'closed_by' => $actor->id,
                'closed_at' => now(),
                'updated_by' => $actor->id,
            ]);

            return $request->refresh();
        });
    }

    public function replace(User $actor, InspectionCorrectionRequest $request, string $message): InspectionCorrectionRequest
    {
        return DB::transaction(function () use ($actor, $request, $message): InspectionCorrectionRequest {
            $request = $this->request($request);
            $this->authorize($actor, 'replace', $request, 'Somente o solicitante vinculado pode pedir novo ajuste.');
            $request->update([
                'status' => InspectionCorrectionRequestStatus::Superseded,
                'closed_by' => $actor->id,
                'closed_at' => now(),
                'updated_by' => $actor->id,
            ]);

            return InspectionCorrectionRequest::query()->create([
                'organization_id' => $this->tenant->id(),
                'inspection_id' => $request->inspection_id,
                'defect_assessment_id' => $request->defect_assessment_id,
                'parent_request_id' => $request->parent_request_id,
                'previous_request_id' => $request->id,
                'flow' => $request->flow,
                'status' => InspectionCorrectionRequestStatus::Marked,
                'request_message' => $message,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        });
    }

    private function rootFlow(User $actor, Inspection $inspection): InspectionCorrectionRequestFlow
    {
        if (! $actor->can('createCorrectionRequests', $inspection)) {
            throw ValidationException::withMessages(['request' => 'Você não pode criar apontamentos nesta etapa da inspeção.']);
        }

        return match ([$inspection->status, $actor->operational_role]) {
            [InspectionStatus::InReview, OperationalRole::Reviewer] => InspectionCorrectionRequestFlow::ReviewerToInspector,
            [InspectionStatus::AwaitingRelease, OperationalRole::Releaser] => InspectionCorrectionRequestFlow::ReleaserToReviewer,
            default => throw ValidationException::withMessages(['request' => 'O fluxo do apontamento não é válido nesta etapa.']),
        };
    }

    private function ensureNoOpenRequest(Inspection $inspection, InspectionCorrectionRequestFlow $flow, ?int $parentId, ?int $assessmentId): void
    {
        $query = InspectionCorrectionRequest::query()
            ->forOrganization($this->tenant->id())
            ->where('inspection_id', $inspection->id)
            ->where('flow', $flow->value)
            ->whereIn('status', [
                InspectionCorrectionRequestStatus::Marked,
                InspectionCorrectionRequestStatus::Requested,
                InspectionCorrectionRequestStatus::Addressed,
            ])
            ->lockForUpdate();

        $parentId === null ? $query->whereNull('parent_request_id') : $query->where('parent_request_id', $parentId);
        $assessmentId === null ? $query->whereNull('defect_assessment_id') : $query->where('defect_assessment_id', $assessmentId);

        if ($query->exists()) {
            throw ValidationException::withMessages(['request' => 'Já existe uma solicitação de correção em aberto para este contexto.']);
        }
    }

    private function hasOpenChildren(InspectionCorrectionRequest $request): bool
    {
        return InspectionCorrectionRequest::query()
            ->forOrganization($this->tenant->id())
            ->where('parent_request_id', $request->id)
            ->whereIn('status', [
                InspectionCorrectionRequestStatus::Marked,
                InspectionCorrectionRequestStatus::Requested,
                InspectionCorrectionRequestStatus::Addressed,
            ])
            ->lockForUpdate()
            ->exists();
    }

    /** @param array<int, string> $with */
    private function request(InspectionCorrectionRequest $request, array $with = []): InspectionCorrectionRequest
    {
        return InspectionCorrectionRequest::query()
            ->forOrganization($this->tenant->id())
            ->with(array_merge(['inspection.responsibles', 'assessment', 'children'], $with))
            ->lockForUpdate()
            ->findOrFail($request->id);
    }

    private function assessment(DefectAssessment $assessment): DefectAssessment
    {
        return DefectAssessment::query()
            ->forOrganization($this->tenant->id())
            ->with(['inspection.responsibles'])
            ->lockForUpdate()
            ->findOrFail($assessment->id);
    }

    private function authorize(User $actor, string $ability, InspectionCorrectionRequest $request, string $message): void
    {
        if (! $actor->can($ability, $request)) {
            throw ValidationException::withMessages(['request' => $message]);
        }
    }
}
