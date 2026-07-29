<?php

namespace App\Actions\Subareas;

use App\Models\Subarea;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;

final class UpdateSubarea
{
    public function handle(Subarea $subarea, array $data): Subarea
    {
        return DB::transaction(function () use ($subarea, $data): Subarea {
            $subarea->update([
                'name' => TextNormalizer::text((string) $data['name']),
                'code' => TextNormalizer::technicalCode($data['code'] ?? null),
                'normalized_code' => TextNormalizer::technicalCode($data['code'] ?? null),
                'description' => TextNormalizer::nullableText($data['description'] ?? null),
            ]);

            return $subarea->refresh();
        });
    }
}
