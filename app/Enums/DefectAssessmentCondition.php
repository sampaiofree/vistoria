<?php

declare(strict_types=1);

namespace App\Enums;

enum DefectAssessmentCondition: string
{
    case New = 'new';
    case Unchanged = 'unchanged';
    case Worsened = 'worsened';
    case Improved = 'improved';
    case Repaired = 'repaired';
    case NotLocated = 'not_located';
    case NotInspected = 'not_inspected';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nova',
            self::Unchanged => 'Igual',
            self::Worsened => 'Agravou',
            self::Improved => 'Melhorou',
            self::Repaired => 'Reparada',
            self::NotLocated => 'Não localizada',
            self::NotInspected => 'Não foi possível inspecionar',
        };
    }

    public function requiresReason(): bool
    {
        return in_array($this, [self::NotLocated, self::NotInspected], true);
    }

    public function keepsDefectActive(): bool
    {
        return $this !== self::Repaired;
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
