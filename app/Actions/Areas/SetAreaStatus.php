<?php

namespace App\Actions\Areas;

use App\Enums\RegistrationStatus;
use App\Models\Area;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SetAreaStatus
{
    public function handle(Area $area, RegistrationStatus $status): Area
    {
        if ($status === RegistrationStatus::Active && ! $area->unit?->isOperationallyActive()) {
            throw ValidationException::withMessages([
                'status' => 'Nao e possivel ativar a area com a unidade pai inativa.',
            ]);
        }

        return DB::transaction(function () use ($area, $status): Area {
            $area->update([
                'status' => $status,
            ]);

            return $area->refresh();
        });
    }
}
