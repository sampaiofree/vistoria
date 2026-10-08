<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Enums\PhotoProcessingStatus;
use App\Models\GeneralAspectsTemplate;
use App\Models\GeneralAspectsTemplateImage;
use App\Models\Inspection;
use App\Models\InspectionGeneralAspectImage;
use App\Models\User;
use App\Services\Reports\GeneralAspectsDocument;
use App\Services\Reports\ResolveGeneralAspectsTemplate;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ApplyGeneralAspectsTemplate
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly GeneralAspectsDocument $documents,
        private readonly ResolveGeneralAspectsTemplate $resolver,
    ) {}

    /** @return array{document:array<string, mixed>, images:array<string, array<string, string>>} */
    public function handle(Inspection $inspection, GeneralAspectsTemplate $template, User $actor): array
    {
        $created = [];
        try {
            return DB::transaction(function () use ($inspection, $template, $actor, &$created): array {
                $inspection = Inspection::query()->forOrganization($this->tenant->id())
                    ->lockForUpdate()->findOrFail($inspection->id);
                $template = GeneralAspectsTemplate::query()->forOrganization($this->tenant->id())
                    ->lockForUpdate()->findOrFail($template->id);
                abort_unless($actor->can('manageGeneralAspects', $inspection), 403);

                $document = $this->resolver->resolve(
                    $template->document,
                    (array) data_get($inspection->context_snapshot, 'equipment', []),
                    $template->schema_version,
                );
                $ids = $this->documents->imageAssetIds($document);
                $sources = GeneralAspectsTemplateImage::query()->forOrganization($this->tenant->id())
                    ->where('template_id', $template->id)->whereIn('public_id', $ids)
                    ->lockForUpdate()->get()->keyBy('public_id');
                if ($sources->count() !== count($ids) || $sources->contains(fn (GeneralAspectsTemplateImage $image): bool => ! $image->isReady())) {
                    throw ValidationException::withMessages(['template' => 'As imagens do modelo não estão disponíveis.']);
                }

                $replacements = [];
                $urls = [];
                foreach ($ids as $id) {
                    $source = $sources->get($id);
                    $image = InspectionGeneralAspectImage::query()->create([
                        'organization_id' => $this->tenant->id(), 'inspection_id' => $inspection->id,
                        'processing_status' => PhotoProcessingStatus::Ready, 'disk' => 'inspection_photos',
                        'original_name' => $source->original_name,
                        'original_mime_type' => $source->original_mime_type,
                        'original_extension' => $source->original_extension,
                        'original_size' => $source->original_size,
                        'original_width' => $source->original_width, 'original_height' => $source->original_height,
                        'optimized_size' => $source->optimized_size,
                        'optimized_width' => $source->optimized_width, 'optimized_height' => $source->optimized_height,
                        'thumbnail_size' => $source->thumbnail_size,
                        'thumbnail_width' => $source->thumbnail_width, 'thumbnail_height' => $source->thumbnail_height,
                        'checksum' => $source->checksum,
                        'uploaded_at' => now(), 'processed_at' => now(), 'unreferenced_at' => now(),
                        'uploaded_by' => $actor->id,
                    ]);
                    $directory = sprintf('organizations/%d/inspections/%s/general-aspects/%s',
                        $this->tenant->id(), $inspection->public_id, $image->public_id);
                    $disk = Storage::disk('inspection_photos');
                    foreach (['optimized', 'thumbnail'] as $variant) {
                        $sourcePath = $source->{$variant.'_path'};
                        $destination = $directory.'/'.$variant.'.webp';
                        $created[] = $destination;
                        try {
                            $copied = $sourcePath && $disk->copy($sourcePath, $destination);
                        } catch (Throwable) {
                            $copied = false;
                        }
                        if (! $copied) {
                            throw ValidationException::withMessages(['template' => 'Não foi possível copiar uma imagem do modelo. Tente novamente.']);
                        }
                        $image->{$variant.'_path'} = $destination;
                    }
                    $image->save();
                    $replacements[$id] = $image->public_id;
                    $urls[$image->public_id] = [
                        'thumbnail' => route('inspection-general-aspect-images.show', [$image, 'thumbnail']),
                        'optimized' => route('inspection-general-aspect-images.show', [$image, 'optimized']),
                    ];
                }

                foreach ($document['content'] as &$block) {
                    if (($block['type'] ?? null) === 'image') {
                        $block['attrs']['assetId'] = $replacements[$block['attrs']['assetId']];
                    }
                }
                unset($block);

                $this->documents->normalize(GeneralAspectsDocument::INSPECTION_SCHEMA_VERSION, $document, allowPendingTextColor: true);

                return ['document' => $document, 'images' => $urls];
            });
        } catch (Throwable $exception) {
            if ($created !== []) {
                try {
                    if (! Storage::disk('inspection_photos')->delete($created)) {
                        Log::warning('Falha ao limpar cópias de imagens após erro na aplicação do modelo.', ['paths' => $created]);
                    }
                } catch (Throwable $cleanupException) {
                    Log::warning('Falha ao limpar cópias de imagens após erro na aplicação do modelo.', [
                        'paths' => $created,
                        'error' => $cleanupException->getMessage(),
                    ]);
                }
            }
            throw $exception;
        }
    }
}
