<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Classification\CreateDefectAssessmentQuantity;
use App\Actions\Classification\DeleteDefectAssessmentQuantity;
use App\Actions\Classification\SaveDefectAssessmentGut;
use App\Actions\Classification\SaveDefectAssessmentTelClassification;
use App\Actions\Classification\UpdateDefectAssessmentQuantity;
use App\Actions\Defects\AssessExistingDefect;
use App\Actions\Defects\CompleteDefectAssessment;
use App\Actions\Defects\UpdateDefectAssessment;
use App\Actions\Photos\DeleteAssessmentPhoto;
use App\Actions\Photos\ReorderAssessmentPhotos;
use App\Actions\Photos\StoreAssessmentPhoto;
use App\Enums\DefectAssessmentStatus;
use App\Http\Controllers\Concerns\ResolvesTenantStructure;
use App\Http\Requests\AssessmentPhotos\StoreAssessmentPhotoRequest;
use App\Http\Requests\Defects\ChangeDefectAssessmentStatusRequest;
use App\Http\Requests\Defects\CompleteDefectAssessmentRequest;
use App\Http\Requests\Defects\StoreExistingDefectAssessmentRequest;
use App\Http\Requests\Defects\UpdateDefectAssessmentGutRequest;
use App\Http\Requests\Defects\UpdateDefectAssessmentQuantityRequest;
use App\Http\Requests\Defects\UpdateDefectAssessmentTelRequest;
use App\Http\Requests\Defects\UpdateDefectAssessmentRequest;
use App\Models\AssessmentPhoto;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\DefectAssessmentQuantity;
use App\Models\Inspection;
use App\Services\Inspections\InspectionReadModelPresenter;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class DefectAssessmentController extends Controller
{
    use ResolvesTenantStructure;

    public function show(
        TenantContext $tenant,
        Request $request,
        DefectAssessment $defectAssessment,
        InspectionReadModelPresenter $presenter,
    ): InertiaResponse {
        $defectAssessment = $this->tenantDefectAssessment($tenant, $defectAssessment);

        $this->authorize('view', $defectAssessment);

        return Inertia::render(
            'DefectAssessments/Show',
            $presenter->assessment($defectAssessment, $request->user()),
        );
    }

    public function store(
        StoreExistingDefectAssessmentRequest $request,
        TenantContext $tenant,
        Inspection $inspection,
        Defect $defect,
        AssessExistingDefect $action,
    ): RedirectResponse {
        $inspection = $this->tenantInspection($tenant, $inspection);
        $defect = $this->tenantDefect($tenant, $defect);

        $this->authorize('create', [DefectAssessment::class, $inspection, $defect]);

        $assessment = $action->handle(
            $request->user(),
            $inspection,
            $defect,
            $request->validated(),
        );

        return redirect()
            ->route('defect-assessments.show', $assessment)
            ->with('success', 'Avaliação registrada.');
    }

    public function update(
        UpdateDefectAssessmentRequest $request,
        TenantContext $tenant,
        DefectAssessment $defectAssessment,
        UpdateDefectAssessment $action,
    ): RedirectResponse {
        $defectAssessment = $this->tenantDefectAssessment($tenant, $defectAssessment);
        $defectAssessment->loadMissing(['defect', 'inspection']);

        $this->authorize('update', $defectAssessment);

        $action->handle(
            $request->user(),
            $defectAssessment,
            $request->validated(),
        );

        return redirect()
            ->route('defect-assessments.show', $defectAssessment)
            ->with('success', 'Avaliação atualizada.');
    }

    public function changeStatus(
        ChangeDefectAssessmentStatusRequest $request,
        TenantContext $tenant,
        DefectAssessment $defectAssessment,
        UpdateDefectAssessment $update,
        CompleteDefectAssessment $publish,
    ): RedirectResponse {
        $defectAssessment = $this->tenantDefectAssessment($tenant, $defectAssessment);
        $defectAssessment->loadMissing(['defect', 'inspection']);

        $this->authorize('changeStatus', $defectAssessment);

        $status = $request->validated('status');
        $wasPublished = $defectAssessment->isComplete();

        if ($status === DefectAssessmentStatus::Draft->value) {
            // Status changes intentionally ignore submitted content: a published
            // assessment must be explicitly reopened before it can be edited.
            $update->handle($request->user(), $defectAssessment, []);
        } elseif (! $wasPublished) {
            // A draft may be published using the values already saved on it.
            $publish->handle($request->user(), $defectAssessment);
        }

        return redirect()
            ->route('defect-assessments.show', $defectAssessment)
            ->with('success', match (true) {
                $status === DefectAssessmentStatus::Complete->value && $wasPublished => 'Avaliação já está publicada.',
                $status === DefectAssessmentStatus::Complete->value => 'Avaliação publicada.',
                default => 'Avaliação movida para rascunho.',
            });
    }

    public function storePhoto(
        StoreAssessmentPhotoRequest $request,
        TenantContext $tenant,
        DefectAssessment $defectAssessment,
        StoreAssessmentPhoto $action,
    ): RedirectResponse {
        $defectAssessment = $this->tenantDefectAssessment($tenant, $defectAssessment);
        $this->authorize('uploadPhoto', $defectAssessment);
        $action->handle($request->user(), $defectAssessment, $request->file('file'), $request->validated());

        return back()->with('success', 'Fotografia recebida para processamento.');
    }

    public function complete(
        CompleteDefectAssessmentRequest $request,
        TenantContext $tenant,
        DefectAssessment $defectAssessment,
        CompleteDefectAssessment $action,
        SaveDefectAssessmentGut $gut,
        SaveDefectAssessmentTelClassification $tel,
    ): RedirectResponse {
        $defectAssessment = $this->tenantDefectAssessment($tenant, $defectAssessment);
        $defectAssessment->loadMissing(['defect', 'inspection']);

        $this->authorize('complete', $defectAssessment);

        if ($defectAssessment->defect->category !== \App\Enums\DefectCategory::RoofCladding
            && collect(UpdateDefectAssessmentGutRequest::technicalFieldNames())
            ->contains(fn (string $field): bool => $request->exists($field))) {
            $gut->handle($request->user(), $defectAssessment, $request->validated());
        }
        if ($defectAssessment->defect->category === \App\Enums\DefectCategory::RoofCladding
            && collect(UpdateDefectAssessmentTelRequest::technicalFieldNames())
                ->contains(fn (string $field): bool => $request->exists($field))) {
            $tel->handle($request->user(), $defectAssessment, $request->validated());
        }

        $action->handle(
            $request->user(),
            $defectAssessment,
            $request->validated(),
        );

        return redirect()
            ->route('defect-assessments.show', $defectAssessment)
            ->with('success', 'Avaliação publicada.');
    }

    public function updateGut(
        UpdateDefectAssessmentGutRequest $request,
        TenantContext $tenant,
        DefectAssessment $defectAssessment,
        SaveDefectAssessmentGut $action,
    ): RedirectResponse {
        $defectAssessment = $this->tenantDefectAssessment($tenant, $defectAssessment);
        $this->authorize('update', $defectAssessment);
        $action->handle($request->user(), $defectAssessment, $request->validated());

        return back()->with('success', 'Classificação GUT atualizada.');
    }

    public function updateTel(
        UpdateDefectAssessmentTelRequest $request,
        TenantContext $tenant,
        DefectAssessment $defectAssessment,
        SaveDefectAssessmentTelClassification $action,
    ): RedirectResponse {
        $defectAssessment = $this->tenantDefectAssessment($tenant, $defectAssessment);
        $this->authorize('update', $defectAssessment);
        $action->handle($request->user(), $defectAssessment, $request->validated());

        return back()->with('success', 'Classificação TEL atualizada.');
    }

    public function storeQuantity(
        UpdateDefectAssessmentQuantityRequest $request,
        TenantContext $tenant,
        DefectAssessment $defectAssessment,
        CreateDefectAssessmentQuantity $action,
    ): RedirectResponse {
        $defectAssessment = $this->tenantDefectAssessment($tenant, $defectAssessment);
        $this->authorize('update', $defectAssessment);
        $action->handle($request->user(), $defectAssessment, $request->validated());

        return back()->with('success', 'Item do quantitativo adicionado.');
    }

    public function updateQuantity(
        UpdateDefectAssessmentQuantityRequest $request,
        TenantContext $tenant,
        DefectAssessmentQuantity $defectAssessmentQuantity,
        UpdateDefectAssessmentQuantity $action,
    ): RedirectResponse {
        $quantity = $this->tenantDefectAssessmentQuantity($tenant, $defectAssessmentQuantity);
        $quantity->loadMissing('assessment');
        $this->authorize('update', $quantity->assessment);
        $action->handle($request->user(), $quantity, $request->validated());

        return back()->with('success', 'Item do quantitativo atualizado.');
    }

    public function destroyQuantity(
        Request $request,
        TenantContext $tenant,
        DefectAssessmentQuantity $defectAssessmentQuantity,
        DeleteDefectAssessmentQuantity $action,
    ): RedirectResponse {
        $quantity = $this->tenantDefectAssessmentQuantity($tenant, $defectAssessmentQuantity);
        $quantity->loadMissing('assessment');
        $this->authorize('update', $quantity->assessment);
        $action->handle($request->user(), $quantity);

        return back()->with('success', 'Item do quantitativo excluído.');
    }

    public function reorderPhotos(
        Request $request,
        TenantContext $tenant,
        DefectAssessment $defectAssessment,
        ReorderAssessmentPhotos $action,
    ): RedirectResponse {
        $defectAssessment = $this->tenantDefectAssessment($tenant, $defectAssessment);
        $defectAssessment->loadMissing('photos');
        $this->authorize('update', $defectAssessment);

        $data = $request->validate(['photo_ids' => ['required', 'array'], 'photo_ids.*' => ['required', 'string']]);
        $action->handle($request->user(), $defectAssessment, $data['photo_ids']);

        return back()->with('success', 'Ordem das fotografias atualizada.');
    }

    public function destroyPhoto(
        Request $request,
        TenantContext $tenant,
        AssessmentPhoto $assessmentPhoto,
        DeleteAssessmentPhoto $action,
    ): RedirectResponse {
        $photo = $this->tenantAssessmentPhoto($tenant, $assessmentPhoto);
        $this->authorize('delete', $photo);
        $action->handle($request->user(), $photo);

        return back()->with('success', 'Fotografia removida.');
    }

    private function tenantAssessmentPhoto(TenantContext $tenant, AssessmentPhoto $photo): AssessmentPhoto
    {
        return AssessmentPhoto::query()
            ->forOrganization($tenant->id())
            ->with(['assessment', 'inspection'])
            ->whereKey($photo->getKey())
            ->firstOrFail();
    }
}
