<?php

namespace App\Actions\Inspections;

use App\Actions\Inspections\Concerns\ValidatesInspectionAssignment;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Models\InspectionResponsible;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RemoveInspectionResponsible
{
    use ValidatesInspectionAssignment;

    public function handle(InspectionResponsible $responsible, User $actor): void
    {
        $organizationId = $this->validateTenant($responsible->inspection, $actor);
        if (! $responsible->belongsToOrganization($organizationId)) {
            throw ValidationException::withMessages(['responsible' => 'A atribuição não pertence à organização atual.']);
        }
        if (! $responsible->user->belongsToOrganization($organizationId) || $responsible->user->isSuperAdmin()) {
            throw ValidationException::withMessages(['responsible' => 'O usuário atribuído não pertence à organização atual.']);
        }

        DB::transaction(function () use ($responsible): void {
            $inspection = $responsible->inspection()->lockForUpdate()->firstOrFail();
            $assignments = InspectionResponsible::query()
                ->where('inspection_id', $inspection->getKey())
                ->where('responsibility', $responsible->responsibility->value)
                ->lockForUpdate();
            $assignments->get();

            if ($inspection->status->isFinal()) {
                throw ValidationException::withMessages(['responsible' => 'Responsáveis de uma inspeção finalizada não podem ser removidos.']);
            }

            if ($this->isRequired($responsible->responsibility, $inspection->status) && (clone $assignments)->count() === 1) {
                throw ValidationException::withMessages(['responsible' => 'O único responsável exigido para a etapa iniciada não pode ser removido.']);
            }

            (clone $assignments)->whereKey($responsible->getKey())->delete();
        });
    }

    private function isRequired(InspectionResponsibility $role, InspectionStatus $status): bool
    {
        $required = match ($status) {
            InspectionStatus::Planned => [],
            InspectionStatus::InProgress => [InspectionResponsibility::Inspector],
            InspectionStatus::AwaitingReview, InspectionStatus::InCorrection => [
                InspectionResponsibility::Inspector, InspectionResponsibility::Preparer, InspectionResponsibility::Reviewer,
            ],
            InspectionStatus::AwaitingApproval, InspectionStatus::Approved => [
                InspectionResponsibility::Inspector, InspectionResponsibility::Preparer,
                InspectionResponsibility::Reviewer, InspectionResponsibility::Approver,
            ],
            InspectionStatus::ReportGenerated => InspectionResponsibility::cases(),
            InspectionStatus::Released, InspectionStatus::Canceled => [],
        };

        return in_array($role, $required, true);
    }
}
