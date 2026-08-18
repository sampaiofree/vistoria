<?php

declare(strict_types=1);

namespace App\Actions\InspectionOverview;

use App\Enums\PhotoProcessingStatus;
use App\Jobs\ProcessInspectionOverviewPhoto;
use App\Models\Inspection;
use App\Models\InspectionOverviewBlock;
use App\Models\InspectionOverviewPhoto;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class StoreInspectionOverviewPhoto
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(User $actor, Inspection $inspection, int $position, int $slot, UploadedFile $file): InspectionOverviewPhoto
    {
        $this->validateSlot($position, $slot);
        $obsoletePaths = [];

        $photoId = DB::transaction(function () use ($actor, $inspection, $position, $slot, $file, &$obsoletePaths): int {
            $locked = Inspection::query()
                ->forOrganization($this->tenant->id())
                ->lockForUpdate()
                ->findOrFail($inspection->getKey());

            if ($locked->status->isFinal()) {
                throw ValidationException::withMessages([
                    'file' => 'As fotografias da Vista geral não podem ser alteradas após o encerramento da inspeção.',
                ]);
            }

            $block = InspectionOverviewBlock::query()->firstOrCreate(
                [
                    'organization_id' => $this->tenant->id(),
                    'inspection_id' => $locked->getKey(),
                    'position' => $position,
                ],
                ['created_by' => $actor->getKey(), 'updated_by' => $actor->getKey()],
            );

            $current = InspectionOverviewPhoto::query()
                ->where('inspection_overview_block_id', $block->getKey())
                ->where('slot', $slot)
                ->lockForUpdate()
                ->first();

            if ($current !== null) {
                $obsoletePaths = array_values(array_filter([
                    $current->original_path,
                    $current->optimized_path,
                    $current->thumbnail_path,
                ]));
                $current->update(['slot' => null]);
                $current->delete();
            }

            $photo = InspectionOverviewPhoto::query()->create([
                'organization_id' => $this->tenant->id(),
                'inspection_id' => $locked->getKey(),
                'inspection_overview_block_id' => $block->getKey(),
                'slot' => $slot,
                'processing_status' => PhotoProcessingStatus::Pending,
                'disk' => 'inspection_photos',
                'original_name' => $file->getClientOriginalName(),
                'original_mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'original_extension' => $file->extension() ?: $file->getClientOriginalExtension(),
                'original_size' => $file->getSize() ?? 0,
                'uploaded_at' => now(),
                'uploaded_by' => $actor->getKey(),
            ]);

            $directory = sprintf(
                'organizations/%d/inspections/%s/report-overview/%s',
                $this->tenant->id(),
                $locked->public_id,
                $photo->public_id,
            );
            $filename = 'original.'.strtolower((string) ($photo->original_extension ?: 'bin'));
            $path = $directory.'/'.$filename;

            if (! Storage::disk('inspection_photos')->putFileAs($directory, $file, $filename)) {
                throw ValidationException::withMessages(['file' => 'Não foi possível armazenar a fotografia.']);
            }

            $photo->update(['original_path' => $path]);
            ProcessInspectionOverviewPhoto::dispatch($photo->getKey())->onQueue('images')->afterCommit();

            return (int) $photo->getKey();
        });

        if ($obsoletePaths !== []) {
            Storage::disk('inspection_photos')->delete($obsoletePaths);
        }

        return InspectionOverviewPhoto::query()->findOrFail($photoId);
    }

    private function validateSlot(int $position, int $slot): void
    {
        if (! in_array($position, [1, 2], true) || ! in_array($slot, [1, 2], true)) {
            throw ValidationException::withMessages(['file' => 'Posição de fotografia inválida.']);
        }
    }
}
