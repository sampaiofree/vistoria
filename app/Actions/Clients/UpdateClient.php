<?php

namespace App\Actions\Clients;

use App\Models\Client;
use App\Support\TextNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class UpdateClient
{
    public function handle(Client $client, array $data): Client
    {
        /** @var UploadedFile|null $logo */
        $logo = $data['logo'] ?? null;

        return DB::transaction(function () use ($client, $data, $logo): Client {
            $client->update([
                'name' => TextNormalizer::text((string) $data['name']),
                'legal_name' => TextNormalizer::nullableText($data['legal_name'] ?? null),
                'document' => TextNormalizer::document($data['document'] ?? null),
                'email' => TextNormalizer::email($data['email'] ?? null),
                'phone' => TextNormalizer::nullableText($data['phone'] ?? null),
                'notes' => TextNormalizer::nullableText($data['notes'] ?? null),
            ]);

            if ($logo instanceof UploadedFile) {
                $oldLogoPath = $client->logo_path;
                $logoPath = $logo->store(
                    'organizations/'.$client->organization_id.'/clients/'.$client->public_id,
                    'public',
                );

                $client->update(['logo_path' => $logoPath]);

                if ($oldLogoPath !== null) {
                    Storage::disk('public')->delete($oldLogoPath);
                }
            }

            return $client->refresh();
        });
    }
}
