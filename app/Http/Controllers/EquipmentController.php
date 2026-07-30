<?php

namespace App\Http\Controllers;

use App\Actions\Equipments\ActivateEquipment;
use App\Actions\Equipments\CreateEquipment;
use App\Actions\Equipments\DeactivateEquipment;
use App\Actions\Equipments\DecommissionEquipment;
use App\Actions\Equipments\UpdateEquipment;
use App\Enums\EquipmentDocumentType;
use App\Enums\EquipmentStatus;
use App\Http\Controllers\Concerns\ResolvesTenantStructure;
use App\Http\Requests\Equipments\StoreEquipmentRequest;
use App\Http\Requests\Equipments\UpdateEquipmentRequest;
use App\Http\Requests\Equipments\UpdateEquipmentStatusRequest;
use App\Models\Area;
use App\Models\Client;
use App\Models\ClientUnit;
use App\Models\Equipment;
use App\Models\EquipmentDocument;
use App\Models\Subarea;
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

        $filters = [
            'search' => trim((string) $request->string('search')),
            'client' => trim((string) $request->string('client')),
            'unit' => trim((string) $request->string('unit')),
            'area' => trim((string) $request->string('area')),
            'subarea' => trim((string) $request->string('subarea')),
            'status' => trim((string) $request->string('status')),
        ];

        $equipments = Equipment::query()
            ->forOrganization($tenant->id())
            ->with([
                'client:id,public_id,name',
                'unit:id,public_id,name',
                'area:id,public_id,name',
                'subarea:id,public_id,name',
            ])
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $tagSearch = TextNormalizer::equipmentTag($filters['search']);

                $query->where(function ($query) use ($filters, $tagSearch): void {
                    $query
                        ->where('normalized_tag', 'like', "%{$tagSearch}%")
                        ->orWhere('tag', 'like', "%{$filters['search']}%")
                        ->orWhere('name', 'like', "%{$filters['search']}%")
                        ->orWhere('manufacturer', 'like', "%{$filters['search']}%")
                        ->orWhere('model', 'like', "%{$filters['search']}%")
                        ->orWhere('serial_number', 'like', "%{$filters['search']}%")
                        ->orWhere('asset_code', 'like', "%{$filters['search']}%");
                });
            })
            ->when($filters['client'] !== '', fn ($query) => $query->where('client_id', (int) $filters['client']))
            ->when($filters['unit'] !== '', fn ($query) => $query->where('client_unit_id', (int) $filters['unit']))
            ->when($filters['area'] !== '', fn ($query) => $query->where('area_id', (int) $filters['area']))
            ->when($filters['subarea'] !== '', fn ($query) => $query->where('subarea_id', (int) $filters['subarea']))
            ->when(EquipmentStatus::tryFrom($filters['status']) !== null, fn ($query) => $query->where('status', $filters['status']))
            ->orderBy('tag')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Equipment $equipment): array => [
                'public_id' => $equipment->public_id,
                'tag' => $equipment->tag,
                'name' => $equipment->name,
                'manufacturer' => $equipment->manufacturer,
                'model' => $equipment->model,
                'serial_number' => $equipment->serial_number,
                'asset_code' => $equipment->asset_code,
                'status' => $equipment->status->value,
                'client' => [
                    'public_id' => $equipment->client->public_id,
                    'name' => $equipment->client->name,
                ],
                'unit' => [
                    'public_id' => $equipment->unit->public_id,
                    'name' => $equipment->unit->name,
                ],
                'area' => [
                    'public_id' => $equipment->area->public_id,
                    'name' => $equipment->area->name,
                ],
                'subarea' => $equipment->subarea === null
                    ? null
                    : [
                        'public_id' => $equipment->subarea->public_id,
                        'name' => $equipment->subarea->name,
                    ],
                'show_url' => route('equipments.show', $equipment),
                'edit_url' => route('equipments.edit', $equipment),
                'status_url' => route('equipments.status', $equipment),
                'can_update' => $request->user()->can('update', $equipment),
                'can_change_status' => $request->user()->can('changeStatus', $equipment),
            ]);

        return Inertia::render('Equipments/Index', [
            'equipments' => $equipments,
            'filters' => $filters,
            'clients' => $this->tenantClients($tenant),
            'units' => $this->tenantUnits($tenant),
            'areas' => $this->tenantAreas($tenant),
            'subareas' => $this->tenantSubareas($tenant),
            'status_options' => $this->statusOptions(),
            'can' => [
                'create' => $request->user()->can('create', Equipment::class),
            ],
            'create_url' => route('equipments.create'),
        ]);
    }

    public function create(TenantContext $tenant): InertiaResponse
    {
        $this->authorize('create', Equipment::class);

        return Inertia::render('Equipments/Create', [
            'action' => route('equipments.store'),
            'cancel_url' => route('equipments.index'),
            'clients' => $this->activeTenantClients($tenant),
            'units' => $this->activeTenantUnits($tenant),
            'areas' => $this->activeTenantAreas($tenant),
            'subareas' => $this->activeTenantSubareas($tenant),
        ]);
    }

    public function store(
        StoreEquipmentRequest $request,
        CreateEquipment $action,
    ): RedirectResponse {
        $this->authorize('create', Equipment::class);

        $equipment = $action->handle($request->user(), $request->validated());

        return redirect()
            ->route('equipments.show', $equipment)
            ->with('success', 'Equipamento criado.');
    }

    public function show(
        TenantContext $tenant,
        Request $request,
        Equipment $equipment,
    ): InertiaResponse {
        $equipment = $this->tenantEquipment($tenant, $equipment);

        $this->authorize('view', $equipment);

        $equipment->loadMissing([
            'client',
            'unit',
            'area',
            'subarea',
            'documents.uploader',
        ]);

        return Inertia::render('Equipments/Show', [
            'equipment' => $this->equipmentSummaryPayload($equipment),
            'client' => [
                'public_id' => $equipment->client->public_id,
                'name' => $equipment->client->name,
                'show_url' => route('clients.show', $equipment->client),
            ],
            'unit' => [
                'public_id' => $equipment->unit->public_id,
                'name' => $equipment->unit->name,
                'show_url' => route('units.show', $equipment->unit),
            ],
            'area' => [
                'public_id' => $equipment->area->public_id,
                'name' => $equipment->area->name,
                'show_url' => route('areas.show', $equipment->area),
            ],
            'subarea' => $equipment->subarea === null
                ? null
                : [
                    'public_id' => $equipment->subarea->public_id,
                    'name' => $equipment->subarea->name,
                    'show_url' => route('subareas.show', $equipment->subarea),
                ],
            'documents' => $equipment->documents
                ->map(fn (EquipmentDocument $document): array => $this->equipmentDocumentPayload($document))
                ->values()
                ->all(),
            'document_types' => EquipmentDocumentType::options(),
            'can' => [
                'create' => $request->user()->can('create', Equipment::class),
                'update' => $request->user()->can('update', $equipment),
                'change_status' => $request->user()->can('changeStatus', $equipment),
                'manage_documents' => $request->user()->can('create', [EquipmentDocument::class, $equipment]),
            ],
            'index_url' => route('equipments.index'),
            'create_url' => route('equipments.create'),
            'edit_url' => route('equipments.edit', $equipment),
            'status_url' => route('equipments.status', $equipment),
            'document_store_url' => route('equipments.documents.store', $equipment),
        ]);
    }

    public function edit(
        TenantContext $tenant,
        Equipment $equipment,
    ): InertiaResponse {
        $equipment = $this->tenantEquipment($tenant, $equipment);

        $this->authorize('update', $equipment);

        $equipment->loadMissing(['client', 'unit', 'area', 'subarea']);

        $options = $this->equipmentFormOptions($tenant, $equipment);

        return Inertia::render('Equipments/Edit', [
            'equipment' => $this->equipmentFormPayload($equipment),
            'action' => route('equipments.update', $equipment),
            'cancel_url' => route('equipments.show', $equipment),
            'clients' => $options['clients'],
            'units' => $options['units'],
            'areas' => $options['areas'],
            'subareas' => $options['subareas'],
        ]);
    }

    public function update(
        UpdateEquipmentRequest $request,
        TenantContext $tenant,
        Equipment $equipment,
        UpdateEquipment $action,
    ): RedirectResponse {
        $equipment = $this->tenantEquipment($tenant, $equipment);

        $this->authorize('update', $equipment);

        $action->handle($request->user(), $equipment, $request->validated());

        return redirect()
            ->route('equipments.show', $equipment)
            ->with('success', 'Equipamento atualizado.');
    }

    public function updateStatus(
        UpdateEquipmentStatusRequest $request,
        TenantContext $tenant,
        Equipment $equipment,
        ActivateEquipment $activate,
        DeactivateEquipment $deactivate,
        DecommissionEquipment $decommission,
    ): RedirectResponse {
        $equipment = $this->tenantEquipment($tenant, $equipment);

        $this->authorize('changeStatus', $equipment);

        $status = EquipmentStatus::from($request->validated('status'));
        $actor = $request->user();

        match ($status) {
            EquipmentStatus::Active => $activate->handle($actor, $equipment),
            EquipmentStatus::Inactive => $deactivate->handle($actor, $equipment),
            EquipmentStatus::Decommissioned => $decommission->handle(
                $actor,
                $equipment,
                $request->validated('reason'),
            ),
        };

        return back()->with('success', 'Status do equipamento atualizado.');
    }

    /**
     * @return array<int, array{id:int, public_id:string, name:string}>
     */
    private function tenantClients(TenantContext $tenant): array
    {
        return Client::query()
            ->forOrganization($tenant->id())
            ->orderBy('name')
            ->get(['id', 'public_id', 'name'])
            ->map(fn (Client $client): array => [
                'id' => $client->id,
                'public_id' => $client->public_id,
                'name' => $client->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id:int, public_id:string, name:string, client_id:int}>
     */
    private function tenantUnits(TenantContext $tenant): array
    {
        return ClientUnit::query()
            ->forOrganization($tenant->id())
            ->orderBy('name')
            ->get(['id', 'public_id', 'name', 'client_id'])
            ->map(fn (ClientUnit $unit): array => [
                'id' => $unit->id,
                'public_id' => $unit->public_id,
                'name' => $unit->name,
                'client_id' => $unit->client_id,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id:int, public_id:string, name:string, client_unit_id:int}>
     */
    private function tenantAreas(TenantContext $tenant): array
    {
        return Area::query()
            ->forOrganization($tenant->id())
            ->orderBy('name')
            ->get(['id', 'public_id', 'name', 'client_unit_id'])
            ->map(fn (Area $area): array => [
                'id' => $area->id,
                'public_id' => $area->public_id,
                'name' => $area->name,
                'client_unit_id' => $area->client_unit_id,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id:int, public_id:string, name:string, area_id:int}>
     */
    private function tenantSubareas(TenantContext $tenant): array
    {
        return Subarea::query()
            ->forOrganization($tenant->id())
            ->orderBy('name')
            ->get(['id', 'public_id', 'name', 'area_id'])
            ->map(fn (Subarea $subarea): array => [
                'id' => $subarea->id,
                'public_id' => $subarea->public_id,
                'name' => $subarea->name,
                'area_id' => $subarea->area_id,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id:int, public_id:string, name:string}>
     */
    private function activeTenantClients(TenantContext $tenant): array
    {
        return Client::query()
            ->forOrganization($tenant->id())
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'public_id', 'name'])
            ->map(fn (Client $client): array => [
                'id' => $client->id,
                'public_id' => $client->public_id,
                'name' => $client->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id:int, public_id:string, name:string, client_id:int}>
     */
    private function activeTenantUnits(TenantContext $tenant): array
    {
        return ClientUnit::query()
            ->forOrganization($tenant->id())
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'public_id', 'name', 'client_id'])
            ->map(fn (ClientUnit $unit): array => [
                'id' => $unit->id,
                'public_id' => $unit->public_id,
                'name' => $unit->name,
                'client_id' => $unit->client_id,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id:int, public_id:string, name:string, client_unit_id:int}>
     */
    private function activeTenantAreas(TenantContext $tenant): array
    {
        return Area::query()
            ->forOrganization($tenant->id())
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'public_id', 'name', 'client_unit_id'])
            ->map(fn (Area $area): array => [
                'id' => $area->id,
                'public_id' => $area->public_id,
                'name' => $area->name,
                'client_unit_id' => $area->client_unit_id,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id:int, public_id:string, name:string, area_id:int}>
     */
    private function activeTenantSubareas(TenantContext $tenant): array
    {
        return Subarea::query()
            ->forOrganization($tenant->id())
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'public_id', 'name', 'area_id'])
            ->map(fn (Subarea $subarea): array => [
                'id' => $subarea->id,
                'public_id' => $subarea->public_id,
                'name' => $subarea->name,
                'area_id' => $subarea->area_id,
            ])
            ->all();
    }

    /**
     * @return array{clients: array<int, array{id:int, public_id:string, name:string}>, units: array<int, array{id:int, public_id:string, name:string, client_id:int}>, areas: array<int, array{id:int, public_id:string, name:string, client_unit_id:int}>, subareas: array<int, array{id:int, public_id:string, name:string, area_id:int}>}
     */
    private function equipmentFormOptions(TenantContext $tenant, Equipment $equipment): array
    {
        return [
            'clients' => $this->appendClientOption(
                $this->activeTenantClients($tenant),
                $equipment->client,
            ),
            'units' => $this->appendUnitOption(
                $this->activeTenantUnits($tenant),
                $equipment->unit,
            ),
            'areas' => $this->appendAreaOption(
                $this->activeTenantAreas($tenant),
                $equipment->area,
            ),
            'subareas' => $this->appendSubareaOption(
                $this->activeTenantSubareas($tenant),
                $equipment->subarea,
            ),
        ];
    }

    /**
     * @param  array<int, array{id:int, public_id:string, name:string}>  $options
     * @return array<int, array{id:int, public_id:string, name:string}>
     */
    private function appendClientOption(array $options, ?Client $client): array
    {
        if ($client === null || $this->optionExists($options, $client->id)) {
            return $options;
        }

        $options[] = [
            'id' => $client->id,
            'public_id' => $client->public_id,
            'name' => $client->name,
        ];

        return $options;
    }

    /**
     * @param  array<int, array{id:int, public_id:string, name:string, client_id:int}>  $options
     * @return array<int, array{id:int, public_id:string, name:string, client_id:int}>
     */
    private function appendUnitOption(array $options, ?ClientUnit $unit): array
    {
        if ($unit === null || $this->optionExists($options, $unit->id)) {
            return $options;
        }

        $options[] = [
            'id' => $unit->id,
            'public_id' => $unit->public_id,
            'name' => $unit->name,
            'client_id' => $unit->client_id,
        ];

        return $options;
    }

    /**
     * @param  array<int, array{id:int, public_id:string, name:string, client_unit_id:int}>  $options
     * @return array<int, array{id:int, public_id:string, name:string, client_unit_id:int}>
     */
    private function appendAreaOption(array $options, ?Area $area): array
    {
        if ($area === null || $this->optionExists($options, $area->id)) {
            return $options;
        }

        $options[] = [
            'id' => $area->id,
            'public_id' => $area->public_id,
            'name' => $area->name,
            'client_unit_id' => $area->client_unit_id,
        ];

        return $options;
    }

    /**
     * @param  array<int, array{id:int, public_id:string, name:string, area_id:int}>  $options
     * @return array<int, array{id:int, public_id:string, name:string, area_id:int}>
     */
    private function appendSubareaOption(array $options, ?Subarea $subarea): array
    {
        if ($subarea === null || $this->optionExists($options, $subarea->id)) {
            return $options;
        }

        $options[] = [
            'id' => $subarea->id,
            'public_id' => $subarea->public_id,
            'name' => $subarea->name,
            'area_id' => $subarea->area_id,
        ];

        return $options;
    }

    /**
     * @param  array<int, array{id:int}>  $options
     */
    private function optionExists(array $options, int $id): bool
    {
        foreach ($options as $option) {
            if ((int) $option['id'] === $id) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{public_id:string, tag:string, normalized_tag:string, name:string, description:?string, manufacturer:?string, model:?string, serial_number:?string, asset_code:?string, abc_code:?string, installation_location:?string, commissioned_at:?string, status:string, notes:?string, decommissioned_at:?string, decommission_reason:?string, show_url:string}
     */
    private function equipmentSummaryPayload(Equipment $equipment): array
    {
        return [
            'public_id' => $equipment->public_id,
            'tag' => $equipment->tag,
            'normalized_tag' => $equipment->normalized_tag,
            'name' => $equipment->name,
            'description' => $equipment->description,
            'manufacturer' => $equipment->manufacturer,
            'model' => $equipment->model,
            'serial_number' => $equipment->serial_number,
            'asset_code' => $equipment->asset_code,
            'abc_code' => $equipment->abc_code,
            'installation_location' => $equipment->installation_location,
            'commissioned_at' => $equipment->commissioned_at?->toDateString(),
            'status' => $equipment->status->value,
            'notes' => $equipment->notes,
            'decommissioned_at' => $equipment->decommissioned_at?->toDateTimeString(),
            'decommission_reason' => $equipment->decommission_reason,
            'show_url' => route('equipments.show', $equipment),
        ];
    }

    /**
     * @return array{public_id:string, client_id:int, client_unit_id:int, area_id:int, subarea_id:?int, tag:string, name:string, description:?string, manufacturer:?string, model:?string, serial_number:?string, asset_code:?string, abc_code:?string, installation_location:?string, commissioned_at:?string, status:string, notes:?string}
     */
    private function equipmentFormPayload(Equipment $equipment): array
    {
        return [
            'public_id' => $equipment->public_id,
            'client_id' => $equipment->client_id,
            'client_unit_id' => $equipment->client_unit_id,
            'area_id' => $equipment->area_id,
            'subarea_id' => $equipment->subarea_id,
            'tag' => $equipment->tag,
            'name' => $equipment->name,
            'description' => $equipment->description,
            'manufacturer' => $equipment->manufacturer,
            'model' => $equipment->model,
            'serial_number' => $equipment->serial_number,
            'asset_code' => $equipment->asset_code,
            'abc_code' => $equipment->abc_code,
            'installation_location' => $equipment->installation_location,
            'commissioned_at' => $equipment->commissioned_at?->toDateString(),
            'status' => $equipment->status->value,
            'notes' => $equipment->notes,
        ];
    }

    /**
     * @return array{id:int, public_id:string, document_group:string, document_type:string, document_type_label:string, title:string, document_number:?string, revision:?string, description:?string, original_name:string, mime_type:string, extension:?string, size:int, checksum:string, is_current:bool, status:string, issued_at:?string, created_at:?string, updated_at:?string, download_url:string, show_url:string, status_url:string, set_current_url:string, uploaded_by:?array{id:int, public_id:string, name:string}}
     */
    private function equipmentDocumentPayload(EquipmentDocument $document): array
    {
        return [
            'id' => $document->id,
            'public_id' => $document->public_id,
            'document_group' => $document->document_group,
            'document_type' => $document->document_type->value,
            'document_type_label' => $document->document_type->label(),
            'title' => $document->title,
            'document_number' => $document->document_number,
            'revision' => $document->revision,
            'description' => $document->description,
            'original_name' => $document->original_name,
            'mime_type' => $document->mime_type,
            'extension' => $document->extension,
            'size' => (int) $document->size,
            'checksum' => $document->checksum,
            'is_current' => (bool) $document->is_current,
            'status' => $document->status->value,
            'issued_at' => $document->issued_at?->toDateString(),
            'created_at' => $document->created_at?->toDateTimeString(),
            'updated_at' => $document->updated_at?->toDateTimeString(),
            'download_url' => route('equipment-documents.download', $document),
            'show_url' => route('equipment-documents.show', $document),
            'status_url' => route('equipment-documents.status', $document),
            'set_current_url' => route('equipment-documents.current', $document),
            'uploaded_by' => $document->uploader === null
                ? null
                : [
                    'id' => $document->uploader->id,
                    'public_id' => $document->uploader->public_id,
                    'name' => $document->uploader->name,
                ],
        ];
    }

    /**
     * @return array<int, array{value:string, label:string}>
     */
    private function statusOptions(): array
    {
        return [
            ['value' => '', 'label' => 'Todos'],
            ['value' => EquipmentStatus::Active->value, 'label' => 'Ativo'],
            ['value' => EquipmentStatus::Inactive->value, 'label' => 'Inativo'],
            ['value' => EquipmentStatus::Decommissioned->value, 'label' => 'Descomissionado'],
        ];
    }
}
