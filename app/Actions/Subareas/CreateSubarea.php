<?php

namespace App\Actions\Subareas;

use App\Enums\RegistrationStatus;
use App\Models\Area;
use App\Models\Subarea;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateSubarea
{
    public function handle(Area $area, int $organizationId, array $data): Subarea
    {
        $this->ensureAreaBelongsToTenant($area, $organizationId);

        if (! $area->isOperationallyActive()) {
            throw ValidationException::withMessages([
                'area_id' => 'A area esta inativa.',
            ]);
        }

        return DB::transaction(function () use ($area, $data): Subarea {
            return Subarea::query()->create([
                'organization_id' => $area->organization_id,
                'area_id' => $area->id,
                'name' => TextNormalizer::text((string) $data['name']),
                'code' => TextNormalizer::technicalCode($data['code'] ?? null),
                'normalized_code' => TextNormalizer::technicalCode($data['code'] ?? null),
                'status' => RegistrationStatus::Active,
                'description' => TextNormalizer::nullableText($data['description'] ?? null),
            ]);
        });
    }

    private function ensureAreaBelongsToTenant(Area $area, int $organizationId): void
    {
        if (! $area->belongsToOrganization($organizationId)) {
            throw ValidationException::withMessages([
                'area_id' => 'A area nao pertence a organizacao atual.',
            ]);
        }
    }
}
