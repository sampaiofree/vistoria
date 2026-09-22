<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Inspections\ManageInspectionCorrectionRequest;
use App\Http\Controllers\Concerns\ResolvesTenantStructure;
use App\Http\Requests\Inspections\AddressCorrectionRequestRequest;
use App\Http\Requests\Inspections\CorrectionRequestMessageRequest;
use App\Http\Requests\Inspections\CreateChildCorrectionRequest;
use App\Models\DefectAssessment;
use App\Models\InspectionCorrectionRequest;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class InspectionCorrectionRequestController extends Controller
{
    use ResolvesTenantStructure;

    public function store(
        CorrectionRequestMessageRequest $request,
        TenantContext $tenant,
        DefectAssessment $defectAssessment,
        ManageInspectionCorrectionRequest $action,
    ): RedirectResponse {
        $assessment = $this->tenantDefectAssessment($tenant, $defectAssessment);
        $assessment->loadMissing('inspection');
        $this->authorize('createCorrectionRequests', $assessment->inspection);

        $action->create($request->user(), $assessment, $request->validated('request_message'));

        return back()->with('success', 'Avaria marcada para ajuste.');
    }

    public function update(
        CorrectionRequestMessageRequest $request,
        TenantContext $tenant,
        InspectionCorrectionRequest $correctionRequest,
        ManageInspectionCorrectionRequest $action,
    ): RedirectResponse {
        $correctionRequest = $this->requestForTenant($tenant, $correctionRequest);
        $this->authorize('update', $correctionRequest);

        $action->update($request->user(), $correctionRequest, $request->validated('request_message'));

        return back()->with('success', 'Solicitação de correção atualizada.');
    }

    public function destroy(
        Request $request,
        TenantContext $tenant,
        InspectionCorrectionRequest $correctionRequest,
        ManageInspectionCorrectionRequest $action,
    ): RedirectResponse {
        $correctionRequest = $this->requestForTenant($tenant, $correctionRequest);
        $this->authorize('delete', $correctionRequest);

        $action->delete($request->user(), $correctionRequest);

        return back()->with('success', 'Solicitação de correção removida.');
    }

    public function address(
        AddressCorrectionRequestRequest $request,
        TenantContext $tenant,
        InspectionCorrectionRequest $correctionRequest,
        ManageInspectionCorrectionRequest $action,
    ): RedirectResponse {
        $correctionRequest = $this->requestForTenant($tenant, $correctionRequest);
        $this->authorize('address', $correctionRequest);

        $action->address($request->user(), $correctionRequest, $request->validated('response_message'));

        return back()->with('success', 'Solicitação marcada como atendida.');
    }

    public function markPending(
        Request $request,
        TenantContext $tenant,
        InspectionCorrectionRequest $correctionRequest,
        ManageInspectionCorrectionRequest $action,
    ): RedirectResponse {
        $correctionRequest = $this->requestForTenant($tenant, $correctionRequest);
        $this->authorize('markPending', $correctionRequest);

        $action->markPending($request->user(), $correctionRequest);

        return back()->with('success', 'Solicitação voltou para pendente.');
    }

    public function close(
        Request $request,
        TenantContext $tenant,
        InspectionCorrectionRequest $correctionRequest,
        ManageInspectionCorrectionRequest $action,
    ): RedirectResponse {
        $correctionRequest = $this->requestForTenant($tenant, $correctionRequest);
        $this->authorize('close', $correctionRequest);

        $action->close($request->user(), $correctionRequest);

        return back()->with('success', 'Solicitação encerrada.');
    }

    public function replace(
        CorrectionRequestMessageRequest $request,
        TenantContext $tenant,
        InspectionCorrectionRequest $correctionRequest,
        ManageInspectionCorrectionRequest $action,
    ): RedirectResponse {
        $correctionRequest = $this->requestForTenant($tenant, $correctionRequest);
        $this->authorize('replace', $correctionRequest);

        $action->replace($request->user(), $correctionRequest, $request->validated('request_message'));

        return back()->with('success', 'Novo ajuste solicitado para a avaria.');
    }

    public function storeChild(
        CreateChildCorrectionRequest $request,
        TenantContext $tenant,
        InspectionCorrectionRequest $correctionRequest,
        ManageInspectionCorrectionRequest $action,
    ): RedirectResponse {
        $parent = $this->requestForTenant($tenant, $correctionRequest);
        $this->authorize('createChild', $parent);

        $assessment = $request->validated('defect_assessment_id') === null
            ? null
            : $this->tenantDefectAssessment($tenant, DefectAssessment::query()->findOrFail($request->validated('defect_assessment_id')));

        $action->createChild($request->user(), $parent, $request->validated('request_message'), $assessment);

        return back()->with('success', 'Ajuste encaminhado ao Inspetor.');
    }

    private function requestForTenant(TenantContext $tenant, InspectionCorrectionRequest $request): InspectionCorrectionRequest
    {
        return InspectionCorrectionRequest::query()
            ->forOrganization($tenant->id())
            ->with(['inspection.responsibles', 'assessment', 'parentRequest', 'children'])
            ->whereKey($request->id)
            ->firstOrFail();
    }
}
