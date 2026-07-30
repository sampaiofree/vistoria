<?php

namespace App\Policies;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
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
            && $this->sameOrganization($user, $inspection);
    }

    public function create(User $user): bool
    {
        return $user->isActive()
            && $user->isCompanyAdmin()
            && ! $user->isSuperAdmin()
            && $user->organization_id !== null;
    }

    public function updatePlanned(User $user, Inspection $inspection): bool
    {
        return $this->create($user)
            && $this->sameOrganization($user, $inspection)
            && $inspection->status === InspectionStatus::Planned;
    }

    public function assignResponsibles(User $user, Inspection $inspection): bool
    {
        return $this->create($user)
            && $this->sameOrganization($user, $inspection)
            && ! $inspection->status->isFinal();
    }

    public function manageReferences(User $user, Inspection $inspection): bool
    {
        return $this->create($user)
            && $this->sameOrganization($user, $inspection)
            && ! $inspection->status->isFinal();
    }

    public function start(User $user, Inspection $inspection): bool
    {
        return $this->activeInOrganization($user)
            && $this->sameOrganization($user, $inspection)
            && $inspection->status === InspectionStatus::Planned
            && $inspection->equipment->canReceiveInspection()
            && $inspection->hasAnyResponsibilityForUser($user, InspectionResponsibility::Inspector);
    }

    public function submitForReview(User $user, Inspection $inspection): bool
    {
        return $this->activeInOrganization($user)
            && $this->sameOrganization($user, $inspection)
            && in_array($inspection->status, [
                InspectionStatus::InProgress,
                InspectionStatus::InCorrection,
            ], true)
            && $inspection->hasResponsibility(InspectionResponsibility::Preparer)
            && $inspection->hasResponsibility(InspectionResponsibility::Reviewer)
            && $inspection->hasAnyResponsibilityForUser($user, InspectionResponsibility::Preparer);
    }

    public function returnForCorrection(User $user, Inspection $inspection): bool
    {
        if (! $this->activeInOrganization($user) || ! $this->sameOrganization($user, $inspection)) {
            return false;
        }

        return match ($inspection->status) {
            InspectionStatus::AwaitingReview => $inspection->hasResponsibility(InspectionResponsibility::Reviewer)
                && $inspection->hasAnyResponsibilityForUser($user, InspectionResponsibility::Reviewer),
            InspectionStatus::AwaitingApproval => $inspection->hasResponsibility(InspectionResponsibility::Approver)
                && $inspection->hasAnyResponsibilityForUser($user, InspectionResponsibility::Approver),
            default => false,
        };
    }

    public function completeReview(User $user, Inspection $inspection): bool
    {
        return $this->activeInOrganization($user)
            && $this->sameOrganization($user, $inspection)
            && $inspection->status === InspectionStatus::AwaitingReview
            && $inspection->hasResponsibility(InspectionResponsibility::Reviewer)
            && $inspection->hasResponsibility(InspectionResponsibility::Approver)
            && $inspection->hasAnyResponsibilityForUser($user, InspectionResponsibility::Reviewer);
    }

    public function approve(User $user, Inspection $inspection): bool
    {
        return $this->activeInOrganization($user)
            && $this->sameOrganization($user, $inspection)
            && $inspection->status === InspectionStatus::AwaitingApproval
            && $inspection->hasResponsibility(InspectionResponsibility::Approver)
            && $inspection->hasAnyResponsibilityForUser($user, InspectionResponsibility::Approver);
    }

    public function release(User $user, Inspection $inspection): bool
    {
        return $this->activeInOrganization($user)
            && $this->sameOrganization($user, $inspection)
            && $inspection->status === InspectionStatus::ReportGenerated
            && $inspection->hasResponsibility(InspectionResponsibility::Releaser)
            && $inspection->hasAnyResponsibilityForUser($user, InspectionResponsibility::Releaser);
    }

    public function cancel(User $user, Inspection $inspection): bool
    {
        return $this->activeInOrganization($user)
            && $this->sameOrganization($user, $inspection)
            && ! $inspection->status->isFinal()
            && (
                $user->isCompanyAdmin()
                || $inspection->hasAnyResponsibilityForUser($user, ...InspectionResponsibility::cases())
            );
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
}
