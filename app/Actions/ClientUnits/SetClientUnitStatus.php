<?php

namespace App\Actions\ClientUnits;

use App\Enums\RegistrationStatus;
use App\Models\ClientUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SetClientUnitStatus
{
    public function handle(ClientUnit $unit, RegistrationStatus $status): ClientUnit
    {
        if ($status === RegistrationStatus::Active && ! $unit->client?->isActive()) {
            throw ValidationException::withMessages([
                'status' => 'Nao e possivel ativar a unidade com o cliente pai inativo.',
            ]);
        }

        return DB::transaction(function () use ($unit, $status): ClientUnit {
            $unit->update([
                'status' => $status,
            ]);

            return $unit->refresh();
        });
    }
}
