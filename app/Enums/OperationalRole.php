<?php

namespace App\Enums;

enum OperationalRole: string
{
    case Planner = 'planner';
    case Inspector = 'inspector';
    case Reviewer = 'reviewer';
    case Releaser = 'releaser';

    public function label(): string
    {
        return match ($this) {
            self::Planner => 'Planejador',
            self::Inspector => 'Inspetor',
            self::Reviewer => 'Revisor',
            self::Releaser => 'Liberador',
        };
    }
}
