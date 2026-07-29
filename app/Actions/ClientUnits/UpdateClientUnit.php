<?php

namespace App\Actions\ClientUnits;

use App\Models\ClientUnit;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;

final class UpdateClientUnit
{
    public function handle(ClientUnit $unit, array $data): ClientUnit
    {
        return DB::transaction(function () use ($unit, $data): ClientUnit {
            $unit->update([
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
                'notes' => TextNormalizer::nullableText($data['notes'] ?? null),
            ]);

            return $unit->refresh();
        });
    }
}
