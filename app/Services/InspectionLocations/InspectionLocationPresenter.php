<?php

declare(strict_types=1);

namespace App\Services\InspectionLocations;

use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectCategory;
use App\Enums\InspectionLocationMapProcessingStatus;
use App\Models\DefectAssessment;
use App\Models\Inspection;
use App\Models\InspectionLocationMap;
use App\Models\User;
use Illuminate\Support\Collection;

final class InspectionLocationPresenter
{
    /** @return array<string, mixed> */
    public function present(Inspection $inspection, User $user): array
    {
        $inspection->loadMissing(['equipment.client', 'referenceDocuments.document', 'previousInspection']);

        $maps = InspectionLocationMap::query()
            ->forOrganization($inspection->organization_id)
            ->where('inspection_id', $inspection->id)
            ->with(['markers', 'equipmentDocument'])
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $assessments = DefectAssessment::query()
            ->forOrganization($inspection->organization_id)
            ->where('inspection_id', $inspection->id)
            ->with(['defect', 'locationMarkers'])
            ->orderBy('id')
            ->get();

        $categories = collect(DefectCategory::cases());

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
                ],
                'show_url' => route('inspections.show', $inspection),
            ],
            'categories' => $categories->map(fn (DefectCategory $category): array => $this->categoryPayload(
                $category,
                $maps->filter(fn (InspectionLocationMap $map): bool => $map->category === $category),
                $assessments->filter(fn (DefectAssessment $assessment): bool => $assessment->defect?->category === $category),
                $inspection,
                $user,
            ))->values()->all(),
            'maps' => $maps->map(fn (InspectionLocationMap $map): array => $this->mapPayload($map, $user))->values()->all(),
            'unlocated_assessments' => $this->unlocatedAssessmentsPayload($assessments),
            'unresolved_markers' => $this->unresolvedMarkersPayload($maps),
            'progress' => [
                'completed' => $assessments->filter(fn (DefectAssessment $assessment): bool => $assessment->status->value === 'complete')->count(),
                'total' => $assessments->count(),
                'percentage' => $assessments->count() === 0
                    ? 0
                    : (int) round($assessments->filter(fn (DefectAssessment $assessment): bool => $assessment->status->value === 'complete')->count() / $assessments->count() * 100),
            ],
            'create_url' => route('inspections.location-maps.create', $inspection),
            'can_create' => $user->can('create', [InspectionLocationMap::class, $inspection]),
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
            'category' => $category->toArray(),
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
                'create' => $user->can('create', [InspectionLocationMap::class, $inspection]),
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
            'category' => $map->category->toArray(),
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
                ? route('inspection-location-maps.background', [
                    'map' => $map,
                    'variant' => 'thumbnail',
                    'v' => $map->background_checksum,
                ])
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
                'category' => $assessment->defect->category->toArray(),
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
                'category' => $map->category->toArray(),
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
