<?php

declare(strict_types=1);

namespace Tests\Unit\InspectionLocations;

use App\Services\InspectionLocations\InspectionLocationPhotoNumbering;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class InspectionLocationPhotoNumberingTest extends TestCase
{
    #[DataProvider('intervals')]
    public function test_formats_photo_intervals(array $numbers, string $expected): void
    {
        $this->assertSame($expected, app(InspectionLocationPhotoNumbering::class)->format($numbers));
    }

    public static function intervals(): array
    {
        return [
            'empty' => [[], '—'],
            'single' => [[1], '1'],
            'pair' => [[1, 2], '1 E 2'],
            'continuous' => [[5, 6, 7, 8], '5 A 8'],
            'discontinuous' => [[1, 3, 4, 7, 8, 9], '1, 3 E 4, 7 A 9'],
        ];
    }

    public function test_global_assignment_deduplicates_reused_photo(): void
    {
        $this->assertSame(
            ['photo-a' => 1, 'photo-b' => 2, 'photo-c' => 3],
            app(InspectionLocationPhotoNumbering::class)->assign(['photo-a', 'photo-b', 'photo-a', 'photo-c']),
        );
    }

    public function test_report_assignment_can_reserve_the_first_four_numbers(): void
    {
        $this->assertSame(
            ['photo-a' => 5, 'photo-b' => 6],
            app(InspectionLocationPhotoNumbering::class)->assign(['photo-a', 'photo-b'], 4),
        );
    }
}
