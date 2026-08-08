<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesTenantStructure;
use App\Models\Inspection;
use App\Services\Defects\BuildReinspectionChecklist;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class ReinspectionChecklistController extends Controller
{
    use ResolvesTenantStructure;

    public function show(TenantContext $tenant, Request $request, Inspection $inspection, BuildReinspectionChecklist $checklist): InertiaResponse
    {
        $inspection = $this->tenantInspection($tenant, $inspection);
        $this->authorize('view', $inspection);

        return Inertia::render('Inspections/ReinspectionChecklist', [
            'inspection' => [
                'public_id' => $inspection->public_id,
                'number' => $inspection->number,
                'show_url' => route('inspections.show', $inspection),
            ],
            'checklist' => $checklist->handle($inspection),
            'can_update' => $request->user()->can('view', $inspection),
        ]);
    }
}
