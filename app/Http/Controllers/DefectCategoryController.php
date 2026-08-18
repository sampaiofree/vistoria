<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Classification\ChangeDefectCategoryStatus;
use App\Actions\Classification\CreateDefectCategory;
use App\Actions\Classification\SaveDefectCategoryGut;
use App\Actions\Classification\UpdateDefectCategory;
use App\Enums\DefectAssessmentCondition;
use App\Enums\InspectionStatus;
use App\Enums\RegistrationStatus;
use App\Http\Requests\Classification\StoreDefectCategoryRequest;
use App\Http\Requests\Classification\UpdateDefectCategoryGutRequest;
use App\Http\Requests\Classification\UpdateDefectCategoryRequest;
use App\Http\Requests\UpdateRegistrationStatusRequest;
use App\Models\DefectAssessment;
use App\Models\DefectCategory;
use App\Models\DefectClassification;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class DefectCategoryController extends Controller
{
    public function index(Request $request, TenantContext $tenant): InertiaResponse
    {
        $this->authorize('viewAny', DefectCategory::class);

        $categories = DefectCategory::query()
            ->forOrganization($tenant->id())
            ->withCount('classifications')
            ->orderBy('position')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DefectCategory $category): array => $this->payload($request, $category));

        return Inertia::render('DefectCategories/Index', [
            'categories' => $categories,
            'can' => ['create' => $request->user()->can('create', DefectCategory::class)],
            'create_url' => route('defect-categories.create'),
        ]);
    }

    public function create(): InertiaResponse
    {
        $this->authorize('create', DefectCategory::class);

        return Inertia::render('DefectCategories/Create', [
            'action' => route('defect-categories.store'),
            'cancel_url' => route('defect-categories.index'),
        ]);
    }

    public function store(StoreDefectCategoryRequest $request, CreateDefectCategory $action): RedirectResponse
    {
        $this->authorize('create', DefectCategory::class);
        $category = $action->handle($request->user(), $request->validated());

        return redirect()->route('defect-categories.show', $category)->with('success', 'Categoria criada.');
    }

    public function show(Request $request, TenantContext $tenant, DefectCategory $defectCategory): InertiaResponse
    {
        $category = $this->tenantCategory($tenant, $defectCategory);
        $this->authorize('view', $category);
        $category->load(['classifications', 'gutOptions']);

        return Inertia::render('DefectCategories/Show', [
            'category' => [
                ...$this->payload($request, $category),
                'description' => $category->description,
                'classifications' => $category->classifications->map(fn ($classification): array => [
                    'public_id' => $classification->public_id,
                    'code' => $classification->code,
                    'name' => $classification->name,
                    'description' => $classification->description,
                    'color' => $classification->color,
                    'status' => $classification->status->value,
                    'position' => $classification->position,
                    'severity_rank' => $classification->severity_rank,
                    'edit_url' => route('defect-classifications.edit', $classification),
                    'status_url' => route('defect-classifications.status', $classification),
                ])->values(),
                'gut' => $this->gutPayload($category),
            ],
            'can' => [
                'update' => $request->user()->can('update', $category),
                'change_status' => $request->user()->can('changeStatus', $category),
                'create_classification' => $request->user()->can('create', DefectClassification::class),
            ],
            'edit_url' => route('defect-categories.edit', $category),
            'classification_create_url' => route('defect-categories.classifications.create', $category),
            'gut_edit_url' => route('defect-categories.gut.edit', $category),
        ]);
    }

    public function editGut(TenantContext $tenant, DefectCategory $defectCategory): InertiaResponse
    {
        $category = $this->tenantCategory($tenant, $defectCategory);
        $this->authorize('update', $category);
        $category->load('gutOptions');

        return Inertia::render('DefectCategories/Gut', [
            'category' => [
                'public_id' => $category->public_id,
                'name' => $category->name,
                'code' => $category->code,
            ],
            'gut' => $this->gutPayload($category),
            'action' => route('defect-categories.gut.update', $category),
            'cancel_url' => route('defect-categories.show', $category),
        ]);
    }

    public function updateGut(
        UpdateDefectCategoryGutRequest $request,
        TenantContext $tenant,
        DefectCategory $defectCategory,
        SaveDefectCategoryGut $action,
    ): RedirectResponse {
        $category = $this->tenantCategory($tenant, $defectCategory);
        $this->authorize('update', $category);
        $action->handle($request->user(), $category, $request->validated());

        return redirect()->route('defect-categories.show', $category)->with('success', 'Configuração GUT atualizada.');
    }

    public function edit(TenantContext $tenant, DefectCategory $defectCategory): InertiaResponse
    {
        $category = $this->tenantCategory($tenant, $defectCategory);
        $this->authorize('update', $category);

        return Inertia::render('DefectCategories/Edit', [
            'category' => [
                'public_id' => $category->public_id,
                'name' => $category->name,
                'code' => $category->code,
                'description' => $category->description,
                'position' => $category->position,
                'requires_location_map' => $category->requires_location_map,
            ],
            'activation_impact' => $this->activationImpact($category),
            'action' => route('defect-categories.update', $category),
            'cancel_url' => route('defect-categories.show', $category),
        ]);
    }

    public function update(UpdateDefectCategoryRequest $request, TenantContext $tenant, DefectCategory $defectCategory, UpdateDefectCategory $action): RedirectResponse
    {
        $category = $this->tenantCategory($tenant, $defectCategory);
        $this->authorize('update', $category);
        $action->handle($request->user(), $category, $request->validated());

        return redirect()->route('defect-categories.show', $category)->with('success', 'Categoria atualizada.');
    }

    public function updateStatus(UpdateRegistrationStatusRequest $request, TenantContext $tenant, DefectCategory $defectCategory, ChangeDefectCategoryStatus $action): RedirectResponse
    {
        $category = $this->tenantCategory($tenant, $defectCategory);
        $this->authorize('changeStatus', $category);
        $action->handle($request->user(), $category, RegistrationStatus::from($request->validated('status')));

        return back()->with('success', 'Status da categoria atualizado.');
    }

    private function tenantCategory(TenantContext $tenant, DefectCategory $category): DefectCategory
    {
        return DefectCategory::query()->forOrganization($tenant->id())->findOrFail($category->getKey());
    }

    /** @return array<string, mixed> */
    private function payload(Request $request, DefectCategory $category): array
    {
        return [
            'public_id' => $category->public_id,
            'name' => $category->name,
            'code' => $category->code,
            'status' => $category->status->value,
            'requires_location_map' => $category->requires_location_map,
            'position' => $category->position,
            'classifications_count' => $category->classifications_count ?? $category->classifications->count(),
            'show_url' => route('defect-categories.show', $category),
            'edit_url' => route('defect-categories.edit', $category),
            'status_url' => route('defect-categories.status', $category),
            'can_update' => $request->user()->can('update', $category),
        ];
    }

    /** @return array{gravity:array<int,array<string,mixed>>,urgency:array<int,array<string,mixed>>,trend:array<int,array<string,mixed>>,configured:bool} */
    private function gutPayload(DefectCategory $category): array
    {
        $grouped = collect(['gravity', 'urgency', 'trend'])
            ->mapWithKeys(fn (string $criterion): array => [
                $criterion => $category->gutOptions
                    ->filter(fn ($option): bool => $option->criterion->value === $criterion)
                    ->map(fn ($option): array => [
                        'id' => $option->id,
                        'criterion' => $option->criterion->value,
                        'score' => $option->score,
                        'color' => $option->color,
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();

        return [
            ...$grouped,
            'configured' => collect($grouped)->contains(fn (array $options): bool => $options !== []),
        ];
    }

    /** @return array{open_inspections:int, unlocated_assessments:int} */
    private function activationImpact(DefectCategory $category): array
    {
        $assessments = DefectAssessment::query()
            ->forOrganization($category->organization_id)
            ->whereHas('defect', fn ($query) => $query->where('defect_category_id', $category->getKey()))
            ->whereNotIn('condition', [
                DefectAssessmentCondition::NotLocated->value,
                DefectAssessmentCondition::NotInspected->value,
            ])
            ->whereHas('inspection', fn ($query) => $query->whereNotIn('status', [
                InspectionStatus::Released->value,
                InspectionStatus::Canceled->value,
            ]));

        return [
            'open_inspections' => (clone $assessments)->distinct()->count('inspection_id'),
            'unlocated_assessments' => (clone $assessments)->whereDoesntHave('locationMarkers')->count(),
        ];
    }
}
