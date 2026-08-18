<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\EquipmentRevisionEmissionType;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Models\Equipment;
use App\Models\EquipmentRevision;
use App\Models\Inspection;
use Illuminate\Support\Collection;

/**
 * Builds the official revision chronology shared by equipment views and
 * report covers. Manual records and released system inspections intentionally
 * use the same stable ordering rules.
 */
final class EquipmentRevisionChronology
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forEquipment(Equipment $equipment, bool $includeNonOfficial = true): Collection
    {
        $equipment->loadMissing([
            'revisions.preparer',
            'revisions.reviewer',
            'revisions.approver',
            'revisions.releaser',
            'inspections.responsibles.user',
        ]);

        $entries = collect();

        foreach ($equipment->revisions as $revision) {
            $entries->push($this->manualEntry($revision));
        }

        foreach ($equipment->inspections as $inspection) {
            $entries->push($this->systemEntry($inspection));
        }

        $currentInspection = $equipment->inspections->first(
            fn (Inspection $inspection): bool => $inspection->status->isOpen(),
        );
        $entries = $entries->map(function (array $entry) use ($currentInspection): array {
            $entry['is_current'] = $currentInspection !== null
                && $entry['source'] === 'system'
                && $entry['public_id'] === $currentInspection->public_id;

            return $entry;
        });

        $official = $entries
            ->filter(fn (array $entry): bool => $entry['published'])
            ->sortBy(fn (array $entry): array => $this->sortKey($entry))
            ->values();

        $numbers = $official
            ->mapWithKeys(fn (array $entry, int $index): array => [$entry['key'] => $index + 1])
            ->all();

        return $entries
            ->when(! $includeNonOfficial, fn (Collection $items): Collection => $items->filter(fn (array $entry): bool => $entry['published']))
            ->map(function (array $entry) use ($numbers): array {
                $entry['revision_number'] = $numbers[$entry['key']] ?? null;

                if ($entry['revision_number'] !== null) {
                    $entry['description'] = $entry['revision_number'] === 1 ? 'Inspeção' : 'Reinspeção';
                }

                return $entry;
            })
            ->sortByDesc(fn (array $entry): array => $this->sortKey($entry))
            ->values();
    }

    /**
     * Returns the cover rows in visual order (current first), with the current
     * inspection appended as the next revision even before it is released.
     *
     * @return array{rows:array<int, array<string,mixed>>, current:?array<string,mixed>, density:string}
     */
    public function forInspectionReport(Inspection $inspection): array
    {
        $inspection->loadMissing([
            'equipment',
            'responsibles.user',
        ]);

        $all = $this->forEquipment($inspection->equipment, true);
        $currentKey = 'system:'.$inspection->public_id;
        $current = $this->systemEntry($inspection);
        // The cover only exposes the official report date for the current
        // document; operational scheduling dates must not leak into it.
        $current['sort_date'] = $inspection->report_date?->toDateString();
        $current['date'] = $inspection->report_date?->format('d/m/Y');
        $current['date_label'] = 'Data do relatório';
        $current['date_is_provisional'] = false;
        $currentSort = $this->sortKey($current);
        if (! $current['published'] && $inspection->report_date !== null) {
            // A preview is the next document even when another historical
            // record shares its date; the current row is rendered last in
            // chronological order and then shown first on the cover.
            $currentSort = [$current['sort_date'], PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX];
        }

        $previous = $all
            ->filter(function (array $entry) use ($currentKey, $currentSort, $inspection): bool {
                if ($entry['key'] === $currentKey) {
                    return false;
                }

                if (! $entry['published']) {
                    return false;
                }

                return $inspection->report_date === null
                    || $this->sortKey($entry) < $currentSort;
            })
            ->sortBy(fn (array $entry): array => $this->sortKey($entry))
            ->values()
            ->all();

        $rows = collect($previous)
            ->push($current)
            ->values()
            ->map(function (array $entry, int $index) use ($currentKey): array {
                $entry['revision_number'] = $index + 1;
                $entry['description'] = $entry['revision_number'] === 1 ? 'Inspeção' : 'Reinspeção';
                $entry['is_current'] = $entry['key'] === $currentKey;
                $entry['compact_responsibles'] = collect($entry['responsibles'])
                    ->mapWithKeys(fn (?string $name, string $role): array => [$role => $this->initials($name)])
                    ->all();
                $entry['full_responsibles'] = collect($entry['responsibles'])
                    ->mapWithKeys(fn (?string $name, string $role): array => [$role => $this->withoutSuffix($name)])
                    ->all();

                return $entry;
            })
            ->reverse()
            ->values();

        $currentRow = $rows->first(fn (array $entry): bool => $entry['is_current']);

        return [
            'rows' => $rows->all(),
            'current' => $currentRow,
            'density' => $rows->count() <= 4 ? 'normal' : ($rows->count() <= 8 ? 'compact' : 'dense'),
        ];
    }

    /** @return array<int, array{value:string,label:string}> */
    public function emissionLegend(): array
    {
        return EquipmentRevisionEmissionType::options();
    }

    /** @return array<string,mixed> */
    private function manualEntry(EquipmentRevision $revision): array
    {
        return [
            'key' => 'manual:'.$revision->public_id,
            'source' => 'manual',
            'source_label' => 'Manual',
            'published' => true,
            'sort_date' => $revision->revision_date?->toDateString(),
            'sort_created_at' => $revision->created_at?->getTimestamp() ?? 0,
            'source_order' => 0,
            'source_id' => $revision->id,
            'public_id' => $revision->public_id,
            'inspection_id' => null,
            'inspection_number' => null,
            'status' => null,
            'status_label' => null,
            'date' => $revision->revision_date?->format('d/m/Y'),
            'date_label' => 'Data da revisão',
            'date_is_provisional' => false,
            'emission_type' => $revision->emission_type?->value,
            'emission_type_label' => $revision->emission_type?->label(),
            'responsibles' => [
                'preparer' => $revision->preparer_name,
                'reviewer' => $revision->reviewer_name,
                'approver' => $revision->approver_name,
                'releaser' => $revision->releaser_name,
            ],
            'is_current' => false,
            'show_url' => null,
            'revision_date_input' => $revision->revision_date?->toDateString(),
            'preparer_id' => $revision->preparer_id,
            'reviewer_id' => $revision->reviewer_id,
            'approver_id' => $revision->approver_id,
            'releaser_id' => $revision->releaser_id,
            'update_url' => route('equipment-revisions.update', $revision),
            'destroy_url' => route('equipment-revisions.destroy', $revision),
        ];
    }

    /** @return array<string,mixed> */
    private function systemEntry(Inspection $inspection): array
    {
        $official = $inspection->status === InspectionStatus::Released
            && $inspection->report_date !== null;

        $date = $inspection->report_date
            ?? $inspection->inspected_on
            ?? $inspection->scheduled_for
            ?? $inspection->created_at;

        return [
            'key' => 'system:'.$inspection->public_id,
            'source' => 'system',
            'source_label' => 'Sistema',
            'published' => $official,
            'sort_date' => $date?->toDateString(),
            'sort_created_at' => $inspection->created_at?->getTimestamp() ?? 0,
            'source_order' => 1,
            'source_id' => $inspection->id,
            'public_id' => $inspection->public_id,
            'inspection_id' => $inspection->public_id,
            'inspection_number' => $inspection->number,
            'status' => $inspection->status->value,
            'status_label' => $inspection->status->label(),
            'date' => $date?->format('d/m/Y'),
            'date_label' => $inspection->report_date !== null
                ? 'Data do relatório'
                : ($inspection->inspected_on !== null ? 'Data da inspeção' : 'Data operacional'),
            'date_is_provisional' => $inspection->report_date === null,
            'emission_type' => $inspection->emission_type?->value,
            'emission_type_label' => $inspection->emission_type?->label(),
            'responsibles' => $this->inspectionResponsibleNames($inspection),
            'is_current' => false,
            'show_url' => route('inspections.show', $inspection),
            'revision_date_input' => null,
            'preparer_id' => null,
            'reviewer_id' => null,
            'approver_id' => null,
            'releaser_id' => null,
            'update_url' => null,
            'destroy_url' => null,
        ];
    }

    /** @return array{preparer:?string,reviewer:?string,approver:?string,releaser:?string} */
    private function inspectionResponsibleNames(Inspection $inspection): array
    {
        $responsibles = $inspection->responsibles->groupBy(
            fn ($responsible): string => $responsible->responsibility->value,
        );

        return collect(InspectionResponsibility::cases())
            ->mapWithKeys(function (InspectionResponsibility $responsibility) use ($responsibles): array {
                $responsible = $responsibles->get($responsibility->value, collect())
                    ->firstWhere('is_primary', true)
                    ?? $responsibles->get($responsibility->value, collect())->first();

                return [$responsibility->value => $responsible?->user?->name];
            })
            ->all();
    }

    /** @param array<string,mixed> $entry */
    private function sortKey(array $entry): array
    {
        return [
            $entry['sort_date'] ?? '9999-12-31',
            (int) ($entry['sort_created_at'] ?? 0),
            (int) ($entry['source_order'] ?? 0),
            (int) ($entry['source_id'] ?? 0),
        ];
    }

    private function withoutSuffix(?string $name): ?string
    {
        return $name === null ? null : trim((string) preg_replace('/\s*—.*$/u', '', $name));
    }

    private function initials(?string $name): ?string
    {
        $name = $this->withoutSuffix($name);
        if (blank($name)) {
            return null;
        }

        $particles = ['a', 'as', 'o', 'os', 'e', 'da', 'das', 'de', 'do', 'dos'];
        $words = preg_split('/\s+/u', trim($name)) ?: [];
        $words = array_values(array_filter($words, fn (string $word): bool => ! in_array(mb_strtolower($word), $particles, true)));
        $initials = collect($words)->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');

        return mb_substr($initials, 0, 5);
    }
}
