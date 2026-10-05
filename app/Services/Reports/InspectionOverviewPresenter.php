<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Actions\InspectionOverview\PositionInspectionOverviewPhotos;
use App\Enums\PhotoProcessingStatus;
use App\Models\Inspection;
use App\Models\InspectionOverviewBlock;
use App\Models\InspectionOverviewPhoto;

final class InspectionOverviewPresenter
{
    /** @return array<string, mixed> */
    public function present(Inspection $inspection, bool $editable = false): array
    {
        $inspection->loadMissing(['overviewBlocks.photos']);
        $blocks = $inspection->overviewBlocks->keyBy('position');
        $orderedPhotos = $inspection->overviewBlocks
            ->sortBy('position')
            ->flatMap(fn (InspectionOverviewBlock $block) => $block->photos
                ->sortBy('slot')
                ->map(fn (InspectionOverviewPhoto $photo): array => [
                    'photo' => $photo,
                    'position' => $block->position,
                    'slot' => $photo->slot,
                ]))
            ->values()
            ->take(PositionInspectionOverviewPhotos::MAX_PHOTOS);
        $photosByNumber = $orderedPhotos->keyBy(fn (array $entry): int => (($entry['position'] - 1) * 2) + $entry['slot']);
        $hasGaps = $orderedPhotos->isNotEmpty()
            && $photosByNumber->keys()->sort()->values()->all() !== range(1, $orderedPhotos->count());
        $pageCount = max(1, (int) ceil(($photosByNumber->keys()->max() ?? 0) / 4));
        $pages = collect(range(1, $pageCount))->map(function (int $pageNumber) use ($inspection, $blocks, $photosByNumber, $editable): array {
            $firstPosition = (($pageNumber - 1) * 2) + 1;
            $first = $blocks->get($firstPosition);
            $second = $blocks->get($firstPosition + 1);
            $comment = collect([$first?->comment, $second?->comment])->first(fn ($value): bool => filled($value));
            $recommendation = collect([$first?->recommendation, $second?->recommendation])->first(fn ($value): bool => filled($value));
            $pageBlocks = collect([0, 1])->map(function (int $pairIndex) use (
                $inspection,
                $photosByNumber,
                $firstPosition,
                $comment,
                $recommendation,
                $editable,
            ): array {
                $position = $firstPosition + $pairIndex;

                return $this->blockPayload(
                    $inspection,
                    $position,
                    [
                        $photosByNumber->get((($position - 1) * 2) + 1),
                        $photosByNumber->get((($position - 1) * 2) + 2),
                    ],
                    $comment,
                    $recommendation,
                    $editable,
                );
            })->all();

            return [
                'number' => $pageNumber,
                'comment' => $comment,
                'recommendation' => $recommendation,
                'update_url' => $editable
                    ? route('inspections.report-overview.blocks.update', ['inspection' => $inspection, 'position' => $firstPosition])
                    : null,
                'blocks' => $pageBlocks,
                'photos' => collect($pageBlocks)
                    ->flatMap(fn (array $block): array => $block['photos'])
                    ->filter(fn (array $slot): bool => $slot['photo'] !== null)
                    ->values()
                    ->all(),
            ];
        })->all();
        $photoCount = $orderedPhotos->count();
        $readyCount = $orderedPhotos->filter(fn (array $entry): bool => $entry['photo']->processing_status === PhotoProcessingStatus::Ready)->count();

        return [
            'pages' => $pages,
            'blocks' => collect($pages)->flatMap(fn (array $page): array => $page['blocks'])->values()->all(),
            'photo_count' => $photoCount,
            'ready_count' => $readyCount,
            'has_gaps' => $hasGaps,
            'complete' => $photoCount >= 2
                && $photoCount % 2 === 0
                && $readyCount === $photoCount
                && ! $hasGaps
                && collect($pages)->every(fn (array $page): bool => filled($page['comment']) && filled($page['recommendation'])),
            'append_url' => $editable && $photoCount < PositionInspectionOverviewPhotos::MAX_PHOTOS
                ? route('inspections.report-overview.photos.append', $inspection)
                : null,
            'reorder_url' => $editable && $photoCount > 1 && ! $hasGaps
                ? route('inspections.report-overview.photos.reorder', $inspection)
                : null,
            'max_photos' => PositionInspectionOverviewPhotos::MAX_PHOTOS,
        ];
    }

    /**
     * @param  array<int, array{photo: InspectionOverviewPhoto, position: int, slot: int}|null>  $entries
     * @return array<string, mixed>
     */
    private function blockPayload(
        Inspection $inspection,
        int $position,
        array $entries,
        ?string $comment,
        ?string $recommendation,
        bool $editable,
    ): array {
        return [
            'position' => $position,
            'title' => sprintf('Fotos %d e %d', (($position - 1) * 2) + 1, $position * 2),
            'comment' => $comment,
            'recommendation' => $recommendation,
            'update_url' => $editable
                ? route('inspections.report-overview.blocks.update', ['inspection' => $inspection, 'position' => $position])
                : null,
            'photos' => collect([1, 2])->map(function (int $slot) use ($inspection, $entries, $position, $editable): array {
                $entry = $entries[$slot - 1] ?? null;
                $photo = $entry['photo'] ?? null;

                return [
                    'slot' => $slot,
                    'number' => (($position - 1) * 2) + $slot,
                    'upload_url' => $editable
                        ? route('inspections.report-overview.photos.store', [
                            'inspection' => $inspection,
                            'position' => $entry['position'] ?? $position,
                            'slot' => $entry['slot'] ?? $slot,
                        ])
                        : null,
                    'photo' => $photo instanceof InspectionOverviewPhoto
                        ? $this->photoPayload($photo, $editable)
                        : null,
                ];
            })->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function photoPayload(InspectionOverviewPhoto $photo, bool $editable): array
    {
        $status = $photo->processing_status;

        return [
            'id' => $photo->public_id,
            'name' => $photo->original_name,
            'status' => $status->value,
            'status_label' => match ($status) {
                PhotoProcessingStatus::Pending => 'Aguardando processamento',
                PhotoProcessingStatus::Processing => 'Processando',
                PhotoProcessingStatus::Ready => 'Pronta',
                PhotoProcessingStatus::Failed => 'Falha no processamento',
            },
            'processing_error' => $photo->processing_error,
            'thumbnail_url' => $photo->isReady()
                ? route('inspection-overview-photos.show', [$photo, 'variant' => 'thumbnail'])
                : null,
            'optimized_url' => $photo->isReady()
                ? route('inspection-overview-photos.show', $photo)
                : null,
            'delete_url' => $editable
                ? route('inspection-overview-photos.destroy', $photo)
                : null,
        ];
    }
}
