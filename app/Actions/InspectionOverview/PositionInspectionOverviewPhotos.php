<?php

declare(strict_types=1);

namespace App\Actions\InspectionOverview;

use App\Models\Inspection;
use App\Models\InspectionOverviewBlock;
use App\Models\InspectionOverviewPhoto;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class PositionInspectionOverviewPhotos
{
    public const MAX_PHOTOS = 508;

    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * Call while the inspection row is locked in a transaction.
     *
     * @param Collection<int, InspectionOverviewPhoto> $photos
     */
    public function handle(User $actor, Inspection $inspection, Collection $photos): void
    {
        if ($photos->count() > self::MAX_PHOTOS) {
            throw ValidationException::withMessages([
                'file' => 'A Vista geral aceita no máximo 508 fotografias.',
            ]);
        }

        $blocks = InspectionOverviewBlock::query()
            ->where('inspection_id', $inspection->getKey())
            ->where('organization_id', $this->tenant->id())
            ->get()
            ->keyBy('position');

        for ($position = 1; $position <= (int) ceil($photos->count() / 2); $position++) {
            if (isset($blocks[$position])) {
                continue;
            }

            $blocks[$position] = InspectionOverviewBlock::query()->create([
                'organization_id' => $this->tenant->id(),
                'inspection_id' => $inspection->getKey(),
                'position' => $position,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);
        }

        // The unique (block, slot) index requires clearing occupied slots first.
        foreach ($photos as $photo) {
            $photo->update(['slot' => null]);
        }

        foreach ($photos->values() as $index => $photo) {
            $position = intdiv($index, 2) + 1;
            $photo->update([
                'inspection_overview_block_id' => $blocks[$position]->getKey(),
                'slot' => ($index % 2) + 1,
            ]);
        }
    }
}
