<?php

declare(strict_types=1);

namespace App\Services\Defects;

use App\Models\Inspection;
use Illuminate\Validation\ValidationException;

final class ReinspectionCoverageValidator
{
    public function __construct(private readonly BuildReinspectionChecklist $checklist) {}

    public function validate(Inspection $inspection): void
    {
        if ($inspection->previous_inspection_id === null) {
            return;
        }

        $data = $this->checklist->handle($inspection);
        $pending = $data['pending'];

        if ($pending === 0) {
            return;
        }

        throw ValidationException::withMessages([
            'inspection' => sprintf('Ainda existem %d avaria(s) anterior(es) sem avaliação completa.', $pending),
        ]);
    }
}
