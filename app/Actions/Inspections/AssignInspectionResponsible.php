<?php

namespace App\Actions\Inspections;

use App\Actions\Inspections\Concerns\ValidatesInspectionAssignment;
use App\Enums\InspectionResponsibility;
use App\Enums\OperationalRole;
use App\Enums\UserAccountType;
use App\Models\Inspection;
use App\Models\InspectionResponsible;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AssignInspectionResponsible
{
    use ValidatesInspectionAssignment;

    public function handle(
        Inspection $inspection,
        User $user,
        InspectionResponsibility|string $responsibility,
        User $actor,
        bool $isPrimary = false,
        ?DateTimeInterface $completedAt = null,
    ): InspectionResponsible {
        $organizationId = $this->validateTenant($inspection, $actor);
        $this->validateUser($user, $organizationId);
        $responsibility = $this->responsibility($responsibility);

        if ($inspection->status->isFinal()) {
            throw ValidationException::withMessages(['inspection' => 'Responsáveis não podem ser alterados em inspeções liberadas ou canceladas.']);
        }

        return DB::transaction(function () use ($inspection, $user, $responsibility, $actor, $completedAt, $organizationId): InspectionResponsible {
            $inspection = Inspection::query()->whereKey($inspection->getKey())->lockForUpdate()->firstOrFail();
            $user->refresh();
            $this->validateUser($user, $organizationId);
            if ($inspection->status->isFinal()) {
                throw ValidationException::withMessages(['inspection' => 'Responsáveis não podem ser alterados em inspeções liberadas ou canceladas.']);
            }
            $requiredRole = match ($responsibility) {
                InspectionResponsibility::Approver => OperationalRole::Reviewer,
                InspectionResponsibility::Releaser => OperationalRole::Releaser,
                default => null,
            };
            if ($requiredRole !== null && ($user->operational_role !== $requiredRole
                || ! in_array($user->account_type, [UserAccountType::Member, UserAccountType::CompanyAdmin], true))) {
                throw ValidationException::withMessages(['user_id' => 'Selecione um usuário operacional com papel de '.$requiredRole->label().'.']);
            }
            $assignments = InspectionResponsible::query()
                ->where('inspection_id', $inspection->getKey())
                ->where('responsibility', $responsibility->value)
                ->lockForUpdate();

            (clone $assignments)->get();
            (clone $assignments)->delete();

            return InspectionResponsible::query()->create([
                'organization_id' => $organizationId,
                'inspection_id' => $inspection->getKey(),
                'user_id' => $user->getKey(),
                'responsibility' => $responsibility,
                'is_primary' => true,
                'assigned_by' => $actor->getKey(),
                'assigned_at' => now(),
                'completed_at' => $completedAt,
            ]);
        });
    }

    private function responsibility(InspectionResponsibility|string $responsibility): InspectionResponsibility
    {
        if ($responsibility instanceof InspectionResponsibility) {
            return $responsibility;
        }

        return InspectionResponsibility::tryFrom($responsibility)
            ?? throw ValidationException::withMessages(['responsibility' => 'A responsabilidade informada é inválida.']);
    }
}
