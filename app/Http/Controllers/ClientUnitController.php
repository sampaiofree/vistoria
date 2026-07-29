<?php

namespace App\Http\Controllers;

use App\Actions\ClientUnits\CreateClientUnit;
use App\Actions\ClientUnits\SetClientUnitStatus;
use App\Actions\ClientUnits\UpdateClientUnit;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Concerns\ResolvesTenantStructure;
use App\Http\Requests\ClientUnits\StoreClientUnitRequest;
use App\Http\Requests\ClientUnits\UpdateClientUnitRequest;
use App\Http\Requests\UpdateRegistrationStatusRequest;
use App\Models\Client;
use App\Models\ClientUnit;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class ClientUnitController extends Controller
{
    use ResolvesTenantStructure;

    public function index(TenantContext $tenant, Client $client): RedirectResponse
    {
        $client = $this->tenantClient($tenant, $client);

        $this->authorize('view', $client);

        return redirect()
            ->route('clients.show', $client);
    }

    public function create(TenantContext $tenant, Client $client): InertiaResponse
    {
        $client = $this->tenantClient($tenant, $client);

        $this->authorize('view', $client);
        $this->authorize('create', ClientUnit::class);

        return Inertia::render('ClientUnits/Create', [
            'client' => [
                'public_id' => $client->public_id,
                'name' => $client->name,
                'show_url' => route('clients.show', $client),
            ],
            'action' => route('clients.units.store', $client),
            'cancel_url' => route('clients.show', $client),
        ]);
    }

    public function store(
        StoreClientUnitRequest $request,
        TenantContext $tenant,
        Client $client,
        CreateClientUnit $action,
    ): RedirectResponse {
        $client = $this->tenantClient($tenant, $client);

        $this->authorize('create', ClientUnit::class);

        $unit = $action->handle($client, $tenant->id(), $request->validated());

        return redirect()
            ->route('units.show', $unit)
            ->with('success', 'Unidade criada.');
    }

    public function show(TenantContext $tenant, ClientUnit $unit): InertiaResponse
    {
        $unit = $this->tenantUnit($tenant, $unit);

        $this->authorize('view', $unit);

        $areas = $unit->areas()
            ->forOrganization($tenant->id())
            ->withCount('subareas')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn ($area): array => [
                'public_id' => $area->public_id,
                'name' => $area->name,
                'code' => $area->code,
                'status' => $area->status->value,
                'subareas_count' => $area->subareas_count,
                'show_url' => route('areas.show', $area),
                'edit_url' => route('areas.edit', $area),
                'status_url' => route('areas.status', $area),
            ]);

        return Inertia::render('ClientUnits/Show', [
            'unit' => [
                'public_id' => $unit->public_id,
                'name' => $unit->name,
                'code' => $unit->code,
                'timezone' => $unit->timezone,
                'address_line' => $unit->address_line,
                'address_number' => $unit->address_number,
                'district' => $unit->district,
                'postal_code' => $unit->postal_code,
                'city' => $unit->city,
                'state' => $unit->state,
                'country_code' => $unit->country_code,
                'status' => $unit->status->value,
                'notes' => $unit->notes,
                'show_url' => route('units.show', $unit),
                'edit_url' => route('units.edit', $unit),
                'status_url' => route('units.status', $unit),
                'create_area_url' => route('units.areas.create', $unit),
            ],
            'client' => [
                'public_id' => $unit->client->public_id,
                'name' => $unit->client->name,
                'show_url' => route('clients.show', $unit->client),
            ],
            'areas' => $areas,
            'can' => [
                'update' => $request?->user()?->can('update', $unit) ?? false,
                'create_area' => $request?->user()?->can('create', ClientUnit::class) ?? false,
            ],
        ]);
    }

    public function edit(TenantContext $tenant, ClientUnit $unit): InertiaResponse
    {
        $unit = $this->tenantUnit($tenant, $unit);

        $this->authorize('update', $unit);

        return Inertia::render('ClientUnits/Edit', [
            'unit' => [
                'name' => $unit->name,
                'code' => $unit->code,
                'timezone' => $unit->timezone,
                'address_line' => $unit->address_line,
                'address_number' => $unit->address_number,
                'district' => $unit->district,
                'postal_code' => $unit->postal_code,
                'city' => $unit->city,
                'state' => $unit->state,
                'country_code' => $unit->country_code,
                'notes' => $unit->notes,
            ],
            'action' => route('units.update', $unit),
            'cancel_url' => route('units.show', $unit),
        ]);
    }

    public function update(
        UpdateClientUnitRequest $request,
        TenantContext $tenant,
        ClientUnit $unit,
        UpdateClientUnit $action,
    ): RedirectResponse {
        $unit = $this->tenantUnit($tenant, $unit);

        $this->authorize('update', $unit);

        $action->handle($unit, $request->validated());

        return redirect()
            ->route('units.show', $unit)
            ->with('success', 'Unidade atualizada.');
    }

    public function updateStatus(
        UpdateRegistrationStatusRequest $request,
        TenantContext $tenant,
        ClientUnit $unit,
        SetClientUnitStatus $action,
    ): RedirectResponse {
        $unit = $this->tenantUnit($tenant, $unit);

        $this->authorize('changeStatus', $unit);

        $action->handle(
            $unit,
            RegistrationStatus::from($request->validated('status')),
        );

        return redirect()
            ->back()
            ->with('success', 'Status da unidade atualizado.');
    }
}
