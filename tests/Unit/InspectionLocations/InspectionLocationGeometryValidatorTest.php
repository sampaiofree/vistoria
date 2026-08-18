<?php

declare(strict_types=1);

namespace Tests\Unit\InspectionLocations;

use App\Services\InspectionLocations\InspectionLocationGeometryValidator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class InspectionLocationGeometryValidatorTest extends TestCase
{
    #[DataProvider('validGeometries')]
    public function test_accepts_supported_geometries(array $geometry): void
    {
        app(InspectionLocationGeometryValidator::class)->validate($geometry, [
            'stroke' => '#0F766E', 'fill' => 'none', 'stroke_width' => 0.005, 'opacity' => 0.8, 'dashed' => false,
        ]);

        $this->addToAssertionCount(1);
    }

    public static function validGeometries(): array
    {
        return [
            'point' => [['version' => 1, 'shapes' => [['type' => 'point', 'x' => 0.2, 'y' => 0.3]]]],
            'rectangle' => [['version' => 1, 'shapes' => [['type' => 'rectangle', 'x' => 0.2, 'y' => 0.3, 'width' => 0.4, 'height' => 0.2]]]],
            'polygon and polyline' => [['version' => 1, 'shapes' => [
                ['type' => 'polygon', 'points' => [[0.1, 0.1], [0.4, 0.1], [0.3, 0.4]]],
                ['type' => 'polyline', 'points' => [[0.5, 0.5], [0.8, 0.7]]],
            ], 'callout' => ['x' => 0.9, 'y' => 0.2, 'anchor_x' => 0.4, 'anchor_y' => 0.3]]],
        ];
    }

    #[DataProvider('invalidGeometries')]
    public function test_rejects_invalid_geometries(array $geometry): void
    {
        $this->expectException(ValidationException::class);
        app(InspectionLocationGeometryValidator::class)->validate($geometry);
    }

    public static function invalidGeometries(): array
    {
        return [
            'unknown version' => [['version' => 2, 'shapes' => [['type' => 'point', 'x' => 0.2, 'y' => 0.3]]]],
            'outside canvas' => [['version' => 1, 'shapes' => [['type' => 'point', 'x' => 1.1, 'y' => 0.3]]]],
            'infinite' => [['version' => 1, 'shapes' => [['type' => 'point', 'x' => INF, 'y' => 0.3]]]],
            'short polygon' => [['version' => 1, 'shapes' => [['type' => 'polygon', 'points' => [[0.1, 0.1], [0.2, 0.2]]]]]],
            'rectangle overflow' => [['version' => 1, 'shapes' => [['type' => 'rectangle', 'x' => 0.8, 'y' => 0.3, 'width' => 0.4, 'height' => 0.2]]]],
            'unknown property' => [['version' => 1, 'shapes' => [['type' => 'point', 'x' => 0.2, 'y' => 0.3, 'radius' => 2]]]],
        ];
    }

    public function test_rejects_excessive_payload(): void
    {
        $this->expectException(ValidationException::class);
        app(InspectionLocationGeometryValidator::class)->validate(
            ['version' => 1, 'shapes' => [['type' => 'point', 'x' => 0.2, 'y' => 0.3]]],
            ['stroke' => '#'.str_repeat('A', 70_000)],
        );
    }

    public function test_normalizes_complete_style_and_uses_legacy_fallback(): void
    {
        $geometry = ['version' => 1, 'shapes' => [[
            'type' => 'rectangle', 'x' => 0.1, 'y' => 0.2, 'width' => 0.3, 'height' => 0.2,
        ]]];
        $validator = app(InspectionLocationGeometryValidator::class);

        $this->assertSame([
            'stroke' => '#AB12CD',
            'fill' => '#AB12CD',
            'stroke_width' => 0.005,
            'opacity' => 0.9,
            'dashed' => false,
        ], $validator->validate($geometry, ['stroke' => '#ab12cd', 'fill' => '#ab12cd']));

        $this->assertSame([
            'stroke' => '#F1DF00',
            'fill' => '#F1DF00',
            'stroke_width' => 0.005,
            'opacity' => 0.9,
            'dashed' => false,
        ], $validator->validate($geometry));
    }

    public function test_accepts_borderless_closed_areas(): void
    {
        $style = app(InspectionLocationGeometryValidator::class)->validate(
            ['version' => 1, 'shapes' => [
                ['type' => 'rectangle', 'x' => 0.1, 'y' => 0.1, 'width' => 0.2, 'height' => 0.2],
                ['type' => 'polygon', 'points' => [[0.5, 0.5], [0.7, 0.5], [0.6, 0.7]]],
            ]],
            ['stroke' => 'none', 'fill' => '#123abc'],
        );

        $this->assertSame('none', $style['stroke']);
        $this->assertSame('#123ABC', $style['fill']);
    }

    #[DataProvider('geometriesRequiringBorder')]
    public function test_rejects_borderless_open_or_mixed_geometries(array $geometry): void
    {
        $this->expectException(ValidationException::class);

        app(InspectionLocationGeometryValidator::class)->validate($geometry, [
            'stroke' => 'none',
            'fill' => '#123ABC',
        ]);
    }

    public static function geometriesRequiringBorder(): array
    {
        return [
            'point' => [['version' => 1, 'shapes' => [['type' => 'point', 'x' => 0.2, 'y' => 0.3]]]],
            'polyline' => [['version' => 1, 'shapes' => [['type' => 'polyline', 'points' => [[0.2, 0.3], [0.4, 0.5]]]]]],
            'mixed' => [['version' => 1, 'shapes' => [
                ['type' => 'rectangle', 'x' => 0.1, 'y' => 0.1, 'width' => 0.2, 'height' => 0.2],
                ['type' => 'point', 'x' => 0.5, 'y' => 0.5],
            ]]],
        ];
    }

    #[DataProvider('invalidStyles')]
    public function test_rejects_invalid_styles(array $style): void
    {
        $this->expectException(ValidationException::class);

        app(InspectionLocationGeometryValidator::class)->validate(
            ['version' => 1, 'shapes' => [['type' => 'rectangle', 'x' => 0.1, 'y' => 0.1, 'width' => 0.2, 'height' => 0.2]]],
            $style,
        );
    }

    public static function invalidStyles(): array
    {
        return [
            'short color' => [['stroke' => '#ABC', 'fill' => '#ABC']],
            'invalid color' => [['stroke' => '#GGGGGG', 'fill' => '#GGGGGG']],
            'borderless without fill' => [['stroke' => 'none']],
            'no stroke and no fill' => [['stroke' => 'none', 'fill' => 'none']],
            'invalid dashed flag' => [['dashed' => 1]],
        ];
    }
}
