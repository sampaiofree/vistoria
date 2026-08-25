<?php

declare(strict_types=1);

namespace App\Services\InspectionLocations;

use App\Enums\DefectAssessmentCondition;
use App\Enums\InspectionLocationMapProcessingStatus;
use App\Models\DefectAssessment;
use App\Models\DefectCategory;
use App\Models\Inspection;
use App\Models\InspectionLocationMap;
use App\Models\User;
use Illuminate\Support\Collection;

final class InspectionLocationPresenter
{
    /** @return array<string, mixed> */
    public function present(Inspection $inspection, User $user): array
    {
        $inspection->loadMissing(['equipment.client', 'equipment.unit', 'referenceDocuments.document', 'previousInspection']);

        $maps = InspectionLocationMap::query()
            ->forOrganization($inspection->organization_id)
            ->where('inspection_id', $inspection->id)
            ->with(['markers', 'equipmentDocument', 'category'])
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $assessments = DefectAssessment::query()
            ->forOrganization($inspection->organization_id)
            ->where('inspection_id', $inspection->id)
            ->with(['defect.categoryDefinition', 'locationMarkers'])
            ->orderBy('id')
            ->get();

        $categoryIds = $maps->pluck('defect_category_id')
            ->merge($assessments->pluck('defect.defect_category_id'))
            ->filter()
            ->unique();

        $categories = DefectCategory::query()
            ->forOrganization($inspection->organization_id)
            ->where(fn ($query) => $query->active()->orWhereIn('id', $categoryIds))
            ->orderBy('position')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return [
            'inspection' => [
                'id' => $inspection->id,
                'public_id' => $inspection->public_id,
                'number' => $inspection->number,
                'status' => $inspection->status->value,
                'status_label' => $inspection->status->label(),
                'equipment' => [
                    'public_id' => $inspection->equipment->public_id,
                    'tag' => $inspection->equipment->tag,
                    'name' => $inspection->equipment->name,
                    'client' => $inspection->equipment->client?->name,
                    'unit' => $inspection->equipment->unit?->name,
                ],
                'show_url' => route('inspections.show', $inspection),
            ],
            'categories' => $categories->map(fn (DefectCategory $category): array => $this->categoryPayload(
                $category,
                $maps->where('defect_category_id', $category->id),
                $assessments->filter(fn (DefectAssessment $assessment): bool => (int) $assessment->defect?->defect_category_id === (int) $category->id),
                $inspection,
                $user,
            ))->values()->all(),
            'maps' => $maps->map(fn (InspectionLocationMap $map): array => $this->mapPayload($map, $user))->values()->all(),
            'unlocated_assessments' => $this->unlocatedAssessmentsPayload($assessments),
            'unresolved_markers' => $this->unresolvedMarkersPayload($maps),
            'coverage' => $this->coveragePayload($categories, $maps, $assessments),
            'progress' => [
                'completed' => $assessments->filter(fn (DefectAssessment $assessment): bool => $assessment->status->value === 'complete')->count(),
                'total' => $assessments->count(),
                'percentage' => $assessments->count() === 0
                    ? 0
                    : (int) round($assessments->filter(fn (DefectAssessment $assessment): bool => $assessment->status->value === 'complete')->count() / $assessments->count() * 100),
            ],
            'create_url' => route('inspections.location-maps.create', $inspection),
            'can_create' => $categories->contains(fn (DefectCategory $category): bool => $user->can('create', [InspectionLocationMap::class, $inspection, $category])),
            'copy_previous' => $user->can('copyPrevious', [InspectionLocationMap::class, $inspection]) && $maps->isEmpty()
                ? [
                    'url' => route('inspections.location-maps.copy-previous', $inspection),
                    'map_count' => $inspection->previousInspection?->locationMaps()->count() ?? 0,
                ]
                : null,
            'tabs' => $this->tabs($inspection, $assessments->count(), $maps->count()),
            'active_tab' => 'locations',
        ];
    }

    /** @param Collection<int, InspectionLocationMap> $maps @param Collection<int, DefectAssessment> $assessments */
    private function categoryPayload(DefectCategory $category, Collection $maps, Collection $assessments, Inspection $inspection, User $user): array
    {
        return [
            'category' => [
                'id' => $category->id,
                'public_id' => $category->public_id,
                'name' => $category->name,
                'code' => $category->code,
                'requires_location_map' => (bool) $category->requires_location_map,
            ],
            'maps' => $maps->map(fn (InspectionLocationMap $map): array => $this->mapPayload($map, $user))->values()->all(),
            'unlocated_assessments' => $assessments
                ->filter(fn (DefectAssessment $assessment): bool => ! in_array($assessment->condition, [
                    DefectAssessmentCondition::NotLocated,
                    DefectAssessmentCondition::NotInspected,
                ], true) && $assessment->locationMarkers->isEmpty())
                ->map(fn (DefectAssessment $assessment): array => [
                    'id' => $assessment->id,
                    'public_id' => $assessment->public_id,
                    'defect_code' => $assessment->defect->code,
                    'title' => $assessment->defect->title,
                    'location_description' => $assessment->location_description,
                    'status' => $assessment->status->value,
                    'show_url' => route('defect-assessments.show', $assessment),
                ])->values()->all(),
            'unresolved_markers' => $maps
                ->flatMap(fn (InspectionLocationMap $map) => $map->markers->whereNull('defect_assessment_id')->map(fn ($marker): array => [
                    'public_id' => $marker->public_id,
                    'label' => $marker->label,
                    'map_title' => $map->title,
                    'edit_url' => route('inspection-location-maps.editor', $map),
                ]))
                ->values()
                ->all(),
            'capabilities' => [
                'create' => $user->can('create', [InspectionLocationMap::class, $inspection, $category]),
            ],
        ];
    }

    private function mapPayload(InspectionLocationMap $map, User $user): array
    {
        $canUpdate = $user->can('update', $map);

        return [
            'id' => $map->id,
            'public_id' => $map->public_id,
            'title' => $map->title,
            'category' => $map->category === null ? null : [
                'id' => $map->category->id,
                'public_id' => $map->category->public_id,
                'name' => $map->category->name,
                'code' => $map->category->code,
            ],
            'description' => $map->description,
            'position' => $map->position,
            'lock_version' => $map->lock_version,
            'source' => [
                'kind' => $map->source_kind->value,
                'page' => $map->source_page,
                'document' => $map->equipmentDocument === null ? null : [
                    'public_id' => $map->equipmentDocument->public_id,
                    'title' => $map->equipmentDocument->title,
                    'revision' => $map->equipmentDocument->revision,
                ],
            ],
            'background_url' => $map->processing_status === InspectionLocationMapProcessingStatus::Ready
                ? route('inspection-location-maps.background', [$map, 'thumbnail'])
                : null,
            'processing_status' => $map->processing_status->value,
            'processing_status_label' => $this->statusLabel($map->processing_status),
            'processing_error' => $map->processing_error,
            'marker_count' => $map->markers->count(),
            'capabilities' => [
                'update' => $canUpdate,
                'delete' => $user->can('delete', $map),
                'open_editor' => $canUpdate && $map->processing_status === InspectionLocationMapProcessingStatus::Ready,
            ],
            'edit_url' => $canUpdate ? route('inspection-location-maps.edit', $map) : null,
            'editor_url' => $canUpdate && $map->processing_status === InspectionLocationMapProcessingStatus::Ready ? route('inspection-location-maps.editor', $map) : null,
            'delete_url' => $user->can('delete', $map) ? route('inspection-location-maps.destroy', $map) : null,
        ];
    }

    /** @param Collection<int, DefectAssessment> $assessments */
    private function unlocatedAssessmentsPayload(Collection $assessments): array
    {
        return $assessments
            ->filter(fn (DefectAssessment $assessment): bool => ! in_array($assessment->condition, [
                DefectAssessmentCondition::NotLocated,
                DefectAssessmentCondition::NotInspected,
            ], true) && $assessment->locationMarkers->isEmpty())
            ->map(fn (DefectAssessment $assessment): array => [
                'id' => $assessment->id,
                'public_id' => $assessment->public_id,
                'defect_code' => $assessment->defect->code,
                'title' => $assessment->defect->title,
                'location_description' => $assessment->location_description,
                'status' => $assessment->status->value,
                'category' => $assessment->defect->categoryDefinition === null ? null : [
                    'code' => $assessment->defect->categoryDefinition->code,
                    'name' => $assessment->defect->categoryDefinition->name,
                ],
                'show_url' => route('defect-assessments.show', $assessment),
            ])->values()->all();
    }

    /** @param Collection<int, InspectionLocationMap> $maps */
    private function unresolvedMarkersPayload(Collection $maps): array
    {
        return $maps
            ->flatMap(fn (InspectionLocationMap $map) => $map->markers->whereNull('defect_assessment_id')->map(fn ($marker): array => [
                'public_id' => $marker->public_id,
                'label' => $marker->label,
                'map_title' => $map->title,
                'category' => $map->category === null ? null : [
                    'code' => $map->category->code,
                    'name' => $map->category->name,
                ],
                'edit_url' => route('inspection-location-maps.editor', $map),
            ]))
            ->values()
            ->all();
    }

    private function statusLabel(InspectionLocationMapProcessingStatus $status): string
    {
        return match ($status) {
            InspectionLocationMapProcessingStatus::Pending => 'Pendente',
            InspectionLocationMapProcessingStatus::Processing => 'Processando',
            InspectionLocationMapProcessingStatus::Ready => 'Disponível',
            InspectionLocationMapProcessingStatus::Failed => 'Falha',
        };
    }

    /** @param Collection<int, DefectCategory> $categories @param Collection<int, InspectionLocationMap> $maps @param Collection<int, DefectAssessment> $assessments */
    private function coveragePayload(Collection $categories, Collection $maps, Collection $assessments): array
    {
        $requiredCategoryIds = $categories
            ->where('requires_location_map', true)
            ->pluck('id');
        $requiredAssessments = $assessments
            ->filter(fn (DefectAssessment $assessment): bool => $requiredCategoryIds->contains($assessment->defect?->defect_category_id))
            ->reject(fn (DefectAssessment $assessment): bool => in_array($assessment->condition, [
                DefectAssessmentCondition::NotLocated,
                DefectAssessmentCondition::NotInspected,
            ], true));
        $requiredMaps = $maps->whereIn('defect_category_id', $requiredCategoryIds);
        $missingMarkers = $requiredAssessments
            ->filter(fn (DefectAssessment $assessment): bool => $assessment->locationMarkers->isEmpty())
            ->count();
        $unreadyMaps = $requiredMaps
            ->reject(fn (InspectionLocationMap $map): bool => $map->processing_status === InspectionLocationMapProcessingStatus::Ready)
            ->count();

        return [
            'enabled_categories' => $requiredCategoryIds->count(),
            'required_assessments' => $requiredAssessments->count(),
            'located_assessments' => $requiredAssessments->count() - $missingMarkers,
            'missing_markers' => $missingMarkers,
            'map_count' => $requiredMaps->count(),
            'ready_maps' => $requiredMaps->count() - $unreadyMaps,
            'unready_maps' => $unreadyMaps,
            'is_complete' => $requiredAssessments->isEmpty()
                || ($requiredMaps->isNotEmpty() && $missingMarkers === 0 && $unreadyMaps === 0),
        ];
    }

    private function tabs(Inspection $inspection, int $assessmentCount, int $mapCount): array
    {
        return [
            ['key' => 'overview', 'label' => 'Visão geral', 'url' => route('inspections.show', $inspection)],
            ['key' => 'report_overview', 'label' => 'Vista geral', 'url' => route('inspections.report-overview', $inspection)],
            ['key' => 'defects', 'label' => 'Avarias', 'url' => route('inspections.defects', $inspection), 'count' => $assessmentCount],
            ['key' => 'locations', 'label' => 'Localização', 'url' => route('inspections.locations', $inspection), 'count' => $mapCount],
            ['key' => 'photos', 'label' => 'Fotografias', 'url' => route('inspections.photos', $inspection)],
            ['key' => 'documents', 'label' => 'Documentos', 'url' => route('inspections.documents', $inspection)],
            ['key' => 'history', 'label' => 'Histórico', 'url' => route('inspections.history', $inspection)],
            ['key' => 'report', 'label' => 'Relatório', 'url' => route('inspections.report-preview', $inspection)],
        ];
    }
}
