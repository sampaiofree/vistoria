<?php

namespace App\Policies;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Enums\UserAccountType;
use App\Models\Inspection;
use App\Models\User;

final class InspectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->activeInOrganization($user);
    }

    public function view(User $user, Inspection $inspection): bool
    {
        return $this->activeInOrganization($user)
            && $this->sameOrganization($user, $inspection)
            && (
                $user->isCompanyAdmin()
                || $inspection->hasAnyResponsibilityForUser($user, ...InspectionResponsibility::cases())
            );
    }

    public function create(User $user): bool
    {
        return $user->isActive()
            && ! $user->isSuperAdmin()
            && $user->organization_id !== null
            && $user->operational_role === OperationalRole::Planner;
    }

    public function updatePlanned(User $user, Inspection $inspection): bool
    {
        return $inspection->status === InspectionStatus::Planned
            && $this->activeWithRoleAndAssignment($user, $inspection, OperationalRole::Planner);
    }

    public function manageReportMetadata(User $user, Inspection $inspection): bool
    {
        return $this->manageReportContent($user, $inspection);
    }

    public function manageGeneralAspects(User $user, Inspection $inspection): bool
    {
        return $this->manageReportContent($user, $inspection);
    }

    public function updateReportRevision(User $user, Inspection $inspection): bool
    {
        return $this->manageReportContent($user, $inspection);
    }

    public function manageReportOverview(User $user, Inspection $inspection): bool
    {
        return $this->manageReportContent($user, $inspection);
    }

    public function manageClassificationM2(User $user, Inspection $inspection): bool
    {
        return $this->manageReportContent($user, $inspection);
    }

    public function assignResponsibles(User $user, Inspection $inspection): bool
    {
        if ($inspection->status === InspectionStatus::Planned) {
            return false;
        }

        if ($inspection->status === InspectionStatus::InReview) {
            return false;
        }

        if ($inspection->status === InspectionStatus::InProgress) {
            return $this->manageInProgress($user, $inspection);
        }

        return $this->activeCompanyAdmin($user)
            && $this->sameOrganization($user, $inspection)
            && ! $inspection->status->isFinal();
    }

    public function start(User $user, Inspection $inspection): bool
    {
        return $this->activeInOrganization($user)
            && $this->sameOrganization($user, $inspection)
            && $inspection->status === InspectionStatus::Planned
            && $inspection->equipment->canReceiveInspection()
            && $user->operational_role === OperationalRole::Inspector
            && $inspection->hasAnyResponsibilityForUser($user, ...InspectionResponsibility::cases());
    }

    public function submitForReview(User $user, Inspection $inspection): bool
    {
        return match ($inspection->status) {
            InspectionStatus::InProgress, InspectionStatus::InCorrection => $this->manageReportContent($user, $inspection),
            default => false,
        };
    }

    public function returnForCorrection(User $user, Inspection $inspection): bool
    {
        if (! $this->activeInOrganization($user) || ! $this->sameOrganization($user, $inspection)) {
            return false;
        }

        return match ($inspection->status) {
            InspectionStatus::InReview => $this->activeWithRoleAndAssignment($user, $inspection, OperationalRole::Reviewer),
            default => false,
        };
    }

    public function startReview(User $user, Inspection $inspection): bool
    {
        return $inspection->status === InspectionStatus::AwaitingReview
            && $this->activeWithRoleAndAssignment($user, $inspection, OperationalRole::Reviewer);
    }

    public function approve(User $user, Inspection $inspection): bool
    {
        return $this->activeInOrganization($user)
            && $this->sameOrganization($user, $inspection)
            && $inspection->status === InspectionStatus::InReview
            && $this->activeWithRoleAndAssignment($user, $inspection, OperationalRole::Reviewer);
    }

    public function createCorrectionRequests(User $user, Inspection $inspection): bool
    {
        return match ($inspection->status) {
            InspectionStatus::InReview => $this->activeWithRoleAndAssignment($user, $inspection, OperationalRole::Reviewer),
            InspectionStatus::AwaitingRelease => $this->activeWithRoleAndAssignment($user, $inspection, OperationalRole::Releaser),
            default => false,
        };
    }

    public function release(User $user, Inspection $inspection): bool
    {
        return $this->activeInOrganization($user)
            && $this->sameOrganization($user, $inspection)
            && $inspection->status === InspectionStatus::AwaitingRelease
            && $this->activeWithRoleAndAssignment($user, $inspection, OperationalRole::Releaser);
    }

    public function returnForReview(User $user, Inspection $inspection): bool
    {
        return $inspection->status === InspectionStatus::AwaitingRelease
            && $this->activeWithRoleAndAssignment($user, $inspection, OperationalRole::Releaser);
    }

    public function cancel(User $user, Inspection $inspection): bool
    {
        if ($inspection->status === InspectionStatus::InProgress) {
            return $this->manageInProgress($user, $inspection);
        }

        return $this->activeInOrganization($user)
            && $this->sameOrganization($user, $inspection)
            && ! $inspection->status->isFinal()
            && (
                $user->isCompanyAdmin()
                || $inspection->hasAnyResponsibilityForUser($user, ...InspectionResponsibility::cases())
            );
    }

    /**
     * Allows changes while the inspection is being performed only to the assigned inspector.
     */
    public function manageInProgress(User $user, Inspection $inspection): bool
    {
        return $this->activeInOrganization($user)
            && $this->sameOrganization($user, $inspection)
            && $inspection->status === InspectionStatus::InProgress
            && $user->operational_role === OperationalRole::Inspector
            && $inspection->hasAnyResponsibilityForUser($user, ...InspectionResponsibility::cases());
    }

    public function manageReportContent(User $user, Inspection $inspection): bool
    {
        if (! $this->activeInOrganization($user)
            || $user->account_type !== UserAccountType::Member
            || ! $this->sameOrganization($user, $inspection)) {
            return false;
        }

        return match ($inspection->status) {
            InspectionStatus::InProgress, InspectionStatus::InCorrection => $user->operational_role === OperationalRole::Inspector,
            InspectionStatus::InReview => $user->operational_role === OperationalRole::Reviewer,
            default => false,
        } && $inspection->hasAnyResponsibilityForUser($user, ...InspectionResponsibility::cases());
    }

    public function manageFieldContent(User $user, Inspection $inspection): bool
    {
        return $this->manageReportContent($user, $inspection);
    }

    private function activeWithRoleAndAssignment(User $user, Inspection $inspection, OperationalRole $role): bool
    {
        return $this->activeInOrganization($user)
            && $this->sameOrganization($user, $inspection)
            && $user->operational_role === $role
            && $inspection->hasAnyResponsibilityForUser($user, ...InspectionResponsibility::cases());
    }

    private function activeInOrganization(User $user): bool
    {
        return $user->isActive()
            && ! $user->isSuperAdmin()
            && $user->organization_id !== null;
    }

    private function sameOrganization(User $user, Inspection $inspection): bool
    {
        return $user->organization_id !== null
            && $inspection->belongsToOrganization($user->organization_id);
    }

    private function activeCompanyAdmin(User $user): bool
    {
        return $user->isActive()
            && $user->isCompanyAdmin()
            && ! $user->isSuperAdmin()
            && $user->organization_id !== null;
    }
}
