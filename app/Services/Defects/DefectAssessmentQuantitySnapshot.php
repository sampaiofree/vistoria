<?php

declare(strict_types=1);

namespace App\Services\Defects;

use App\Enums\DefectCategory;
use App\Models\DefectAssessmentQuantity;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class DefectAssessmentQuantitySnapshot
{
    public const VERSION = 2;

    private const SCALE = 16;

    private const MAX_VALUE = '9999999999999999999999.9999999999999999';

    /**
     * @param  Collection<int, DefectAssessmentQuantity>  $items
     * @return array<string, mixed>|null
     */
    public function build(DefectCategory $category, Collection $items): ?array
    {
        $items = $items->sortBy([
            ['position', 'asc'],
            ['id', 'asc'],
        ])->values();

        if ($items->isEmpty()) {
            return null;
        }

        $expectedUnit = $items->first()->measurement_unit;
        $total = BigDecimal::zero()->toScale(self::SCALE);

        foreach ($items as $item) {
            if ($item->category !== $category || $item->measurement_unit !== $expectedUnit) {
                throw ValidationException::withMessages([
                    'quantity' => 'Os itens do quantitativo devem pertencer à mesma categoria e unidade.',
                ]);
            }

            $total = $total->plus(BigDecimal::of((string) $item->measurement_value));
        }

        $total = $total->toScale(self::SCALE, RoundingMode::Unnecessary);
        if ($total->isGreaterThan(self::MAX_VALUE)) {
            throw ValidationException::withMessages([
                'quantity' => 'O total dos itens está fora da precisão suportada.',
            ]);
        }

        return [
            'source' => 'native_quantity_catalog',
            'snapshot_version' => self::VERSION,
            'category' => $category->value,
            'measurement_unit' => $expectedUnit->value,
            'item_count' => $items->count(),
            'total' => (string) $total,
            'items' => $items->map(
                fn (DefectAssessmentQuantity $item): array => $item->snapshot(),
            )->all(),
        ];
    }

    /**
     * Accepts the previous single-item shape without recalculating historical data.
     *
     * @param  array<string, mixed>|null  $snapshot
     * @return array<string, mixed>|null
     */
    public function normalize(?array $snapshot): ?array
    {
        if ($snapshot === null) {
            return null;
        }

        if (is_array($snapshot['items'] ?? null)) {
            $items = collect($snapshot['items'])
                ->values()
                ->map(function (mixed $item, int $index): array {
                    $item = is_array($item) ? $item : [];
                    $item['position'] ??= $index + 1;
                    $item['description'] ??= null;

                    return $item;
                })
                ->all();

            return [
                ...$snapshot,
                'snapshot_version' => $snapshot['snapshot_version'] ?? self::VERSION,
                'item_count' => $snapshot['item_count'] ?? count($items),
                'total' => (string) ($snapshot['total'] ?? '0'),
                'items' => $items,
            ];
        }

        $item = $snapshot;
        $item['position'] ??= 1;
        $item['description'] ??= null;

        return [
            'source' => 'quantity_snapshot',
            'snapshot_version' => self::VERSION,
            'category' => $snapshot['category'] ?? null,
            'measurement_unit' => $snapshot['measurement_unit'] ?? null,
            'item_count' => 1,
            'total' => (string) ($snapshot['total'] ?? '0'),
            'items' => [$item],
        ];
    }
}
