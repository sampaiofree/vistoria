<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Services\Pwa\OrganizationAppBranding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PwaController extends Controller
{
    public function manifest(Organization $organization, OrganizationAppBranding $branding): JsonResponse
    {
        abort_unless($organization->isActive(), 404);

        return response()->json($branding->manifest($organization), headers: [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'no-cache',
        ]);
    }

    public function icon(Request $request, Organization $organization, int $size, OrganizationAppBranding $branding): Response
    {
        abort_unless($organization->isActive() && in_array($size, OrganizationAppBranding::ICON_SIZES, true), 404);

        $contents = $branding->icon($organization, $size);
        $response = response($contents, headers: [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setEtag(hash('sha256', $contents));
        $response->isNotModified($request);

        return $response;
    }
}
