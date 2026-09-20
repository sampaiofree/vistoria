<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\InspectionLocationMapProcessingStatus;
use App\Models\DefectLocationMapVersion;
use App\Services\InspectionLocations\InspectionLocationAssetGuard;
use App\Services\Tenancy\TenantContext;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class InspectionLocationMapAssetController extends Controller
{
    public function background(
        TenantContext $tenant,
        DefectLocationMapVersion $mapVersion,
        InspectionLocationAssetGuard $assetGuard,
        string $variant = 'background',
    ): StreamedResponse {
        $version = DefectLocationMapVersion::query()
            ->forOrganization($tenant->id())
            ->with('map.defect')
            ->whereKey($mapVersion->id)
            ->firstOrFail();
        $user = request()->user();
        abort_unless($user->isActive() && ! $user->isSuperAdmin() && $user->organization_id === $version->organization_id, 403);
        abort_unless($version->processing_status === InspectionLocationMapProcessingStatus::Ready, 404);

        try {
            $asset = $assetGuard->background($version, $variant);
        } catch (RuntimeException) {
            abort(404);
        }

        abort_unless($asset['disk']->exists($asset['path']), 404);
        $stream = $asset['disk']->readStream($asset['path']);
        abort_unless(is_resource($stream), 404);
        $contentType = $asset['path'] === $version->background_path
            ? ($version->background_mime_type ?? 'image/webp')
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
