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

    public function description(): string
    {
        return match ($this) {
            self::C2 => 'Baixa corrosividade; baixa poluição.',
            self::C3 => 'Corrosividade média; ambiente urbano/industrial moderadamente poluído.',
            self::C4 => 'Alta corrosividade; forte poluição industrial, umidade e agentes agressivos.',
            self::C5 => 'Corrosividade muito alta em ambientes internos.',
            self::CX => 'Corrosividade muito alta em ambientes externos.',
        };
    }

    /** @return list<array{value:string,label:string,description:string,score:int}> */
    public static function options(): array
    {
        return array_map(fn (self $classification): array => [
            'value' => $classification->value,
            'label' => $classification->label(),
            'description' => $classification->description(),
            'score' => $classification->urgency(),
        ], self::cases());
    }
}
