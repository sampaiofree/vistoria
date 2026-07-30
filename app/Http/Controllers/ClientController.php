<?php

namespace App\Http\Controllers;

use App\Actions\Clients\CreateClient;
use App\Actions\Clients\SetClientStatus;
use App\Actions\Clients\UpdateClient;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Concerns\ResolvesTenantStructure;
use App\Http\Requests\Clients\StoreClientRequest;
use App\Http\Requests\Clients\UpdateClientRequest;
use App\Http\Requests\UpdateRegistrationStatusRequest;
use App\Models\Client;
use App\Models\ClientUnit;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class ClientController extends Controller
{
    use ResolvesTenantStructure;

    public function index(Request $request, TenantContext $tenant): InertiaResponse
    {
        $this->authorize('viewAny', Client::class);

        $search = trim((string) $request->string('search'));

        $clients = Client::query()
            ->forOrganization($tenant->id())
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('legal_name', 'like', "%{$search}%")
                        ->orWhere('document', 'like', "%{$search}%");
                });
            })
            ->withCount('units')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Client $client): array => [
                'public_id' => $client->public_id,
                'name' => $client->name,
                'legal_name' => $client->legal_name,
                'document' => $client->document,
                'email' => $client->email,
                'phone' => $client->phone,
                'status' => $client->status->value,
                'units_count' => $client->units_count,
                'show_url' => route('clients.show', $client),
                'edit_url' => route('clients.edit', $client),
                'status_url' => route('clients.status', $client),
                'can_update' => $request->user()->can('update', $client),
            ]);

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'filters' => [
                'search' => $search,
            ],
            'can' => [
                'create' => $request->user()->can('create', Client::class),
            ],
            'create_url' => route('clients.create'),
        ]);
    }

    public function create(): InertiaResponse
    {
        $this->authorize('create', Client::class);

        return Inertia::render('Clients/Create', [
            'action' => route('clients.store'),
            'cancel_url' => route('clients.index'),
        ]);
    }

    public function store(
        StoreClientRequest $request,
        TenantContext $tenant,
        CreateClient $action,
    ): RedirectResponse {
        $this->authorize('create', Client::class);

        $client = $action->handle($tenant->id(), $request->validated());

        return redirect()
            ->route('clients.show', $client)
            ->with('success', 'Cliente criado.');
    }

    public function show(
        TenantContext $tenant,
        Request $request,
        Client $client,
    ): InertiaResponse {
        $client = $this->tenantClient($tenant, $client);

        $this->authorize('view', $client);

        $units = $client->units()
            ->forOrganization($tenant->id())
            ->withCount('areas')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn ($unit): array => [
                'public_id' => $unit->public_id,
                'name' => $unit->name,
                'code' => $unit->code,
                'status' => $unit->status->value,
                'areas_count' => $unit->areas_count,
                'show_url' => route('units.show', $unit),
                'edit_url' => route('units.edit', $unit),
                'status_url' => route('units.status', $unit),
                'can_update' => $request->user()->can('update', $unit),
            ]);

        return Inertia::render('Clients/Show', [
            'client' => [
                'public_id' => $client->public_id,
                'name' => $client->name,
                'legal_name' => $client->legal_name,
                'document' => $client->document,
                'email' => $client->email,
                'phone' => $client->phone,
                'status' => $client->status->value,
                'notes' => $client->notes,
                'show_url' => route('clients.show', $client),
                'edit_url' => route('clients.edit', $client),
                'status_url' => route('clients.status', $client),
                'create_unit_url' => route('clients.units.create', $client),
            ],
            'units' => $units,
            'can' => [
                'create_unit' => $request->user()->can('create', ClientUnit::class),
                'update' => $request->user()->can('update', $client),
            ],
        ]);
    }

    public function edit(TenantContext $tenant, Client $client): InertiaResponse
    {
        $client = $this->tenantClient($tenant, $client);

        $this->authorize('update', $client);

        return Inertia::render('Clients/Edit', [
            'client' => [
                'name' => $client->name,
                'legal_name' => $client->legal_name,
                'document' => $client->document,
                'email' => $client->email,
                'phone' => $client->phone,
                'status' => $client->status->value,
                'notes' => $client->notes,
            ],
            'action' => route('clients.update', $client),
            'cancel_url' => route('clients.show', $client),
        ]);
    }

    public function update(
        UpdateClientRequest $request,
        TenantContext $tenant,
        Client $client,
        UpdateClient $action,
    ): RedirectResponse {
        $client = $this->tenantClient($tenant, $client);

        $this->authorize('update', $client);

        $action->handle($client, $request->validated());

        return redirect()
            ->route('clients.show', $client)
            ->with('success', 'Cliente atualizado.');
    }

    public function updateStatus(
        UpdateRegistrationStatusRequest $request,
        TenantContext $tenant,
        Client $client,
        SetClientStatus $action,
    ): RedirectResponse {
        $client = $this->tenantClient($tenant, $client);

        $this->authorize('changeStatus', $client);

        $action->handle(
            $client,
            RegistrationStatus::from($request->validated('status')),
        );

        return redirect()
            ->back()
            ->with('success', 'Status do cliente atualizado.');
    }
}
