<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\InspectionOverview\UpdateInspectionOverviewBlock;
use App\Http\Requests\InspectionOverview\UpdateInspectionOverviewBlockRequest;
use App\Models\Inspection;
use App\Services\Reports\InspectionOverviewPresenter;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class InspectionOverviewController extends Controller
{
    public function show(
        TenantContext $tenant,
        Request $request,
        Inspection $inspection,
        InspectionOverviewPresenter $presenter,
    ): InertiaResponse {
        $inspection = Inspection::query()
            ->forOrganization($tenant->id())
            ->with(['equipment.client', 'overviewBlocks.photos'])
            ->whereKey($inspection->getKey())
            ->firstOrFail();
        $this->authorize('view', $inspection);
        $editable = $request->user()->can('manageReportOverview', $inspection);

        return Inertia::render('Inspections/ReportOverview', [
            'inspection' => [
                'id' => $inspection->id,
                'public_id' => $inspection->public_id,
                'number' => $inspection->number,
                'status' => $inspection->status->value,
                'status_label' => $inspection->status->label(),
                'overview_url' => route('inspections.show', $inspection),
                'equipment' => [
                    'public_id' => $inspection->equipment->public_id,
                    'tag' => $inspection->equipment->tag,
                    'name' => $inspection->equipment->name,
                    'show_url' => route('equipments.show', $inspection->equipment),
                ],
            ],
            'overview' => $presenter->present($inspection, $editable),
            'tabs' => $this->tabs($inspection),
            'active_tab' => 'report_overview',
            'capabilities' => ['edit' => $editable],
        ]);
    }

    public function update(
        UpdateInspectionOverviewBlockRequest $request,
        TenantContext $tenant,
        Inspection $inspection,
        int $position,
        UpdateInspectionOverviewBlock $action,
    ): RedirectResponse {
        $inspection = Inspection::query()->forOrganization($tenant->id())->whereKey($inspection->getKey())->firstOrFail();
        $this->authorize('manageReportOverview', $inspection);
        $action->handle($request->user(), $inspection, $position, $request->validated());

        return back()->with('success', 'Textos da Vista geral atualizados.');
    }

    /** @return array<int, array<string, mixed>> */
    private function tabs(Inspection $inspection): array
    {
        return [
            ['key' => 'overview', 'label' => 'Visão geral', 'url' => route('inspections.show', $inspection)],
            ['key' => 'report_overview', 'label' => 'Vista geral', 'url' => route('inspections.report-overview', $inspection)],
            ['key' => 'defects', 'label' => 'Avarias', 'url' => route('inspections.defects', $inspection)],
            ['key' => 'classifications', 'label' => 'Classificação', 'url' => route('inspections.classifications', $inspection)],
            ['key' => 'photos', 'label' => 'Fotografias', 'url' => route('inspections.photos', $inspection)],
            ['key' => 'history', 'label' => 'Histórico', 'url' => route('inspections.history', $inspection)],
            ['key' => 'report', 'label' => 'Relatório', 'url' => route('inspections.report-preview', $inspection)],
        ];
    }
}
