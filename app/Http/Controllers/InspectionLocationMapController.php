<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\InspectionLocations\CopyInspectionLocationMapsFromPreviousInspection;
use App\Actions\InspectionLocations\CreateInspectionLocationMap;
use App\Actions\InspectionLocations\DeleteInspectionLocationMap;
use App\Actions\InspectionLocations\ReorderInspectionLocationMaps;
use App\Actions\InspectionLocations\StoreInspectionLocationMapSource;
use App\Actions\InspectionLocations\UpdateInspectionLocationMap;
use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectAssessmentStatus;
use App\Exceptions\StaleInspectionLocationMapException;
use App\Http\Controllers\Concerns\ResolvesTenantStructure;
use App\Http\Requests\InspectionLocations\StoreInspectionLocationMapRequest;
use App\Http\Requests\InspectionLocations\StoreInspectionLocationMapSourceRequest;
use App\Http\Requests\InspectionLocations\UpdateInspectionLocationMapRequest;
use App\Models\DefectAssessment;
use App\Models\DefectCategory;
use App\Models\Inspection;
use App\Models\InspectionLocationMap;
use App\Services\InspectionLocations\InspectionLocationPhotoNumbering;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class InspectionLocationMapController extends Controller
{
    use ResolvesTenantStructure;

    public function create(Request $request, TenantContext $tenant, Inspection $inspection): InertiaResponse
    {
        $inspection = $this->tenantInspection($tenant, $inspection);
        $this->authorize('view', $inspection);

        $categories = DefectCategory::query()
            ->forOrganization($tenant->id())
            ->active()
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->filter(fn (DefectCategory $category): bool => $request->user()->can('create', [InspectionLocationMap::class, $inspection, $category]))
            ->map(fn (DefectCategory $category): array => ['id' => $category->id, 'name' => $category->name, 'code' => $category->code])
            ->values();

        return Inertia::render('InspectionLocationMaps/Create', [
            'inspection' => ['number' => $inspection->number, 'equipment' => ['tag' => $inspection->equipment->tag]],
            'categories' => $categories,
            'action' => route('inspections.location-maps.store', $inspection),
            'cancel_url' => route('inspections.locations', $inspection),
        ]);
    }

    public function edit(TenantContext $tenant, InspectionLocationMap $map): InertiaResponse
    {
        $map = $this->tenantInspectionLocationMap($tenant, $map);
        $this->authorize('update', $map);
        $map->loadMissing(['inspection.equipment', 'category', 'equipmentDocument']);
        $map->loadCount('markers');

        return Inertia::render('InspectionLocationMaps/Edit', [
            'map' => [
                'public_id' => $map->public_id,
                'title' => $map->title,
                'description' => $map->description,
                'position' => $map->position,
                'marker_count' => $map->markers_count,
                'lock_version' => $map->lock_version,
                'category' => ['name' => $map->category->name, 'code' => $map->category->code],
                'source_kind' => $map->source_kind->value,
                'source_page' => $map->source_page,
                'document' => $map->equipmentDocument?->only(['title', 'revision']),
                'processing_status' => $map->processing_status->value,
                'processing_error' => $map->processing_error,
                'background_url' => $map->processing_status->value === 'ready' && $map->background_path
                    ? route('inspection-location-maps.background', [
                        'map' => $map,
                        'v' => $map->background_checksum,
                    ])
                    : null,
                'editor_url' => $map->processing_status->value === 'ready' ? route('inspection-location-maps.editor', $map) : null,
            ],
            'inspection' => ['number' => $map->inspection->number, 'equipment' => ['tag' => $map->inspection->equipment->tag]],
            'update_url' => route('inspection-location-maps.update', $map),
            'source_url' => route('inspection-location-maps.source', $map),
            'delete_url' => route('inspection-location-maps.destroy', $map),
            'cancel_url' => route('inspections.locations', $map->inspection),
        ]);
    }

    public function editor(TenantContext $tenant, InspectionLocationMap $map, InspectionLocationPhotoNumbering $photoNumbering): InertiaResponse
    {
        $map = $this->tenantInspectionLocationMap($tenant, $map);
        $this->authorize('update', $map);
        abort_unless($map->processing_status->value === 'ready' && $map->background_path !== null, 409, 'A imagem-base ainda não está disponível.');
        abort_if(
            $map->markers()->count() > (int) config('inspection_locations.limits.markers_per_map'),
            422,
            'O mapa excede o limite seguro de marcações.',
        );
        $map->loadMissing(['inspection.equipment', 'category', 'markers.assessment.defect', 'markers.assessment.photos', 'markers.photos']);

        $assessmentQuery = DefectAssessment::query()
            ->forOrganization($tenant->id())
            ->where('inspection_id', $map->inspection_id)
            ->where('status', DefectAssessmentStatus::Complete->value)
            ->whereNotIn('condition', [
                DefectAssessmentCondition::NotLocated->value,
                DefectAssessmentCondition::NotInspected->value,
            ])
            ->whereHas('defect', fn ($query) => $query->where('defect_category_id', $map->defect_category_id));
        abort_if(
            (clone $assessmentQuery)->count() > (int) config('inspection_locations.limits.assessments_per_editor'),
            422,
            'A categoria excede o limite seguro de avaliações no editor.',
        );
        $assessments = $assessmentQuery
            ->withCount('photos')
            ->with([
                'defect',
                'locationMarkers',
                'photos' => fn ($query) => $query
                    ->orderBy('position')
                    ->orderBy('id'),
            ])
            ->orderBy('id')
            ->get();
        $numbering = $photoNumbering->buildForReport($map->inspection);

        return Inertia::render('InspectionLocationMaps/Editor', [
            'map' => [
                'public_id' => $map->public_id,
                'title' => $map->title,
                'lock_version' => $map->lock_version,
                'background_url' => route('inspection-location-maps.background', [
                    'map' => $map,
                    'v' => $map->background_checksum,
                ]),
                'background_width' => $map->background_width,
                'background_height' => $map->background_height,
                'category' => ['name' => $map->category->name, 'code' => $map->category->code],
                'store_marker_url' => route('inspection-location-maps.markers.store', $map),
                'reorder_markers_url' => route('inspection-location-maps.markers.reorder', $map),
            ],
            'markers' => $map->markers->map(function ($marker) use ($photoNumbering, $numbering): array {
                $numbers = $photoNumbering->numbersForMarker($marker, $numbering);

                return [
                    'public_id' => $marker->public_id,
                    'defect_assessment_id' => $marker->defect_assessment_id,
                    'label' => $marker->label,
                    'geometry' => $marker->geometry,
                    'style' => $marker->style,
                    'position' => $marker->position,
                    'lock_version' => $marker->lock_version,
                    'update_url' => route('inspection-location-markers.update', $marker),
                    'delete_url' => route('inspection-location-markers.destroy', $marker),
                    'sync_photos_url' => route('inspection-location-markers.photos.sync', $marker),
                    'photo_ids' => $marker->photos->pluck('public_id')->values(),
                    'photo_numbers' => $numbers,
                    'photo_interval' => $photoNumbering->format($numbers),
                    'photo_legend' => $photoNumbering->legend($numbers),
                    'defect_code' => $marker->assessment?->defect?->code,
                    'defect_title' => $marker->assessment?->defect?->title,
                    'assessment_status' => $marker->assessment?->status?->value,
                ];
            })->values(),
            'assessments' => $assessments->map(function (DefectAssessment $assessment) use ($photoNumbering, $numbering): array {
                $numbers = $photoNumbering->numbersForAssessment($assessment, $numbering);

                return [
                    'id' => $assessment->id,
                    'public_id' => $assessment->public_id,
                    'defect_code' => $assessment->defect->code,
                    'title' => $assessment->defect->title,
                    'location_description' => $assessment->location_description,
                    'marker_count' => $assessment->locationMarkers->count(),
                    'is_available' => $assessment->locationMarkers->isEmpty(),
                    'photo_count' => $assessment->photos_count,
                    'photo_numbers' => $numbers,
                    'photo_legend' => $photoNumbering->legend($numbers),
                    'photos_truncated' => false,
                    'photos' => $assessment->photos->map(fn ($photo): array => [
                        'public_id' => $photo->public_id,
                        'caption' => $photo->caption,
                        'position' => $photo->position,
                        'processing_status' => $photo->processing_status->value,
                        'report_number' => $numbering[$photo->public_id] ?? null,
                        'thumbnail_url' => $photo->isReady() ? route('assessment-photos.show', [$photo, 'thumbnail']) : null,
                    ])->values(),
                ];
            })->values(),
            'back_url' => route('inspection-location-maps.edit', $map),
            'defects_url' => route('inspections.defects', $map->inspection),
        ]);
    }

    public function store(StoreInspectionLocationMapRequest $request, TenantContext $tenant, Inspection $inspection, CreateInspectionLocationMap $action): RedirectResponse
    {
        $inspection = $this->tenantInspection($tenant, $inspection);
        $category = DefectCategory::query()->forOrganization($tenant->id())->whereKey($request->validated('defect_category_id'))->firstOrFail();
        $this->authorize('create', [InspectionLocationMap::class, $inspection, $category]);
        $map = $action->handle($request->user(), $inspection, $request->validated());

        return redirect()->route('inspection-location-maps.edit', $map)->with('success', 'Mapa de localização criado.');
    }

    public function update(UpdateInspectionLocationMapRequest $request, TenantContext $tenant, InspectionLocationMap $map, UpdateInspectionLocationMap $action): RedirectResponse
    {
        $map = $this->tenantInspectionLocationMap($tenant, $map);
        $this->authorize('update', $map);
        try {
            $action->handle($request->user(), $map, $request->validated());
        } catch (StaleInspectionLocationMapException $exception) {
            throw ValidationException::withMessages(['lock_version' => $exception->getMessage()]);
        }

        return back()->with('success', 'Mapa de localização atualizado.');
    }

    public function destroy(TenantContext $tenant, InspectionLocationMap $map, DeleteInspectionLocationMap $action): RedirectResponse
    {
        $map = $this->tenantInspectionLocationMap($tenant, $map);
        $this->authorize('delete', $map);
        $inspection = $map->inspection;
        $action->handle(request()->user(), $map);

        return redirect()->route('inspections.locations', $inspection)->with('success', 'Mapa de localização removido.');
    }

    public function source(StoreInspectionLocationMapSourceRequest $request, TenantContext $tenant, InspectionLocationMap $map, StoreInspectionLocationMapSource $action): RedirectResponse
    {
        $map = $this->tenantInspectionLocationMap($tenant, $map);
        $this->authorize('update', $map);
        try {
            $action->handle($request->user(), $map, $request->file('file'), $request->validated());
        } catch (StaleInspectionLocationMapException $exception) {
            throw ValidationException::withMessages(['lock_version' => $exception->getMessage()]);
        }

        return back()->with('success', 'Imagem do mapa enviada para processamento.');
    }

    public function copyPrevious(Request $request, TenantContext $tenant, Inspection $inspection, CopyInspectionLocationMapsFromPreviousInspection $action): RedirectResponse
    {
        $inspection = $this->tenantInspection($tenant, $inspection);
        $this->authorize('copyPrevious', [InspectionLocationMap::class, $inspection]);
        $summary = $action->handle($request->user(), $inspection);

        return redirect()->route('inspections.locations', $inspection)->with(
            'success',
            sprintf('%d mapa(s) e %d marcação(ões) copiados. %d pendência(s) de avaliação.', $summary['maps'], $summary['markers'], $summary['pending_markers']),
        );
    }

    public function reorder(Request $request, TenantContext $tenant, Inspection $inspection, ReorderInspectionLocationMaps $action): RedirectResponse
    {
        $inspection = $this->tenantInspection($tenant, $inspection);
        $this->authorize('reorder', [InspectionLocationMap::class, $inspection]);
        $data = $request->validate([
            'maps' => ['required', 'array', 'max:'.config('inspection_locations.limits.maps_per_inspection')],
            'maps.*.public_id' => ['required', 'string', 'distinct'],
            'maps.*.lock_version' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $action->handle($request->user(), $inspection, $data['maps']);
        } catch (StaleInspectionLocationMapException $exception) {
            throw ValidationException::withMessages(['maps' => $exception->getMessage()]);
        }

        return back()->with('success', 'Ordem dos mapas atualizada.');
    }
}
