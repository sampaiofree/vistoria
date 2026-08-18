<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\EquipmentRevisions\DeleteEquipmentRevision;
use App\Actions\EquipmentRevisions\StoreEquipmentRevision;
use App\Actions\EquipmentRevisions\UpdateEquipmentRevision;
use App\Http\Controllers\Concerns\ResolvesTenantStructure;
use App\Http\Requests\EquipmentRevisions\StoreEquipmentRevisionRequest;
use App\Http\Requests\EquipmentRevisions\UpdateEquipmentRevisionRequest;
use App\Models\Equipment;
use App\Models\EquipmentRevision;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;

final class EquipmentRevisionController extends Controller
{
    use ResolvesTenantStructure;

    public function store(
        StoreEquipmentRevisionRequest $request,
        TenantContext $tenant,
        Equipment $equipment,
        StoreEquipmentRevision $action,
    ): RedirectResponse {
        $equipment = $this->tenantEquipment($tenant, $equipment);

        $this->authorize('create', [EquipmentRevision::class, $equipment]);

        $action->handle($request->user(), $equipment, $request->validated());

        return redirect()
            ->route('equipments.show', $equipment)
            ->with('success', 'Revisão histórica adicionada.');
    }

    public function update(
        UpdateEquipmentRevisionRequest $request,
        TenantContext $tenant,
        EquipmentRevision $equipmentRevision,
        UpdateEquipmentRevision $action,
    ): RedirectResponse {
        $equipmentRevision = $this->tenantRevision($tenant, $equipmentRevision);

        $this->authorize('update', $equipmentRevision);

        $action->handle($request->user(), $equipmentRevision, $request->validated());

        return redirect()
            ->route('equipments.show', $equipmentRevision->equipment)
            ->with('success', 'Revisão histórica atualizada.');
    }

    public function destroy(
        TenantContext $tenant,
        EquipmentRevision $equipmentRevision,
        DeleteEquipmentRevision $action,
    ): RedirectResponse {
        $equipmentRevision = $this->tenantRevision($tenant, $equipmentRevision);

        $this->authorize('delete', $equipmentRevision);

        $equipment = $equipmentRevision->equipment;
        $action->handle(request()->user(), $equipmentRevision);

        return redirect()
            ->route('equipments.show', $equipment)
            ->with('success', 'Revisão histórica removida.');
    }

    private function tenantRevision(TenantContext $tenant, EquipmentRevision $revision): EquipmentRevision
    {
        return EquipmentRevision::query()
            ->forOrganization($tenant->id())
            ->with('equipment')
            ->whereKey($revision->getKey())
            ->firstOrFail();
    }
}
