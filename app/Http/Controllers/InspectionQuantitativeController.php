<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Inspection;
use App\Services\Navigation\InspectionContextNavigation;
use App\Services\Reports\BuildInspectionQuantitativeWorksheet;
use App\Services\Reports\ExportInspectionQuantitativeWorksheet;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class InspectionQuantitativeController extends Controller
{
    public function show(TenantContext $tenant, Request $request, Inspection $inspection, BuildInspectionQuantitativeWorksheet $builder, InspectionContextNavigation $navigation): InertiaResponse
    {
        $inspection = $this->viewableInspection($tenant, $inspection);

        return Inertia::render('Inspections/Quantitative', [
            'inspection' => [
                'number' => $inspection->number,
                'status' => $inspection->status->value,
                'overview_url' => route($request->user()->isClient() ? 'inspections.report-preview' : 'inspections.show', $inspection),
            ],
            'worksheet' => $builder->build($inspection),
            'export_url' => route('inspections.quantitative.export', $inspection),
            'tabs' => $navigation->forRequest($request)['items'] ?? [],
        ]);
    }

    public function export(TenantContext $tenant, Inspection $inspection, BuildInspectionQuantitativeWorksheet $builder, ExportInspectionQuantitativeWorksheet $exporter): StreamedResponse
    {
        $inspection = $this->viewableInspection($tenant, $inspection);
        $filename = 'quantitativo-'.Str::slug($inspection->number ?: $inspection->public_id).'.xls';

        return $exporter->download($builder->build($inspection), $filename);
    }

    private function viewableInspection(TenantContext $tenant, Inspection $inspection): Inspection
    {
        $inspection = Inspection::query()->forOrganization($tenant->id())->whereKey($inspection->id)->firstOrFail();
        $this->authorize('view', $inspection);

        return $inspection;
    }
}
