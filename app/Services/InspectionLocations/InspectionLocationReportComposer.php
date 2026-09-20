<?php

declare(strict_types=1);

namespace App\Services\InspectionLocations;

use App\Enums\DefectAssessmentStatus;
use App\Enums\DefectCategory;
use App\Models\AssessmentPhoto;
use App\Models\DefectAssessment;
use App\Models\Inspection;
use App\Services\Classification\NativeDefectCatalog;
use App\Services\Defects\DefectAssessmentQuantitySnapshot;

final class InspectionLocationReportComposer
{
    public function __construct(
        private readonly InspectionLocationPhotoNumbering $photoNumbering,
        private readonly InspectionLocationReportCategoryOrder $categoryOrder,
        private readonly DefectLocationColor $colors,
        private readonly DefectAssessmentQuantitySnapshot $quantitySnapshots,
    ) {}

    /** @return array<string,mixed> */
    public function compose(Inspection $inspection): array
    {
        $assessments = DefectAssessment::query()
            ->forOrganization($inspection->organization_id)
            ->where('inspection_id', $inspection->id)
            ->where('status', DefectAssessmentStatus::Complete->value)
            ->with(['defect', 'quantities', 'photos', 'location', 'locationMapVersion.map'])
            ->get()
            ->filter(fn (DefectAssessment $assessment): bool => ! $assessment->condition->isCanceled()
                && $assessment->locationMapVersion?->isReady()
                && $assessment->location?->isConfirmed())
            ->sortBy(fn (DefectAssessment $assessment): array => [
                $assessment->defect->category->position(),
                $assessment->defect->sequence_number,
                $assessment->id,
            ])->values();

        $numbering = $this->photoNumbering->buildForReport($inspection, $assessments);
        $categories = $this->categoryOrder->sort(collect(DefectCategory::cases()))
            ->map(function (DefectCategory $category) use ($assessments, $numbering): array {
                $categoryAssessments = $assessments->filter(
                    fn (DefectAssessment $assessment): bool => $assessment->defect->category === $category,
                );

                return [
                    'category' => $category->toArray(),
                    'maps' => $categoryAssessments
                        ->map(fn (DefectAssessment $assessment): array => $this->mapPayload($assessment, $numbering))
                        ->values()->all(),
                ];
            })
            ->filter(fn (array $category): bool => $category['maps'] !== [])
            ->values();

        $sheets = $categories->flatMap(fn (array $category): array => collect($category['maps'])
            ->values()
            ->map(fn (array $map, int $index): array => [
                'id' => $category['category']['code'].'-'.$map['assessment_public_id'],
                'number' => $index + 1,
                'category' => $category['category'],
                'maps' => [$map],
            ])->all())->values();

        return [
            'categories' => $categories->all(),
            'sheets' => $sheets->all(),
            'numbering' => $numbering,
            'photo_count' => count($numbering),
            'map_count' => $assessments->count(),
            'marker_count' => $assessments->count(),
        ];
    }

    /** @param array<string,int> $numbering @return array<string,mixed> */
    private function mapPayload(DefectAssessment $assessment, array $numbering): array
    {
        $version = $assessment->locationMapVersion;
        $map = $version->map;
        $location = $assessment->location;
        $numbers = $this->photoNumbering->numbersForAssessment($assessment, $numbering);
        $quantitySnapshot = is_array($assessment->quantity_snapshot)
            ? $this->quantitySnapshots->normalize($assessment->quantity_snapshot)
            : $this->quantitySnapshots->build($assessment->defect->category, $assessment->quantities);
        $style = $this->colors->styleForAssessment($assessment);

        return [
            'public_id' => $map->public_id,
            'assessment_public_id' => $assessment->public_id,
            'title' => $assessment->defect->code.' · '.$assessment->defect->title,
            'report_title' => $assessment->defect->code.' · '.$assessment->defect->title,
            'description' => $assessment->location_description,
            'observations' => $location->label ?? $assessment->location_description,
            'position' => (int) $assessment->defect->sequence_number,
            'processing_status' => $version->processing_status->value,
            'geometry_schema_version' => 1,
            'source' => [
                'version' => $version->version,
                'disk' => $version->source_disk,
                'path' => $version->source_path,
                'mime_type' => $version->source_mime_type,
                'size' => $version->source_size,
                'checksum' => $version->source_checksum,
                'document' => null,
            ],
            'background' => [
                'disk' => $version->background_disk,
                'path' => $version->background_path,
                'mime_type' => $version->background_mime_type,
                'size' => $version->background_size,
                'width' => $version->background_width,
                'height' => $version->background_height,
                'checksum' => $version->background_checksum,
                'url' => route('defect-location-map-versions.background', [
                    'mapVersion' => $version,
                    'v' => $version->background_checksum,
                ]),
            ],
            'marker_count' => 1,
            'damage_rows' => [[
                'assessment' => ['public_id' => $assessment->public_id],
                'defect' => ['public_id' => $assessment->defect->public_id, 'code' => $assessment->defect->code],
                'photo_numbers' => $numbers,
                'photo_interval' => $this->photoNumbering->format($numbers),
                'quantity' => $this->quantityPayload($quantitySnapshot),
                'gut' => [
                    'gravity' => $this->gutCriterionPayload($assessment, 'gravity'),
                    'urgency' => $this->gutCriterionPayload($assessment, 'urgency'),
                    'trend' => $this->gutCriterionPayload($assessment, 'trend'),
                ],
                'classification' => [
                    'code' => data_get($assessment->classification_snapshot, 'code') ?? $assessment->classification_code,
                    'color' => $this->colors->forAssessment($assessment),
                ],
            ]],
            'classification_legend' => NativeDefectCatalog::classifications($assessment->defect->category)
                ->map(fn ($classification): array => ['code' => $classification->code, 'color' => $classification->color])
                ->values()->all(),
            'markers' => [[
                'public_id' => $location->public_id,
                'label' => $location->label,
                'position' => 1,
                'geometry' => $location->geometry,
                'style' => $style,
                'assessment' => [
                    'public_id' => $assessment->public_id,
                    'condition' => $assessment->condition->value,
                    'status' => $assessment->status->value,
                    'location_description' => $assessment->location_description,
                ],
                'defect' => [
                    'public_id' => $assessment->defect->public_id,
                    'code' => $assessment->defect->code,
                    'title' => $assessment->defect->title,
                ],
                'photo_numbers' => $numbers,
                'photo_interval' => $this->photoNumbering->format($numbers),
                'photo_legend' => $this->photoNumbering->legend($numbers),
                'photos' => $this->photoPayloads($assessment, $numbering),
            ]],
        ];
    }

    /** @param array<string,mixed>|null $snapshot @return array<string,mixed>|null */
    private function quantityPayload(?array $snapshot): ?array
    {
        if ($snapshot === null) {
            return null;
        }

        $unit = match ($snapshot['measurement_unit'] ?? null) {
            'm2' => 'M²',
            'm3' => 'M³',
            'kg' => 'KG',
            default => mb_strtoupper((string) ($snapshot['measurement_unit'] ?? '')),
        };

        return [
            'value' => number_format((float) ($snapshot['total'] ?? 0), 2, ',', '.'),
            'raw_value' => (string) ($snapshot['total'] ?? '0'),
            'unit' => $unit,
            'snapshot' => $snapshot,
        ];
    }

    /** @return array{score:?int,color:?string} */
    private function gutCriterionPayload(DefectAssessment $assessment, string $criterion): array
    {
        return [
            'score' => data_get($assessment->gut_snapshot, "criteria.{$criterion}.score") ?? $assessment->{$criterion},
            'color' => data_get($assessment->gut_snapshot, "criteria.{$criterion}.color"),
        ];
    }

    /** @param array<string,int> $numbering @return array<int,array<string,mixed>> */
    private function photoPayloads(DefectAssessment $assessment, array $numbering): array
    {
        return $assessment->photos
            ->filter(fn (AssessmentPhoto $photo): bool => isset($numbering[$photo->public_id]))
            ->sortBy(fn (AssessmentPhoto $photo): array => [(int) $photo->position, (int) $photo->id])
            ->map(fn (AssessmentPhoto $photo): array => [
                'public_id' => $photo->public_id,
                'number' => $numbering[$photo->public_id],
                'position' => (int) $photo->position,
                'type' => $photo->photo_type->value,
                'caption' => $photo->caption,
                'processing_status' => $photo->processing_status->value,
                'checksum' => $photo->checksum,
                'url' => $photo->isReady() ? route('assessment-photos.show', [$photo, 'optimized']) : null,
                'thumbnail_url' => $photo->isReady() ? route('assessment-photos.show', [$photo, 'thumbnail']) : null,
            ])->values()->all();
    }
}
