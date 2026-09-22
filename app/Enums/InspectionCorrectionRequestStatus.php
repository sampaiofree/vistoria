<?php

declare(strict_types=1);

namespace App\Enums;

enum InspectionCorrectionRequestStatus: string
{
    case Marked = 'marked';
    case Requested = 'requested';
    case Addressed = 'addressed';
    case Closed = 'closed';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Marked => 'Marcada para correção',
            self::Requested => 'Correção solicitada',
            self::Addressed => 'Ajuste realizado',
            self::Closed => 'Encerrada',
            self::Superseded => 'Substituída por nova solicitação',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Marked, self::Requested, self::Addressed], true);
    }
}
