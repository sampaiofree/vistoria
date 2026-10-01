<?php

declare(strict_types=1);

namespace App\Actions\InspectionOverview;

use App\Enums\PhotoProcessingStatus;
use App\Models\Inspection;
use App\Models\InspectionOverviewPhoto;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReorderInspectionOverviewPhotos
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly PositionInspectionOverviewPhotos $positionPhotos,
    ) {}

    /** @param array<int, string> $publicIds */
    public function handle(User $actor, Inspection $inspection, array $publicIds): void
    {
        DB::transaction(function () use ($actor, $inspection, $publicIds): void {
            $locked = Inspection::query()
                ->forOrganization($this->tenant->id())
                ->lockForUpdate()
                ->findOrFail($inspection->getKey());

            if ($locked->status->isFinal()) {
                throw ValidationException::withMessages(['photo_ids' => 'As fotografias não podem ser reordenadas após o encerramento da inspeção.']);
            }

            $photos = InspectionOverviewPhoto::query()
                ->where('organization_id', $this->tenant->id())
                ->where('inspection_id', $locked->getKey())
                ->lockForUpdate()
                ->get();

            $byId = $photos->keyBy('public_id');

            if ($photos->count() !== count($publicIds)
                || count(array_unique($publicIds)) !== count($publicIds)
                || $byId->keys()->diff($publicIds)->isNotEmpty()) {
                throw ValidationException::withMessages(['photo_ids' => 'A lista de fotografias está desatualizada. Recarregue a página e tente novamente.']);
            }

            if ($photos->contains(fn (InspectionOverviewPhoto $photo): bool => $photo->processing_status !== PhotoProcessingStatus::Ready)) {
                throw ValidationException::withMessages(['photo_ids' => 'Aguarde o processamento de todas as fotografias para alterar a ordem.']);
            }

            $this->positionPhotos->handle(
                $actor,
                $locked,
                collect($publicIds)->map(fn (string $id): InspectionOverviewPhoto => $byId[$id]),
            );
        });
    }
}
