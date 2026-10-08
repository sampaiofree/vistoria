<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PhotoProcessingStatus;
use App\Jobs\ProcessGeneralAspectsTemplateImage;
use App\Models\GeneralAspectsTemplateImage;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class GeneralAspectsTemplateImageController extends Controller
{
    public function store(Request $request, TenantContext $tenant): JsonResponse
    {
        $this->authorize('update', $tenant->organization());
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:25600'],
            'draft_token' => ['required', 'ulid'],
        ]);

        $id = DB::transaction(function () use ($data, $request, $tenant): int {
            $file = $data['file'];
            $image = GeneralAspectsTemplateImage::query()->create([
                'organization_id' => $tenant->id(), 'draft_token' => $data['draft_token'],
                'uploaded_by' => $request->user()->getKey(),
                'processing_status' => PhotoProcessingStatus::Pending,
                'disk' => 'inspection_photos',
                'original_name' => mb_strimwidth($file->getClientOriginalName(), 0, 250),
                'original_mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'original_extension' => $file->extension() ?: $file->getClientOriginalExtension(),
                'original_size' => $file->getSize() ?? 0,
                'uploaded_at' => now(), 'unreferenced_at' => now(),
            ]);
            $directory = sprintf('organizations/%d/general-aspect-templates/assets/%s', $tenant->id(), $image->public_id);
            $filename = 'original.'.strtolower((string) ($image->original_extension ?: 'bin'));
            $path = Storage::disk('inspection_photos')->putFileAs($directory, $file, $filename);
            if (! $path) {
                throw new \RuntimeException('Não foi possível armazenar a imagem.');
            }
            $image->update(['original_path' => $path]);
            ProcessGeneralAspectsTemplateImage::dispatch($image->id)->onQueue('images')->afterCommit();

            return $image->id;
        });

        return response()->json($this->statusPayload(GeneralAspectsTemplateImage::query()->findOrFail($id)), 202);
    }

    public function status(Request $request, TenantContext $tenant, GeneralAspectsTemplateImage $image): JsonResponse
    {
        $this->authorize('update', $tenant->organization());
        $image = $this->tenantImage($tenant, $image);
        abort_unless($image->template_id !== null || $image->uploaded_by === $request->user()->getKey(), 404);

        return response()->json($this->statusPayload($image));
    }

    public function show(Request $request, TenantContext $tenant, GeneralAspectsTemplateImage $image, string $variant): StreamedResponse
    {
        $this->authorize('update', $tenant->organization());
        $image = $this->tenantImage($tenant, $image);
        abort_unless($image->template_id !== null || $image->uploaded_by === $request->user()->getKey(), 404);
        $path = $variant === 'thumbnail' ? $image->thumbnail_path : $image->optimized_path;
        abort_unless($image->isReady() && $path && Storage::disk($image->disk)->exists($path), 404);
        $stream = Storage::disk($image->disk)->readStream($path);
        abort_unless(is_resource($stream), 404);

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=3600',
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function tenantImage(TenantContext $tenant, GeneralAspectsTemplateImage $image): GeneralAspectsTemplateImage
    {
        return GeneralAspectsTemplateImage::query()->forOrganization($tenant->id())->findOrFail($image->id);
    }

    /** @return array<string, mixed> */
    private function statusPayload(GeneralAspectsTemplateImage $image): array
    {
        return [
            'assetId' => $image->public_id,
            'status' => $image->processing_status->value,
            'error' => $image->processing_status === PhotoProcessingStatus::Failed ? 'Não foi possível processar a imagem. Escolha outra.' : null,
            'statusUrl' => route('settings.inspection-report.general-aspects.images.status', $image),
            'thumbnailUrl' => $image->isReady() ? route('settings.inspection-report.general-aspects.images.show', [$image, 'thumbnail']) : null,
            'optimizedUrl' => $image->isReady() ? route('settings.inspection-report.general-aspects.images.show', [$image, 'optimized']) : null,
        ];
    }
}
