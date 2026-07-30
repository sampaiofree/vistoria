<?php

declare(strict_types=1);

namespace App\Enums;

enum DefectCategory: string
{
    case Civil = 'civil';

    public function label(): string
    {
        return match ($this) {
            self::Civil => 'Civil',
        };
    }

    public function code(): string
    {
        return match ($this) {
            self::Civil => 'CV',
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
