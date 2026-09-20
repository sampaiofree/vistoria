<?php

declare(strict_types=1);

namespace App\Enums;

enum AssetAbcClass: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';

    public function gravity(): int
    {
        return match ($this) {
            self::A => 3,
            self::B => 2,
            self::C, self::D => 1,
        };
    }

    public function label(): string
    {
        return "Classe {$this->value}";
    }

    /** @return list<array{value:string,label:string,score:int}> */
    public static function options(): array
    {
        return array_map(fn (self $class): array => [
            'value' => $class->value,
            'label' => $class->label(),
            'score' => $class->gravity(),
        ], self::cases());
    }
}
