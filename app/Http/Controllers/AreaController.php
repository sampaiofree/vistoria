<?php

namespace App\Http\Controllers;

use App\Actions\Areas\CreateArea;
use App\Actions\Areas\SetAreaStatus;
use App\Actions\Areas\UpdateArea;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Concerns\ResolvesTenantStructure;
use App\Http\Requests\Areas\StoreAreaRequest;
use App\Http\Requests\Areas\UpdateAreaRequest;
use App\Http\Requests\UpdateRegistrationStatusRequest;
use App\Models\Area;
use App\Models\ClientUnit;
use App\Models\Subarea;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class AreaController extends Controller
{
    use ResolvesTenantStructure;

    public function index(TenantContext $tenant, ClientUnit $unit): RedirectResponse
    {
        $unit = $this->tenantUnit($tenant, $unit);

        $this->authorize('view', $unit);

        return redirect()
            ->route('units.show', $unit);
    }

    public function create(TenantContext $tenant, ClientUnit $unit): InertiaResponse
    {
        $unit = $this->tenantUnit($tenant, $unit);

        $this->authorize('view', $unit);
        $this->authorize('create', Area::class);

        return Inertia::render('Areas/Create', [
            'unit' => [
                'public_id' => $unit->public_id,
                'name' => $unit->name,
                'show_url' => route('units.show', $unit),
            ],
            'client' => [
                'public_id' => $unit->client->public_id,
                'name' => $unit->client->name,
                'show_url' => route('clients.show', $unit->client),
            ],
            'action' => route('units.areas.store', $unit),
            'cancel_url' => route('units.show', $unit),
        ]);
    }

    public function store(
        StoreAreaRequest $request,
        TenantContext $tenant,
        ClientUnit $unit,
        CreateArea $action,
    ): RedirectResponse {
        $unit = $this->tenantUnit($tenant, $unit);

        $this->authorize('create', Area::class);

        $area = $action->handle($unit, $tenant->id(), $request->validated());

        return redirect()
            ->route('areas.show', $area)
            ->with('success', 'Area criada.');
    }

    public function show(
        TenantContext $tenant,
        Request $request,
        Area $area,
    ): InertiaResponse {
        $area = $this->tenantArea($tenant, $area);

        $this->authorize('view', $area);

        $subareas = $area->subareas()
            ->forOrganization($tenant->id())
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn ($subarea): array => [
                'public_id' => $subarea->public_id,
                'name' => $subarea->name,
                'code' => $subarea->code,
                'status' => $subarea->status->value,
                'show_url' => route('subareas.show', $subarea),
                'edit_url' => route('subareas.edit', $subarea),
                'status_url' => route('subareas.status', $subarea),
            ]);

        return Inertia::render('Areas/Show', [
            'area' => [
                'public_id' => $area->public_id,
                'name' => $area->name,
                'code' => $area->code,
                'status' => $area->status->value,
                'description' => $area->description,
                'show_url' => route('areas.show', $area),
                'edit_url' => route('areas.edit', $area),
                'status_url' => route('areas.status', $area),
                'create_subarea_url' => route('areas.subareas.create', $area),
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
            'subareas' => $subareas,
            'can' => [
                'update' => $request->user()->can('update', $area),
                'create_subarea' => $request->user()->can('create', Subarea::class),
            ],
        ]);
    }

    public function edit(TenantContext $tenant, Area $area): InertiaResponse
    {
        $area = $this->tenantArea($tenant, $area);

        $this->authorize('update', $area);

        return Inertia::render('Areas/Edit', [
            'area' => [
                'name' => $area->name,
                'code' => $area->code,
                'status' => $area->status->value,
                'description' => $area->description,
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
            'action' => route('areas.update', $area),
            'cancel_url' => route('areas.show', $area),
        ]);
    }

    public function update(
        UpdateAreaRequest $request,
        TenantContext $tenant,
        Area $area,
        UpdateArea $action,
    ): RedirectResponse {
        $area = $this->tenantArea($tenant, $area);

        $this->authorize('update', $area);

        $action->handle($area, $request->validated());

        return redirect()
            ->route('areas.show', $area)
            ->with('success', 'Area atualizada.');
    }

    public function updateStatus(
        UpdateRegistrationStatusRequest $request,
        TenantContext $tenant,
        Area $area,
        SetAreaStatus $action,
    ): RedirectResponse {
        $area = $this->tenantArea($tenant, $area);

        $this->authorize('changeStatus', $area);

        $action->handle(
            $area,
            RegistrationStatus::from($request->validated('status')),
        );

        return redirect()
            ->back()
            ->with('success', 'Status da area atualizado.');
    }
}
