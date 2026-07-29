<?php

namespace App\Enums;

enum InspectionStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case AwaitingReview = 'awaiting_review';
    case InCorrection = 'in_correction';
    case AwaitingApproval = 'awaiting_approval';
    case Approved = 'approved';
    case ReportGenerated = 'report_generated';
    case Released = 'released';
    case Canceled = 'canceled';

    public function allowsReferenceDocumentChanges(): bool
    {
        return in_array($this, [
            self::Planned,
            self::InProgress,
            self::AwaitingReview,
            self::InCorrection,
        ], true);
    }
}
