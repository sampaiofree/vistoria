<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesTenantStructure;
use App\Models\AssessmentPhoto;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AssessmentPhotoController extends Controller
{
    use ResolvesTenantStructure;

    public function show(TenantContext $tenant, Request $request, AssessmentPhoto $assessmentPhoto, string $variant = 'optimized'): StreamedResponse
    {
        $photo = AssessmentPhoto::query()->forOrganization($tenant->id())->with('assessment')->whereKey($assessmentPhoto->getKey())->firstOrFail();
        $this->authorize('view', $photo);

        $path = match ($variant) {
            'thumbnail' => $photo->thumbnail_path,
            'original' => $photo->original_path,
            default => $photo->optimized_path,
        };

        abort_unless($photo->isReady() && $path !== null && Storage::disk($photo->disk)->exists($path), 404);

        $contentType = $variant === 'original'
            ? $photo->original_mime_type
            : 'image/webp';

        $stream = Storage::disk($photo->disk)->readStream($path);

        abort_unless(is_resource($stream), 404);

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
