<?php

declare(strict_types=1);

namespace App\Actions\Inspections;

use App\Actions\Inspections\Concerns\ValidatesInspectionResponsibility;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Models\Inspection;
use App\Models\User;

final class StartInspection
{
    use ValidatesInspectionResponsibility;

    public function __construct(private readonly TransitionInspection $transition) {}

    public function handle(Inspection $inspection, User $actor): Inspection
    {
        return $this->transition->handle(
            $inspection, $actor, [InspectionStatus::Planned], InspectionStatus::InProgress, 'started_at',
            function (Inspection $locked) use ($actor): void {
                $this->requireResponsible($locked, InspectionResponsibility::Inspector);
                $this->requireResponsible($locked, InspectionResponsibility::Inspector, $actor);
            },
            extraAttributes: ['inspected_on' => $inspection->inspected_on ?? today()],
        );
    }
}
