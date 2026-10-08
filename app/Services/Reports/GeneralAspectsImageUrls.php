<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Inspection;
use App\Models\InspectionGeneralAspectImage;

final class GeneralAspectsImageUrls
{
    /** @param array<string, mixed>|null $document
     * @return array<string, array{thumbnail:string, optimized:string}>
     */
    public function forDocument(Inspection $inspection, ?array $document): array
    {
        $ids = app(GeneralAspectsDocument::class)->imageAssetIds($document);
        if ($ids === []) {
            return [];
        }

        return InspectionGeneralAspectImage::query()
            ->where('organization_id', $inspection->organization_id)
            ->where('inspection_id', $inspection->id)
            ->whereIn('public_id', $ids)
            ->whereNull('unreferenced_at')
            ->get()
            ->filter(fn (InspectionGeneralAspectImage $image): bool => $image->isReady())
            ->mapWithKeys(fn (InspectionGeneralAspectImage $image): array => [$image->public_id => [
                'thumbnail' => route('inspection-general-aspect-images.show', [$image, 'thumbnail']),
                'optimized' => route('inspection-general-aspect-images.show', [$image, 'optimized']),
            ]])->all();
    }
}
