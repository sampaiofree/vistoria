<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Settings\ReportResponsiblesRequest;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class ReportResponsiblesController extends Controller
{
    public function edit(TenantContext $tenant): Response
    {
        $this->authorize('update', $tenant->organization());

        return Inertia::render('Settings/InspectionReports/Responsibles', [
            'names' => $tenant->organization()->only(['report_reviewer_name', 'report_releaser_name']),
            'action' => route('settings.inspection-report.responsibles.update'),
        ]);
    }

    public function update(ReportResponsiblesRequest $request, TenantContext $tenant): RedirectResponse
    {
        $tenant->organization()->update($request->validated());

        return redirect()->route('settings.inspection-report.responsibles.edit')
            ->with('success', 'Responsáveis do relatório atualizados.');
    }
}
