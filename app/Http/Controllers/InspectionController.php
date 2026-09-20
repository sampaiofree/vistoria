<?php

namespace App\Http\Controllers;

use App\Actions\InspectionLocations\BuildInspectionLocationSnapshot;
use App\Actions\Inspections\CreateInspectionBatch;
use App\Actions\Inspections\UpdateGeneralAspects;
use App\Actions\Inspections\UpdatePlannedInspection;
use App\Actions\Inspections\UpdateReportMetadata;
use App\Actions\Inspections\UpdateInspectionReportRevision;
use App\Enums\AtmosphericCorrosivity;
use App\Enums\DefectCategory;
use App\Enums\EquipmentRevisionEmissionType;
use App\Enums\EquipmentStatus;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use App\Enums\OperationalRole;
use App\Enums\RegistrationStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Concerns\ResolvesTenantStructure;
use App\Http\Requests\Inspections\ConfirmInspectionBatchRequest;
use App\Http\Requests\Inspections\PreviewInspectionBatchRequest;
use App\Http\Requests\Inspections\UpdateGeneralAspectsRequest;
use App\Http\Requests\Inspections\UpdatePlannedInspectionRequest;
use App\Http\Requests\Inspections\UpdateReportMetadataRequest;
use App\Http\Requests\Inspections\UpdateInspectionReportRevisionRequest;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Equipment;
use App\Models\EquipmentDocument;
use App\Models\Inspection;
use App\Models\InspectionReferenceDocument;
use App\Models\InspectionResponsible;
use App\Models\InspectionStatusHistory;
use App\Models\User;
use App\Services\InspectionLocations\InspectionLocationReportComposer;
use App\Services\InspectionLocations\InspectionLocationReportSequenceComposer;
use App\Services\Inspections\InspectionReadModelPresenter;
use App\Services\Reports\GeneralAspectsDocument;
use App\Services\Tenancy\TenantContext;
use App\Support\TextNormalizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
                'responsibles.user:id,public_id,name',
                'statusHistories:id,inspection_id,to_status,created_at',
            ])
            ->when(! $request->user()->isCompanyAdmin(), fn ($query) => $query
                ->whereHas('responsibles', fn ($responsibles) => $responsibles
                    ->where('user_id', $request->user()->getKey())))
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
                                ->orWhere('maintenance_item_code', 'like', '%'.$tagSearch.'%')
                                ->orWhere('tag', 'like', '%'.$search.'%')
                                ->orWhere('name', 'like', '%'.$search.'%')
                                ->orWhereHas('client', fn ($client) => $client->where('name', 'like', '%'.$search.'%'));
                        })
                        ->orWhereHas('responsibles.user', fn ($responsibles) => $responsibles->where('name', 'like', '%'.$search.'%'));
                });
            })
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
            ->when($filters['scheduled_from'] !== '', fn ($query) => $query->whereDate('planned_start_on', '>=', $filters['scheduled_from']))
            ->when($filters['scheduled_to'] !== '', fn ($query) => $query->whereDate('planned_start_on', '<=', $filters['scheduled_to']))
            ->when($filters['inspected_from'] !== '', fn ($query) => $query->whereDate('inspected_on', '>=', $filters['inspected_from']))
            ->when($filters['inspected_to'] !== '', fn ($query) => $query->whereDate('inspected_on', '<=', $filters['inspected_to']))
            ->orderByRaw('CASE WHEN planned_start_on IS NULL THEN 1 ELSE 0 END')
            ->orderBy('planned_start_on')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Inspection $inspection): array => $this->inspectionListPayload($request, $inspection));

        return Inertia::render('Inspections/Index', [
            'inspections' => $inspections,
            'filters' => $filters,
            'options' => [
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

    public function create(Request $request, TenantContext $tenant): InertiaResponse|RedirectResponse
    {
        $this->authorizePlanningCreation($request->user());

        $preview = null;
        $token = trim((string) $request->string('preview'));

        if ($token !== '') {
            $cachedPreview = Cache::get($this->batchPreviewCacheKey($token));

            if (! is_array($cachedPreview)
                || ($cachedPreview['user_id'] ?? null) !== $request->user()->getKey()
                || ($cachedPreview['organization_id'] ?? null) !== $tenant->id()) {
                return redirect()
                    ->route('inspections.create')
                    ->with('error', 'A prévia expirou ou não está disponível. Revise e envie o planejamento novamente.');
            }

            $preview = [
                'token' => $token,
                'inspections' => $cachedPreview['inspections'],
            ];
        }

        return Inertia::render('Inspections/Create', [
            'preview_action' => route('inspections.store'),
            'confirm_action' => route('inspections.confirm'),
            'cancel_url' => route('inspections.index'),
            'equipment_search_url' => route('inspections.equipment-options'),
            'selected_equipment' => $this->availableEquipmentOptions(
                $tenant,
                equipmentIds: collect($preview['inspections'] ?? [])
                    ->pluck('equipment_id')
                    ->filter()
                    ->map(fn (mixed $id): int => (int) $id)
                    ->all(),
            ),
            'inspectors' => $this->inspectorOptions($tenant),
            'preview' => $preview,
        ]);
    }

    public function equipmentOptions(Request $request, TenantContext $tenant): JsonResponse
    {
        $this->authorizePlanningCreation($request->user());

        $search = trim((string) $request->string('search'));

        if (Str::length($search) < 2) {
            return response()->json(['equipment' => []]);
        }

        return response()->json([
            'equipment' => $this->availableEquipmentOptions($tenant, $search),
        ]);
    }

    public function store(
        PreviewInspectionBatchRequest $request,
        TenantContext $tenant,
        CreateInspectionBatch $action,
    ): RedirectResponse {
        $this->authorizePlanningCreation($request->user());

        $inspections = array_values($request->validated('inspections'));
        $action->preview($request->user(), $inspections);
        $token = (string) Str::uuid();

        Cache::put($this->batchPreviewCacheKey($token), [
            'user_id' => $request->user()->getKey(),
            'organization_id' => $tenant->id(),
            'inspections' => $inspections,
        ], now()->addMinutes(30));

        return redirect()
            ->route('inspections.create', ['preview' => $token]);
    }

    public function confirm(
        ConfirmInspectionBatchRequest $request,
        TenantContext $tenant,
        CreateInspectionBatch $action,
    ): RedirectResponse {
        $this->authorizePlanningCreation($request->user());

        $token = $request->validated('token');
        $cachedPreview = Cache::get($this->batchPreviewCacheKey($token));

        if (! is_array($cachedPreview)
            || ($cachedPreview['user_id'] ?? null) !== $request->user()->getKey()
            || ($cachedPreview['organization_id'] ?? null) !== $tenant->id()) {
            throw ValidationException::withMessages([
                'token' => 'A prévia expirou ou não está disponível. Revise e envie o planejamento novamente.',
            ]);
        }

        $inspections = $action->handle($request->user(), $cachedPreview['inspections']);
        Cache::forget($this->batchPreviewCacheKey($token));

        return redirect()
            ->route('inspections.index')
            ->with('success', sprintf('%d inspeç%s criada%s.', $inspections->count(), $inspections->count() === 1 ? 'ão' : 'ões', $inspections->count() === 1 ? '' : 's'));
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
                'planned_start_on' => $inspection->planned_start_on?->format('d/m/Y'),
                'planned_end_on' => $inspection->planned_end_on?->format('d/m/Y'),
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
                'photos_url' => route('inspections.photos', $inspection),
                'documents_url' => route('inspections.documents', $inspection),
                'history_url' => route('inspections.history', $inspection),
                'report_url' => route('inspections.report-preview', $inspection),
                'report_revision' => $inspection->report_revision,
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
                'update_report_revision' => $request->user()->can('updateReportRevision', $inspection)
                    ? ['action' => route('inspections.report-revision.update', $inspection)]
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
            'report_metadata' => $this->reportMetadataPayload(
                $inspection,
                $canManageReportMetadata,
                $request->user()->operational_role !== OperationalRole::Inspector,
            ),
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
                'roles' => InspectionResponsibility::options(),
            ],
            'tabs' => [
                ['key' => 'overview', 'label' => 'Visão geral', 'url' => route('inspections.show', $inspection)],
                ['key' => 'report_overview', 'label' => 'Vista geral', 'url' => route('inspections.report-overview', $inspection)],
                ['key' => 'defects', 'label' => 'Avarias', 'url' => route('inspections.defects', $inspection)],
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
                            'categories' => DefectCategory::options(),
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
            'report_metadata' => $this->reportMetadataPayload(
                $inspection,
                $canManageReportMetadata,
                $request->user()->operational_role !== OperationalRole::Inspector,
            ),
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
            'previousInspection.equipment',
            'responsibles.user',
        ]);

        $inspector = $inspection->responsibles
            ->where('responsibility', InspectionResponsibility::Reviewer)
            ->firstWhere('is_primary', true)
            ?? $inspection->responsibles->firstWhere('responsibility', InspectionResponsibility::Reviewer);

        return Inertia::render('Inspections/Edit', [
            'inspection' => $this->inspectionDetailPayload($request, $inspection),
            'equipment_options' => $this->planningEquipmentOptions($tenant),
            'inspectors' => $this->inspectorOptions($tenant),
            'selected_inspector_id' => $inspector?->user_id,
            'atmospheric_options' => AtmosphericCorrosivity::options(),
            'action' => route('inspections.update', $inspection),
            'cancel_url' => route('inspections.show', $inspection),
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

    public function updateReportRevision(
        UpdateInspectionReportRevisionRequest $request,
        TenantContext $tenant,
        Inspection $inspection,
        UpdateInspectionReportRevision $action,
    ): RedirectResponse {
        $inspection = $this->tenantInspection($tenant, $inspection);
        $this->authorize('updateReportRevision', $inspection);

        $action->handle($inspection, $request->user(), $request->validated('report_revision'));

        return redirect()
            ->route('inspections.show', $inspection)
            ->with('success', 'Revisão atualizada.');
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
    private function planningEquipmentOptions(TenantContext $tenant): array
    {
        return Equipment::query()
            ->forOrganization($tenant->id())
            ->where('status', EquipmentStatus::Active->value)
            ->whereHas('client', fn ($client) => $client->where('status', RegistrationStatus::Active->value))
            ->orderBy('tag')
            ->get(['id', 'tag', 'name'])
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
     * @return array<int, array{id:int, public_id:string, maintenance_item_code:string, tag:string, description:?string, name:string}>
     */
    private function availableEquipmentOptions(TenantContext $tenant, ?string $search = null, array $equipmentIds = []): array
    {
        if ($search === null && $equipmentIds === []) {
            return [];
        }

        $query = Equipment::query()
            ->forOrganization($tenant->id())
            ->where('status', EquipmentStatus::Active->value)
            ->whereHas('client', fn ($client) => $client->where('status', RegistrationStatus::Active->value));

        if ($equipmentIds !== []) {
            $query->whereKey($equipmentIds);
        }

        if ($search !== null) {
            $normalizedTagSearch = TextNormalizer::equipmentTag($search);

            $query->where(function ($equipment) use ($search, $normalizedTagSearch): void {
                $equipment
                    ->where('maintenance_item_code', 'like', '%'.$search.'%')
                    ->orWhere('tag', 'like', '%'.$search.'%')
                    ->orWhere('normalized_tag', 'like', '%'.$normalizedTagSearch.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
            });
        }

        return $query
            ->orderBy('maintenance_item_code')
            ->limit($search === null ? count($equipmentIds) : 20)
            ->get(['id', 'public_id', 'maintenance_item_code', 'tag', 'description', 'name'])
            ->values()
            ->map(fn (Equipment $equipment): array => [
                'id' => $equipment->id,
                'public_id' => $equipment->public_id,
                'maintenance_item_code' => $equipment->maintenance_item_code,
                'tag' => $equipment->tag,
                'description' => $equipment->description,
                'name' => $equipment->name,
            ])
            ->all();
    }

    /** @return array<int, array{id:int, public_id:string, name:string}> */
    private function inspectorOptions(TenantContext $tenant): array
    {
        return User::query()
            ->where('organization_id', $tenant->id())
            ->where('status', UserStatus::Active->value)
            ->where('operational_role', OperationalRole::Inspector->value)
            ->orderBy('name')
            ->get(['id', 'public_id', 'name'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'public_id' => $user->public_id,
                'name' => $user->name,
            ])
            ->all();
    }

    private function batchPreviewCacheKey(string $token): string
    {
        return 'inspection-batch-preview:'.$token;
    }

    private function authorizePlanningCreation(User $user): void
    {
        if (! $user->can('create', Inspection::class)) {
            throw new AuthorizationException('Somente usuários ativos com papel Planejador podem criar inspeções.');
        }
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
            ['key' => 'photos', 'label' => 'Fotografias', 'url' => route('inspections.photos', $inspection)],
            ['key' => 'documents', 'label' => 'Documentos', 'url' => route('inspections.documents', $inspection)],
            ['key' => 'history', 'label' => 'Histórico', 'url' => route('inspections.history', $inspection)],
            ['key' => 'report', 'label' => 'Relatório', 'url' => route('inspections.report-preview', $inspection)],
        ];
    }

    /**
     * @return array<string, mixed>
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
            'released_at' => $inspection->released_at?->toISOString(),
            'service_order' => $inspection->service_order,
            'report_date' => $inspection->report_date?->toDateString(),
            'report_revision' => $inspection->report_revision,
            'emission_type' => $inspection->emission_type?->value,
            'emission_type_label' => $inspection->emission_type?->label(),
            'first_page_text_template' => $inspection->first_page_text_template,
            'external_report_number' => $inspection->external_report_number,
            'report_designer' => $inspection->report_designer,
            'designer_i_report_number' => $inspection->designer_i_report_number,
            'procedure_number' => $inspection->procedure_number,
            'atmospheric_classification' => $inspection->atmospheric_classification,
            'planned_start_on' => $inspection->planned_start_on?->format('d/m/Y'),
            'planned_end_on' => $inspection->planned_end_on?->format('d/m/Y'),
            'planned_start_on_input' => $inspection->planned_start_on?->toDateString(),
            'planned_end_on_input' => $inspection->planned_end_on?->toDateString(),
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
    private function reportMetadataPayload(
        Inspection $inspection,
        bool $canEdit,
        bool $canEditRestrictedFields,
    ): array {
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
            'can_edit_restricted_fields' => $canEdit && $canEditRestrictedFields,
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
     * @return array<string, mixed>
     */
    private function inspectionListPayload(Request $request, Inspection $inspection): array
    {
        $stageResponsibles = collect([
            'planner' => InspectionResponsibility::Preparer,
            'inspector' => InspectionResponsibility::Reviewer,
            'reviewer' => InspectionResponsibility::Approver,
            'releaser' => InspectionResponsibility::Releaser,
        ])->mapWithKeys(function (InspectionResponsibility $responsibility, string $stage) use ($inspection): array {
            $assignments = $inspection->responsibles
                ->filter(fn (InspectionResponsible $assignment): bool => $assignment->responsibility === $responsibility);
            $responsible = $assignments->firstWhere('is_primary', true) ?? $assignments->first();

            return [$stage => $responsible?->user?->name];
        })->all();

        return [
            'id' => $inspection->id,
            'public_id' => $inspection->public_id,
            'number' => $inspection->number,
            'inspection_type' => $inspection->inspection_type->value,
            'type' => $inspection->inspection_type->value,
            'inspection_type_label' => $inspection->inspection_type->label(),
            'status' => $inspection->status->value,
            'status_label' => $inspection->status->label(),
            'status_milestone' => $this->inspectionStatusMilestonePayload($inspection),
            'planned_start_on' => $inspection->planned_start_on?->format('d/m/Y'),
            'planned_end_on' => $inspection->planned_end_on?->format('d/m/Y'),
            'inspected_on' => $inspection->inspected_on?->format('d/m/Y'),
            'inspected_at' => $inspection->inspected_on?->format('d/m/Y'),
            'equipment' => [
                'id' => $inspection->equipment->id,
                'public_id' => $inspection->equipment->public_id,
                'tag' => $inspection->equipment->tag,
                'name' => $inspection->equipment->name,
                'show_url' => route('equipments.show', $inspection->equipment),
            ],
            'stage_responsibles' => $stageResponsibles,
            'edit_url' => $inspection->status === InspectionStatus::Planned
                && $request->user()->can('updatePlanned', $inspection)
                ? route('inspections.edit', $inspection)
                : null,
            'show_url' => route('inspections.show', $inspection),
        ];
    }

    /**
     * @return array{label:string,value:?string}
     */
    private function inspectionStatusMilestonePayload(Inspection $inspection): array
    {
        $historyDate = function (InspectionStatus $status) use ($inspection): ?string {
            $history = $inspection->statusHistories
                ->filter(fn (InspectionStatusHistory $history): bool => $history->to_status === $status)
                ->last();

            return $history?->created_at?->format('d/m/Y');
        };

        return match ($inspection->status) {
            InspectionStatus::Planned => [
                'label' => 'Planejamento',
                'value' => match (true) {
                    $inspection->planned_start_on !== null && $inspection->planned_end_on !== null
                        && ! $inspection->planned_start_on->isSameDay($inspection->planned_end_on) => sprintf(
                            '%s a %s',
                            $inspection->planned_start_on->format('d/m/Y'),
                            $inspection->planned_end_on->format('d/m/Y'),
                        ),
                    $inspection->planned_start_on !== null => $inspection->planned_start_on->format('d/m/Y'),
                    $inspection->planned_end_on !== null => $inspection->planned_end_on->format('d/m/Y'),
                    default => null,
                },
            ],
            InspectionStatus::InProgress => ['label' => 'Iniciada em', 'value' => $inspection->started_at?->format('d/m/Y') ?? $inspection->inspected_on?->format('d/m/Y')],
            InspectionStatus::AwaitingReview => ['label' => 'Concluída em', 'value' => $inspection->field_completed_at?->format('d/m/Y')],
            InspectionStatus::InReview => ['label' => 'Revisão iniciada em', 'value' => $historyDate(InspectionStatus::InReview)],
            InspectionStatus::InCorrection => ['label' => 'Devolvida em', 'value' => $historyDate(InspectionStatus::InCorrection)],
            InspectionStatus::AwaitingRelease => ['label' => 'Aprovada em', 'value' => $inspection->approved_at?->format('d/m/Y')],
            InspectionStatus::Released => ['label' => 'Liberada em', 'value' => $inspection->released_at?->format('d/m/Y')],
            InspectionStatus::Canceled => ['label' => 'Cancelada em', 'value' => $inspection->canceled_at?->format('d/m/Y')],
        };
    }

    /**
     * @return array{id:int, public_id:string, tag:string, defect_code_prefix:?string, name:string, client:?array{id:int, public_id:string, name:string, show_url:string}, show_url:string}
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
            'classification_code' => $assessment->classification_code,
            'classification' => $assessment->classification_snapshot,
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
                    ? 'Reenviar para revisão'
                    : 'Enviar para revisão',
                'description' => $inspection->status === InspectionStatus::InCorrection
                    ? 'Retoma o fluxo após a correção.'
                    : 'Envia a inspeção concluída para a revisão.',
                'action' => route('inspections.submit-for-review', $inspection),
                'requires_justification' => false,
            ];
        }

        if ($request->user()->can('returnForCorrection', $inspection)) {
            $transitions[] = [
                'key' => 'return_for_correction',
                'label' => 'Enviar para correção',
                'description' => 'Retorna a inspeção ao Inspetor para ajustes.',
                'action' => route('inspections.return-for-correction', $inspection),
                'requires_justification' => true,
            ];
        }

        if ($request->user()->can('startReview', $inspection)) {
            $transitions[] = [
                'key' => 'start_review',
                'label' => 'Iniciar revisão',
                'description' => 'Inicia a análise técnica pelo Revisor.',
                'action' => route('inspections.start-review', $inspection),
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

        if ($request->user()->can('release', $inspection)) {
            $transitions[] = [
                'key' => 'release',
                'label' => 'Liberar inspeção',
                'description' => 'Finaliza a liberação da inspeção.',
                'action' => route('inspections.release', $inspection),
                'requires_justification' => false,
            ];
        }

        if ($request->user()->can('returnForReview', $inspection)) {
            $transitions[] = [
                'key' => 'return_for_review',
                'label' => 'Devolver para revisão',
                'description' => 'Retorna a inspeção para uma nova revisão.',
                'action' => route('inspections.return-for-review', $inspection),
                'requires_justification' => true,
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
