<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\InspectionLocations\CreateInspectionLocationMarker;
use App\Actions\InspectionLocations\DeleteInspectionLocationMarker;
use App\Actions\InspectionLocations\ReorderInspectionLocationMarkers;
use App\Actions\InspectionLocations\SyncInspectionLocationMarkerPhotos;
use App\Actions\InspectionLocations\UpdateInspectionLocationMarker;
use App\Exceptions\StaleInspectionLocationMapException;
use App\Exceptions\StaleInspectionLocationMarkerException;
use App\Http\Controllers\Concerns\ResolvesTenantStructure;
use App\Http\Requests\InspectionLocations\StoreInspectionLocationMarkerRequest;
use App\Http\Requests\InspectionLocations\SyncInspectionLocationMarkerPhotosRequest;
use App\Http\Requests\InspectionLocations\UpdateInspectionLocationMarkerRequest;
use App\Models\DefectAssessment;
use App\Models\InspectionLocationMap;
use App\Models\InspectionLocationMarker;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class InspectionLocationMarkerController extends Controller
{
    use ResolvesTenantStructure;

    public function store(StoreInspectionLocationMarkerRequest $request, TenantContext $tenant, InspectionLocationMap $map, CreateInspectionLocationMarker $action): RedirectResponse
    {
        $map = $this->tenantInspectionLocationMap($tenant, $map);
        $assessment = DefectAssessment::query()->forOrganization($tenant->id())->whereKey($request->validated('defect_assessment_id'))->firstOrFail();
        $this->authorize('create', [InspectionLocationMarker::class, $map, $assessment]);

        try {
            $action->handle($request->user(), $map, $request->validated());
        } catch (StaleInspectionLocationMapException $exception) {
            throw ValidationException::withMessages(['map_lock_version' => $exception->getMessage()]);
        }

        return back()->with('success', 'Marcação criada.');
    }

    public function update(UpdateInspectionLocationMarkerRequest $request, TenantContext $tenant, InspectionLocationMarker $marker, UpdateInspectionLocationMarker $action): RedirectResponse
    {
        $marker = $this->tenantInspectionLocationMarker($tenant, $marker);
        $this->authorize('update', $marker);

        try {
            $action->handle($request->user(), $marker, $request->validated());
        } catch (StaleInspectionLocationMarkerException|StaleInspectionLocationMapException $exception) {
            throw ValidationException::withMessages(['lock_version' => $exception->getMessage()]);
        }

        return back()->with('success', 'Marcação atualizada.');
    }

    public function destroy(Request $request, TenantContext $tenant, InspectionLocationMarker $marker, DeleteInspectionLocationMarker $action): RedirectResponse
    {
        $marker = $this->tenantInspectionLocationMarker($tenant, $marker);
        $this->authorize('delete', $marker);
        $validated = $request->validate([
            'lock_version' => ['required', 'integer', 'min:1'],
            'map_lock_version' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $action->handle($request->user(), $marker, (int) $validated['lock_version'], (int) $validated['map_lock_version']);
        } catch (StaleInspectionLocationMarkerException|StaleInspectionLocationMapException $exception) {
            throw ValidationException::withMessages(['lock_version' => $exception->getMessage()]);
        }

        return back()->with('success', 'Marcação removida.');
    }

    public function syncPhotos(SyncInspectionLocationMarkerPhotosRequest $request, TenantContext $tenant, InspectionLocationMarker $marker, SyncInspectionLocationMarkerPhotos $action): RedirectResponse
    {
        $marker = $this->tenantInspectionLocationMarker($tenant, $marker);
        $this->authorize('update', $marker);

        try {
            $action->handle(
                $request->user(),
                $marker,
                $request->validated('photo_ids'),
                (int) $request->validated('lock_version'),
                (int) $request->validated('map_lock_version'),
            );
        } catch (StaleInspectionLocationMarkerException|StaleInspectionLocationMapException $exception) {
            throw ValidationException::withMessages(['lock_version' => $exception->getMessage()]);
        }

        return back()->with('success', 'Fotografias da marcação atualizadas.');
    }

    public function reorder(Request $request, TenantContext $tenant, InspectionLocationMap $map, ReorderInspectionLocationMarkers $action): RedirectResponse
    {
        $map = $this->tenantInspectionLocationMap($tenant, $map);
        $this->authorize('update', $map);
        $validated = $request->validate([
            'marker_ids' => ['present', 'array', 'max:'.config('inspection_locations.limits.markers_per_map')],
            'marker_ids.*' => ['required', 'string', 'distinct'],
            'map_lock_version' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $action->handle(
                $request->user(),
                $map,
                $validated['marker_ids'],
                (int) $validated['map_lock_version'],
            );
        } catch (StaleInspectionLocationMapException $exception) {
            throw ValidationException::withMessages(['map_lock_version' => $exception->getMessage()]);
        }

        return back()->with('success', 'Ordem das marcações atualizada.');
    }
}
