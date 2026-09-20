<?php

declare(strict_types=1);

namespace App\Enums;

enum DefectAssessmentCondition: string
{
    case New = 'new';
    case Reinspected = 'reinspected';
    case Reclassified = 'reclassified';
    case Canceled = 'canceled';
    case CanceledWithoutRepair = 'canceled_sr';
    case Treated = 'treated';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nova',
            self::Reinspected => 'Reinspecionada',
            self::Reclassified => 'Reclassificada',
            self::Canceled => 'Cancelada',
            self::CanceledWithoutRepair => 'Cancelada S/R',
            self::Treated => 'Tratada',
        };
    }

    public function requiresReason(): bool
    {
        return in_array($this, [self::Canceled, self::CanceledWithoutRepair], true);
    }

    public function keepsDefectActive(): bool
    {
        return $this !== self::Treated;
    }

    public function requiresEvidence(): bool
    {
        return ! $this->isCanceled();
    }

    public function requiresGut(): bool
    {
        return in_array($this, [self::New, self::Reinspected, self::Reclassified], true);
    }

    public function isCanceled(): bool
    {
        return in_array($this, [self::Canceled, self::CanceledWithoutRepair], true);
    }

    public function marksDefectAsRepaired(): bool
    {
        return $this === self::Treated;
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
