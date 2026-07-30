<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Inspections\ApproveInspection;
use App\Actions\Inspections\CancelInspection;
use App\Actions\Inspections\CompleteInspectionReview;
use App\Actions\Inspections\ReleaseInspection;
use App\Actions\Inspections\ReturnInspectionForCorrection;
use App\Actions\Inspections\StartInspection;
use App\Actions\Inspections\SubmitInspectionForReview;
use App\Http\Controllers\Concerns\ResolvesTenantStructure;
use App\Http\Requests\Inspections\ApproveInspectionRequest;
use App\Http\Requests\Inspections\CancelInspectionRequest;
use App\Http\Requests\Inspections\CompleteInspectionReviewRequest;
use App\Http\Requests\Inspections\ReleaseInspectionRequest;
use App\Http\Requests\Inspections\ReturnInspectionForCorrectionRequest;
use App\Http\Requests\Inspections\StartInspectionRequest;
use App\Http\Requests\Inspections\SubmitInspectionForReviewRequest;
use App\Models\Inspection;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;

final class InspectionTransitionController extends Controller
{
    use ResolvesTenantStructure;

    public function start(
        StartInspectionRequest $request,
        TenantContext $tenant,
        Inspection $inspection,
        StartInspection $action,
    ): RedirectResponse {
        $inspection = $this->tenantInspection($tenant, $inspection);

        $action->handle($inspection, $request->user());

        return back()->with('success', 'Inspeção iniciada.');
    }

    public function submitForReview(
        SubmitInspectionForReviewRequest $request,
        TenantContext $tenant,
        Inspection $inspection,
        SubmitInspectionForReview $action,
    ): RedirectResponse {
        $inspection = $this->tenantInspection($tenant, $inspection);

        $action->handle($inspection, $request->user());

        return back()->with('success', 'Inspeção enviada para revisão.');
    }

    public function returnForCorrection(
        ReturnInspectionForCorrectionRequest $request,
        TenantContext $tenant,
        Inspection $inspection,
        ReturnInspectionForCorrection $action,
    ): RedirectResponse {
        $inspection = $this->tenantInspection($tenant, $inspection);

        $action->handle(
            $inspection,
            $request->user(),
            $request->validated('justification'),
        );

        return back()->with('success', 'Inspeção devolvida para correção.');
    }

    public function completeReview(
        CompleteInspectionReviewRequest $request,
        TenantContext $tenant,
        Inspection $inspection,
        CompleteInspectionReview $action,
    ): RedirectResponse {
        $inspection = $this->tenantInspection($tenant, $inspection);

        $action->handle($inspection, $request->user());

        return back()->with('success', 'Revisão concluída.');
    }

    public function approve(
        ApproveInspectionRequest $request,
        TenantContext $tenant,
        Inspection $inspection,
        ApproveInspection $action,
    ): RedirectResponse {
        $inspection = $this->tenantInspection($tenant, $inspection);

        $action->handle($inspection, $request->user());

        return back()->with('success', 'Inspeção aprovada.');
    }

    public function release(
        ReleaseInspectionRequest $request,
        TenantContext $tenant,
        Inspection $inspection,
        ReleaseInspection $action,
    ): RedirectResponse {
        $inspection = $this->tenantInspection($tenant, $inspection);

        $action->handle($inspection, $request->user());

        return back()->with('success', 'Inspeção liberada.');
    }

    public function cancel(
        CancelInspectionRequest $request,
        TenantContext $tenant,
        Inspection $inspection,
        CancelInspection $action,
    ): RedirectResponse {
        $inspection = $this->tenantInspection($tenant, $inspection);

        $action->handle(
            $inspection,
            $request->user(),
            $request->validated('justification'),
        );

        return back()->with('success', 'Inspeção cancelada.');
    }
}
