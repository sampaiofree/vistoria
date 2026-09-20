<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\DefectCategory;
use App\Enums\MeasurementUnit;
use App\Models\DefectAssessmentQuantity;
use App\Services\Defects\DefectAssessmentQuantitySnapshot;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class DefectAssessmentQuantitySnapshotTest extends TestCase
{
    public function test_it_sums_raw_item_totals_and_preserves_item_details(): void
    {
        $snapshot = (new DefectAssessmentQuantitySnapshot)->build(
            DefectCategory::Civil,
            new Collection([
                $this->item(2, '0.2400000000000000', 'Trecho 2'),
                $this->item(1, '0.6000000000000000', 'Trecho 1'),
            ]),
        );

        $this->assertSame('0.8400000000000000', $snapshot['total']);
        $this->assertSame(2, $snapshot['item_count']);
        $this->assertSame([1, 2], array_column($snapshot['items'], 'position'));
        $this->assertSame('Trecho 1', $snapshot['items'][0]['description']);
    }

    public function test_it_rejects_aggregate_decimal_overflow(): void
    {
        $this->expectException(ValidationException::class);

        (new DefectAssessmentQuantitySnapshot)->build(
            DefectCategory::Civil,
            new Collection([
                $this->item(1, '6000000000000000000000.0000000000000000'),
                $this->item(2, '6000000000000000000000.0000000000000000'),
            ]),
        );
    }

    public function test_it_wraps_a_legacy_single_item_snapshot_without_recalculation(): void
    {
        $snapshot = (new DefectAssessmentQuantitySnapshot)->normalize([
            'category' => 'CV',
            'measurement_unit' => 'm3',
            'total' => '1.2345678901234567',
            'formula_version' => 1,
        ]);

        $this->assertSame('1.2345678901234567', $snapshot['total']);
        $this->assertSame(1, $snapshot['item_count']);
        $this->assertSame('1.2345678901234567', $snapshot['items'][0]['total']);
        $this->assertSame(1, $snapshot['items'][0]['formula_version']);
    }

    private function item(int $position, string $total, ?string $description = null): DefectAssessmentQuantity
    {
        return new DefectAssessmentQuantity([
            'category' => DefectCategory::Civil,
            'measurement_unit' => MeasurementUnit::CubicMeter,
            'measurement_value' => $total,
            'quantity' => '1.0000000000000000',
            'position' => $position,
            'description' => $description,
        ]);
    }
}
