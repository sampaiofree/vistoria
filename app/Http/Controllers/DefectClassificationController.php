<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Classification\ChangeDefectClassificationStatus;
use App\Actions\Classification\CreateDefectClassification;
use App\Actions\Classification\UpdateDefectClassification;
use App\Enums\RegistrationStatus;
use App\Http\Requests\Classification\StoreDefectClassificationRequest;
use App\Http\Requests\Classification\UpdateDefectClassificationRequest;
use App\Http\Requests\UpdateRegistrationStatusRequest;
use App\Models\DefectCategory;
use App\Models\DefectClassification;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class DefectClassificationController extends Controller
{
    public function create(TenantContext $tenant, DefectCategory $defectCategory): InertiaResponse
    {
        $category = $this->tenantCategory($tenant, $defectCategory);
        $this->authorize('create', DefectClassification::class);

        return Inertia::render('DefectClassifications/Create', [
            'category' => ['public_id' => $category->public_id, 'name' => $category->name, 'code' => $category->code],
            'action' => route('defect-categories.classifications.store', $category),
            'cancel_url' => route('defect-categories.show', $category),
        ]);
    }

    public function store(StoreDefectClassificationRequest $request, TenantContext $tenant, DefectCategory $defectCategory, CreateDefectClassification $action): RedirectResponse
    {
        $category = $this->tenantCategory($tenant, $defectCategory);
        $this->authorize('create', DefectClassification::class);
        $classification = $action->handle($request->user(), $category, $request->validated());

        return redirect()->route('defect-categories.show', $category)->with('success', 'Classificação criada.');
    }

    public function edit(TenantContext $tenant, DefectClassification $defectClassification): InertiaResponse
    {
        $classification = $this->tenantClassification($tenant, $defectClassification);
        $this->authorize('update', $classification);

        return Inertia::render('DefectClassifications/Edit', [
            'classification' => [
                'public_id' => $classification->public_id,
                'code' => $classification->code,
                'name' => $classification->name,
                'description' => $classification->description,
                'color' => $classification->color,
                'position' => $classification->position,
                'severity_rank' => $classification->severity_rank,
            ],
            'category' => [
                'public_id' => $classification->category->public_id,
                'name' => $classification->category->name,
                'code' => $classification->category->code,
            ],
            'action' => route('defect-classifications.update', $classification),
            'cancel_url' => route('defect-categories.show', $classification->category),
        ]);
    }

    public function update(UpdateDefectClassificationRequest $request, TenantContext $tenant, DefectClassification $defectClassification, UpdateDefectClassification $action): RedirectResponse
    {
        $classification = $this->tenantClassification($tenant, $defectClassification);
        $this->authorize('update', $classification);
        $action->handle($request->user(), $classification, $request->validated());

        return redirect()->route('defect-categories.show', $classification->category)->with('success', 'Classificação atualizada.');
    }

    public function updateStatus(UpdateRegistrationStatusRequest $request, TenantContext $tenant, DefectClassification $defectClassification, ChangeDefectClassificationStatus $action): RedirectResponse
    {
        $classification = $this->tenantClassification($tenant, $defectClassification);
        $this->authorize('changeStatus', $classification);
        $action->handle($request->user(), $classification, RegistrationStatus::from($request->validated('status')));

        return back()->with('success', 'Status da classificação atualizado.');
    }

    private function tenantCategory(TenantContext $tenant, DefectCategory $category): DefectCategory
    {
        return DefectCategory::query()->forOrganization($tenant->id())->findOrFail($category->getKey());
    }

    private function tenantClassification(TenantContext $tenant, DefectClassification $classification): DefectClassification
    {
        return DefectClassification::query()
            ->forOrganization($tenant->id())
            ->with('category')
            ->findOrFail($classification->getKey());
    }
}
