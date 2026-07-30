<?php

declare(strict_types=1);

namespace App\Enums;

enum DefectStatus: string
{
    case Active = 'active';
    case Repaired = 'repaired';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Ativa',
            self::Repaired => 'Reparada',
            self::Archived => 'Arquivada',
        };
    }

    /**
     * @return array<int, array{value:string, label:string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases(),
        );
    }
}
