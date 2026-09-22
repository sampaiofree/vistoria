<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\InspectionLocations\DeleteDefectAssessmentLocation;
use App\Actions\InspectionLocations\DeleteDefectLocationMapVersion;
use App\Actions\InspectionLocations\StoreDefectLocationMapVersion;
use App\Actions\InspectionLocations\UpsertDefectAssessmentLocation;
use App\Exceptions\StaleDefectAssessmentLocationException;
use App\Http\Controllers\Concerns\ResolvesTenantStructure;
use App\Http\Requests\InspectionLocations\StoreDefectLocationMapRequest;
use App\Http\Requests\InspectionLocations\UpdateDefectAssessmentLocationRequest;
use App\Models\DefectAssessment;
use App\Services\InspectionLocations\DefectLocationColor;
use App\Services\InspectionLocations\InspectionLocationPhotoNumbering;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class DefectAssessmentLocationController extends Controller
{
    use ResolvesTenantStructure;

    public function storeMap(
        StoreDefectLocationMapRequest $request,
        TenantContext $tenant,
        DefectAssessment $defectAssessment,
        StoreDefectLocationMapVersion $action,
    ): RedirectResponse {
        $assessment = $this->tenantDefectAssessment($tenant, $defectAssessment);
        $this->authorize('update', $assessment);
        $action->handle($request->user(), $assessment, $request->file('file'));

        return back()->with('success', 'Imagem do mapa enviada para processamento.');
    }

    public function destroyMap(
        TenantContext $tenant,
        DefectAssessment $defectAssessment,
        DeleteDefectLocationMapVersion $action,
    ): RedirectResponse {
        $assessment = $this->tenantDefectAssessment($tenant, $defectAssessment);
        $this->authorize('update', $assessment);
        $action->handle(request()->user(), $assessment);

        return redirect()->route('defect-assessments.show', $assessment)->with('success', 'Mapa removido desta avaliação.');
    }

    public function editor(
        TenantContext $tenant,
        DefectAssessment $defectAssessment,
        DefectLocationColor $colors,
        InspectionLocationPhotoNumbering $numbering,
    ): InertiaResponse {
        $assessment = $this->tenantDefectAssessment($tenant, $defectAssessment);
        $assessment->loadMissing(['defect.equipment', 'inspection', 'photos', 'locationMapVersion.map', 'location']);
        $this->authorize('update', $assessment);
        abort_unless($assessment->locationMapVersion?->isReady(), 409, 'A imagem-base ainda não está disponível.');

        $reportNumbers = $numbering->buildForReport($assessment->inspection);

        return Inertia::render('DefectAssessments/LocationEditor', [
            'assessment' => [
                'public_id' => $assessment->public_id,
                'defect_code' => $assessment->defect->code,
                'defect_title' => $assessment->defect->title,
                'category' => $assessment->defect->category->toArray(),
                'color' => $colors->forAssessment($assessment),
                'photo_legend' => $numbering->displayLegendForAssessment($assessment, $reportNumbers),
                'show_url' => route('defect-assessments.show', $assessment),
            ],
            'map' => [
                'public_id' => $assessment->locationMapVersion->map->public_id,
                'version_public_id' => $assessment->locationMapVersion->public_id,
                'version' => $assessment->locationMapVersion->version,
                'background_url' => route('defect-location-map-versions.background', [
                    'mapVersion' => $assessment->locationMapVersion,
                    'v' => $assessment->locationMapVersion->background_checksum,
                ]),
                'background_width' => $assessment->locationMapVersion->background_width,
                'background_height' => $assessment->locationMapVersion->background_height,
            ],
            'location' => $assessment->location === null ? null : [
                'public_id' => $assessment->location->public_id,
                'geometry' => $assessment->location->geometry,
                'label' => $assessment->location->label,
                'confirmed' => $assessment->location->isConfirmed(),
                'lock_version' => $assessment->location->lock_version,
            ],
            'update_url' => route('defect-assessments.location.update', $assessment),
            'delete_url' => $assessment->location === null ? null : route('defect-assessments.location.destroy', $assessment),
        ]);
    }

    public function update(
        UpdateDefectAssessmentLocationRequest $request,
        TenantContext $tenant,
        DefectAssessment $defectAssessment,
        UpsertDefectAssessmentLocation $action,
    ): RedirectResponse {
        $assessment = $this->tenantDefectAssessment($tenant, $defectAssessment);
        $this->authorize('update', $assessment);
        try {
            $action->handle($request->user(), $assessment, $request->validated());
        } catch (StaleDefectAssessmentLocationException $exception) {
            throw ValidationException::withMessages(['lock_version' => $exception->getMessage()]);
        }

        return back()->with('success', 'Localização confirmada.');
    }

    public function destroy(
        Request $request,
        TenantContext $tenant,
        DefectAssessment $defectAssessment,
        DeleteDefectAssessmentLocation $action,
    ): RedirectResponse {
        $assessment = $this->tenantDefectAssessment($tenant, $defectAssessment);
        $this->authorize('update', $assessment);
        $data = $request->validate(['lock_version' => ['required', 'integer', 'min:1']]);
        try {
            $action->handle($request->user(), $assessment, (int) $data['lock_version']);
        } catch (StaleDefectAssessmentLocationException $exception) {
            throw ValidationException::withMessages(['lock_version' => $exception->getMessage()]);
        }

        return redirect()->route('defect-assessments.show', $assessment)->with('success', 'Localização removida.');
    }
}
