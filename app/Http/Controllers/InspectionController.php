<?php

namespace App\Http\Controllers;

use App\Actions\InspectionLocations\BuildInspectionLocationSnapshot;
use App\Actions\Inspections\CreateInspection;
use App\Actions\Inspections\UpdateGeneralAspects;
use App\Actions\Inspections\UpdatePlannedInspection;
use App\Actions\Inspections\UpdateReportMetadata;
use App\Enums\EquipmentRevisionEmissionType;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use App\Enums\UserStatus;
use App\Http\Controllers\Concerns\ResolvesTenantStructure;
use App\Http\Requests\Inspections\StoreInspectionRequest;
use App\Http\Requests\Inspections\UpdateGeneralAspectsRequest;
use App\Http\Requests\Inspections\UpdatePlannedInspectionRequest;
use App\Http\Requests\Inspections\UpdateReportMetadataRequest;
use App\Models\Client;
use App\Models\ClientUnit;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\DefectCategory;
use App\Models\Equipment;
use App\Models\EquipmentDocument;
use App\Models\Inspection;
use App\Models\InspectionReferenceDocument;
use App\Models\InspectionResponsible;
use App\Models\InspectionStatusHistory;
use App\Models\User;
use App\Services\InspectionLocations\InspectionLocationPresenter;
use App\Services\InspectionLocations\InspectionLocationReportComposer;
use App\Services\InspectionLocations\InspectionLocationReportSequenceComposer;
use App\Services\Inspections\InspectionReadModelPresenter;
use App\Services\Reports\GeneralAspectsDocument;
use App\Services\Tenancy\TenantContext;
use App\Support\TextNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class InspectionController extends Controller
{
    use ResolvesTenantStructure;

    public function index(Request $request, TenantContext $tenant): InertiaResponse
    {
        $this->authorize('viewAny', Inspection::class);

        $filters = [
            'search' => $this->filterValue($request, 'search', 'number'),
            'number' => $this->filterValue($request, 'number', 'search'),
            'client' => trim((string) $request->string('client')),
            'unit' => trim((string) $request->string('unit')),
            'equipment' => trim((string) $request->string('equipment')),
            'status' => trim((string) $request->string('status')),
            'type' => $this->filterValue($request, 'type', 'inspection_type'),
            'inspection_type' => $this->filterValue($request, 'inspection_type', 'type'),
            'responsible' => trim((string) $request->string('responsible')),
            'responsibility' => trim((string) $request->string('responsibility')),
            'scheduled_from' => $this->filterValue($request, 'scheduled_from', 'from'),
            'scheduled_to' => $this->filterValue($request, 'scheduled_to', 'to'),
            'inspected_from' => trim((string) $request->string('inspected_from')),
            'inspected_to' => trim((string) $request->string('inspected_to')),
            'from' => $this->filterValue($request, 'from', 'scheduled_from'),
            'to' => $this->filterValue($request, 'to', 'scheduled_to'),
        ];

        $inspections = Inspection::query()
            ->forOrganization($tenant->id())
            ->with([
                'equipment.client:id,public_id,name',
                'equipment.unit:id,public_id,name',
                'responsibles.user:id,public_id,name',
            ])
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];
                $tagSearch = TextNormalizer::equipmentTag($search);

                $query->where(function ($query) use ($search, $tagSearch): void {
                    $query
                        ->where('number', 'like', '%'.$search.'%')
                        ->orWhere('service_order', 'like', '%'.$search.'%')
                        ->orWhere('external_report_number', 'like', '%'.$search.'%')
                        ->orWhere('procedure_number', 'like', '%'.$search.'%')
                        ->orWhere('atmospheric_classification', 'like', '%'.$search.'%')
                        ->orWhereHas('equipment', function ($equipment) use ($search, $tagSearch): void {
                            $equipment
                                ->where('normalized_tag', 'like', '%'.$tagSearch.'%')
                                ->orWhere('tag', 'like', '%'.$search.'%')
                                ->orWhere('name', 'like', '%'.$search.'%')
                                ->orWhereHas('client', fn ($client) => $client->where('name', 'like', '%'.$search.'%'))
                                ->orWhereHas('unit', fn ($unit) => $unit->where('name', 'like', '%'.$search.'%'));
                        })
                        ->orWhereHas('responsibles.user', fn ($responsibles) => $responsibles->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($filters['client'] !== '', fn ($query) => $query->whereHas('equipment', fn ($equipment) => $equipment->where('client_id', (int) $filters['client'])))
            ->when($filters['unit'] !== '', fn ($query) => $query->whereHas('equipment', fn ($equipment) => $equipment->where('client_unit_id', (int) $filters['unit'])))
            ->when($filters['equipment'] !== '', fn ($query) => $query->where('equipment_id', (int) $filters['equipment']))
            ->when(InspectionStatus::tryFrom($filters['status']) !== null, fn ($query) => $query->where('status', $filters['status']))
            ->when(InspectionType::tryFrom($filters['type']) !== null, fn ($query) => $query->where('inspection_type', $filters['type']))
            ->when($filters['responsible'] !== '', function ($query) use ($filters): void {
                $query->whereHas('responsibles', function ($responsibles) use ($filters): void {
                    $responsibles->where('user_id', (int) $filters['responsible']);

                    if (InspectionResponsibility::tryFrom($filters['responsibility']) !== null) {
                        $responsibles->where('responsibility', $filters['responsibility']);
                    }
                });
            })
            ->when($filters['scheduled_from'] !== '', fn ($query) => $query->whereDate('scheduled_for', '>=', $filters['scheduled_from']))
            ->when($filters['scheduled_to'] !== '', fn ($query) => $query->whereDate('scheduled_for', '<=', $filters['scheduled_to']))
            ->when($filters['inspected_from'] !== '', fn ($query) => $query->whereDate('inspected_on', '>=', $filters['inspected_from']))
            ->when($filters['inspected_to'] !== '', fn ($query) => $query->whereDate('inspected_on', '<=', $filters['inspected_to']))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Inspection $inspection): array => $this->inspectionListPayload($request, $inspection));

        return Inertia::render('Inspections/Index', [
            'inspections' => $inspections,
            'filters' => $filters,
            'options' => [
                'clients' => $this->clientFilterOptions($tenant),
                'units' => $this->unitFilterOptions($tenant),
                'equipment' => $this->equipmentFilterOptions($tenant),
                'statuses' => InspectionStatus::options(),
                'types' => InspectionType::options(),
                'responsibles' => $this->responsibleFilterOptions($tenant),
            ],
            'capabilities' => [
                'create' => $request->user()->can('create', Inspection::class),
            ],
            'create_url' => route('inspections.create'),
        ]);
    }

    public function create(TenantContext $tenant): InertiaResponse
    {
        $this->authorize('create', Inspection::class);

        return Inertia::render('Inspections/Create', [
            'action' => route('inspections.store'),
            'cancel_url' => route('inspections.index'),
            'equipment' => $this->availableEquipmentOptions($tenant),
            'released_inspections' => $this->releasedInspectionOptions($tenant),
            'inspection_types' => InspectionType::options(),
        ]);
    }

    public function store(
        StoreInspectionRequest $request,
        TenantContext $tenant,
        CreateInspection $action,
    ): RedirectResponse {
        $this->authorize('create', Inspection::class);

        $equipment = Equipment::query()
            ->forOrganization($tenant->id())
            ->whereKey((int) $request->validated('equipment_id'))
            ->firstOrFail();

        $inspection = $action->handle(
            $request->user(),
            $equipment,
            $request->validated(),
        );

        return redirect()
            ->route('inspections.show', $inspection)
            ->with('success', 'Inspeção criada.');
    }

    public function show(
        TenantContext $tenant,
        Request $request,
        Inspection $inspection,
        InspectionReadModelPresenter $presenter,
    ): InertiaResponse {
        $inspection = $this->tenantInspection($tenant, $inspection);
        $this->authorize('view', $inspection);
        $inspection->loadMissing([
            'equipment.client',
            'equipment.unit',
            'previousInspection',
            'responsibles.user',
            'referenceDocuments.document.uploader',
            'referenceDocuments.actor',
            'statusHistories.actor',
            'updatedBy',
        ]);

        $canManageReportMetadata = $request->user()->can('manageReportMetadata', $inspection);
        $canManageGeneralAspects = $request->user()->can('manageGeneralAspects', $inspection);

        return Inertia::render('Inspections/Show', [
            'inspection' => [
                'id' => $inspection->id,
                'public_id' => $inspection->public_id,
                'number' => $inspection->number,
                'inspection_type' => $inspection->inspection_type->value,
                'type' => $inspection->inspection_type->value,
                'inspection_type_label' => $inspection->inspection_type->label(),
                'status' => $inspection->status->value,
                'status_label' => $inspection->status->label(),
                'report_generated_at' => $inspection->report_generated_at?->toISOString(),
                'released_at' => $inspection->released_at?->toISOString(),
                'previous_inspection' => $inspection->previousInspection === null ? null : [
                    'number' => $inspection->previousInspection->number,
                    'show_url' => route('inspections.show', $inspection->previousInspection),
                ],
                'reinspection_checklist_url' => $inspection->previousInspection === null
                    ? null
                    : route('inspections.reinspection-checklist', $inspection),
                'overview_url' => route('inspections.show', $inspection),
                'report_overview_url' => route('inspections.report-overview', $inspection),
                'defects_url' => route('inspections.defects', $inspection),
                'locations_url' => route('inspections.locations', $inspection),
                'photos_url' => route('inspections.photos', $inspection),
                'documents_url' => route('inspections.documents', $inspection),
                'history_url' => route('inspections.history', $inspection),
                'report_url' => route('inspections.report-preview', $inspection),
                'equipment' => $this->inspectionEquipmentPayload($inspection->equipment),
                // Mantidos para compatibilidade com consumidores legados do payload; a Visão geral não os renderiza.
                'context_snapshot' => $inspection->context_snapshot,
                'reference_document_ids' => $inspection->referenceDocuments->pluck('equipment_document_id')->map(fn ($id): int => (int) $id)->values()->all(),
                'history' => $inspection->statusHistories->map(fn (InspectionStatusHistory $history): array => $this->inspectionHistoryPayload($history))->values()->all(),
            ],
            'summary' => [
                'total' => 0,
                'completed' => 0,
                'pending' => 0,
                'progress_percent' => 0,
                'criticality' => ['code' => '—', 'label' => 'Não classificada'],
                'condition_breakdown' => [],
                'classification_breakdown' => [],
            ],
            'tabs' => $this->inspectionTabs($inspection),
            'active_tab' => 'overview',
            // Compatibilidade do contrato legado; a nova Visão geral não renderiza estes resumos.
            'content' => ['highlights' => [], 'metrics' => []],
            'capabilities' => [
                'update_planned' => $request->user()->can('updatePlanned', $inspection)
                    ? ['action' => route('inspections.edit', $inspection)]
                    : false,
                'manage_report_metadata' => $canManageReportMetadata
                    ? ['action' => route('inspections.report-metadata.update', $inspection)]
                    : false,
                'manage_general_aspects' => $canManageGeneralAspects
                    ? ['action' => route('inspections.general-aspects.update', $inspection)]
                    : false,
                'assign_responsibles' => $request->user()->can('assignResponsibles', $inspection)
                    ? ['action' => route('inspections.responsibles.store', $inspection)]
                    : false,
                'manage_references' => $request->user()->can('manageReferences', $inspection)
                    ? ['action' => route('inspections.reference-documents.update', $inspection)]
                    : false,
                'transition' => $this->availableTransitions($request, $inspection) !== [],
            ],
            'report_metadata' => $this->reportMetadataPayload($inspection, $canManageReportMetadata),
            'general_aspects' => $this->generalAspectsPayload($inspection, $canManageGeneralAspects),
            'emission_options' => EquipmentRevisionEmissionType::options(),
            'transitions' => $this->availableTransitions($request, $inspection),
            'index_url' => route('inspections.index'),
        ]);
    }

    public function team(
        TenantContext $tenant,
        Request $request,
        Inspection $inspection,
    ): InertiaResponse {
        $inspection = $this->tenantInspection($tenant, $inspection);
        $this->authorize('view', $inspection);
        $inspection->loadMissing(['equipment', 'responsibles.user']);

        $canAssign = $request->user()->can('assignResponsibles', $inspection);

        return Inertia::render('Inspections/Team', [
            'inspection' => [
                'public_id' => $inspection->public_id,
                'number' => $inspection->number,
                'status' => $inspection->status->value,
                'status_label' => $inspection->status->label(),
                'equipment' => [
                    'tag' => $inspection->equipment->tag,
                    'name' => $inspection->equipment->name,
                ],
            ],
            'responsibles' => $inspection->responsibles
                ->map(fn (InspectionResponsible $responsible): array => $this->inspectionResponsiblePayload($inspection, $responsible))
                ->values()
                ->all(),
            'capabilities' => [
                'assign' => $canAssign
                    ? ['action' => route('inspections.responsibles.store', $inspection)]
                    : false,
            ],
            'assignment_options' => [
                'users' => $canAssign ? $this->responsibleAssignmentOptions($tenant) : [],
                'roles' => $canAssign ? InspectionResponsibility::options() : [],
            ],
            'tabs' => [
                ['key' => 'overview', 'label' => 'Visão geral', 'url' => route('inspections.show', $inspection)],
                ['key' => 'report_overview', 'label' => 'Vista geral', 'url' => route('inspections.report-overview', $inspection)],
                ['key' => 'defects', 'label' => 'Avarias', 'url' => route('inspections.defects', $inspection)],
                ['key' => 'locations', 'label' => 'Localização', 'url' => route('inspections.locations', $inspection)],
                ['key' => 'team', 'label' => 'Equipe', 'url' => route('inspections.team', $inspection), 'count' => $inspection->responsibles->count()],
                ['key' => 'photos', 'label' => 'Fotografias', 'url' => route('inspections.photos', $inspection)],
                ['key' => 'documents', 'label' => 'Documentos', 'url' => route('inspections.documents', $inspection)],
                ['key' => 'history', 'label' => 'Histórico', 'url' => route('inspections.history', $inspection)],
                ['key' => 'report', 'label' => 'Relatório', 'url' => route('inspections.report-preview', $inspection)],
            ],
            'active_tab' => 'team',
            'back_url' => route('inspections.show', $inspection),
        ]);
    }

    public function defects(
        TenantContext $tenant,
        Request $request,
        Inspection $inspection,
        InspectionReadModelPresenter $presenter,
    ): InertiaResponse {
        return $this->renderHub($tenant, $request, $inspection, $presenter, 'defects');
    }

    public function locations(
        TenantContext $tenant,
        Request $request,
        Inspection $inspection,
        InspectionLocationPresenter $presenter,
    ): InertiaResponse {
        $inspection = $this->tenantInspection($tenant, $inspection);
        $this->authorize('view', $inspection);

        return Inertia::render('InspectionLocationMaps/Index', $presenter->present($inspection, $request->user()));
    }

    public function photos(
        TenantContext $tenant,
        Request $request,
        Inspection $inspection,
        InspectionReadModelPresenter $presenter,
    ): InertiaResponse {
        return $this->renderHub($tenant, $request, $inspection, $presenter, 'photos');
    }

    public function documents(
        TenantContext $tenant,
        Request $request,
        Inspection $inspection,
        InspectionReadModelPresenter $presenter,
    ): InertiaResponse {
        return $this->renderHub($tenant, $request, $inspection, $presenter, 'documents');
    }

    public function history(
        TenantContext $tenant,
        Request $request,
        Inspection $inspection,
        InspectionReadModelPresenter $presenter,
    ): InertiaResponse {
        return $this->renderHub($tenant, $request, $inspection, $presenter, 'history');
    }

    public function reportPreview(
        TenantContext $tenant,
        Request $request,
        Inspection $inspection,
        InspectionReadModelPresenter $presenter,
        InspectionLocationReportComposer $locationComposer,
        BuildInspectionLocationSnapshot $locationSnapshot,
        InspectionLocationReportSequenceComposer $locationSequence,
    ): InertiaResponse {
        return $this->renderHub(
            $tenant,
            $request,
            $inspection,
            $presenter,
            'report',
            $locationComposer,
            $locationSnapshot,
            $locationSequence,
        );
    }

    private function renderHub(
        TenantContext $tenant,
        Request $request,
        Inspection $inspection,
        InspectionReadModelPresenter $presenter,
        string $activeTab,
        ?InspectionLocationReportComposer $locationComposer = null,
        ?BuildInspectionLocationSnapshot $locationSnapshot = null,
        ?InspectionLocationReportSequenceComposer $locationSequence = null,
    ): InertiaResponse {
        $inspection = $this->tenantInspection($tenant, $inspection);

        $this->authorize('view', $inspection);

        $inspection->loadMissing([
            'equipment.client',
            'equipment.unit',
            'equipment.defects.assessments.inspection',
            'equipment.defects.assessments.creator',
            'equipment.defects.assessments.previousAssessment.inspection',
            'equipment.defects.assessments.previousAssessment.creator',
            'equipment.defects.firstInspection',
            'equipment.defects.draftAssessments.previousAssessment.inspection',
            'equipment.defects.draftAssessments.previousAssessment.creator',
            'equipment.defects.latestAssessment.inspection',
            'equipment.defects.latestAssessment.creator',
            'previousInspection.equipment',
            'nextInspections.equipment',
            'responsibles.user',
            'updatedBy',
            'referenceDocuments.document.uploader',
            'referenceDocuments.actor',
            'statusHistories.actor',
        ]);

        $canAssignResponsibles = $request->user()->can('assignResponsibles', $inspection);
        $canManageReferences = $request->user()->can('manageReferences', $inspection);
        $canUpdatePlanned = $request->user()->can('updatePlanned', $inspection);
        $canManageReportMetadata = $request->user()->can('manageReportMetadata', $inspection);
        $canManageGeneralAspects = $request->user()->can('manageGeneralAspects', $inspection);
        $canCreateDefects = $request->user()->can('create', [Defect::class, $inspection]);
        $transitions = $this->availableTransitions($request, $inspection);
        $viewFirstPayload = $presenter->inspection(
            $inspection,
            $request->user(),
            $this->inspectionDetailPayload($request, $inspection),
            $activeTab,
        );

        if ($activeTab === 'report' && $locationComposer !== null && $locationSnapshot !== null && $locationSequence !== null) {
            $locationReport = $locationComposer->compose($inspection);
            $viewFirstPayload['content']['location_source'] = 'inspection_maps';
            $viewFirstPayload['content']['locations'] = $locationReport['sheets'];
            $viewFirstPayload['content']['location_documentation'] = $locationReport;
            $viewFirstPayload['content']['location_snapshot'] = $locationSnapshot->fromComposition($inspection, $locationReport);
            $viewFirstPayload['content']['location_sequence'] = $locationSequence->compose(
                $locationReport['sheets'],
                $viewFirstPayload['content']['photographic_documentation']['blocks'] ?? [],
            );
        }

        return Inertia::render('Inspections/Show', array_merge($viewFirstPayload, [
            'capabilities' => [
                'update_planned' => $canUpdatePlanned
                    ? [
                        'action' => route('inspections.edit', $inspection),
                    ]
                    : false,
                'manage_report_metadata' => $canManageReportMetadata
                    ? ['action' => route('inspections.report-metadata.update', $inspection)]
                    : false,
                'manage_general_aspects' => $canManageGeneralAspects
                    ? ['action' => route('inspections.general-aspects.update', $inspection)]
                    : false,
                'assign_responsibles' => $canAssignResponsibles
                    ? [
                        'action' => route('inspections.responsibles.store', $inspection),
                    ]
                    : false,
                'manage_references' => $canManageReferences
                    ? [
                        'action' => route('inspections.reference-documents.update', $inspection),
                    ]
                    : false,
                'defects' => $canCreateDefects
                    ? [
                        'create' => [
                            'action' => route('inspections.defects.store', $inspection),
                            'categories' => DefectCategory::query()
                                ->forOrganization($tenant->id())
                                ->active()
                                ->orderBy('position')
                                ->orderBy('name')
                                ->get(['id', 'public_id', 'name', 'code'])
                                ->map(fn (DefectCategory $category): array => [
                                    'id' => $category->id,
                                    'public_id' => $category->public_id,
                                    'name' => $category->name,
                                    'code' => $category->code,
                                ])
                                ->values()
                                ->all(),
                        ],
                    ]
                    : false,
                'transition' => $transitions !== [],
            ],
            'assignment_options' => [
                'users' => $this->responsibleAssignmentOptions($tenant),
                'roles' => InspectionResponsibility::options(),
            ],
            'available_documents' => $canManageReferences
                ? $this->availableReferenceDocumentOptions($tenant, $inspection)
                : [],
            'transitions' => $transitions,
            'report_metadata' => $this->reportMetadataPayload($inspection, $canManageReportMetadata),
            'general_aspects' => $this->generalAspectsPayload($inspection, $canManageGeneralAspects),
            'emission_options' => EquipmentRevisionEmissionType::options(),
            'index_url' => route('inspections.index'),
        ]));
    }

    public function edit(
        TenantContext $tenant,
        Request $request,
        Inspection $inspection,
    ): InertiaResponse {
        $inspection = $this->tenantInspection($tenant, $inspection);

        $this->authorize('updatePlanned', $inspection);

        $inspection->loadMissing([
            'equipment.client',
            'equipment.unit',
            'previousInspection.equipment',
        ]);

        return Inertia::render('Inspections/Edit', [
            'inspection' => $this->inspectionDetailPayload($request, $inspection),
            'action' => route('inspections.update', $inspection),
            'cancel_url' => route('inspections.show', $inspection),
            'inspection_types' => InspectionType::options(),
        ]);
    }

    public function update(
        UpdatePlannedInspectionRequest $request,
        TenantContext $tenant,
        Inspection $inspection,
        UpdatePlannedInspection $action,
    ): RedirectResponse {
        $inspection = $this->tenantInspection($tenant, $inspection);

        $this->authorize('updatePlanned', $inspection);

        $action->handle(
            $inspection,
            $request->user(),
            $request->validated(),
        );

        return redirect()
            ->route('inspections.show', $inspection)
            ->with('success', 'Inspeção atualizada.');
    }

    public function updateReportMetadata(
        UpdateReportMetadataRequest $request,
        TenantContext $tenant,
        Inspection $inspection,
        UpdateReportMetadata $action,
    ): RedirectResponse {
        $inspection = $this->tenantInspection($tenant, $inspection);
        $this->authorize('manageReportMetadata', $inspection);

        $action->handle($inspection, $request->user(), $request->validated());

        return redirect()
            ->route('inspections.show', $inspection)
            ->with('success', 'Dados do relatório atualizados.');
    }

    public function updateGeneralAspects(
        UpdateGeneralAspectsRequest $request,
        TenantContext $tenant,
        Inspection $inspection,
        UpdateGeneralAspects $action,
    ): RedirectResponse {
        $inspection = $this->tenantInspection($tenant, $inspection);
        $this->authorize('manageGeneralAspects', $inspection);

        $action->handle($inspection, $request->user(), $request->validated());

        return redirect()
            ->route('inspections.show', $inspection)
            ->with('success', 'Aspectos gerais atualizados.');
    }

    /**
     * @return array<int, array{value:int, label:string}>
     */
    private function equipmentFilterOptions(TenantContext $tenant): array
    {
        return Equipment::query()
            ->forOrganization($tenant->id())
            ->orderBy('tag')
            ->get(['id', 'public_id', 'tag', 'name'])
            ->map(fn (Equipment $equipment): array => [
                'value' => $equipment->id,
                'label' => sprintf('%s — %s', $equipment->tag, $equipment->name),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value:int, label:string}>
     */
    private function responsibleFilterOptions(TenantContext $tenant): array
    {
        return User::query()
            ->where('organization_id', $tenant->id())
            ->where('status', UserStatus::Active->value)
            ->orderBy('name')
            ->get(['id', 'public_id', 'name'])
            ->map(fn (User $user): array => [
                'value' => $user->id,
                'label' => $user->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value:int, label:string}>
     */
    private function clientFilterOptions(TenantContext $tenant): array
    {
        return Client::query()
            ->forOrganization($tenant->id())
            ->orderBy('name')
            ->get(['id', 'public_id', 'name'])
            ->map(fn (Client $client): array => [
                'value' => $client->id,
                'label' => $client->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value:int, label:string}>
     */
    private function unitFilterOptions(TenantContext $tenant): array
    {
        return ClientUnit::query()
            ->forOrganization($tenant->id())
            ->with('client:id,public_id,name')
            ->orderBy('name')
            ->get(['id', 'public_id', 'name', 'client_id'])
            ->map(fn (ClientUnit $unit): array => [
                'value' => $unit->id,
                'label' => sprintf('%s — %s', $unit->client?->name ?? '', $unit->name),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id:int, public_id:string, tag:string, name:string}>
     */
    private function availableEquipmentOptions(TenantContext $tenant): array
    {
        return Equipment::query()
            ->forOrganization($tenant->id())
            ->with(['client', 'unit', 'area', 'subarea'])
            ->orderBy('tag')
            ->get()
            ->filter(fn (Equipment $equipment): bool => $equipment->canReceiveInspection())
            ->values()
            ->map(fn (Equipment $equipment): array => [
                'id' => $equipment->id,
                'public_id' => $equipment->public_id,
                'tag' => $equipment->tag,
                'name' => $equipment->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id:int, equipment_id:int, number:?string, status:string, released_at:?string}>
     */
    private function releasedInspectionOptions(TenantContext $tenant): array
    {
        return Inspection::query()
            ->forOrganization($tenant->id())
            ->with(['equipment:id,public_id,tag,name'])
            ->where('status', InspectionStatus::Released->value)
            ->orderByDesc('released_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Inspection $inspection): array => [
                'id' => $inspection->id,
                'equipment_id' => $inspection->equipment_id,
                'number' => $inspection->number,
                'status' => $inspection->status->value,
                'released_at' => $inspection->released_at?->format('d/m/Y'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id:int, public_id:string, name:string}>
     */
    private function responsibleAssignmentOptions(TenantContext $tenant): array
    {
        return User::query()
            ->where('organization_id', $tenant->id())
            ->where('status', UserStatus::Active->value)
            ->orderBy('name')
            ->get(['id', 'public_id', 'name'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'public_id' => $user->public_id,
                'name' => $user->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function inspectionTabs(Inspection $inspection): array
    {
        return [
            ['key' => 'overview', 'label' => 'Visão geral', 'url' => route('inspections.show', $inspection)],
            ['key' => 'report_overview', 'label' => 'Vista geral', 'url' => route('inspections.report-overview', $inspection)],
            ['key' => 'defects', 'label' => 'Avarias', 'url' => route('inspections.defects', $inspection)],
            ['key' => 'locations', 'label' => 'Localização', 'url' => route('inspections.locations', $inspection)],
            ['key' => 'photos', 'label' => 'Fotografias', 'url' => route('inspections.photos', $inspection)],
            ['key' => 'documents', 'label' => 'Documentos', 'url' => route('inspections.documents', $inspection)],
            ['key' => 'history', 'label' => 'Histórico', 'url' => route('inspections.history', $inspection)],
            ['key' => 'report', 'label' => 'Relatório', 'url' => route('inspections.report-preview', $inspection)],
        ];
    }

    /**
     * @return array{id:int, public_id:string, number:?string, inspection_type:string, type:string, inspection_type_label:string, status:string, status_label:string, scheduled_for:?string, scheduled_at:?string, equipment:array{id:int, public_id:string, tag:string, defect_code_prefix:?string, name:string, show_url:string}, defects:array<int, array{id:int, public_id:string, code:string, title:string, origin_description:?string, category:string, category_label:string, status:string, status_label:string, sequence_number:int, latest_assessment:?array{id:int, public_id:string, condition:string, condition_label:string, status:string, status_label:string, assessed_at:?string}, show_url:string}>, previous_inspection:?array{id:int, public_id:string, number:?string, inspection_type:string, type:string, status:string, show_url:string}, responsibles:array<int, array{id:int, public_id:string, name:string, responsibility:string, responsibility_label:string, is_primary:bool, assigned_at:?string, completed_at:?string, user:array{id:int, public_id:string, name:string}, set_primary_url:string, destroy_url:string}>, reference_documents:array<int, array{id:int, created_at:string, document:array{id:int, public_id:string, document_group:string, document_type:string, document_type_label:string, title:string, document_number:?string, revision:?string, description:?string, original_name:string, mime_type:string, extension:?string, size:int, checksum:string, is_current:bool, status:string, status_label:string, issued_at:?string, created_at:?string, updated_at:?string, download_url:string, show_url:string, uploaded_by:?array{id:int, public_id:string, name:string}}, added_by:?array{id:int, public_id:string, name:string}, delete_url:string}>, reference_document_ids:array<int, int>, history:array<int, array{id:int, from_status:?string, to_status:string, reason:?string, justification:?string, created_at:string, user:?array{id:int, public_id:string, name:string}}>, context_snapshot:array<string, mixed>, snapshot_version:int}
     */
    private function inspectionDetailPayload(Request $request, Inspection $inspection): array
    {
        return [
            'id' => $inspection->id,
            'public_id' => $inspection->public_id,
            'number' => $inspection->number,
            'equipment_id' => $inspection->equipment_id,
            'previous_inspection_id' => $inspection->previous_inspection_id,
            'inspection_type' => $inspection->inspection_type->value,
            'type' => $inspection->inspection_type->value,
            'inspection_type_label' => $inspection->inspection_type->label(),
            'status' => $inspection->status->value,
            'status_label' => $inspection->status->label(),
            'report_generated_at' => $inspection->report_generated_at?->toISOString(),
            'released_at' => $inspection->released_at?->toISOString(),
            'service_order' => $inspection->service_order,
            'report_date' => $inspection->report_date?->toDateString(),
            'emission_type' => $inspection->emission_type?->value,
            'emission_type_label' => $inspection->emission_type?->label(),
            'first_page_text_template' => $inspection->first_page_text_template,
            'external_report_number' => $inspection->external_report_number,
            'report_designer' => $inspection->report_designer,
            'designer_i_report_number' => $inspection->designer_i_report_number,
            'procedure_number' => $inspection->procedure_number,
            'atmospheric_classification' => $inspection->atmospheric_classification,
            'scheduled_for' => $inspection->scheduled_for?->format('d/m/Y'),
            'scheduled_at' => $inspection->scheduled_for?->format('d/m/Y'),
            'scheduled_for_input' => $inspection->scheduled_for?->toDateString(),
            'inspected_on' => $inspection->inspected_on?->format('d/m/Y'),
            'inspected_on_input' => $inspection->inspected_on?->toDateString(),
            'equipment' => $this->inspectionEquipmentPayload($inspection->equipment),
            'defects' => $inspection->equipment->defects
                ->map(fn (Defect $defect): array => $this->defectPayload($request, $inspection, $defect))
                ->values()
                ->all(),
            'previous_inspection' => $inspection->previousInspection === null
                ? null
                : [
                    'id' => $inspection->previousInspection->id,
                    'public_id' => $inspection->previousInspection->public_id,
                    'number' => $inspection->previousInspection->number,
                    'inspection_type' => $inspection->previousInspection->inspection_type->value,
                    'type' => $inspection->previousInspection->inspection_type->value,
                    'inspection_type_label' => $inspection->previousInspection->inspection_type->label(),
                    'status' => $inspection->previousInspection->status->value,
                    'status_label' => $inspection->previousInspection->status->label(),
                    'released_at' => $inspection->previousInspection->released_at?->format('d/m/Y'),
                    'show_url' => route('inspections.show', $inspection->previousInspection),
                ],
            'next_inspections' => $inspection->nextInspections
                ->map(fn (Inspection $nextInspection): array => [
                    'id' => $nextInspection->id,
                    'public_id' => $nextInspection->public_id,
                    'number' => $nextInspection->number,
                    'inspection_type' => $nextInspection->inspection_type->value,
                    'type' => $nextInspection->inspection_type->value,
                    'inspection_type_label' => $nextInspection->inspection_type->label(),
                    'status' => $nextInspection->status->value,
                    'status_label' => $nextInspection->status->label(),
                    'released_at' => $nextInspection->released_at?->format('d/m/Y'),
                    'show_url' => route('inspections.show', $nextInspection),
                ])
                ->values()
                ->all(),
            'responsibles' => $inspection->responsibles
                ->map(fn (InspectionResponsible $responsible): array => $this->inspectionResponsiblePayload($inspection, $responsible))
                ->values()
                ->all(),
            'reference_documents' => $inspection->referenceDocuments
                ->map(fn (InspectionReferenceDocument $referenceDocument): array => $this->inspectionReferenceDocumentPayload($inspection, $referenceDocument))
                ->values()
                ->all(),
            'reference_document_ids' => $inspection->referenceDocuments
                ->pluck('equipment_document_id')
                ->map(fn ($documentId): int => (int) $documentId)
                ->values()
                ->all(),
            'history' => $inspection->statusHistories
                ->map(fn (InspectionStatusHistory $history): array => $this->inspectionHistoryPayload($history))
                ->values()
                ->all(),
            'context_snapshot' => $inspection->context_snapshot,
            'snapshot_version' => (int) $inspection->snapshot_version,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reportMetadataPayload(Inspection $inspection, bool $canEdit): array
    {
        return [
            'emission_type' => $inspection->emission_type?->value,
            'emission_type_label' => $inspection->emission_type?->label(),
            'report_date' => $inspection->report_date?->toDateString(),
            'service_order' => $inspection->service_order,
            'external_report_number' => $inspection->external_report_number,
            'report_designer' => $inspection->report_designer,
            'designer_i_report_number' => $inspection->designer_i_report_number,
            'first_page_text_template' => $inspection->first_page_text_template,
            'updated_at' => $inspection->updated_at?->format('d/m/Y H:i'),
            'updated_by' => $inspection->updatedBy === null ? null : [
                'id' => $inspection->updatedBy->id,
                'name' => $inspection->updatedBy->name,
            ],
            'can_edit' => $canEdit,
            'update_url' => $canEdit ? route('inspections.report-metadata.update', $inspection) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function generalAspectsPayload(Inspection $inspection, bool $canEdit): array
    {
        $documents = app(GeneralAspectsDocument::class);
        $stored = $documents->fromStored($inspection->general_notes);

        return [
            'schema_version' => GeneralAspectsDocument::SCHEMA_VERSION,
            'document' => $stored['document'] ?? $documents->emptyDocument(),
            'has_content' => $stored !== null,
            'can_edit' => $canEdit,
            'update_url' => $canEdit ? route('inspections.general-aspects.update', $inspection) : null,
        ];
    }

    /**
     * @return array{id:int, public_id:string, number:?string, inspection_type:string, type:string, inspection_type_label:string, status:string, status_label:string, scheduled_for:?string, scheduled_at:?string, equipment:array{id:int, public_id:string, tag:string, name:string, show_url:string}, responsibles:array<int, array{id:int, public_id:string, name:string, responsibility:string, responsibility_label:string, is_primary:bool}> , show_url:string}
     */
    private function inspectionListPayload(Request $request, Inspection $inspection): array
    {
        $primaryResponsible = $inspection->responsibles->firstWhere('is_primary', true)
            ?? $inspection->responsibles->first();

        return [
            'id' => $inspection->id,
            'public_id' => $inspection->public_id,
            'number' => $inspection->number,
            'inspection_type' => $inspection->inspection_type->value,
            'type' => $inspection->inspection_type->value,
            'inspection_type_label' => $inspection->inspection_type->label(),
            'status' => $inspection->status->value,
            'status_label' => $inspection->status->label(),
            'scheduled_for' => $inspection->scheduled_for?->format('d/m/Y'),
            'scheduled_at' => $inspection->scheduled_for?->format('d/m/Y'),
            'inspected_on' => $inspection->inspected_on?->format('d/m/Y'),
            'inspected_at' => $inspection->inspected_on?->format('d/m/Y'),
            'equipment' => $this->inspectionEquipmentPayload($inspection->equipment),
            'primary_responsible' => $primaryResponsible === null
                ? null
                : [
                    'id' => $primaryResponsible->user->id,
                    'public_id' => $primaryResponsible->user->public_id,
                    'name' => $primaryResponsible->user->name,
                    'responsibility' => $primaryResponsible->responsibility->value,
                    'responsibility_label' => $primaryResponsible->responsibility->label(),
                    'is_primary' => (bool) $primaryResponsible->is_primary,
                ],
            'responsibles' => $inspection->responsibles
                ->map(fn (InspectionResponsible $responsible): array => [
                    'id' => $responsible->user->id,
                    'public_id' => $responsible->user->public_id,
                    'name' => $responsible->user->name,
                    'responsibility' => $responsible->responsibility->value,
                    'responsibility_label' => $responsible->responsibility->label(),
                    'is_primary' => (bool) $responsible->is_primary,
                ])
                ->values()
                ->all(),
            'edit_url' => $inspection->status === InspectionStatus::Planned
                && $request->user()->can('updatePlanned', $inspection)
                ? route('inspections.edit', $inspection)
                : null,
            'show_url' => route('inspections.show', $inspection),
        ];
    }

    /**
     * @return array{id:int, public_id:string, tag:string, defect_code_prefix:?string, name:string, client:?array{id:int, public_id:string, name:string, show_url:string}, unit:?array{id:int, public_id:string, name:string, show_url:string}, show_url:string}
     */
    private function inspectionEquipmentPayload(Equipment $equipment): array
    {
        return [
            'id' => $equipment->id,
            'public_id' => $equipment->public_id,
            'tag' => $equipment->tag,
            'defect_code_prefix' => $equipment->defect_code_prefix,
            'name' => $equipment->name,
            'client' => $equipment->client === null
                ? null
                : [
                    'id' => $equipment->client->id,
                    'public_id' => $equipment->client->public_id,
                    'name' => $equipment->client->name,
                    'show_url' => route('clients.show', $equipment->client),
                ],
            'unit' => $equipment->unit === null
                ? null
                : [
                    'id' => $equipment->unit->id,
                    'public_id' => $equipment->unit->public_id,
                    'name' => $equipment->unit->name,
                    'show_url' => route('units.show', $equipment->unit),
                ],
            'show_url' => route('equipments.show', $equipment),
        ];
    }

    /**
     * @return array{id:int, public_id:string, code:string, title:string, origin_description:?string, category:string, category_label:string, status:string, status_label:string, sequence_number:int, latest_assessment:?array{id:int, public_id:string, condition:string, condition_label:string, status:string, status_label:string, assessed_at:?string}, show_url:string}
     */
    private function defectPayload(Request $request, Inspection $inspection, Defect $defect): array
    {
        $currentAssessment = $this->currentDefectAssessment($defect, $inspection);
        $latestAssessment = $currentAssessment ?? $defect->latestAssessment;
        $previousAssessment = $latestAssessment?->previousAssessment;
        $canCreateAssessment = $currentAssessment === null
            && $request->user()->can('create', [DefectAssessment::class, $inspection, $defect]);
        $canUpdateAssessment = $currentAssessment !== null && $request->user()->can('update', $currentAssessment);
        $canCompleteAssessment = $currentAssessment !== null && $request->user()->can('complete', $currentAssessment);

        return [
            'id' => $defect->id,
            'public_id' => $defect->public_id,
            'code' => $defect->code,
            'title' => $defect->title,
            'origin_description' => $defect->origin_description,
            'category' => $defect->categoryCode(),
            'category_label' => $defect->categoryLabel(),
            'status' => $defect->status->value,
            'status_label' => $defect->status->label(),
            'sequence_number' => $defect->sequence_number,
            'latest_assessment' => $latestAssessment === null
                ? null
                : $this->defectAssessmentPayload($latestAssessment),
            'current_assessment' => $currentAssessment === null
                ? null
                : $this->defectAssessmentPayload($currentAssessment),
            'previous_assessment' => $previousAssessment === null
                ? null
                : $this->defectAssessmentPayload($previousAssessment),
            'latest_complete_assessment' => $defect->latestAssessment === null
                ? null
                : $this->defectAssessmentPayload($defect->latestAssessment),
            'assessment_state' => $currentAssessment?->status->value ?? 'not_assessed',
            'assessment_actions' => [
                'store_url' => $canCreateAssessment
                    ? route('inspections.defects.assessments.store', [$inspection, $defect])
                    : null,
                'update_url' => $canUpdateAssessment && $currentAssessment !== null
                    ? route('defect-assessments.update', $currentAssessment)
                    : null,
                'complete_url' => $canCompleteAssessment && $currentAssessment !== null
                    ? route('defect-assessments.complete', $currentAssessment)
                    : null,
            ],
            'can_assess' => $canCreateAssessment,
            'can_update_assessment' => $canUpdateAssessment,
            'can_complete_assessment' => $canCompleteAssessment,
            'show_url' => route('defects.show', $defect),
        ];
    }

    private function currentDefectAssessment(Defect $defect, Inspection $inspection): ?DefectAssessment
    {
        return $defect->assessments
            ->firstWhere('inspection_id', $inspection->getKey());
    }

    /**
     * @return array{id:int, public_id:string, condition:string, condition_label:string, status:string, status_label:string, location_description:?string, comment:?string, recommendation:?string, reason:?string, internal_notes:?string, assessed_at:?string, snapshot_version:int, defect_snapshot:array<string, mixed>, inspection:array{id:int, public_id:string, number:?string, show_url:string}, creator:?array{id:int, public_id:string, name:string}}
     */
    private function defectAssessmentPayload(DefectAssessment $assessment): array
    {
        return [
            'id' => $assessment->id,
            'public_id' => $assessment->public_id,
            'condition' => $assessment->condition->value,
            'condition_label' => $assessment->condition->label(),
            'status' => $assessment->status->value,
            'status_label' => $assessment->status->label(),
            'defect_classification_id' => $assessment->defect_classification_id,
            'classification_code' => $assessment->classification_code,
            'classification' => $assessment->classification === null ? null : [
                'code' => $assessment->classification->code,
                'name' => $assessment->classification->name,
                'status' => $assessment->classification->status->value,
                'severity_rank' => $assessment->classification->severity_rank,
            ],
            'location_description' => $assessment->location_description,
            'comment' => $assessment->comment,
            'recommendation' => $assessment->recommendation,
            'reason' => $assessment->reason,
            'internal_notes' => $assessment->internal_notes,
            'assessed_at' => $assessment->assessed_at?->toDateTimeString(),
            'snapshot_version' => (int) $assessment->snapshot_version,
            'defect_snapshot' => $assessment->defect_snapshot ?? [],
            'inspection' => [
                'id' => $assessment->inspection->id,
                'public_id' => $assessment->inspection->public_id,
                'number' => $assessment->inspection->number,
                'show_url' => route('inspections.show', $assessment->inspection),
            ],
            'creator' => $assessment->creator === null
                ? null
                : [
                    'id' => $assessment->creator->id,
                    'public_id' => $assessment->creator->public_id,
                    'name' => $assessment->creator->name,
                ],
        ];
    }

    /**
     * @return array{id:int, name:string, responsibility:string, responsibility_label:string, is_primary:bool, assigned_at:?string, completed_at:?string, user:array{id:int, public_id:string, name:string}, set_primary_url:string, destroy_url:string}
     */
    private function inspectionResponsiblePayload(Inspection $inspection, InspectionResponsible $responsible): array
    {
        return [
            'id' => $responsible->id,
            'name' => $responsible->user->name,
            'responsibility' => $responsible->responsibility->value,
            'responsibility_label' => $responsible->responsibility->label(),
            'is_primary' => (bool) $responsible->is_primary,
            'assigned_at' => $responsible->assigned_at?->format('d/m/Y H:i'),
            'completed_at' => $responsible->completed_at?->format('d/m/Y H:i'),
            'user' => [
                'id' => $responsible->user->id,
                'public_id' => $responsible->user->public_id,
                'name' => $responsible->user->name,
            ],
            'set_primary_url' => route('inspections.responsibles.update', [$inspection, $responsible]),
            'destroy_url' => route('inspections.responsibles.destroy', [$inspection, $responsible]),
        ];
    }

    /**
     * @return array{id:int, created_at:string, document:array{id:int, public_id:string, document_group:string, document_type:string, document_type_label:string, title:string, document_number:?string, revision:?string, description:?string, original_name:string, mime_type:string, extension:?string, size:int, checksum:string, is_current:bool, status:string, status_label:string, issued_at:?string, created_at:?string, updated_at:?string, download_url:string, show_url:string, uploaded_by:?array{id:int, public_id:string, name:string}}, added_by:?array{id:int, public_id:string, name:string}, delete_url:string}
     */
    private function inspectionReferenceDocumentPayload(
        Inspection $inspection,
        InspectionReferenceDocument $referenceDocument,
    ): array {
        return [
            'id' => $referenceDocument->id,
            'created_at' => $referenceDocument->created_at?->format('d/m/Y H:i'),
            'document' => $this->equipmentDocumentPayload($referenceDocument->document),
            'added_by' => $referenceDocument->actor === null
                ? null
                : [
                    'id' => $referenceDocument->actor->id,
                    'public_id' => $referenceDocument->actor->public_id,
                    'name' => $referenceDocument->actor->name,
                ],
            'delete_url' => route('inspections.reference-documents.destroy', [$inspection, $referenceDocument]),
        ];
    }

    /**
     * @return array<int, array{id:int, public_id:string, document_group:string, document_type:string, document_type_label:string, title:string, document_number:?string, revision:?string, description:?string, original_name:string, mime_type:string, extension:?string, size:int, checksum:string, is_current:bool, status:string, status_label:string, issued_at:?string, created_at:?string, updated_at:?string, download_url:string, show_url:string, uploaded_by:?array{id:int, public_id:string, name:string}}>
     */
    private function availableReferenceDocumentOptions(TenantContext $tenant, Inspection $inspection): array
    {
        return EquipmentDocument::query()
            ->forOrganization($tenant->id())
            ->where('equipment_id', $inspection->equipment_id)
            ->with('uploader')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (EquipmentDocument $document): array => $this->equipmentDocumentPayload($document))
            ->values()
            ->all();
    }

    /**
     * @return array{id:int, public_id:string, document_group:string, document_type:string, document_type_label:string, title:string, document_number:?string, revision:?string, description:?string, original_name:string, mime_type:string, extension:?string, size:int, checksum:string, is_current:bool, status:string, status_label:string, issued_at:?string, created_at:?string, updated_at:?string, download_url:string, show_url:string, uploaded_by:?array{id:int, public_id:string, name:string}}
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
            'status_label' => $document->status->label(),
            'issued_at' => $document->issued_at?->toDateString(),
            'created_at' => $document->created_at?->toDateTimeString(),
            'updated_at' => $document->updated_at?->toDateTimeString(),
            'download_url' => route('equipment-documents.download', $document),
            'show_url' => route('equipment-documents.show', $document),
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
     * @return array<int, array{key:string, label:string, description:?string, action:string, requires_justification:bool}>
     */
    private function availableTransitions(Request $request, Inspection $inspection): array
    {
        $transitions = [];

        if ($request->user()->can('start', $inspection)) {
            $transitions[] = [
                'key' => 'start',
                'label' => 'Iniciar inspeção',
                'description' => 'Muda a inspeção de planejada para em inspeção.',
                'action' => route('inspections.start', $inspection),
                'requires_justification' => false,
            ];
        }

        if ($request->user()->can('submitForReview', $inspection)) {
            $transitions[] = [
                'key' => 'submit_for_review',
                'label' => $inspection->status === InspectionStatus::InCorrection
                    ? 'Reenviar para verificação'
                    : 'Enviar para verificação',
                'description' => $inspection->status === InspectionStatus::InCorrection
                    ? 'Retoma o fluxo após a correção.'
                    : 'Envia a inspeção concluída para a verificação.',
                'action' => route('inspections.submit-for-review', $inspection),
                'requires_justification' => false,
            ];
        }

        if ($request->user()->can('returnForCorrection', $inspection)) {
            $transitions[] = [
                'key' => 'return_for_correction',
                'label' => $inspection->status === InspectionStatus::AwaitingApproval
                    ? 'Devolver para correção'
                    : 'Solicitar correção',
                'description' => $inspection->status === InspectionStatus::AwaitingApproval
                    ? 'Retorna a inspeção para ajustes antes da aprovação.'
                    : 'Retorna a inspeção para ajustes após a verificação.',
                'action' => route('inspections.return-for-correction', $inspection),
                'requires_justification' => true,
            ];
        }

        if ($request->user()->can('completeReview', $inspection)) {
            $transitions[] = [
                'key' => 'complete_review',
                'label' => 'Concluir verificação',
                'description' => 'Avança a inspeção para aguardando aprovação.',
                'action' => route('inspections.complete-review', $inspection),
                'requires_justification' => false,
            ];
        }

        if ($request->user()->can('approve', $inspection)) {
            $transitions[] = [
                'key' => 'approve',
                'label' => 'Aprovar inspeção',
                'description' => 'Registra a aprovação técnica da inspeção.',
                'action' => route('inspections.approve', $inspection),
                'requires_justification' => false,
            ];
        }

        if ($request->user()->can('generateReport', $inspection)) {
            $transitions[] = [
                'key' => 'generate_report',
                'label' => 'Gerar relatório',
                'description' => 'Registra a geração do relatório e prepara a inspeção para liberação.',
                'action' => route('inspections.generate-report', $inspection),
                'requires_justification' => false,
            ];
        }

        if ($request->user()->can('release', $inspection)) {
            $transitions[] = [
                'key' => 'release',
                'label' => 'Liberar inspeção',
                'description' => 'Finaliza a liberação depois do relatório gerado.',
                'action' => route('inspections.release', $inspection),
                'requires_justification' => false,
            ];
        }

        if ($request->user()->can('cancel', $inspection)) {
            $transitions[] = [
                'key' => 'cancel',
                'label' => 'Cancelar inspeção',
                'description' => 'Cancela a inspeção atual com justificativa.',
                'action' => route('inspections.cancel', $inspection),
                'requires_justification' => true,
            ];
        }

        return $transitions;
    }

    /**
     * @return array{id:int, from_status:?string, to_status:string, reason:?string, justification:?string, created_at:string, user:?array{id:int, public_id:string, name:string}}
     */
    private function inspectionHistoryPayload(InspectionStatusHistory $history): array
    {
        return [
            'id' => $history->id,
            'from_status' => $history->from_status?->value,
            'to_status' => $history->to_status->value,
            'reason' => $history->reason,
            'justification' => $history->reason,
            'created_at' => $history->created_at->format('d/m/Y H:i'),
            'user' => $history->actor === null
                ? null
                : [
                    'id' => $history->actor->id,
                    'public_id' => $history->actor->public_id,
                    'name' => $history->actor->name,
                ],
        ];
    }

    private function filterValue(Request $request, string $key, string $fallbackKey): string
    {
        $value = trim((string) $request->string($key));

        if ($value === '') {
            $value = trim((string) $request->string($fallbackKey));
        }

        return $value;
    }
}
