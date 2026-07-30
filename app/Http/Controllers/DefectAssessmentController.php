<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Defects\AssessExistingDefect;
use App\Actions\Defects\CompleteDefectAssessment;
use App\Actions\Defects\UpdateDefectAssessment;
use App\Http\Controllers\Concerns\ResolvesTenantStructure;
use App\Http\Requests\Defects\CompleteDefectAssessmentRequest;
use App\Http\Requests\Defects\StoreExistingDefectAssessmentRequest;
use App\Http\Requests\Defects\UpdateDefectAssessmentRequest;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Inspection;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;

final class DefectAssessmentController extends Controller
{
    use ResolvesTenantStructure;

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

        $action->handle(
            $request->user(),
            $inspection,
            $defect,
            $request->validated(),
        );

        return redirect()
            ->route('inspections.show', $inspection)
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
            ->route('defects.show', $defectAssessment->defect)
            ->with('success', 'Avaliação atualizada.');
    }

    public function complete(
        CompleteDefectAssessmentRequest $request,
        TenantContext $tenant,
        DefectAssessment $defectAssessment,
        CompleteDefectAssessment $action,
    ): RedirectResponse {
        $defectAssessment = $this->tenantDefectAssessment($tenant, $defectAssessment);
        $defectAssessment->loadMissing(['defect', 'inspection']);

        $this->authorize('complete', $defectAssessment);

        $action->handle(
            $request->user(),
            $defectAssessment,
            $request->validated(),
        );

        return redirect()
            ->route('defects.show', $defectAssessment->defect)
            ->with('success', 'Avaliação concluída.');
    }
}
