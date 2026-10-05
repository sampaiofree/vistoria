<?php

declare(strict_types=1);

namespace App\Enums;

enum InspectionCorrectionRequestFlow: string
{
    case PlannerToInspector = 'planner_to_inspector';
    case ReviewerToInspector = 'reviewer_to_inspector';
    case ReviewerToPlanner = 'reviewer_to_planner';
    case ReleaserToReviewer = 'releaser_to_reviewer';

    public function requesterLabel(): string
    {
        return match ($this) {
            self::PlannerToInspector => 'Planejador',
            self::ReviewerToInspector, self::ReviewerToPlanner => 'Revisor',
            self::ReleaserToReviewer => 'Liberador',
        };
    }

    public function responderLabel(): string
    {
        return match ($this) {
            self::PlannerToInspector => 'Inspetor',
            self::ReviewerToInspector => 'Inspetor',
            self::ReviewerToPlanner => 'Planejador',
            self::ReleaserToReviewer => 'Revisor',
        };
    }
}
