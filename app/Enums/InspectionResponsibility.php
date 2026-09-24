<?php

namespace App\Enums;

enum InspectionResponsibility: string
{
    case Preparer = 'preparer';
    case Reviewer = 'reviewer';
    case Approver = 'approver';
    case Releaser = 'releaser';

    public function label(): string
    {
        return match ($this) {
            self::Preparer => 'Preparador',
            self::Reviewer => 'Inspetor',
            self::Approver => 'Revisor',
            self::Releaser => 'Liberador',
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
