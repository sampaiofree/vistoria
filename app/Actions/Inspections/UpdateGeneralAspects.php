<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Enums\PhotoProcessingStatus;
use App\Models\Inspection;
use App\Models\InspectionGeneralAspectImage;
use App\Models\User;
use App\Services\Reports\GeneralAspectsDocument;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;

final class UpdateGeneralAspects
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly GeneralAspectsDocument $documents,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(Inspection $inspection, User $actor, array $data): Inspection
    {
        if (! $actor->isActive() || $actor->isSuperAdmin() || ! $actor->belongsToOrganization($this->tenant->id())) {
            throw ValidationException::withMessages([
                'actor' => 'O usuário não pode editar os aspectos gerais na organização atual.',
            ]);
        }

        $document = $this->documents->normalize(
            (int) $data['schema_version'],
            $this->documents->withFlatHeadings($data['document'] ?? null),
        );

        try {
            $stored = $document === null
                ? null
                : json_encode($document, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages([
                'document' => 'O documento de aspectos gerais não pôde ser salvo.',
            ]);
        }

        $assetIds = $this->documents->imageAssetIds($document['document'] ?? null);

        return DB::transaction(function () use ($inspection, $actor, $stored, $assetIds): Inspection {
            $inspection = Inspection::query()
                ->forOrganization($this->tenant->id())
                ->lockForUpdate()
                ->findOrFail($inspection->getKey());

            $images = InspectionGeneralAspectImage::query()
                ->where('inspection_id', $inspection->id)
                ->where('organization_id', $this->tenant->id())
                ->whereIn('public_id', $assetIds)
                ->lockForUpdate()->get();
            if ($images->count() !== count($assetIds)
                || $images->contains(fn (InspectionGeneralAspectImage $image): bool => $image->processing_status !== PhotoProcessingStatus::Ready)) {
                throw ValidationException::withMessages(['document' => 'Aguarde todas as imagens ficarem prontas antes de salvar.']);
            }

            $inspection->update([
                'general_notes' => $stored,
                'updated_by' => $actor->getKey(),
            ]);

            InspectionGeneralAspectImage::query()
                ->where('inspection_id', $inspection->id)
                ->where('organization_id', $this->tenant->id())
                ->when($assetIds !== [], fn ($query) => $query->whereNotIn('public_id', $assetIds))
                ->whereNull('unreferenced_at')->update(['unreferenced_at' => now()]);
            if ($assetIds !== []) {
                InspectionGeneralAspectImage::query()
                    ->where('inspection_id', $inspection->id)
                    ->where('organization_id', $this->tenant->id())
                    ->whereIn('public_id', $assetIds)->update(['unreferenced_at' => null]);
            }

            return $inspection->refresh();
        });
    }
}
