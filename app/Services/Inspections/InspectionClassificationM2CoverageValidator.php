<?php

declare(strict_types=1);

namespace App\Services\Inspections;

use App\Models\Inspection;
use App\Services\Reports\BuildInspectionClassificationSummary;
use Illuminate\Validation\ValidationException;

final class InspectionClassificationM2CoverageValidator
{
    public function __construct(private readonly BuildInspectionClassificationSummary $summary) {}

    public function validate(Inspection $inspection): void
    {
        $pending = collect($this->summary->build($inspection)['categories'])
            ->flatMap(fn (array $category) => collect($category['rows'])
                ->filter(fn (array $row): bool => $row['defect_count'] > 0 && blank($row['sap_m2_number']))
                ->map(fn (array $row): string => $category['code'].' '.$row['classification_code']))
            ->values();

        if ($pending->isEmpty()) {
            return;
        }

        throw ValidationException::withMessages([
            'inspection' => 'Preencha as Notas M2 das classificações: '.$pending->implode(', ').'.',
        ]);
    }
}
