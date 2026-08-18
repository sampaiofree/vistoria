<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kept only so historical migrations remain replayable. Profile management
 * is no longer part of the application runtime.
 */
enum ClassificationProfileStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Retired = 'retired';
}
