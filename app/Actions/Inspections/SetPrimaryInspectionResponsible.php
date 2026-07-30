<?php

namespace App\Actions\Inspections;

use App\Actions\Inspections\Concerns\ValidatesInspectionAssignment;
use App\Models\InspectionResponsible;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SetPrimaryInspectionResponsible
{
    use ValidatesInspectionAssignment;

    public function handle(InspectionResponsible $responsible, User $actor): InspectionResponsible
    {
        $organizationId = $this->validateTenant($responsible->inspection, $actor);

        if (! $responsible->belongsToOrganization($organizationId)) {
            throw ValidationException::withMessages(['responsible' => 'A atribuição não pertence à organização atual.']);
        }
        $this->validateUser($responsible->user, $organizationId);

        if ($responsible->inspection->status->isFinal()) {
            throw ValidationException::withMessages(['responsible' => 'Responsáveis não podem ser alterados em inspeções liberadas ou canceladas.']);
        }

        return DB::transaction(function () use ($responsible): InspectionResponsible {
            $assignments = InspectionResponsible::query()
                ->where('inspection_id', $responsible->inspection_id)
                ->where('responsibility', $responsible->responsibility->value)
                ->lockForUpdate();
            $assignments->get();
            (clone $assignments)->where('is_primary', true)->update(['is_primary' => false]);
            (clone $assignments)->whereKey($responsible->getKey())->update(['is_primary' => true]);

            return $responsible->refresh();
        });
    }
}
