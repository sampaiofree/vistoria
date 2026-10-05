<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\InspectionCorrectionRequestFlow;
use App\Enums\InspectionCorrectionRequestStatus;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
use App\Enums\UserAccountType;
use App\Models\Inspection;
use App\Models\InspectionCorrectionRequest;
use App\Models\User;

final class InspectionCorrectionRequestPolicy
{
    public function update(User $user, InspectionCorrectionRequest $request): bool
    {
        return $request->status === InspectionCorrectionRequestStatus::Marked
            && $this->isRequester($user, $request);
    }

    public function delete(User $user, InspectionCorrectionRequest $request): bool
    {
        return $this->update($user, $request);
    }

    public function address(User $user, InspectionCorrectionRequest $request): bool
    {
        return $request->status === InspectionCorrectionRequestStatus::Requested
            && $this->isResponder($user, $request);
    }

    public function markPending(User $user, InspectionCorrectionRequest $request): bool
    {
        return $request->status === InspectionCorrectionRequestStatus::Addressed
            && $this->isResponder($user, $request);
    }

    public function close(User $user, InspectionCorrectionRequest $request): bool
    {
        return in_array($request->status, [InspectionCorrectionRequestStatus::Addressed, InspectionCorrectionRequestStatus::Closed], true)
            && $this->isRequester($user, $request);
    }

    public function replace(User $user, InspectionCorrectionRequest $request): bool
    {
        return $request->status === InspectionCorrectionRequestStatus::Addressed
            && $this->isRequester($user, $request);
    }

    public function createChild(User $user, InspectionCorrectionRequest $request): bool
    {
        return $request->flow === InspectionCorrectionRequestFlow::ReleaserToReviewer
            && $request->parent_request_id === null
            && $request->status === InspectionCorrectionRequestStatus::Requested
            && $this->hasRoleAtStatus($user, $request->inspection, OperationalRole::Reviewer, InspectionStatus::InReview);
    }

    private function isRequester(User $user, InspectionCorrectionRequest $request): bool
    {
        [$role, $status] = match ($request->flow) {
            InspectionCorrectionRequestFlow::PlannerToInspector => [OperationalRole::Planner, InspectionStatus::AwaitingM2],
            InspectionCorrectionRequestFlow::ReviewerToInspector, InspectionCorrectionRequestFlow::ReviewerToPlanner => [OperationalRole::Reviewer, InspectionStatus::InReview],
            InspectionCorrectionRequestFlow::ReleaserToReviewer => [OperationalRole::Releaser, InspectionStatus::AwaitingRelease],
        };

        return $this->hasRoleAtStatus($user, $request->inspection, $role, $status);
    }

    private function isResponder(User $user, InspectionCorrectionRequest $request): bool
    {
        [$role, $status] = match ($request->flow) {
            InspectionCorrectionRequestFlow::PlannerToInspector => [OperationalRole::Inspector, InspectionStatus::InCorrection],
            InspectionCorrectionRequestFlow::ReviewerToInspector => [OperationalRole::Inspector, InspectionStatus::InCorrection],
            InspectionCorrectionRequestFlow::ReviewerToPlanner => [OperationalRole::Planner, InspectionStatus::AwaitingM2],
            InspectionCorrectionRequestFlow::ReleaserToReviewer => [OperationalRole::Reviewer, InspectionStatus::InReview],
        };

        return $this->hasRoleAtStatus($user, $request->inspection, $role, $status);
    }

    private function hasRoleAtStatus(User $user, ?Inspection $inspection, OperationalRole $role, InspectionStatus $status): bool
    {
        return $inspection !== null
            && $user->isActive()
            && ! $user->isSuperAdmin()
            && $user->organization_id !== null
            && $inspection->organization_id === $user->organization_id
            && $inspection->status === $status
            && $user->operational_role === $role
            && ($user->account_type === UserAccountType::Member
                || (in_array($role, [OperationalRole::Reviewer, OperationalRole::Releaser], true)
                    && $user->account_type === UserAccountType::CompanyAdmin))
            && $inspection->hasAnyResponsibilityForUser($user, match ($role) {
                OperationalRole::Planner => InspectionResponsibility::Preparer,
                OperationalRole::Inspector => InspectionResponsibility::Reviewer,
                OperationalRole::Reviewer => InspectionResponsibility::Approver,
                OperationalRole::Releaser => InspectionResponsibility::Releaser,
            });
    }
}
