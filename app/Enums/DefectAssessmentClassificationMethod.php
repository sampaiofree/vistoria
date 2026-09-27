<?php

declare(strict_types=1);

namespace App\Enums;

enum DefectAssessmentClassificationMethod: string
{
    case Gut = 'gut';
    case EngineeringNote = 'engineering_note';

    public function label(): string
    {
        return match ($this) {
            self::Gut => 'GUT',
            self::EngineeringNote => 'Nota de Engenharia',
        };
    }

    /** @return array<int, array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $method): array => ['value' => $method->value, 'label' => $method->label()],
            self::cases(),
        );
    }
}
