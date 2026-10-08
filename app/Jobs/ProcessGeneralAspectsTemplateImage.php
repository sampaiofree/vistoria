<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PhotoProcessingStatus;
use App\Models\GeneralAspectsTemplateImage;
use App\Services\Photos\PhotoVariantProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class ProcessGeneralAspectsTemplateImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public readonly int $imageId) {}

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(?PhotoVariantProcessor $processor = null): void
    {
        $image = GeneralAspectsTemplateImage::query()->find($this->imageId);
        if ($image === null || $image->isReady()) {
            return;
        }

        $image->update(['processing_status' => PhotoProcessingStatus::Processing, 'processing_error' => null]);
        try {
            if ($image->original_path === null) {
                throw new RuntimeException('Arquivo original não encontrado.');
            }
            $image->update(array_merge(($processor ?? app(PhotoVariantProcessor::class))->process($image->disk, $image->original_path), [
                'processed_at' => now(), 'processing_status' => PhotoProcessingStatus::Ready,
            ]));
            Storage::disk($image->disk)->delete($image->original_path);
            $image->update(['original_path' => null]);
        } catch (Throwable $exception) {
            $image->update(['processing_error' => $exception->getMessage()]);
            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $image = GeneralAspectsTemplateImage::query()->find($this->imageId);
        if ($image === null || $image->isReady()) {
            return;
        }
        Storage::disk($image->disk)->delete(array_values(array_filter([
            $image->original_path, $image->optimized_path, $image->thumbnail_path,
        ])));
        $image->update([
            'processing_status' => PhotoProcessingStatus::Failed,
            'processing_error' => $exception->getMessage(),
            'original_path' => null, 'optimized_path' => null, 'thumbnail_path' => null,
            'processed_at' => null,
        ]);
    }
}
