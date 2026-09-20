<?php

declare(strict_types=1);

namespace App\Enums;

enum QuantityCalculationMode: string
{
    case Calculated = 'calculated';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Calculated => 'Calculado',
            self::Manual => 'Manual',
        };
    }
}
