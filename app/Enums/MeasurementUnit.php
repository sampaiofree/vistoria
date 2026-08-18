<?php

declare(strict_types=1);

namespace App\Enums;

enum MeasurementUnit: string
{
    case Unit = 'unit';
    case Meter = 'm';
    case SquareMeter = 'm2';
    case CubicMeter = 'm3';
    case Millimeter = 'mm';
    case Centimeter = 'cm';
    case Kilogram = 'kg';
    case Liter = 'l';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Unit => 'Unidade',
            self::Meter => 'Metro (m)',
            self::SquareMeter => 'Metro quadrado (m²)',
            self::CubicMeter => 'Metro cúbico (m³)',
            self::Millimeter => 'Milímetro (mm)',
            self::Centimeter => 'Centímetro (cm)',
            self::Kilogram => 'Quilograma (kg)',
            self::Liter => 'Litro (l)',
            self::Other => 'Outra',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::Unit => 'un.',
            self::SquareMeter => 'm²',
            self::CubicMeter => 'm³',
            default => $this->value,
        };
    }

    /** @return array<int, array{value:string,label:string,symbol:string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $unit): array => [
                'value' => $unit->value,
                'label' => $unit->label(),
                'symbol' => $unit->symbol(),
            ],
            self::cases(),
        );
    }
}
