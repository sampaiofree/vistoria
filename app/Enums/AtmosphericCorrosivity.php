<?php

declare(strict_types=1);

namespace App\Enums;

enum AtmosphericCorrosivity: string
{
    case C2 = 'C2';
    case C3 = 'C3';
    case C4 = 'C4';
    case C5 = 'C5';
    case CX = 'CX';

    public function urgency(): int
    {
        return match ($this) {
            self::C2 => 1,
            self::C3 => 2,
            self::C4 => 3,
            self::C5 => 4,
            self::CX => 5,
        };
    }

    public function label(): string
    {
        return "Atmosfera {$this->value}";
    }

    /** @return list<array{value:string,label:string,score:int}> */
    public static function options(): array
    {
        return array_map(fn (self $classification): array => [
            'value' => $classification->value,
            'label' => $classification->label(),
            'score' => $classification->urgency(),
        ], self::cases());
    }
}
