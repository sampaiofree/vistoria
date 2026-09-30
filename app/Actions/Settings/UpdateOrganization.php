<?php

namespace App\Actions\Settings;

use App\Models\Organization;
use App\Support\TextNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class UpdateOrganization
{
    public function handle(Organization $organization, array $data): Organization
    {
        /** @var UploadedFile|null $logo */
        $logo = $data['logo'] ?? null;
        /** @var UploadedFile|null $icon */
        $icon = $data['icon'] ?? null;
        /** @var UploadedFile|null $pwaIcon */
        $pwaIcon = $data['pwa_icon'] ?? null;
        $oldLogo = $organization->logo_path;
        $oldIcon = $organization->icon_path;
        $oldPwaIcon = $organization->pwa_icon_path;

        $newPaths = [];

        try {
            $organization = DB::transaction(function () use ($organization, $data, $logo, $icon, $pwaIcon, &$newPaths): Organization {
                $organization->update([
                    'name' => TextNormalizer::text((string) $data['name']),
                    'legal_name' => TextNormalizer::nullableText($data['legal_name'] ?? null),
                    'document' => TextNormalizer::document($data['document'] ?? null),
                    'primary_color' => TextNormalizer::hexColor((string) $data['primary_color']),
                ]);

                foreach (['logo' => $logo, 'icon' => $icon, 'pwa_icon' => $pwaIcon] as $kind => $file) {
                    if (! $file instanceof UploadedFile) {
                        continue;
                    }

                    $path = $file->store('organizations/'.$organization->public_id.'/branding', 'branding_images');
                    if (! is_string($path)) {
                        throw new RuntimeException('Não foi possível armazenar a identidade visual da empresa.');
                    }
                    $newPaths[$kind] = $path;
                    $organization->update([$kind.'_path' => $path]);
                }

                return $organization->refresh();
            });
        } catch (Throwable $exception) {
            if ($newPaths !== []) {
                try {
                    Storage::disk('branding_images')->delete(array_values($newPaths));
                } catch (Throwable $cleanupException) {
                    Log::warning('Não foi possível limpar a nova identidade visual da empresa após erro.', [
                        'organization_public_id' => $organization->public_id,
                        'exception' => $cleanupException::class,
                    ]);
                }
            }
            throw $exception;
        }

        foreach (['logo' => $oldLogo, 'icon' => $oldIcon, 'pwa_icon' => $oldPwaIcon] as $kind => $oldPath) {
            if (! isset($newPaths[$kind]) || $oldPath === null) {
                continue;
            }
            try {
                if (! Storage::disk('branding_images')->delete($oldPath)) {
                    throw new RuntimeException('Exclusão não confirmada.');
                }
            } catch (Throwable $exception) {
                Log::warning('Não foi possível remover a identidade visual anterior da empresa.', [
                    'organization_public_id' => $organization->public_id,
                    'kind' => $kind,
                    'exception' => $exception::class,
                ]);
            }
        }

        return $organization;
    }
}
