<?php

declare(strict_types=1);

namespace App\Services\Inspections;

use App\Models\Inspection;
use App\Models\InspectionStatusHistory;
use App\Models\User;
use Carbon\CarbonInterface;

final class RecordClassificationChange
{
    public function record(Inspection $inspection, User $actor, string $section, array $before, array $after): void
    {
        $normalize = fn ($value) => $value instanceof CarbonInterface ? $value->toDateString() : $value;
        $before = array_map($normalize, $before);
        $after = array_map($normalize, $after);
        if ($before === $after) {
            return;
        }

        InspectionStatusHistory::query()->create([
            'organization_id' => $inspection->organization_id,
            'inspection_id' => $inspection->id,
            'from_status' => $inspection->status,
            'to_status' => $inspection->status,
            'changed_by' => $actor->id,
            'reason' => 'Classificação/M2: '.$section.' atualizado.',
            'metadata' => ['event' => 'classification_updated', 'section' => $section, 'before' => $before, 'after' => $after, 'changes' => $this->changes($inspection, $before, $after)],
            'created_at' => now(),
        ]);
    }

    private function changes(Inspection $inspection, array $before, array $after): array
    {
        $codes = app(\App\Services\Defects\InspectionAssessmentResolver::class)->query($inspection)->with('defect')->get()
            ->mapWithKeys(fn ($assessment) => [$assessment->id => $assessment->defect->code]);
        $flatten = function (array $values) use ($codes): array {
            $fields = [];
            foreach (['general_drawing' => 'Desenho geral', 'procedure_number' => 'Procedimento', 'inspected_on' => 'Data da inspeção'] as $key => $label) {
                if (array_key_exists($key, $values)) {
                    $fields[$label] = $values[$key];
                }
            }
            foreach ($values['links'] ?? [] as $row) {
                $fields['Nota M2 '.$row['category'].' '.$row['classification_code']] = $row['sap_number'];
            }
            foreach ($values['special_rows'] ?? [] as $row) {
                foreach (['service' => 'Serviço', 'priority' => 'Prioridade', 'note' => 'Nota'] as $key => $label) {
                    $fields[$label.' — '.($codes[$row['defect_assessment_id']] ?? 'Avaria '.$row['defect_assessment_id'])] = $row[$key];
                }
            }

            return $fields;
        };
        $old = $flatten($before);
        $new = $flatten($after);
        $changes = [];
        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $label) {
            if (($old[$label] ?? null) !== ($new[$label] ?? null)) {
                $changes[] = ['label' => $label, 'before' => $old[$label] ?? null, 'after' => $new[$label] ?? null];
            }
        }

        return $changes;
    }

    public function notes(Inspection $inspection): array
    {
        return [
            'links' => $inspection->classificationM2Links()->with('note')->orderBy('category')->orderBy('classification_code')->get()
                ->map(fn ($link) => ['category' => $link->category, 'classification_code' => $link->classification_code, 'sap_number' => $link->note?->sap_number])->all(),
            'special_rows' => $inspection->specialAssessmentNotes()->orderBy('defect_assessment_id')->get()
                ->map(fn ($note) => $note->only(['defect_assessment_id', 'service', 'priority', 'note']))->all(),
        ];
    }
}
