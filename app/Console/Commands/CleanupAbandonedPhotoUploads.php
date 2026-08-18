<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PhotoProcessingStatus;
use App\Models\AssessmentPhoto;
use App\Models\InspectionOverviewPhoto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

final class CleanupAbandonedPhotoUploads extends Command
{
    protected $signature = 'photos:cleanup-abandoned {--hours=24 : Idade mínima dos uploads abandonados}';

    protected $description = 'Marca como falhos e remove arquivos de uploads de fotos abandonados';

    public function handle(): int
    {
        $cutoff = now()->subHours((int) $this->option('hours'));
        $count = 0;

        foreach ([AssessmentPhoto::class, InspectionOverviewPhoto::class] as $model) {
            $model::query()
                ->whereIn('processing_status', [PhotoProcessingStatus::Pending->value, PhotoProcessingStatus::Processing->value])
                ->where('uploaded_at', '<', $cutoff)
                ->chunkById(100, function ($photos) use (&$count): void {
                    foreach ($photos as $photo) {
                        $disk = Storage::disk($photo->disk);
                        foreach ([$photo->original_path, $photo->optimized_path, $photo->thumbnail_path] as $path) {
                            if ($path !== null) {
                                $disk->delete($path);
                            }
                        }

                        $photo->update([
                            'processing_status' => PhotoProcessingStatus::Failed,
                            'processing_error' => 'Upload abandonado e limpo automaticamente.',
                        ]);
                        $count++;
                    }
                });
        }

        $this->info(sprintf('%d upload(s) abandonado(s) limpo(s).', $count));

        return self::SUCCESS;
    }
}
