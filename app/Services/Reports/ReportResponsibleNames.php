<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\InspectionResponsibility;
use App\Models\Inspection;
use App\Models\Organization;

final class ReportResponsibleNames
{
    /** @return array{reviewer:?string,approver:?string,releaser:?string} */
    public function forOrganization(int $organizationId, bool $isM3 = false): array
    {
        // Read all names together, independently of any cached model relation.
        $prefix = $isM3 ? 'report_m3_' : 'report_';
        $organization = Organization::query()->findOrFail($organizationId, [
            $prefix.'verifier_name', $prefix.'reviewer_name', $prefix.'releaser_name',
        ]);

        return [
            'reviewer' => $organization->{$prefix.'verifier_name'},
            'approver' => $organization->{$prefix.'reviewer_name'},
            'releaser' => $organization->{$prefix.'releaser_name'},
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

        return $this->forOrganization($inspection->organization_id, $inspection->is_m3);
    }

    /** @return array{preparer:?string,reviewer:?string,approver:?string,releaser:?string} */
    public function coverNamesForInspection(Inspection $inspection): array
    {
        $inspection->loadMissing(['responsibles.user']);
        $inspectors = $inspection->responsibles->where('responsibility', InspectionResponsibility::Reviewer);
        $inspector = $inspectors->firstWhere('is_primary', true) ?? $inspectors->first();
        $name = $inspector?->user?->name;

        return [
            'preparer' => $name === null ? null : trim((string) preg_replace('/\s*—.*$/u', '', $name)),
            ...$this->forInspection($inspection),
        ];
    }

    /**
     * @param  array{preparer:?string,reviewer:?string,approver:?string,releaser:?string}  $names
     * @return list<string>
     */
    public function missingCoverLabels(array $names): array
    {
        $labels = [
            'preparer' => 'Preparado',
            'reviewer' => 'Verificado',
            'approver' => 'Aprovado',
            'releaser' => 'Liberado',
        ];

        return array_values(array_filter($labels, function (string $label, string $role) use ($names): bool {
            $name = $names[$role] ?? null;

            return blank($name) || blank(trim((string) preg_replace('/\s*—.*$/u', '', $name)));
        }, ARRAY_FILTER_USE_BOTH));
    }
}
