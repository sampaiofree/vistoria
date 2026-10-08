<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Inspections\StoreInspectionGeneralAspectImage;
use App\Models\Inspection;
use App\Models\InspectionGeneralAspectImage;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class InspectionGeneralAspectImageController extends Controller
{
    public function store(Request $request, TenantContext $tenant, Inspection $inspection, StoreInspectionGeneralAspectImage $action): JsonResponse
    {
        $inspection = Inspection::query()->forOrganization($tenant->id())->findOrFail($inspection->id);
        $this->authorize('manageGeneralAspects', $inspection);
        $data = $request->validate(['file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:25600']]);
        $image = $action->handle($inspection, $request->user(), $data['file']);

        return response()->json($this->statusPayload($image), 202);
    }

    public function status(TenantContext $tenant, Inspection $inspection, InspectionGeneralAspectImage $image): JsonResponse
    {
        $image = $this->tenantImage($tenant, $image);
        abort_unless($image->inspection_id === $inspection->id, 404);
        $this->authorize('manageGeneralAspects', $image->inspection);

        return response()->json($this->statusPayload($image));
    }

    public function show(TenantContext $tenant, InspectionGeneralAspectImage $image, string $variant): StreamedResponse
    {
        $image = $this->tenantImage($tenant, $image);
        $this->authorize('view', $image->inspection);
        abort_unless($image->unreferenced_at === null || request()->user()->can('manageGeneralAspects', $image->inspection), 403);
        $path = $variant === 'thumbnail' ? $image->thumbnail_path : $image->optimized_path;
        abort_unless($image->isReady() && $path !== null && Storage::disk($image->disk)->exists($path), 404);
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

    private function tenantImage(TenantContext $tenant, InspectionGeneralAspectImage $image): InspectionGeneralAspectImage
    {
        return InspectionGeneralAspectImage::query()->forOrganization($tenant->id())
            ->with('inspection')->findOrFail($image->id);
    }

    /** @return array<string, mixed> */
    private function statusPayload(InspectionGeneralAspectImage $image): array
    {
        return [
            'assetId' => $image->public_id,
            'status' => $image->processing_status->value,
            'error' => $image->processing_status->value === 'failed' ? 'Não foi possível processar a imagem. Escolha outra.' : null,
            'statusUrl' => route('inspections.general-aspects.images.status', [$image->inspection, $image]),
            'thumbnailUrl' => $image->isReady() ? route('inspection-general-aspect-images.show', [$image, 'thumbnail']) : null,
            'optimizedUrl' => $image->isReady() ? route('inspection-general-aspect-images.show', [$image, 'optimized']) : null,
        ];
    }
}
