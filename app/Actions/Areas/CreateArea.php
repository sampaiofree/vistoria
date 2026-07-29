<?php

namespace App\Actions\Areas;

use App\Enums\RegistrationStatus;
use App\Models\Area;
use App\Models\ClientUnit;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateArea
{
    public function handle(ClientUnit $unit, int $organizationId, array $data): Area
    {
        $this->ensureUnitBelongsToTenant($unit, $organizationId);

        if (! $unit->isOperationallyActive()) {
            throw ValidationException::withMessages([
                'client_unit_id' => 'A unidade esta inativa.',
            ]);
        }

        return DB::transaction(function () use ($unit, $data): Area {
            return Area::query()->create([
                'organization_id' => $unit->organization_id,
                'client_unit_id' => $unit->id,
                'name' => TextNormalizer::text((string) $data['name']),
                'code' => TextNormalizer::technicalCode($data['code'] ?? null),
                'normalized_code' => TextNormalizer::technicalCode($data['code'] ?? null),
                'status' => RegistrationStatus::Active,
                'description' => TextNormalizer::nullableText($data['description'] ?? null),
            ]);
        });
    }

    private function ensureUnitBelongsToTenant(ClientUnit $unit, int $organizationId): void
    {
        if (! $unit->belongsToOrganization($organizationId)) {
            throw ValidationException::withMessages([
                'client_unit_id' => 'A unidade nao pertence a organizacao atual.',
            ]);
        }
    }
}
