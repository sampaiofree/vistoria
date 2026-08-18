<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\InspectionOverview\DeleteInspectionOverviewPhoto;
use App\Actions\InspectionOverview\RetryInspectionOverviewPhoto;
use App\Actions\InspectionOverview\StoreInspectionOverviewPhoto;
use App\Http\Requests\InspectionOverview\StoreInspectionOverviewPhotoRequest;
use App\Models\Inspection;
use App\Models\InspectionOverviewPhoto;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class InspectionOverviewPhotoController extends Controller
{
    public function store(
        StoreInspectionOverviewPhotoRequest $request,
        TenantContext $tenant,
        Inspection $inspection,
        int $position,
        int $slot,
        StoreInspectionOverviewPhoto $action,
    ): RedirectResponse {
        $inspection = Inspection::query()->forOrganization($tenant->id())->whereKey($inspection->getKey())->firstOrFail();
        $this->authorize('manageReportOverview', $inspection);
        $action->handle($request->user(), $inspection, $position, $slot, $request->file('file'));

        return back()->with('success', 'Fotografia recebida para processamento.');
    }

    public function show(
        TenantContext $tenant,
        Request $request,
        InspectionOverviewPhoto $overviewPhoto,
        string $variant = 'optimized',
    ): StreamedResponse {
        $photo = $this->tenantPhoto($tenant, $overviewPhoto);
        $this->authorize('view', $photo);
        $path = match ($variant) {
            'thumbnail' => $photo->thumbnail_path,
            'original' => $photo->original_path,
            default => $photo->optimized_path,
        };

        abort_unless($photo->isReady() && $path !== null && Storage::disk($photo->disk)->exists($path), 404);
        $stream = Storage::disk($photo->disk)->readStream($path);
        abort_unless(is_resource($stream), 404);

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $variant === 'original' ? $photo->original_mime_type : 'image/webp',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function retry(
        TenantContext $tenant,
        Request $request,
        InspectionOverviewPhoto $overviewPhoto,
        RetryInspectionOverviewPhoto $action,
    ): RedirectResponse {
        $photo = $this->tenantPhoto($tenant, $overviewPhoto);
        $this->authorize('update', $photo);
        $action->handle($request->user(), $photo);

        return back()->with('success', 'Fotografia reenviada para processamento.');
    }

    public function destroy(
        TenantContext $tenant,
        Request $request,
        InspectionOverviewPhoto $overviewPhoto,
        DeleteInspectionOverviewPhoto $action,
    ): RedirectResponse {
        $photo = $this->tenantPhoto($tenant, $overviewPhoto);
        $this->authorize('delete', $photo);
        $action->handle($request->user(), $photo);

        return back()->with('success', 'Fotografia removida.');
    }

    private function tenantPhoto(TenantContext $tenant, InspectionOverviewPhoto $photo): InspectionOverviewPhoto
    {
        return InspectionOverviewPhoto::query()
            ->forOrganization($tenant->id())
            ->with('inspection')
            ->whereKey($photo->getKey())
            ->firstOrFail();
    }
}
