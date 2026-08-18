<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\InspectionLocationMapProcessingStatus;
use App\Http\Controllers\Concerns\ResolvesTenantStructure;
use App\Models\InspectionLocationMap;
use App\Services\InspectionLocations\InspectionLocationAssetGuard;
use App\Services\Tenancy\TenantContext;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class InspectionLocationMapAssetController extends Controller
{
    use ResolvesTenantStructure;

    public function background(
        TenantContext $tenant,
        InspectionLocationMap $map,
        InspectionLocationAssetGuard $assetGuard,
        string $variant = 'background',
    ): StreamedResponse {
        $map = $this->tenantInspectionLocationMap($tenant, $map);
        abort_unless(request()->user()->can('view', $map), 403);
        abort_unless($map->processing_status === InspectionLocationMapProcessingStatus::Ready, 404);

        try {
            $asset = $assetGuard->background($map, $variant);
        } catch (RuntimeException) {
            abort(404);
        }

        abort_unless($asset['disk']->exists($asset['path']), 404);
        $stream = $asset['disk']->readStream($asset['path']);
        abort_unless(is_resource($stream), 404);
        $contentType = $asset['path'] === $map->background_path
            ? ($map->background_mime_type ?? 'image/webp')
            : 'image/webp';

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'private, max-age=300',
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
