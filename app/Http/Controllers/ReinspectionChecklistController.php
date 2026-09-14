<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesTenantStructure;
use App\Models\Inspection;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;

final class ReinspectionChecklistController extends Controller
{
    use ResolvesTenantStructure;

    public function show(TenantContext $tenant, Inspection $inspection): RedirectResponse
    {
        $inspection = $this->tenantInspection($tenant, $inspection);
        $this->authorize('view', $inspection);

        return redirect()->route('inspections.defects', $inspection);
    }
}
