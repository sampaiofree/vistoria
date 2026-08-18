<?php

declare(strict_types=1);

namespace App\Actions\Classification;

use App\Models\DefectCategory;
use App\Models\User;
use App\Support\TextNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateDefectCategory
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, DefectCategory $category, array $data): DefectCategory
    {
        return DB::transaction(function () use ($actor, $category, $data): DefectCategory {
            $code = TextNormalizer::technicalCode((string) $data['code']);

            if ($code !== $category->code && ($category->defects()->exists() || $category->codeSequences()->exists())) {
                throw ValidationException::withMessages([
                    'code' => 'O código não pode ser alterado depois que a categoria foi utilizada.',
                ]);
            }

            $category->update([
                'name' => TextNormalizer::text((string) $data['name']),
                'code' => $code,
                'description' => TextNormalizer::nullableText($data['description'] ?? null),
                'position' => (int) ($data['position'] ?? $category->position),
                'requires_location_map' => (bool) ($data['requires_location_map'] ?? $category->requires_location_map),
                'updated_by' => $actor->getKey(),
            ]);

            return $category->refresh();
        });
    }
}
