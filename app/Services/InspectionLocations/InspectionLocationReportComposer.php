<?php

declare(strict_types=1);

namespace App\Services\InspectionLocations;

use App\Enums\DefectAssessmentStatus;
use App\Enums\DefectCategory;
use App\Enums\InspectionLocationMapProcessingStatus;
use App\Models\AssessmentPhoto;
use App\Models\DefectAssessment;
use App\Models\Inspection;
use App\Models\InspectionLocationMap;
use App\Models\InspectionLocationMarker;
use App\Services\Classification\DefectClassificationDefinition;
use App\Services\Classification\NativeDefectCatalog;
use Illuminate\Support\Collection;

final class InspectionLocationReportComposer
{
    public function __construct(
        private readonly InspectionLocationPhotoNumbering $photoNumbering,
        private readonly InspectionLocationReportCategoryOrder $categoryOrder,
    ) {}

    /** @return array<string, mixed> */
    public function compose(Inspection $inspection): array
    {
        $maps = $inspection->locationMaps()
            ->where('organization_id', $inspection->organization_id)
            ->with([
                'equipmentDocument',
                'markers.assessment.defect',
                'markers.assessment.quantity',
                'markers.assessment.photos',
                'markers.photos',
            ])
            ->orderBy('position')
            ->orderBy('id')
            ->get();
        $numbering = $this->photoNumbering->buildForReport($inspection, $maps);
        $categories = $this->categoryOrder->sort(collect(DefectCategory::cases()))
            ->filter(fn (DefectCategory $category): bool => $maps->contains(fn (InspectionLocationMap $map): bool => $map->category === $category))
            ->map(fn (DefectCategory $category): array => [
                'category' => $category->toArray(),
                'maps' => $maps
                    ->filter(fn (InspectionLocationMap $map): bool => $map->category === $category)
                    ->map(fn (InspectionLocationMap $map): array => $this->mapPayload(
                        $map,
                        $numbering,
                        NativeDefectCatalog::classifications($category),
                    ))
                    ->values()
                    ->all(),
            ])
            ->values();

        $sheets = $categories->flatMap(function (array $category): array {
            return collect($category['maps'])
                ->values()
                ->map(fn (array $map, int $index): array => [
                    'id' => $category['category']['code'].'-'.$map['public_id'],
                    'number' => $index + 1,
                    'category' => $category['category'],
                    'maps' => [$map],
                ])
                ->all();
        })->values();

        return [
            'categories' => $categories->all(),
            'sheets' => $sheets->all(),
            'numbering' => $numbering,
            'photo_count' => count($numbering),
            'map_count' => $categories->sum(fn (array $category): int => count($category['maps'])),
            'marker_count' => $categories->sum(fn (array $category): int => collect($category['maps'])->sum('marker_count')),
        ];
    }

    /**
     * @param  array<string, int>  $numbering
     * @param  Collection<int, DefectClassificationDefinition>  $classifications
     * @return array<string, mixed>
     */
    private function mapPayload(
        InspectionLocationMap $map,
        array $numbering,
        Collection $classifications,
    ): array {
        $markers = $map->markers
            ->filter(fn (InspectionLocationMarker $marker): bool => $marker->assessment?->status === DefectAssessmentStatus::Complete)
            ->values();

        return [
            'public_id' => $map->public_id,
            'title' => $map->title,
            'report_title' => $this->reportTitle($map),
            'description' => $map->description,
            'observations' => $map->description,
            'position' => $map->position,
            'processing_status' => $map->processing_status->value,
            'geometry_schema_version' => $map->geometry_schema_version,
            'source' => [
                'kind' => $map->source_kind->value,
                'page' => $map->source_page,
                'crop' => $map->source_crop,
                'reference_snapshot' => $map->reference_snapshot,
                'disk' => $map->source_disk,
                'path' => $map->source_path,
                'mime_type' => $map->source_mime_type,
                'size' => $map->source_size,
                'checksum' => $map->source_checksum,
                'document' => $map->equipmentDocument === null ? null : [
                    'public_id' => $map->equipmentDocument->public_id,
                    'title' => $map->equipmentDocument->title,
                    'document_number' => $map->equipmentDocument->document_number,
                    'revision' => $map->equipmentDocument->revision,
                ],
            ],
            'background' => [
                'disk' => $map->background_disk,
                'path' => $map->background_path,
                'mime_type' => $map->background_mime_type,
                'size' => $map->background_size,
                'width' => $map->background_width,
                'height' => $map->background_height,
                'checksum' => $map->background_checksum,
                'url' => $map->processing_status === InspectionLocationMapProcessingStatus::Ready
                    ? route('inspection-location-maps.background', [
                        'map' => $map,
                        'v' => $map->background_checksum,
                    ])
                    : null,
            ],
            'marker_count' => $markers->count(),
            'damage_rows' => $this->damageRows($markers, $numbering),
            'classification_legend' => $this->classificationLegend($classifications, $markers),
            'markers' => $markers
                ->map(fn (InspectionLocationMarker $marker): array => $this->markerPayload($marker, $numbering))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, InspectionLocationMarker>  $markers
     * @param  array<string, int>  $numbering
     * @return array<int, array<string, mixed>>
     */
    private function damageRows(Collection $markers, array $numbering): array
    {
        return $markers
            ->groupBy(fn (InspectionLocationMarker $marker): string => (string) $marker->defect_assessment_id)
            ->map(function (Collection $assessmentMarkers) use ($numbering): array {
                /** @var InspectionLocationMarker $firstMarker */
                $firstMarker = $assessmentMarkers->first();
                $assessment = $firstMarker->assessment;
                $quantity = $assessment?->quantity;
                $numbers = $assessment === null
                    ? []
                    : $this->photoNumbering->numbersForAssessment($assessment, $numbering);
                sort($numbers);

                return [
                    'assessment' => $assessment === null ? null : [
                        'public_id' => $assessment->public_id,
                    ],
                    'defect' => $assessment?->defect === null ? null : [
                        'public_id' => $assessment->defect->public_id,
                        'code' => $assessment->defect->code,
                    ],
                    'photo_numbers' => $numbers,
                    'photo_interval' => $this->photoNumbering->format($numbers),
                    'quantity' => $quantity === null ? null : [
                        'value' => $quantity->value(),
                        'unit' => mb_strtoupper($quantity->measurement_unit->symbol()),
                    ],
                    'gut' => [
                        'gravity' => $this->gutCriterionPayload($assessment, 'gravity'),
                        'urgency' => $this->gutCriterionPayload($assessment, 'urgency'),
                        'trend' => $this->gutCriterionPayload($assessment, 'trend'),
                    ],
                    'classification' => [
                        'code' => data_get($assessment?->classification_snapshot, 'code') ?? $assessment?->classification_code,
                        'color' => data_get($assessment?->classification_snapshot, 'color'),
                    ],
                ];
            })
            ->values()
            ->all();
    }

    /** @return array{score:?int,color:?string} */
    private function gutCriterionPayload(?DefectAssessment $assessment, string $criterion): array
    {
        return [
            'score' => data_get($assessment?->gut_snapshot, "criteria.{$criterion}.score") ?? $assessment?->{$criterion},
            'color' => data_get($assessment?->gut_snapshot, "criteria.{$criterion}.color"),
        ];
    }

    /**
     * @param  Collection<int, DefectClassificationDefinition>  $classifications
     * @param  Collection<int, InspectionLocationMarker>  $markers
     * @return list<array{code:string,color:string}>
     */
    private function classificationLegend(Collection $classifications, Collection $markers): array
    {
        $saved = $markers->map(fn (InspectionLocationMarker $marker): ?array => $marker->assessment?->classification_snapshot)
            ->filter(fn (?array $snapshot): bool => isset($snapshot['code'], $snapshot['color']))
            ->map(fn (array $snapshot): array => ['code' => $snapshot['code'], 'color' => $snapshot['color']]);

        return $classifications->map(function (DefectClassificationDefinition $classification) use ($saved): array {
            return $saved->firstWhere('code', $classification->code) ?? [
                'code' => $classification->code,
                'color' => $classification->color,
            ];
        })->concat($saved)->unique('code')->values()->all();
    }

    /** @param array<string, int> $numbering @return array<string, mixed> */
    private function markerPayload(InspectionLocationMarker $marker, array $numbering): array
    {
        $photos = $this->photoNumbering->photosForMarker($marker)
            ->filter(fn (AssessmentPhoto $photo): bool => isset($numbering[$photo->public_id]))
            ->values();
        $numbers = $photos
            ->map(fn (AssessmentPhoto $photo): ?int => $numbering[$photo->public_id] ?? null)
            ->filter()
            ->values()
            ->all();

        return [
            'public_id' => $marker->public_id,
            'label' => $marker->label,
            'position' => $marker->position,
            'geometry' => $marker->geometry,
            'style' => $marker->style,
            'assessment' => $marker->assessment === null ? null : [
                'public_id' => $marker->assessment->public_id,
                'condition' => $marker->assessment->condition->value,
                'status' => $marker->assessment->status->value,
                'location_description' => $marker->assessment->location_description,
            ],
            'defect' => $marker->assessment?->defect === null ? null : [
                'public_id' => $marker->assessment->defect->public_id,
                'code' => $marker->assessment->defect->code,
                'title' => $marker->assessment->defect->title,
            ],
            'photo_numbers' => $numbers,
            'photo_interval' => $this->photoNumbering->format($numbers),
            'photo_legend' => $this->photoNumbering->legend($numbers),
            'photos' => $photos
                ->map(fn (AssessmentPhoto $photo): array => [
                    'public_id' => $photo->public_id,
                    'number' => $numbering[$photo->public_id] ?? null,
                    'position' => (int) ($photo->pivot?->position ?? $photo->position),
                    'type' => $photo->photo_type->value,
                    'caption' => $photo->caption,
                    'processing_status' => $photo->processing_status->value,
                    'checksum' => $photo->checksum,
                    'url' => $photo->isReady() ? route('assessment-photos.show', [$photo, 'optimized']) : null,
                    'thumbnail_url' => $photo->isReady() ? route('assessment-photos.show', [$photo, 'thumbnail']) : null,
                ])
                ->values()
                ->all(),
        ];
    }

    private function reportTitle(InspectionLocationMap $map): string
    {
        $reference = $map->equipmentDocument?->document_number
            ?? data_get($map->reference_snapshot, 'document_number');

        return $reference === null || trim((string) $reference) === ''
            ? $map->title
            : $map->title.' — PROJETO DE REFERÊNCIA: '.$reference;
    }
}
