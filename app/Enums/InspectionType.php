<?php

declare(strict_types=1);

namespace App\Enums;

enum InspectionType: string
{
    case Initial = 'initial';
    case Reinspection = 'reinspection';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Inspeção inicial',
            self::Reinspection => 'Reinspeção',
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
