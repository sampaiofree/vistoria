<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Inspections\AssignInspectionResponsible;
use App\Actions\Inspections\RemoveInspectionResponsible;
use App\Actions\Inspections\SetPrimaryInspectionResponsible;
use App\Http\Controllers\Concerns\ResolvesTenantStructure;
use App\Http\Requests\Inspections\StoreInspectionResponsibleRequest;
use App\Http\Requests\Inspections\UpdateInspectionResponsibleRequest;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class InspectionResponsibleController extends Controller
{
    use ResolvesTenantStructure;

    public function store(
        StoreInspectionResponsibleRequest $request,
        TenantContext $tenant,
        Inspection $inspection,
        AssignInspectionResponsible $action,
    ): RedirectResponse {
        $inspection = $this->tenantInspection($tenant, $inspection);

        $this->authorize('assignResponsibles', $inspection);

        $user = User::query()
            ->where('organization_id', $tenant->id())
            ->where('id', (int) $request->validated('user_id'))
            ->firstOrFail();

        $action->handle(
            $inspection,
            $user,
            $request->validated('responsibility'),
            $request->user(),
            $request->boolean('is_primary'),
        );

        return back()->with('success', 'Responsável atribuído.');
    }

    public function update(
        UpdateInspectionResponsibleRequest $request,
        TenantContext $tenant,
        Inspection $inspection,
        InspectionResponsible $responsible,
        SetPrimaryInspectionResponsible $action,
    ): RedirectResponse {
        $inspection = $this->tenantInspection($tenant, $inspection);
        $responsible = $this->tenantInspectionResponsible($tenant, $inspection, $responsible);

        $this->authorize('assignResponsibles', $inspection);

        $action->handle($responsible, $request->user());

        return back()->with('success', 'Responsável principal atualizado.');
    }

    public function destroy(
        Request $request,
        TenantContext $tenant,
        Inspection $inspection,
        InspectionResponsible $responsible,
        RemoveInspectionResponsible $action,
    ): RedirectResponse {
        $inspection = $this->tenantInspection($tenant, $inspection);
        $responsible = $this->tenantInspectionResponsible($tenant, $inspection, $responsible);

        $this->authorize('assignResponsibles', $inspection);

        $action->handle($responsible, $request->user());

        return back()->with('success', 'Responsável removido.');
    }
}
