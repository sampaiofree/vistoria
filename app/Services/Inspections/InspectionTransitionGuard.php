<?php

declare(strict_types=1);

namespace App\Services\Inspections;

use App\Enums\InspectionStatus;

final class InspectionTransitionGuard
{
    private const ALLOWED = [
        'planned' => ['in_progress', 'canceled'],
        'in_progress' => ['awaiting_review', 'canceled'],
        'awaiting_review' => ['in_correction', 'awaiting_approval', 'canceled'],
        'in_correction' => ['awaiting_review', 'canceled'],
        'awaiting_approval' => ['in_correction', 'approved', 'canceled'],
        'approved' => ['report_generated'],
        'report_generated' => ['released'],
        'released' => [],
        'canceled' => [],
    ];

    public function allows(InspectionStatus $from, InspectionStatus $to): bool
    {
        return in_array($to->value, self::ALLOWED[$from->value] ?? [], true);
    }
}
