<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Equipments\ActivateEquipment;
use App\Actions\Equipments\CreateEquipment;
use App\Actions\Equipments\DeactivateEquipment;
use App\Actions\Equipments\DecommissionEquipment;
use App\Actions\Equipments\UpdateEquipment;
use App\Enums\AssetAbcClass;
use App\Enums\EquipmentStatus;
use App\Http\Controllers\Concerns\ResolvesTenantStructure;
use App\Http\Requests\Equipments\StoreEquipmentRequest;
use App\Http\Requests\Equipments\UpdateEquipmentRequest;
use App\Http\Requests\Equipments\UpdateEquipmentStatusRequest;
use App\Models\Client;
use App\Models\Equipment;
use App\Services\Reports\EquipmentRevisionChronology;
use App\Services\Tenancy\TenantContext;
use App\Support\TextNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class EquipmentController extends Controller
{
    use ResolvesTenantStructure;

    public function index(Request $request, TenantContext $tenant): InertiaResponse
    {
        $this->authorize('viewAny', Equipment::class);
        $filters = ['search' => trim((string) $request->string('search'))];

        $equipments = Equipment::query()->forOrganization($tenant->id())
            ->withExists(['inspections', 'defects'])
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $tagSearch = TextNormalizer::equipmentTag($filters['search']);
                $query->where(fn ($query) => $query
                    ->where('normalized_tag', 'like', "%{$tagSearch}%")
                    ->orWhere('numero_cliente', 'like', "%{$tagSearch}%")
                    ->orWhere('numero_interno', 'like', "%{$tagSearch}%")
                    ->orWhere('maintenance_item_code', 'like', "%{$tagSearch}%")
                    ->orWhere('defect_code_prefix', 'like', "%{$tagSearch}%")
                    ->orWhere('tag', 'like', "%{$filters['search']}%")
                    ->orWhere('description', 'like', "%{$filters['search']}%")
                    ->orWhere('area_name', 'like', "%{$filters['search']}%")
                    ->orWhere('subarea_name', 'like', "%{$filters['search']}%")
                    ->orWhere('name', 'like', "%{$filters['search']}%"));
            })
            ->orderBy('tag')->paginate(20)->withQueryString()
            ->through(fn (Equipment $equipment): array => [
                'public_id' => $equipment->public_id, 'numero_cliente' => $equipment->numero_cliente, 'numero_interno' => $equipment->numero_interno, 'maintenance_item_code' => $equipment->maintenance_item_code, 'tag' => $equipment->tag,
                'defect_code_prefix' => $equipment->defect_code_prefix, 'description' => $equipment->description, 'area_name' => $equipment->area_name, 'subarea_name' => $equipment->subarea_name,
                'status' => $equipment->status->value,
                'show_url' => route('equipments.show', $equipment), 'edit_url' => route('equipments.edit', $equipment), 'status_url' => route('equipments.status', $equipment),
                'can_update' => $request->user()->can('update', $equipment), 'can_change_status' => $request->user()->can('changeStatus', $equipment),
            ]);

        return Inertia::render('Equipments/Index', [
            'equipments' => $equipments, 'filters' => $filters,
            'can' => ['create' => $request->user()->can('create', Equipment::class)], 'create_url' => route('equipments.create'), 'import_url' => route('equipments.import.create'), 'index_url' => route('equipments.index'),
        ]);
    }

    public function create(TenantContext $tenant): InertiaResponse
    {
        $this->authorize('create', Equipment::class);

        $clientName = Client::query()
            ->forOrganization($tenant->id())
            ->value('name');

        return Inertia::render('Equipments/Create', [
            'action' => route('equipments.store'),
            'cancel_url' => route('equipments.index'),
            'abc_options' => AssetAbcClass::options(),
            'identifier_context' => $this->equipmentIdentifierContext($tenant, $clientName),
        ]);
    }

    public function store(StoreEquipmentRequest $request, CreateEquipment $action): RedirectResponse
    {
        $this->authorize('create', Equipment::class);
        $equipment = $action->handle($request->user(), $request->validated());

        return redirect()->route('equipments.show', $equipment)->with('success', 'Equipamento criado.');
    }

    public function show(TenantContext $tenant, Request $request, Equipment $equipment, EquipmentRevisionChronology $chronology): InertiaResponse
    {
        $equipment = $this->tenantEquipment($tenant, $equipment);
        $this->authorize('view', $equipment);
        $equipment->loadMissing('client');

        return Inertia::render('Equipments/Show', [
            'equipment' => $this->equipmentSummaryPayload($equipment), 'client' => ['name' => $equipment->client->name], 'history_entries' => $chronology->forEquipment($equipment)->all(),
            'identifier_context' => $this->equipmentIdentifierContext($tenant, $equipment->client->name),
            'can' => ['update' => $request->user()->can('update', $equipment)],
            'index_url' => route('equipments.index'), 'edit_url' => route('equipments.edit', $equipment),
        ]);
    }

    public function edit(TenantContext $tenant, Equipment $equipment): InertiaResponse
    {
        $equipment = $this->tenantEquipment($tenant, $equipment);
        $this->authorize('update', $equipment);
        $equipment->loadMissing('client')->loadCount(['inspections', 'defects']);

        return Inertia::render('Equipments/Edit', [
            'equipment' => $this->equipmentFormPayload($equipment),
            'action' => route('equipments.update', $equipment),
            'cancel_url' => route('equipments.show', $equipment),
            'abc_options' => AssetAbcClass::options(),
            'identifier_context' => $this->equipmentIdentifierContext($tenant, $equipment->client->name),
            'related_records' => [
                'inspections_count' => $equipment->inspections_count,
                'defects_count' => $equipment->defects_count,
                'requires_confirmation' => $equipment->inspections_count > 0 || $equipment->defects_count > 0,
            ],
        ]);
    }

    public function update(UpdateEquipmentRequest $request, TenantContext $tenant, Equipment $equipment, UpdateEquipment $action): RedirectResponse
    {
        $equipment = $this->tenantEquipment($tenant, $equipment);
        $this->authorize('update', $equipment);
        $action->handle($request->user(), $equipment, $request->validated());

        return redirect()->route('equipments.show', $equipment)->with('success', 'Equipamento atualizado.');
    }

    public function updateStatus(UpdateEquipmentStatusRequest $request, TenantContext $tenant, Equipment $equipment, ActivateEquipment $activate, DeactivateEquipment $deactivate, DecommissionEquipment $decommission): RedirectResponse
    {
        $equipment = $this->tenantEquipment($tenant, $equipment);
        $this->authorize('changeStatus', $equipment);
        $actor = $request->user();
        match (EquipmentStatus::from($request->validated('status'))) {
            EquipmentStatus::Active => $activate->handle($actor, $equipment), EquipmentStatus::Inactive => $deactivate->handle($actor, $equipment), EquipmentStatus::Decommissioned => $decommission->handle($actor, $equipment, $request->validated('reason')),
        };

        return back()->with('success', 'Status do equipamento atualizado.');
    }

    private function equipmentSummaryPayload(Equipment $equipment): array
    {
        return [...$this->equipmentFormPayload($equipment), 'public_id' => $equipment->public_id, 'tag' => $equipment->tag, 'name' => $equipment->name, 'status' => $equipment->status->value, 'show_url' => route('equipments.show', $equipment)];
    }

    private function equipmentFormPayload(Equipment $equipment): array
    {
        return $equipment->only(['numero_cliente', 'numero_interno', 'maintenance_plan_code', 'maintenance_item_code', 'area_code', 'area_name', 'subarea_code', 'subarea_name', 'task_list_group', 'task_list_group_counter', 'public_id', 'tag', 'defect_code_prefix', 'name', 'description', 'abc_code', 'installation_location', 'status']);
    }

    /** @return array{client_name: ?string, organization_name: string} */
    private function equipmentIdentifierContext(TenantContext $tenant, ?string $clientName): array
    {
        return [
            'client_name' => $clientName,
            'organization_name' => $tenant->organization()->name,
        ];
    }
}
