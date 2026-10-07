<?php

declare(strict_types=1);

namespace App\Services\Inspections;

use App\Models\Inspection;
use Illuminate\Validation\ValidationException;

final class ReportEquipmentCoverageValidator
{
    public function validate(Inspection $inspection): void
    {
        if (blank($inspection->report_equipment_name)) {
            throw ValidationException::withMessages([
                'inspection' => 'Preencha EQUIPAMENTO em Dados do relatório na Visão Geral antes de avançar.',
            ]);
        }
    }
}
