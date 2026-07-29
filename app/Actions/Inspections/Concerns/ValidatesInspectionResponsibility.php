<?php

declare(strict_types=1);

namespace App\Actions\Inspections\Concerns;

use App\Enums\InspectionResponsibility;
use App\Models\Inspection;
use App\Models\User;
use Illuminate\Validation\ValidationException;

trait ValidatesInspectionResponsibility
{
    private function requireResponsible(Inspection $inspection, InspectionResponsibility $responsibility, ?User $actor = null): void
    {
        if (! $inspection->hasActiveResponsible($responsibility, $actor)) {
            throw ValidationException::withMessages([
                'responsibility' => "É necessário um {$responsibility->value} ativo atribuído à inspeção.",
            ]);
        }
    }
}
