<?php

declare(strict_types=1);

namespace App\Services\Inspections;

use App\Models\Inspection;
use App\Services\Reports\BuildInspectionClassificationSummary;
use App\Support\TextNormalizer;
use Illuminate\Validation\ValidationException;

final class InspectionClassificationM2CoverageValidator
{
    public function __construct(private readonly BuildInspectionClassificationSummary $summary) {}

    public function validate(Inspection $inspection): void
    {
        $summary = $this->summary->build($inspection);
        $pending = collect($summary['categories'])
            ->flatMap(fn (array $category) => collect($category['rows'])
                ->filter(fn (array $row): bool => $row['defect_count'] > 0 && blank($row['sap_m2_number']))
                ->map(fn (array $row): string => $category['code'].' '.$row['classification_code']))
            ->values();
        $invalid = collect($summary['categories'])
            ->flatMap(fn (array $category) => collect($category['rows'])
                ->filter(fn (array $row): bool => $row['defect_count'] > 0
                    && filled($row['sap_m2_number'])
                    && mb_strlen(TextNormalizer::text($row['sap_m2_number'])) !== 8)
                ->map(fn (array $row): string => $category['code'].' '.$row['classification_code']))
            ->values();

        $messages = [];
        if ($pending->isNotEmpty()) {
            $messages[] = 'Preencha as Notas M2 das classificações: '.$pending->implode(', ').'.';
        }
        if ($invalid->isNotEmpty()) {
            $messages[] = 'Corrija as Notas M2 para exatamente 8 caracteres nas classificações: '.$invalid->implode(', ').'.';
        }
        foreach ($summary['special_assessment_rows'] as $row) {
            $missing = collect(['service' => 'Serviço', 'priority' => 'Prioridade', 'note' => 'Nota'])
                ->filter(fn (string $label, string $field): bool => blank($row[$field]))->values();
            if ($missing->isNotEmpty()) {
                $messages[] = 'Preencha '.$missing->implode(', ').' na tratativa especial '.$row['code'].'.';
            }
        }
        if ($messages === []) {
            return;
        }

        throw ValidationException::withMessages([
            'inspection' => implode(' ', $messages),
        ]);
    }
}
