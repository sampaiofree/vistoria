<?php

declare(strict_types=1);

namespace App\Services\Reports;

final class GutGravityLegend
{
    /** @param array<string, mixed> $gravity */
    public static function fromSnapshot(array $gravity): string
    {
        $safety = data_get($gravity, 'safety_impact.score');
        $activity = data_get($gravity, 'asset_impact.score');

        if (is_numeric($safety) && is_numeric($activity)) {
            if ((int) $activity > (int) $safety) {
                return 'IMP. ATIV.';
            }

            if ((int) $safety > (int) $activity) {
                return 'IMP. SEG.';
            }
        }

        return 'IMP. ATIV. / IMP. SEG.';
    }
}
