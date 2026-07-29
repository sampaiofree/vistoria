<?php

namespace App\Actions\ClientUnits;

use App\Enums\RegistrationStatus;
use App\Models\Client;
use App\Models\ClientUnit;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateClientUnit
{
    public function handle(Client $client, int $organizationId, array $data): ClientUnit
    {
        $this->ensureClientBelongsToTenant($client, $organizationId);

        if (! $client->isActive()) {
            throw ValidationException::withMessages([
                'client_id' => 'O cliente esta inativo.',
            ]);
        }

        return DB::transaction(function () use ($client, $data): ClientUnit {
            return ClientUnit::query()->create([
                'organization_id' => $client->organization_id,
                'client_id' => $client->id,
                'name' => TextNormalizer::text((string) $data['name']),
                'code' => TextNormalizer::technicalCode($data['code'] ?? null),
                'normalized_code' => TextNormalizer::technicalCode($data['code'] ?? null),
                'timezone' => TextNormalizer::nullableText($data['timezone'] ?? null),
                'address_line' => TextNormalizer::nullableText($data['address_line'] ?? null),
                'address_number' => TextNormalizer::nullableText($data['address_number'] ?? null),
                'district' => TextNormalizer::nullableText($data['district'] ?? null),
                'postal_code' => TextNormalizer::document($data['postal_code'] ?? null),
                'city' => TextNormalizer::nullableText($data['city'] ?? null),
                'state' => TextNormalizer::nullableText($data['state'] ?? null),
                'country_code' => mb_strtoupper((string) ($data['country_code'] ?? 'BR')),
                'status' => RegistrationStatus::Active,
                'notes' => TextNormalizer::nullableText($data['notes'] ?? null),
            ]);
        });
    }

    private function ensureClientBelongsToTenant(Client $client, int $organizationId): void
    {
        if (! $client->belongsToOrganization($organizationId)) {
            throw ValidationException::withMessages([
                'client_id' => 'O cliente nao pertence a organizacao atual.',
            ]);
        }
    }
}
