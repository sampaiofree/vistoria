<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\PhotoProcessingStatus;
use App\Models\Inspection;
use App\Models\InspectionOverviewBlock;
use App\Models\InspectionOverviewPhoto;

final class InspectionOverviewPresenter
{
    /** @return array{blocks: array<int, array<string, mixed>>, complete: bool} */
    public function present(Inspection $inspection, bool $editable = false): array
    {
        $inspection->loadMissing(['overviewBlocks.photos']);
        $blocks = $inspection->overviewBlocks->keyBy('position');

        $payload = collect([1, 2])
            ->map(fn (int $position): array => $this->blockPayload(
                $inspection,
                $blocks->get($position),
                $position,
                $editable,
            ))
            ->values()
            ->all();

        return [
            'blocks' => $payload,
            'complete' => collect($payload)->every(function (array $block): bool {
                return filled($block['comment'])
                    && filled($block['recommendation'])
                    && collect($block['photos'])->every(
                        fn (array $slot): bool => ($slot['photo']['status'] ?? null) === PhotoProcessingStatus::Ready->value,
                    );
            }),
        ];
    }

    /** @return array<string, mixed> */
    private function blockPayload(
        Inspection $inspection,
        ?InspectionOverviewBlock $block,
        int $position,
        bool $editable,
    ): array {
        $photos = $block?->photos->keyBy('slot') ?? collect();

        return [
            'position' => $position,
            'title' => $position === 1 ? 'Fotos 1 e 2' : 'Fotos 3 e 4',
            'comment' => $block?->comment,
            'recommendation' => $block?->recommendation,
            'update_url' => $editable
                ? route('inspections.report-overview.blocks.update', ['inspection' => $inspection, 'position' => $position])
                : null,
            'photos' => collect([1, 2])->map(function (int $slot) use ($inspection, $photos, $position, $editable): array {
                $photo = $photos->get($slot);

                return [
                    'slot' => $slot,
                    'number' => (($position - 1) * 2) + $slot,
                    'upload_url' => $editable
                        ? route('inspections.report-overview.photos.store', [
                            'inspection' => $inspection,
                            'position' => $position,
                            'slot' => $slot,
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
            'original_url' => $photo->isReady()
                ? route('inspection-overview-photos.show', [$photo, 'variant' => 'original'])
                : null,
            'retry_url' => $editable && $status === PhotoProcessingStatus::Failed
                ? route('inspection-overview-photos.retry', $photo)
                : null,
            'delete_url' => $editable
                ? route('inspection-overview-photos.destroy', $photo)
                : null,
        ];
    }
}
