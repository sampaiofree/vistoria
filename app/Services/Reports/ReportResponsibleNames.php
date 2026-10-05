<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Inspection;
use App\Models\Organization;

final class ReportResponsibleNames
{
    /** @return array{reviewer:?string,approver:?string,releaser:?string} */
    public function forOrganization(int $organizationId): array
    {
        // Read all names together, independently of any cached model relation.
        $organization = Organization::query()->findOrFail($organizationId, ['report_verifier_name', 'report_reviewer_name', 'report_releaser_name']);

        return [
            'reviewer' => $organization->report_verifier_name,
            'approver' => $organization->report_reviewer_name,
            'releaser' => $organization->report_releaser_name,
        ];
    }

    /** @return array{reviewer:?string,approver:?string,releaser:?string} */
    public function forInspection(Inspection $inspection): array
    {
        if ($inspection->status->isFinal()) {
            // A finalized report never falls back to current company or user names.
            return [
                'reviewer' => $inspection->report_responsibles_snapshot['reviewer'] ?? null,
                'approver' => $inspection->report_responsibles_snapshot['approver'] ?? null,
                'releaser' => $inspection->report_responsibles_snapshot['releaser'] ?? null,
            ];
        }

        return $this->forOrganization($inspection->organization_id);
    }
}
