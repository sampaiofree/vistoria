<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\GeneralAspectsTemplate;
use App\Models\GeneralAspectsTemplateImage;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final class GeneralAspectsTemplateImages
{
    public function __construct(private readonly GeneralAspectsDocument $documents) {}

    /** @param array<string, mixed> $document
     * @return Collection<int, GeneralAspectsTemplateImage>
     */
    public function validatedImages(TenantContext $tenant, array $document, ?GeneralAspectsTemplate $template, ?string $draftToken, int $actorId): Collection
    {
        $ids = $this->documents->imageAssetIds($document);
        $images = GeneralAspectsTemplateImage::query()->forOrganization($tenant->id())
            ->whereIn('public_id', $ids)->lockForUpdate()->get();
        if ($images->count() !== count($ids) || $images->contains(function (GeneralAspectsTemplateImage $image) use ($template, $draftToken, $actorId): bool {
            $existing = $template !== null && $image->template_id === $template->id;
            $draft = $draftToken !== null && $image->template_id === null
                && $image->draft_token === $draftToken && $image->uploaded_by === $actorId;

            return ! $image->isReady() || (! $existing && ! $draft);
        })) {
            throw ValidationException::withMessages([
                'document' => 'Todas as imagens do modelo precisam estar prontas e pertencer a este modelo ou envio.',
            ]);
        }

        return $images;
    }

    /** @param Collection<int, GeneralAspectsTemplateImage> $images */
    public function reconcile(GeneralAspectsTemplate $template, Collection $images): void
    {
        $ids = $images->pluck('id')->all();
        GeneralAspectsTemplateImage::query()->where('template_id', $template->id)
            ->when($ids !== [], fn ($query) => $query->whereNotIn('id', $ids))
            ->update(['template_id' => null, 'unreferenced_at' => now()]);
        if ($ids !== []) {
            GeneralAspectsTemplateImage::query()->whereIn('id', $ids)->update([
                'template_id' => $template->id, 'draft_token' => null, 'unreferenced_at' => null,
            ]);
        }
    }

    /** @return array<string, array{status:string, thumbnail:string, optimized:string}> */
    public function urlsForTemplate(GeneralAspectsTemplate $template): array
    {
        $ids = $this->documents->imageAssetIds($template->document);

        return GeneralAspectsTemplateImage::query()->forOrganization($template->organization_id)
            ->where('template_id', $template->id)->whereIn('public_id', $ids)
            ->get()->filter(fn (GeneralAspectsTemplateImage $image): bool => $image->isReady())
            ->mapWithKeys(fn (GeneralAspectsTemplateImage $image): array => [$image->public_id => [
                'status' => 'ready',
                'thumbnail' => route('settings.inspection-report.general-aspects.images.show', [$image, 'thumbnail']),
                'optimized' => route('settings.inspection-report.general-aspects.images.show', [$image, 'optimized']),
            ]])->all();
    }
}
