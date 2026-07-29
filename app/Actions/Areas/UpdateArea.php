<?php

namespace App\Actions\Areas;

use App\Models\Area;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;

final class UpdateArea
{
    public function handle(Area $area, array $data): Area
    {
        return DB::transaction(function () use ($area, $data): Area {
            $area->update([
                'name' => TextNormalizer::text((string) $data['name']),
                'code' => TextNormalizer::technicalCode($data['code'] ?? null),
                'normalized_code' => TextNormalizer::technicalCode($data['code'] ?? null),
                'description' => TextNormalizer::nullableText($data['description'] ?? null),
            ]);

            return $area->refresh();
        });
    }
}
