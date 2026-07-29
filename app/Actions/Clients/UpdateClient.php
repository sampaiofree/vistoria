<?php

namespace App\Actions\Clients;

use App\Models\Client;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;

final class UpdateClient
{
    public function handle(Client $client, array $data): Client
    {
        return DB::transaction(function () use ($client, $data): Client {
            $client->update([
                'name' => TextNormalizer::text((string) $data['name']),
                'legal_name' => TextNormalizer::nullableText($data['legal_name'] ?? null),
                'document' => TextNormalizer::document($data['document'] ?? null),
                'email' => TextNormalizer::email($data['email'] ?? null),
                'phone' => TextNormalizer::nullableText($data['phone'] ?? null),
                'notes' => TextNormalizer::nullableText($data['notes'] ?? null),
            ]);

            return $client->refresh();
        });
    }
}
