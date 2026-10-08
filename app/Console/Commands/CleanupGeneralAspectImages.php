<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PhotoProcessingStatus;
use App\Models\GeneralAspectsTemplateImage;
use App\Models\InspectionGeneralAspectImage;
use App\Services\Reports\GeneralAspectsDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class CleanupGeneralAspectImages extends Command
{
    protected $signature = 'general-aspects:cleanup-images';

    protected $description = 'Remove imagens dos aspectos gerais abandonadas há mais de sete dias';

    public function handle(GeneralAspectsDocument $documents): int
    {
        InspectionGeneralAspectImage::query()
            ->with('inspection')
            ->where('unreferenced_at', '<=', now()->subDays(7))
            ->whereIn('processing_status', [PhotoProcessingStatus::Ready, PhotoProcessingStatus::Failed])
            ->chunkById(100, function ($images) use ($documents): void {
                foreach ($images as $image) {
                    $saved = $documents->fromStored($image->inspection?->general_notes);
                    if (in_array($image->public_id, $documents->imageAssetIds($saved['document'] ?? null), true)) {
                        $image->update(['unreferenced_at' => null]);

                        continue;
                    }

                    try {
                        $paths = array_values(array_filter([$image->original_path, $image->optimized_path, $image->thumbnail_path]));
                        if ($paths !== [] && ! Storage::disk($image->disk)->delete($paths)) {
                            continue;
                        }
                        $image->delete();
                    } catch (Throwable $exception) {
                        $this->warn("Não foi possível remover a imagem {$image->public_id}: {$exception->getMessage()}");
                    }
                }
            });

        GeneralAspectsTemplateImage::query()
            ->with('template')
            ->where('unreferenced_at', '<=', now()->subDays(7))
            ->whereIn('processing_status', [PhotoProcessingStatus::Ready, PhotoProcessingStatus::Failed])
            ->chunkById(100, function ($images) use ($documents): void {
                foreach ($images as $image) {
                    $ids = $documents->imageAssetIds($image->template?->document);
                    if (in_array($image->public_id, $ids, true)) {
                        $image->update(['unreferenced_at' => null]);

                        continue;
                    }
                    try {
                        $paths = array_values(array_filter([$image->original_path, $image->optimized_path, $image->thumbnail_path]));
                        if ($paths !== [] && ! Storage::disk($image->disk)->delete($paths)) {
                            continue;
                        }
                        $image->delete();
                    } catch (Throwable $exception) {
                        $this->warn("Não foi possível remover a imagem do modelo {$image->public_id}: {$exception->getMessage()}");
                    }
                }
            });

        return self::SUCCESS;
    }
}
