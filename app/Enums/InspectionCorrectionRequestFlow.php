<?php

declare(strict_types=1);

namespace App\Enums;

enum InspectionCorrectionRequestFlow: string
{
    case ReviewerToInspector = 'reviewer_to_inspector';
    case ReleaserToReviewer = 'releaser_to_reviewer';

    public function requesterLabel(): string
    {
        return match ($this) {
            self::ReviewerToInspector => 'Revisor',
            self::ReleaserToReviewer => 'Liberador',
        };
    }

    public function responderLabel(): string
    {
        return match ($this) {
            self::ReviewerToInspector => 'Inspetor',
            self::ReleaserToReviewer => 'Revisor',
        };
    }
}
