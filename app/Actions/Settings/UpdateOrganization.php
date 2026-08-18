<?php

namespace App\Actions\Settings;

use App\Models\Organization;
use App\Support\TextNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class UpdateOrganization
{
    public function handle(Organization $organization, array $data): Organization
    {
        /** @var UploadedFile|null $logo */
        $logo = $data['logo'] ?? null;
        /** @var UploadedFile|null $icon */
        $icon = $data['icon'] ?? null;
        $oldLogo = $organization->logo_path;
        $oldIcon = $organization->icon_path;

        $organization->update([
            'name' => TextNormalizer::text((string) $data['name']),
            'legal_name' => TextNormalizer::nullableText($data['legal_name'] ?? null),
            'document' => TextNormalizer::document($data['document'] ?? null),
            'primary_color' => TextNormalizer::hexColor((string) $data['primary_color']),
        ]);

        if ($logo instanceof UploadedFile) {
            $logoPath = $logo->store('organizations/'.$organization->public_id.'/branding', 'public');
            $organization->update(['logo_path' => $logoPath]);

            if ($oldLogo !== null) {
                Storage::disk('public')->delete($oldLogo);
            }
        }

        if ($icon instanceof UploadedFile) {
            $iconPath = $icon->store('organizations/'.$organization->public_id.'/branding', 'public');
            $organization->update(['icon_path' => $iconPath]);

            if ($oldIcon !== null) {
                Storage::disk('public')->delete($oldIcon);
            }
        }

        return $organization->refresh();
    }
}
