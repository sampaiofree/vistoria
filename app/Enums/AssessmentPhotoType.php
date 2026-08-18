<?php

declare(strict_types=1);

namespace App\Enums;

enum AssessmentPhotoType: string
{
    case Overview = 'overview';
    case Detail = 'detail';
    case Context = 'context';
    case RepairEvidence = 'repair_evidence';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Overview => 'Vista geral',
            self::Detail => 'Detalhe',
            self::Context => 'Contexto',
            self::RepairEvidence => 'Evidência de reparo',
            self::Other => 'Outra',
        };
    }
}
