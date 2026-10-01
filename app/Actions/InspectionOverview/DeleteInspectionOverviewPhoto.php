<?php

declare(strict_types=1);

namespace App\Actions\InspectionOverview;

use App\Models\Inspection;
use App\Models\InspectionOverviewPhoto;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class DeleteInspectionOverviewPhoto
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly PositionInspectionOverviewPhotos $positionPhotos,
    ) {}

    public function handle(User $actor, InspectionOverviewPhoto $photo): void
    {
        $photo = InspectionOverviewPhoto::query()
            ->forOrganization($this->tenant->id())
            ->with('inspection')
            ->findOrFail($photo->getKey());

        $paths = array_values(array_filter([$photo->original_path, $photo->optimized_path, $photo->thumbnail_path]));

        DB::transaction(function () use ($actor, $photo): void {
            $inspection = Inspection::query()
                ->forOrganization($this->tenant->id())
                ->lockForUpdate()
                ->findOrFail($photo->inspection_id);

            if ($inspection->status->isFinal()) {
                throw ValidationException::withMessages(['photo' => 'A fotografia não pode ser removida após o encerramento da inspeção.']);
            }

            $current = InspectionOverviewPhoto::query()->whereKey($photo->getKey())->lockForUpdate()->firstOrFail();
            $current->update(['slot' => null]);
            $current->delete();

            $remaining = InspectionOverviewPhoto::query()
                ->where('organization_id', $this->tenant->id())
                ->where('inspection_id', $inspection->getKey())
                ->join('inspection_overview_blocks as blocks', 'blocks.id', '=', 'inspection_overview_photos.inspection_overview_block_id')
                ->orderBy('blocks.position')
                ->orderBy('inspection_overview_photos.slot')
                ->select('inspection_overview_photos.*')
                ->lockForUpdate()
                ->get();

            $this->positionPhotos->handle($actor, $inspection, $remaining);
        });

        Storage::disk($photo->disk)->delete($paths);
    }
}
