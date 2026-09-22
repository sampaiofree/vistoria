<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\InspectionCorrectionRequestFlow;
use App\Enums\InspectionCorrectionRequestStatus;
use App\Enums\InspectionStatus;
use App\Enums\OperationalRole;
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
        return $request->status === InspectionCorrectionRequestStatus::Addressed
            && $this->isRequester($user, $request);
    }

    public function replace(User $user, InspectionCorrectionRequest $request): bool
    {
        return $this->close($user, $request);
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
            InspectionCorrectionRequestFlow::ReviewerToInspector => [OperationalRole::Reviewer, InspectionStatus::InReview],
            InspectionCorrectionRequestFlow::ReleaserToReviewer => [OperationalRole::Releaser, InspectionStatus::AwaitingRelease],
        };

        return $this->hasRoleAtStatus($user, $request->inspection, $role, $status);
    }

    private function isResponder(User $user, InspectionCorrectionRequest $request): bool
    {
        [$role, $status] = match ($request->flow) {
            InspectionCorrectionRequestFlow::ReviewerToInspector => [OperationalRole::Inspector, InspectionStatus::InCorrection],
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
            && $inspection->hasAnyResponsibilityForUser($user, ...\App\Enums\InspectionResponsibility::cases());
    }
}
