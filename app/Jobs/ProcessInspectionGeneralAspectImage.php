<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PhotoProcessingStatus;
use App\Models\InspectionGeneralAspectImage;
use App\Services\Notifications\NotifyInspectionImageFailure;
use App\Services\Photos\PhotoVariantProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ProcessInspectionGeneralAspectImage implements ShouldQueue
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
        $image = InspectionGeneralAspectImage::query()->find($this->imageId);
        if ($image === null || $image->isReady()) {
            return;
        }

        $image->update(['processing_status' => PhotoProcessingStatus::Processing, 'processing_error' => null]);

        try {
            if ($image->original_path === null) {
                throw new \RuntimeException('Arquivo original não encontrado.');
            }

            $image->update(array_merge(($processor ?? app(PhotoVariantProcessor::class))->process($image->disk, $image->original_path), [
                'processed_at' => now(),
                'processing_status' => PhotoProcessingStatus::Ready,
            ]));

            $this->removeOriginal($image->refresh());
        } catch (Throwable $exception) {
            $image->update(['processing_error' => $exception->getMessage()]);
            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $image = InspectionGeneralAspectImage::query()->with('inspection')->find($this->imageId);
        if ($image === null || $image->isReady() || $image->processing_status === PhotoProcessingStatus::Failed) {
            return;
        }

        $this->deletePaths($image, ['original_path', 'optimized_path', 'thumbnail_path']);
        $image->update([
            'processing_status' => PhotoProcessingStatus::Failed,
            'processing_error' => $exception->getMessage(),
            'processed_at' => null,
        ]);

        app(NotifyInspectionImageFailure::class)->handle(
            $image->inspection,
            $image->uploaded_by,
            'Falha no processamento de imagem dos Aspectos Gerais',
            sprintf('A imagem “%s” não pôde ser processada. Escolha outra imagem.', $image->original_name),
            route('inspections.show', $image->inspection),
        );
    }

    private function removeOriginal(InspectionGeneralAspectImage $image): void
    {
        $this->deletePaths($image, ['original_path']);
    }

    /** @param list<string> $attributes */
    private function deletePaths(InspectionGeneralAspectImage $image, array $attributes): void
    {
        foreach ($attributes as $attribute) {
            $path = $image->{$attribute};
            if ($path === null) {
                continue;
            }
            try {
                $disk = Storage::disk($image->disk);
                if (! $disk->exists($path) || $disk->delete($path)) {
                    $image->{$attribute} = null;
                } else {
                    Log::warning('Não foi possível remover imagem dos Aspectos Gerais.', ['asset_id' => $image->public_id, 'path' => $path]);
                }
            } catch (Throwable $exception) {
                Log::warning('Não foi possível remover imagem dos Aspectos Gerais.', ['asset_id' => $image->public_id, 'exception' => $exception::class]);
            }
        }
        $image->save();
    }
}
