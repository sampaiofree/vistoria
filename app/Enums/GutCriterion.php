<?php

declare(strict_types=1);

namespace App\Enums;

enum GutCriterion: string
{
    case Gravity = 'gravity';
    case Urgency = 'urgency';
    case Trend = 'trend';
}
