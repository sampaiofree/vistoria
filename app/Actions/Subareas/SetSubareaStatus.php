<?php

namespace App\Actions\Subareas;

use App\Enums\RegistrationStatus;
use App\Models\Subarea;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SetSubareaStatus
{
    public function handle(Subarea $subarea, RegistrationStatus $status): Subarea
    {
        if ($status === RegistrationStatus::Active && ! $subarea->area?->isOperationallyActive()) {
            throw ValidationException::withMessages([
                'status' => 'Nao e possivel ativar a subarea com a area pai inativa.',
            ]);
        }

        return DB::transaction(function () use ($subarea, $status): Subarea {
            $subarea->update([
                'status' => $status,
            ]);

            return $subarea->refresh();
        });
    }
}
