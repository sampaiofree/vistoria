<?php

namespace App\Actions\Inspections;

use App\Actions\Inspections\Concerns\ValidatesInspectionAssignment;
use App\Enums\InspectionResponsibility;
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

        return DB::transaction(function () use ($inspection, $user, $responsibility, $actor, $isPrimary, $completedAt, $organizationId): InspectionResponsible {
            Inspection::query()->whereKey($inspection->getKey())->lockForUpdate()->firstOrFail();
            $assignments = InspectionResponsible::query()
                ->where('inspection_id', $inspection->getKey())
                ->where('responsibility', $responsibility->value)
                ->lockForUpdate();

            if ((clone $assignments)->where('user_id', $user->getKey())->exists()) {
                throw ValidationException::withMessages(['user' => 'O usuário já possui esta responsabilidade na inspeção.']);
            }

            if ($isPrimary) {
                (clone $assignments)->where('is_primary', true)->update(['is_primary' => false]);
            }

            return InspectionResponsible::query()->create([
                'organization_id' => $organizationId,
                'inspection_id' => $inspection->getKey(),
                'user_id' => $user->getKey(),
                'responsibility' => $responsibility,
                'is_primary' => $isPrimary,
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
