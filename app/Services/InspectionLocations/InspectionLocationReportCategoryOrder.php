<?php

declare(strict_types=1);

namespace App\Services\InspectionLocations;

use App\Models\DefectCategory;
use Illuminate\Support\Collection;

final class InspectionLocationReportCategoryOrder
{
    /** @var array<string, int> */
    private const PRIORITIES = [
        'TAC' => 0,
        'REC' => 1,
        'CV' => 2,
        'CIVIL' => 2,
    ];

    public function priority(?string $code, ?string $name = null): int
    {
        $normalizedCode = $this->normalize($code);
        $normalizedName = $this->normalize($name);

        return self::PRIORITIES[$normalizedCode]
            ?? self::PRIORITIES[$normalizedName]
            ?? 1000;
    }

    /** @param Collection<int, DefectCategory> $categories @return Collection<int, DefectCategory> */
    public function sort(Collection $categories): Collection
    {
        return $categories
            ->sortBy(fn (DefectCategory $category): array => [
                $this->priority($category->code, $category->name),
                $category->position,
                $this->normalize($category->name),
                $category->id,
            ])
            ->values();
    }

    private function normalize(?string $value): string
    {
        return mb_strtoupper(trim((string) $value));
    }
}
