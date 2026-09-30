<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Organization;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BrandingImageController extends Controller
{
    public function company(TenantContext $tenant, Organization $organization, string $kind): StreamedResponse
    {
        abort_unless($organization->getKey() === $tenant->id(), 404);
        $path = $kind === 'logo' ? $organization->logo_path : $organization->icon_path;

        return $this->serve($path, 'organizations/'.$organization->public_id.'/branding/');
    }

    public function client(TenantContext $tenant, Client $client): StreamedResponse
    {
        $client = Client::query()->forOrganization($tenant->id())->whereKey($client->getKey())->firstOrFail();

        return $this->serve(
            $client->logo_path,
            'organizations/'.$tenant->id().'/clients/'.$client->public_id.'/',
        );
    }

    private function serve(?string $path, string $prefix): StreamedResponse
    {
        abort_unless($path !== null && str_starts_with($path, $prefix)
            && ! str_contains($path, '..') && ! str_contains($path, "\0")
            && ! str_contains($path, '\\'), 404);

        $contentType = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => null,
        };
        abort_unless($contentType !== null, 404);

        $disk = Storage::disk('branding_images');
        abort_unless($disk->exists($path), 404);
        $stream = $disk->readStream($path);
        abort_unless(is_resource($stream), 404);

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
