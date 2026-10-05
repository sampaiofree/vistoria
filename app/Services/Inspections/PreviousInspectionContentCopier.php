<?php

declare(strict_types=1);

namespace App\Services\Inspections;

use App\Enums\PhotoProcessingStatus;
use App\Models\DefectAssessment;
use App\Models\Inspection;
use App\Models\InspectionClassificationM2Link;
use App\Models\InspectionOverviewBlock;
use App\Models\InspectionOverviewPhoto;
use App\Models\InspectionSpecialAssessmentNote;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

final class PreviousInspectionContentCopier
{
    /**
     * Called inside the inspection transaction. The caller must remove the paths
     * collected here if that transaction (including an enclosing batch) rolls back.
     *
     * @param  array<int, array{disk:string,path:string}>  $copiedFiles
     */
    public function copy(Inspection $inspection, ?Inspection $previous, User $actor, array &$copiedFiles): void
    {
        if ($previous === null) {
            $this->ensureInitialBlocks($inspection, $actor);

            return;
        }

        $previous->load(['overviewBlocks.photos', 'classificationM2Links', 'specialAssessmentNotes']);
        $blocks = [];

        foreach ($previous->overviewBlocks as $sourceBlock) {
            $blocks[$sourceBlock->position] = InspectionOverviewBlock::query()->create([
                'organization_id' => $inspection->organization_id,
                'inspection_id' => $inspection->id,
                'position' => $sourceBlock->position,
                'comment' => $sourceBlock->comment,
                'recommendation' => $sourceBlock->recommendation,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        }

        $this->ensureInitialBlocks($inspection, $actor, $blocks);

        foreach ($previous->overviewBlocks as $sourceBlock) {
            foreach ($sourceBlock->photos as $sourcePhoto) {
                if ($sourcePhoto->processing_status !== PhotoProcessingStatus::Ready
                    || $sourcePhoto->slot === null) {
                    continue;
                }

                if ($sourcePhoto->optimized_path === null || $sourcePhoto->thumbnail_path === null) {
                    Log::warning('Fotografia histórica pronta sem variantes; espaço deixado vazio na reinspeção.', [
                        'photo_public_id' => $sourcePhoto->public_id,
                    ]);

                    continue;
                }

                try {
                    $disk = Storage::disk($sourcePhoto->disk);
                    $sourceExists = $disk->exists($sourcePhoto->optimized_path) && $disk->exists($sourcePhoto->thumbnail_path);
                } catch (Throwable $exception) {
                    throw ValidationException::withMessages([
                        'inspection' => 'Não foi possível acessar as fotografias da inspeção anterior. Tente novamente.',
                    ]);
                }
                if (! $sourceExists) {
                    Log::warning('Arquivo de fotografia histórica ausente; espaço deixado vazio na reinspeção.', [
                        'photo_public_id' => $sourcePhoto->public_id,
                    ]);

                    continue;
                }

                $photo = InspectionOverviewPhoto::query()->create([
                    'organization_id' => $inspection->organization_id,
                    'inspection_id' => $inspection->id,
                    'inspection_overview_block_id' => $blocks[$sourceBlock->position]->id,
                    'slot' => $sourcePhoto->slot,
                    'processing_status' => PhotoProcessingStatus::Pending,
                    'disk' => $sourcePhoto->disk,
                    'original_name' => $sourcePhoto->original_name,
                    'original_mime_type' => $sourcePhoto->original_mime_type,
                    'original_extension' => $sourcePhoto->original_extension,
                    'original_size' => $sourcePhoto->original_size,
                    'original_width' => $sourcePhoto->original_width,
                    'original_height' => $sourcePhoto->original_height,
                    'optimized_size' => $sourcePhoto->optimized_size,
                    'optimized_width' => $sourcePhoto->optimized_width,
                    'optimized_height' => $sourcePhoto->optimized_height,
                    'thumbnail_size' => $sourcePhoto->thumbnail_size,
                    'thumbnail_width' => $sourcePhoto->thumbnail_width,
                    'thumbnail_height' => $sourcePhoto->thumbnail_height,
                    'checksum' => $sourcePhoto->checksum,
                    'uploaded_at' => now(),
                    'processed_at' => now(),
                    'uploaded_by' => $actor->id,
                ]);
                $directory = sprintf(
                    'organizations/%d/inspections/%s/report-overview/%s',
                    $inspection->organization_id,
                    $inspection->public_id,
                    $photo->public_id,
                );
                $optimizedPath = $directory.'/optimized.webp';
                $thumbnailPath = $directory.'/thumbnail.webp';

                foreach ([[$sourcePhoto->optimized_path, $optimizedPath], [$sourcePhoto->thumbnail_path, $thumbnailPath]] as [$source, $target]) {
                    $copiedFiles[] = ['disk' => $sourcePhoto->disk, 'path' => $target];
                    try {
                        $copied = $disk->copy($source, $target);
                    } catch (Throwable $exception) {
                        $copied = false;
                    }
                    if (! $copied) {
                        throw ValidationException::withMessages([
                            'inspection' => 'Não foi possível copiar uma fotografia da inspeção anterior. Tente novamente.',
                        ]);
                    }
                }

                $photo->update([
                    'optimized_path' => $optimizedPath,
                    'thumbnail_path' => $thumbnailPath,
                    'processing_status' => PhotoProcessingStatus::Ready,
                ]);
            }
        }

        foreach ($previous->classificationM2Links as $link) {
            InspectionClassificationM2Link::query()->create([
                'organization_id' => $inspection->organization_id,
                'inspection_id' => $inspection->id,
                'category' => $link->category,
                'classification_code' => $link->classification_code,
                'sap_m2_note_id' => $link->sap_m2_note_id,
                'created_by' => $actor->id,
            ]);
        }

        $notes = $previous->specialAssessmentNotes->keyBy('defect_assessment_id');
        foreach ($inspection->defectScopes()->where('requires_reinspection', false)->get() as $scope) {
            $sourceNote = $notes->get($scope->source_assessment_id);
            if ($sourceNote !== null) {
                $this->createSpecialNote($inspection, $scope->source_assessment_id, $sourceNote, $actor);
            }
        }
    }

    public function copySpecialNoteForAssessment(DefectAssessment $assessment, User $actor): void
    {
        $inspection = $assessment->inspection;
        if ($assessment->previous_assessment_id === null
            || $inspection->previous_inspection_id === null
            || data_get($inspection->context_snapshot, 'previous_content_version') !== 1) {
            return;
        }

        $sourceNote = InspectionSpecialAssessmentNote::query()
            ->where('organization_id', $inspection->organization_id)
            ->where('inspection_id', $inspection->previous_inspection_id)
            ->where('defect_assessment_id', $assessment->previous_assessment_id)
            ->first();

        if ($sourceNote !== null) {
            $this->createSpecialNote($inspection, $assessment->id, $sourceNote, $actor);
        }
    }

    /** @return array<int, array{disk:string,path:string}> */
    public function clear(Inspection $inspection): array
    {
        $oldFiles = [];
        foreach ($inspection->overviewPhotos()->get() as $photo) {
            foreach ([$photo->original_path, $photo->optimized_path, $photo->thumbnail_path] as $path) {
                if ($path !== null) {
                    $oldFiles[] = ['disk' => $photo->disk, 'path' => $path];
                }
            }
            $photo->forceDelete();
        }
        $inspection->overviewBlocks()->delete();
        $inspection->classificationM2Links()->delete();
        $inspection->specialAssessmentNotes()->delete();

        return $oldFiles;
    }

    /** @param array<int, array{disk:string,path:string}> $files */
    public function deleteFiles(array $files): void
    {
        foreach ($files as $file) {
            try {
                if (! Storage::disk($file['disk'])->delete($file['path'])) {
                    Log::warning('Não foi possível remover uma fotografia copiada.', $file);
                }
            } catch (Throwable $exception) {
                Log::warning('Não foi possível remover uma fotografia copiada.', [
                    ...$file,
                    'exception' => $exception::class,
                ]);
            }
        }
    }

    /** @param array<int, InspectionOverviewBlock> $existing */
    private function ensureInitialBlocks(Inspection $inspection, User $actor, array $existing = []): void
    {
        foreach ([1, 2] as $position) {
            if (isset($existing[$position])) {
                continue;
            }
            InspectionOverviewBlock::query()->create([
                'organization_id' => $inspection->organization_id,
                'inspection_id' => $inspection->id,
                'position' => $position,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        }
    }

    private function createSpecialNote(Inspection $inspection, int $assessmentId, InspectionSpecialAssessmentNote $source, User $actor): void
    {
        InspectionSpecialAssessmentNote::query()->firstOrCreate([
            'organization_id' => $inspection->organization_id,
            'inspection_id' => $inspection->id,
            'defect_assessment_id' => $assessmentId,
        ], [
            'service' => $source->service,
            'priority' => $source->priority,
            'note' => $source->note,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }
}
