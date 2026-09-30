<?php

namespace App\Actions\Clients;

use App\Models\Client;
use App\Support\TextNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class UpdateClient
{
    public function handle(Client $client, array $data): Client
    {
        /** @var UploadedFile|null $logo */
        $logo = $data['logo'] ?? null;

        $oldLogoPath = $client->logo_path;
        $newLogoPath = null;

        try {
            $client = DB::transaction(function () use ($client, $data, $logo, &$newLogoPath): Client {
                $client->update([
                    'name' => TextNormalizer::text((string) $data['name']),
                    'legal_name' => TextNormalizer::nullableText($data['legal_name'] ?? null),
                    'document' => TextNormalizer::document($data['document'] ?? null),
                    'email' => TextNormalizer::email($data['email'] ?? null),
                    'phone' => TextNormalizer::nullableText($data['phone'] ?? null),
                    'notes' => TextNormalizer::nullableText($data['notes'] ?? null),
                ]);

                if ($logo instanceof UploadedFile) {
                    $newLogoPath = $logo->store(
                        'organizations/'.$client->organization_id.'/clients/'.$client->public_id,
                        'branding_images',
                    );
                    if (! is_string($newLogoPath)) {
                        throw new RuntimeException('Não foi possível armazenar o logotipo do cliente.');
                    }
                    $client->update(['logo_path' => $newLogoPath]);
                }

                return $client->refresh();
            });
        } catch (Throwable $exception) {
            if (is_string($newLogoPath)) {
                try {
                    Storage::disk('branding_images')->delete($newLogoPath);
                } catch (Throwable $cleanupException) {
                    Log::warning('Não foi possível limpar o novo logotipo do cliente após erro.', [
                        'client_public_id' => $client->public_id,
                        'exception' => $cleanupException::class,
                    ]);
                }
            }
            throw $exception;
        }

        if ($newLogoPath !== null && $oldLogoPath !== null) {
            try {
                if (! Storage::disk('branding_images')->delete($oldLogoPath)) {
                    throw new RuntimeException('Exclusão não confirmada.');
                }
            } catch (Throwable $exception) {
                Log::warning('Não foi possível remover o logotipo anterior do cliente.', [
                    'client_public_id' => $client->public_id,
                    'exception' => $exception::class,
                ]);
            }
        }

        return $client;
    }
}
