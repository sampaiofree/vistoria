<?php

declare(strict_types=1);

namespace App\Services\Inspections;

use App\Enums\InspectionStatus;

final class InspectionTransitionGuard
{
    /**
     * @var array<string, array<int, string>>
     */
    private const ALLOWED = [
        'planned' => [
            'in_progress',
            'canceled',
        ],
        'in_progress' => [
            'awaiting_review',
            'canceled',
        ],
        'awaiting_review' => [
            'in_correction',
            'awaiting_approval',
            'canceled',
        ],
        'in_correction' => [
            'awaiting_review',
            'canceled',
        ],
        'awaiting_approval' => [
            'in_correction',
            'approved',
            'canceled',
        ],
        'approved' => [
            'report_generated',
            'canceled',
        ],
        'report_generated' => [
            'released',
            'canceled',
        ],
        'released' => [],
        'canceled' => [],
    ];

    public function allows(InspectionStatus $from, InspectionStatus $to): bool
    {
        return in_array($to->value, self::ALLOWED[$from->value] ?? [], true);
    }

    /**
     * @return array<int, InspectionStatus>
     */
    public function allowedTargets(InspectionStatus $from): array
    {
        return array_values(array_filter(
            array_map(
                fn (string $value): ?InspectionStatus => InspectionStatus::tryFrom($value),
                self::ALLOWED[$from->value] ?? [],
            ),
        ));
    }
}
