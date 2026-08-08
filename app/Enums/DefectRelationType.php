<?php

declare(strict_types=1);

namespace App\Enums;

enum DefectRelationType: string
{
    case Split = 'split';
    case Recurrence = 'recurrence';
    case Related = 'related';

    public function label(): string
    {
        return match ($this) {
            self::Split => 'Divisão',
            self::Recurrence => 'Recorrência',
            self::Related => 'Relacionada',
        };
    }
}
