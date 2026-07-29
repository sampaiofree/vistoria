<?php

namespace App\Actions\Clients;

use App\Enums\RegistrationStatus;
use App\Models\Client;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;

final class CreateClient
{
    public function handle(int $organizationId, array $data): Client
    {
        return DB::transaction(function () use ($organizationId, $data): Client {
            return Client::query()->create([
                'organization_id' => $organizationId,
                'name' => TextNormalizer::text((string) $data['name']),
                'legal_name' => TextNormalizer::nullableText($data['legal_name'] ?? null),
                'document' => TextNormalizer::document($data['document'] ?? null),
                'email' => TextNormalizer::email($data['email'] ?? null),
                'phone' => TextNormalizer::nullableText($data['phone'] ?? null),
                'status' => RegistrationStatus::Active,
                'notes' => TextNormalizer::nullableText($data['notes'] ?? null),
            ]);
        });
    }
}
