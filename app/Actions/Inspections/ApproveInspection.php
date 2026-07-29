<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Actions\Inspections\Concerns\ValidatesInspectionResponsibility;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Models\Inspection;
use App\Models\User;

final class ApproveInspection
{
    use ValidatesInspectionResponsibility;

    public function __construct(private readonly TransitionInspection $transition) {}

    public function handle(Inspection $inspection, User $actor): Inspection
    {
        return $this->transition->handle(
            $inspection, $actor, [InspectionStatus::AwaitingApproval], InspectionStatus::Approved, 'approved_at',
            fn (Inspection $locked) => $this->requireResponsible($locked, InspectionResponsibility::Approver, $actor),
        );
    }
}
