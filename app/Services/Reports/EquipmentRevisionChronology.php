<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Models\Equipment;
use App\Models\Inspection;
use Illuminate\Support\Collection;

/** Builds the persisted report-revision history for equipment and reports. */
final class EquipmentRevisionChronology
{
    /** @return Collection<int, array<string, mixed>> */
    public function forEquipment(Equipment $equipment): Collection
    {
        $equipment->loadMissing(['inspections.responsibles.user']);
        $currentInspection = $equipment->inspections->first(fn (Inspection $inspection): bool => $inspection->status->isOpen());

        return $equipment->inspections
            ->map(function (Inspection $inspection) use ($currentInspection): array {
                $entry = $this->systemEntry($inspection);
                $entry['is_current'] = $currentInspection !== null && $inspection->public_id === $currentInspection->public_id;

                return $entry;
            })
            ->sortByDesc(fn (array $entry): array => [$entry['revision_number'] ?? -1, $entry['source_id']])
            ->values();
    }

    /** @return array{rows:array<int, array<string,mixed>>, current:?array<string,mixed>, density:string} */
    public function forInspectionReport(Inspection $inspection): array
    {
        $inspection->loadMissing(['equipment.inspections.responsibles.user', 'responsibles.user']);
        $currentKey = 'system:'.$inspection->public_id;
        $previous = $inspection->equipment->inspections
            ->filter(fn (Inspection $entry): bool => $entry->status === InspectionStatus::Released && $entry->public_id !== $inspection->public_id)
            ->map(fn (Inspection $entry): array => $this->systemEntry($entry))
            ->sortByDesc(fn (array $entry): array => [$entry['revision_number'] ?? -1, $entry['source_id']])
            ->values();

        $current = $this->systemEntry($inspection);
        $current['is_current'] = true;
        $current['date'] = $inspection->report_date?->format('d/m/Y');
        $current['date_label'] = 'Data do relatório';
        $current['date_is_provisional'] = false;

        $rows = $previous->prepend($current)->map(function (array $entry) use ($currentKey): array {
            $entry['is_current'] = $entry['key'] === $currentKey;
            $entry['compact_responsibles'] = collect($entry['responsibles'])
                ->mapWithKeys(fn (?string $name, string $role): array => [$role => $this->initials($name)])
                ->all();
            $entry['full_responsibles'] = collect($entry['responsibles'])
                ->mapWithKeys(fn (?string $name, string $role): array => [$role => $this->withoutSuffix($name)])
                ->all();

            return $entry;
        })->values();

        return [
            'rows' => $rows->all(),
            'current' => $rows->firstWhere('is_current', true),
            'density' => $rows->count() <= 4 ? 'normal' : ($rows->count() <= 8 ? 'compact' : 'dense'),
        ];
    }

    /** @return array<string,mixed> */
    private function systemEntry(Inspection $inspection): array
    {
        $date = $inspection->report_date ?? $inspection->inspected_on ?? $inspection->planned_start_on ?? $inspection->created_at;
        $revision = $inspection->report_revision;

        return [
            'key' => 'system:'.$inspection->public_id,
            'source' => 'system',
            'source_label' => 'Sistema',
            'source_id' => $inspection->id,
            'public_id' => $inspection->public_id,
            'inspection_id' => $inspection->public_id,
            'inspection_number' => $inspection->number,
            'revision_number' => $revision,
            'description' => $revision === null ? 'Sem revisão' : ($revision === 0 ? 'Inspeção' : 'Reinspeção'),
            'status' => $inspection->status->value,
            'status_label' => $inspection->status->label(),
            'date' => $date?->format('d/m/Y'),
            'date_label' => $inspection->report_date !== null ? 'Data do relatório' : ($inspection->inspected_on !== null ? 'Data da inspeção' : 'Data operacional'),
            'date_is_provisional' => $inspection->report_date === null,
            'emission_type' => $inspection->emission_type?->value,
            'emission_type_label' => $inspection->emission_type?->label(),
            'responsibles' => $this->inspectionResponsibleNames($inspection),
            'is_current' => false,
            'show_url' => route('inspections.show', $inspection),
        ];
    }

    /** @return array{preparer:?string,reviewer:?string,approver:?string,releaser:?string} */
    private function inspectionResponsibleNames(Inspection $inspection): array
    {
        $responsibles = $inspection->responsibles->groupBy(fn ($responsible): string => $responsible->responsibility->value);

        return collect(InspectionResponsibility::cases())->mapWithKeys(function (InspectionResponsibility $responsibility) use ($responsibles): array {
            $responsible = $responsibles->get($responsibility->value, collect())->firstWhere('is_primary', true)
                ?? $responsibles->get($responsibility->value, collect())->first();

            return [$responsibility->value => $responsible?->user?->name];
        })->all();
    }

    private function withoutSuffix(?string $name): ?string
    {
        return $name === null ? null : trim((string) preg_replace('/\s*—.*$/u', '', $name));
    }

    private function initials(?string $name): ?string
    {
        $name = $this->withoutSuffix($name);
        if ($name === null || $name === '') return null;

        return collect(preg_split('/\s+/u', $name) ?: [])->filter()
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    }
}
