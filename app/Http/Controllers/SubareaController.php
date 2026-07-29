<?php

namespace App\Http\Controllers;

use App\Actions\Subareas\CreateSubarea;
use App\Actions\Subareas\SetSubareaStatus;
use App\Actions\Subareas\UpdateSubarea;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Concerns\ResolvesTenantStructure;
use App\Http\Requests\Subareas\StoreSubareaRequest;
use App\Http\Requests\Subareas\UpdateSubareaRequest;
use App\Http\Requests\UpdateRegistrationStatusRequest;
use App\Models\Area;
use App\Models\Subarea;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class SubareaController extends Controller
{
    use ResolvesTenantStructure;

    public function index(TenantContext $tenant, Area $area): RedirectResponse
    {
        $area = $this->tenantArea($tenant, $area);

        $this->authorize('view', $area);

        return redirect()
            ->route('areas.show', $area);
    }

    public function create(TenantContext $tenant, Area $area): InertiaResponse
    {
        $area = $this->tenantArea($tenant, $area);

        $this->authorize('view', $area);
        $this->authorize('create', Subarea::class);

        return Inertia::render('Subareas/Create', [
            'area' => [
                'public_id' => $area->public_id,
                'name' => $area->name,
                'show_url' => route('areas.show', $area),
            ],
            'unit' => [
                'public_id' => $area->unit->public_id,
                'name' => $area->unit->name,
                'show_url' => route('units.show', $area->unit),
            ],
            'client' => [
                'public_id' => $area->unit->client->public_id,
                'name' => $area->unit->client->name,
                'show_url' => route('clients.show', $area->unit->client),
            ],
            'action' => route('areas.subareas.store', $area),
            'cancel_url' => route('areas.show', $area),
        ]);
    }

    public function store(
        StoreSubareaRequest $request,
        TenantContext $tenant,
        Area $area,
        CreateSubarea $action,
    ): RedirectResponse {
        $area = $this->tenantArea($tenant, $area);

        $this->authorize('create', Subarea::class);

        $subarea = $action->handle($area, $tenant->id(), $request->validated());

        return redirect()
            ->route('subareas.show', $subarea)
            ->with('success', 'Subarea criada.');
    }

    public function show(
        TenantContext $tenant,
        Request $request,
        Subarea $subarea,
    ): InertiaResponse {
        $subarea = $this->tenantSubarea($tenant, $subarea);

        $this->authorize('view', $subarea);

        return Inertia::render('Subareas/Show', [
            'subarea' => [
                'public_id' => $subarea->public_id,
                'name' => $subarea->name,
                'code' => $subarea->code,
                'status' => $subarea->status->value,
                'description' => $subarea->description,
                'show_url' => route('subareas.show', $subarea),
                'edit_url' => route('subareas.edit', $subarea),
                'status_url' => route('subareas.status', $subarea),
            ],
            'area' => [
                'public_id' => $subarea->area->public_id,
                'name' => $subarea->area->name,
                'show_url' => route('areas.show', $subarea->area),
            ],
            'unit' => [
                'public_id' => $subarea->area->unit->public_id,
                'name' => $subarea->area->unit->name,
                'show_url' => route('units.show', $subarea->area->unit),
            ],
            'client' => [
                'public_id' => $subarea->area->unit->client->public_id,
                'name' => $subarea->area->unit->client->name,
                'show_url' => route('clients.show', $subarea->area->unit->client),
            ],
            'can' => [
                'update' => $request->user()->can('update', $subarea),
            ],
        ]);
    }

    public function edit(TenantContext $tenant, Subarea $subarea): InertiaResponse
    {
        $subarea = $this->tenantSubarea($tenant, $subarea);

        $this->authorize('update', $subarea);

        return Inertia::render('Subareas/Edit', [
            'subarea' => [
                'name' => $subarea->name,
                'code' => $subarea->code,
                'status' => $subarea->status->value,
                'description' => $subarea->description,
            ],
            'area' => [
                'public_id' => $subarea->area->public_id,
                'name' => $subarea->area->name,
                'show_url' => route('areas.show', $subarea->area),
            ],
            'unit' => [
                'public_id' => $subarea->area->unit->public_id,
                'name' => $subarea->area->unit->name,
                'show_url' => route('units.show', $subarea->area->unit),
            ],
            'client' => [
                'public_id' => $subarea->area->unit->client->public_id,
                'name' => $subarea->area->unit->client->name,
                'show_url' => route('clients.show', $subarea->area->unit->client),
            ],
            'action' => route('subareas.update', $subarea),
            'cancel_url' => route('subareas.show', $subarea),
        ]);
    }

    public function update(
        UpdateSubareaRequest $request,
        TenantContext $tenant,
        Subarea $subarea,
        UpdateSubarea $action,
    ): RedirectResponse {
        $subarea = $this->tenantSubarea($tenant, $subarea);

        $this->authorize('update', $subarea);

        $action->handle($subarea, $request->validated());

        return redirect()
            ->route('subareas.show', $subarea)
            ->with('success', 'Subarea atualizada.');
    }

    public function updateStatus(
        UpdateRegistrationStatusRequest $request,
        TenantContext $tenant,
        Subarea $subarea,
        SetSubareaStatus $action,
    ): RedirectResponse {
        $subarea = $this->tenantSubarea($tenant, $subarea);

        $this->authorize('changeStatus', $subarea);

        $action->handle(
            $subarea,
            RegistrationStatus::from($request->validated('status')),
        );

        return redirect()
            ->back()
            ->with('success', 'Status da subarea atualizado.');
    }
}
