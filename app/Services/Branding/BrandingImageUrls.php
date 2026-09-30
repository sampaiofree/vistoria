<?php

declare(strict_types=1);

namespace App\Services\Branding;

use App\Models\Client;
use App\Models\Organization;

final class BrandingImageUrls
{
    public function companyLogo(?Organization $organization): ?string
    {
        return $organization?->logo_path === null
            ? null
            : route('branding.company', ['organization' => $organization, 'kind' => 'logo', 'v' => basename($organization->logo_path)]);
    }

    public function companyIcon(?Organization $organization): ?string
    {
        return $organization?->icon_path === null
            ? null
            : route('branding.company', ['organization' => $organization, 'kind' => 'icon', 'v' => basename($organization->icon_path)]);
    }

    public function clientLogo(?Client $client): ?string
    {
        return $client?->logo_path === null
            ? null
            : route('branding.client', ['client' => $client, 'v' => basename($client->logo_path)]);
    }
}
