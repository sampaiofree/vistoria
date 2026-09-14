<?php

declare(strict_types=1);

namespace App\Services\Reports;

use Illuminate\Support\Collection;

final class InspectionPhotographicDocumentationComposer
{
    /**
     * @param  array<int, array<string, mixed>>|Collection<int, array<string, mixed>>  $items
     * @param  array<string, int>  $numbering
     * @return array{blocks:array<int, array<string, mixed>>, photo_count:int, unindexed_photo_ids:array<int, string>}
     */
    public function compose(array|Collection $items, array $numbering = []): array
    {
        $blocks = [];
        $unindexedPhotoIds = [];

        foreach (collect($items) as $item) {
            if (($item['assessment']['status'] ?? null) === 'draft') {
                continue;
            }

            $photos = $this->orderedPhotos($item['photos'] ?? []);
            if ($photos->isEmpty()) {
                continue;
            }

            $preparedPhotos = $photos->map(function (array $photo) use ($numbering, &$unindexedPhotoIds, $item): array {
                $id = (string) ($photo['id'] ?? '');
                if ($id !== '' && ! isset($numbering[$id])) {
                    $unindexedPhotoIds[] = $id;
                }

                return [
                    'id' => $id,
                    'sequence' => $id !== '' ? ($numbering[$id] ?? null) : null,
                    'title' => $item['title'] ?? 'Avaria',
                    'caption' => $photo['caption'] ?? null,
                    'url' => $photo['url'] ?? null,
                    'thumbnail_url' => $photo['thumbnail_url'] ?? null,
                    'status' => $photo['status'] ?? $photo['processing_status'] ?? 'ready',
                    'status_label' => $photo['status_label'] ?? null,
                    'position' => (int) ($photo['position'] ?? 0),
                ];
            })->values();

            foreach ($preparedPhotos->chunk(2)->values() as $pairIndex => $pair) {
                $blocks[] = [
                    'id' => sprintf('%s-photo-pair-%d', $item['id'] ?? 'defect', $pairIndex + 1),
                    'assessment_id' => data_get($item, 'assessment.id'),
                    'assessment_public_id' => data_get($item, 'assessment.public_id'),
                    'category' => $item['category'] ?? null,
                    'category_label' => $item['category_label'] ?? null,
                    'defect_id' => $item['id'] ?? null,
                    'defect_code' => $item['code'] ?? null,
                    'classification_code' => data_get($item, 'classification.code') ?? data_get($item, 'assessment.classification_code'),
                    'condition' => data_get($item, 'assessment.condition'),
                    'condition_label' => data_get($item, 'assessment.condition_label'),
                    'previous_classification' => data_get($item, 'previous_assessment_summary.classification'),
                    'current_classification' => $item['classification'] ?? null,
                    'defect_title' => $item['title'] ?? 'Avaria',
                    'equipment_label' => null,
                    'comment' => data_get($item, 'assessment.comment'),
                    'recommendation' => data_get($item, 'assessment.recommendation'),
                    'pair_number' => $pairIndex + 1,
                    'photos' => $pair->values()->all(),
                ];
            }
        }

        return [
            'blocks' => $blocks,
            'photo_count' => collect($blocks)->sum(fn (array $block): int => count($block['photos'])),
            'unindexed_photo_ids' => array_values(array_unique($unindexedPhotoIds)),
        ];
    }

    /**
     * Every photo follows the explicit gallery order.
     *
     * @param  array<int, array<string, mixed>>|Collection<int, array<string, mixed>>  $photos
     * @return Collection<int, array<string, mixed>>
     */
    private function orderedPhotos(array|Collection $photos): Collection
    {
        return collect($photos)
            ->sortBy(fn (array $photo): array => [
                (int) ($photo['position'] ?? 0),
                (string) ($photo['id'] ?? ''),
            ])
            ->values();
    }
}
