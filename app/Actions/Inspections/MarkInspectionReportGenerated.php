<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Enums\InspectionStatus;
use App\Models\Inspection;
use App\Models\User;

/** @internal Reserved for the report generation process; do not expose through a controller. */
final class MarkInspectionReportGenerated
{
    public function __construct(private readonly TransitionInspection $transition) {}

    public function handle(Inspection $inspection, User $actor): Inspection
    {
        return $this->transition->handle(
            $inspection, $actor, [InspectionStatus::Approved], InspectionStatus::ReportGenerated, 'report_generated_at',
            static function (Inspection $inspection): void {},
        );
    }
}
