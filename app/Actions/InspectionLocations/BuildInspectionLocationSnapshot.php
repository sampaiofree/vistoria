<?php

declare(strict_types=1);

namespace App\Actions\InspectionLocations;

use App\Models\Inspection;
use App\Services\InspectionLocations\InspectionLocationReportComposer;

final class BuildInspectionLocationSnapshot
{
    public function __construct(private readonly InspectionLocationReportComposer $composer) {}

    /** @return array<string, mixed> */
    public function handle(Inspection $inspection): array
    {
        return $this->fromComposition($inspection, $this->composer->compose($inspection));
    }

    /** @param array<string, mixed> $report @return array<string, mixed> */
    public function fromComposition(Inspection $inspection, array $report): array
    {

        return [
            'version' => 1,
            'inspection' => [
                'public_id' => $inspection->public_id,
                'number' => $inspection->number,
            ],
            'categories' => collect($report['categories'])->map(fn (array $category): array => [
                'category' => $category['category'],
                'maps' => collect($category['maps'])->map(fn (array $map): array => [
                    'public_id' => $map['public_id'],
                    'title' => $map['title'],
                    'report_title' => $map['report_title'],
                    'description' => $map['description'],
                    'observations' => $map['observations'],
                    'position' => $map['position'],
                    'geometry_schema_version' => $map['geometry_schema_version'],
                    'source' => $map['source'],
                    'background' => collect($map['background'])->except('url')->all(),
                    'damage_rows' => $map['damage_rows'],
                    'classification_legend' => $map['classification_legend'],
                    'markers' => collect($map['markers'])->map(fn (array $marker): array => [
                        'public_id' => $marker['public_id'],
                        'label' => $marker['label'],
                        'position' => $marker['position'],
                        'geometry' => $marker['geometry'],
                        'style' => $marker['style'],
                        'assessment' => $marker['assessment'],
                        'defect' => $marker['defect'],
                        'photo_numbers' => $marker['photo_numbers'],
                        'photo_interval' => $marker['photo_interval'],
                        'photo_legend' => $marker['photo_legend'],
                        'photos' => collect($marker['photos'])->map(fn (array $photo): array => collect($photo)
                            ->except(['url', 'thumbnail_url'])
                            ->all())->all(),
                    ])->all(),
                ])->all(),
            ])->all(),
            'photo_count' => $report['photo_count'],
            'map_count' => $report['map_count'],
            'marker_count' => $report['marker_count'],
        ];
    }
}
