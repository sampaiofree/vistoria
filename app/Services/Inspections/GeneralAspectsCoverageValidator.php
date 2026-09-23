<?php

declare(strict_types=1);

namespace App\Services\Inspections;

use App\Models\Inspection;
use App\Services\Reports\GeneralAspectsDocument;
use Illuminate\Validation\ValidationException;

final class GeneralAspectsCoverageValidator
{
    public function __construct(private readonly GeneralAspectsDocument $documents) {}

    public function validate(Inspection $inspection): void
    {
        if ($this->documents->fromStored($inspection->general_notes) !== null) {
            return;
        }

        throw ValidationException::withMessages([
            'inspection' => 'Preencha os Aspectos gerais do equipamento antes de enviar para revisão.',
        ]);
    }
}
