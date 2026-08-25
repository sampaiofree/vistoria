<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PhotoProcessingStatus;
use App\Models\AssessmentPhoto;
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

final class ProcessAssessmentPhoto implements ShouldQueue
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
        $photo = AssessmentPhoto::query()->findOrFail($this->photoId);
        $photo->update(['processing_status' => PhotoProcessingStatus::Processing, 'processing_error' => null]);

        try {
            if (! $photo->original_path) {
                throw new \RuntimeException('Arquivo original não encontrado.');
            }

            $photo->update(array_merge($processor->process($photo->disk, $photo->original_path), [
                'processed_at' => now(),
                'processing_status' => PhotoProcessingStatus::Ready,
            ]));

            $this->removeOriginal($photo->refresh());
        } catch (Throwable $exception) {
            $photo->update(['processing_error' => $exception->getMessage()]);
            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $photo = AssessmentPhoto::query()->with(['inspection', 'assessment'])->find($this->photoId);

        if ($photo === null || $photo->processing_status === PhotoProcessingStatus::Ready || $photo->processing_status === PhotoProcessingStatus::Failed) {
            return;
        }

        $this->removeFailedArtifacts($photo);
        $photo->update([
            'processing_status' => PhotoProcessingStatus::Failed,
            'processing_error' => $exception->getMessage(),
            'processed_at' => null,
        ]);

        app(NotifyInspectionImageFailure::class)->handle(
            $photo->inspection,
            $photo->uploaded_by,
            'Falha no processamento de fotografia',
            sprintf('A fotografia “%s” não pôde ser processada. Escolha outra imagem.', $photo->original_name),
            route('defect-assessments.show', $photo->assessment),
        );
    }

    private function removeOriginal(AssessmentPhoto $photo): void
    {
        $path = $photo->original_path;
        if ($path === null) {
            return;
        }

        try {
            $disk = Storage::disk($photo->disk);
            if (! $disk->exists($path) || $disk->delete($path)) {
                $photo->update(['original_path' => null]);
            } else {
                Log::warning('Não foi possível remover o upload temporário de uma fotografia processada.', ['photo_public_id' => $photo->public_id]);
            }
        } catch (Throwable $exception) {
            Log::warning('Não foi possível remover o upload temporário de uma fotografia processada.', [
                'photo_public_id' => $photo->public_id,
                'exception' => $exception::class,
            ]);
        }
    }

    private function removeFailedArtifacts(AssessmentPhoto $photo): void
    {
        $disk = Storage::disk($photo->disk);

        foreach (['original_path', 'optimized_path', 'thumbnail_path'] as $attribute) {
            $path = $photo->{$attribute};
            if ($path === null) {
                continue;
            }

            try {
                if (! $disk->exists($path) || $disk->delete($path)) {
                    $photo->setAttribute($attribute, null);
                }
            } catch (Throwable $exception) {
                Log::warning('Não foi possível remover um arquivo de fotografia após falha definitiva.', [
                    'photo_public_id' => $photo->public_id,
                    'attribute' => $attribute,
                    'exception' => $exception::class,
                ]);
            }
        }

        $photo->save();
    }
}
