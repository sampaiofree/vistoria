<?php

declare(strict_types=1);

namespace App\Services\Inspections;

use App\Models\Inspection;
use Illuminate\Validation\ValidationException;

final class InspectionTechnicalReferencesCoverageValidator
{
    public function validate(Inspection $inspection): void
    {
        $missing = [];

        if (blank($inspection->general_drawing)) {
            $missing[] = 'DESENHO GERAL';
        }

        if (blank($inspection->procedure_number)) {
            $missing[] = 'PROC. INSPEÇÃO';
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'inspection' => 'Preencha '.implode(' e ', $missing).' na Visão Geral antes de avançar.',
            ]);
        }
    }
}
