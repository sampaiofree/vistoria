<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PhotoProcessingStatus;
use App\Models\InspectionOverviewPhoto;
use App\Services\Photos\PhotoVariantProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class ProcessInspectionOverviewPhoto implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public readonly int $photoId) {}

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(?PhotoVariantProcessor $processor = null): void
    {
        $processor ??= app(PhotoVariantProcessor::class);
        $photo = InspectionOverviewPhoto::query()->find($this->photoId);

        // A substituição de um slot pode ocorrer antes de o job antigo iniciar.
        if ($photo === null) {
            return;
        }
        $photo->update(['processing_status' => PhotoProcessingStatus::Processing, 'processing_error' => null]);

        try {
            if (! $photo->original_path) {
                throw new \RuntimeException('Arquivo original não encontrado.');
            }

            $photo->update(array_merge($processor->process($photo->disk, $photo->original_path), [
                'processed_at' => now(),
                'processing_status' => PhotoProcessingStatus::Ready,
            ]));
        } catch (Throwable $exception) {
            $photo->update(['processing_status' => PhotoProcessingStatus::Failed, 'processing_error' => $exception->getMessage()]);

            throw $exception;
        }
    }
}
