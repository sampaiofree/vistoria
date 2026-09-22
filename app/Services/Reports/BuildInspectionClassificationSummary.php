<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\DefectAssessmentStatus;
use App\Enums\DefectCategory;
use App\Models\DefectAssessment;
use App\Models\Inspection;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;

final class BuildInspectionClassificationSummary
{
    /** @return array<string, mixed> */
    public function build(Inspection $inspection): array
    {
        $inspection->load('classificationM2Links.note');
        $assessments = DefectAssessment::query()
            ->forOrganization($inspection->organization_id)
            ->with('defect:id,category')
            ->where('inspection_id', $inspection->id)
            ->where('status', DefectAssessmentStatus::Complete->value)
            ->whereNotNull('classification_code')
            ->get();
        $links = $inspection->classificationM2Links->keyBy(
            fn ($link): string => $link->category.'|'.$link->classification_code,
        );

        $categories = collect([
            DefectCategory::AnticorrosiveTreatment,
            DefectCategory::StructuralRecovery,
            DefectCategory::Civil,
            DefectCategory::RoofCladding,
        ])->map(function (DefectCategory $category) use ($assessments, $links): array {
            $categoryAssessments = $assessments->filter(
                fn (DefectAssessment $assessment): bool => $assessment->defect->category === $category,
            );
            $rows = self::classifications($category)->map(function (array $definition) use ($categoryAssessments, $links, $category): array {
                $group = $categoryAssessments->filter(
                    fn (DefectAssessment $assessment): bool => $assessment->classification_code === $definition['code'],
                );
                $quantity = $this->quantity($category, $group);
                $link = $links->get($category->value.'|'.$definition['code']);
                $firstAssessment = $group->first();
                $priority = $group->map(fn (DefectAssessment $assessment): ?int => $assessment->classification_priority
                    ?? data_get($assessment->classification_snapshot, 'severity_rank'))
                    ->filter(fn (?int $value): bool => $value !== null)
                    ->min() ?? $definition['severity_rank'];

                return [
                    'category' => $category->value,
                    'classification_code' => $definition['code'],
                    'classification_label' => data_get($firstAssessment?->classification_snapshot, 'name', $definition['name']),
                    'priority' => $priority,
                    'defect_count' => $group->count(),
                    'quantity' => $quantity,
                    'sap_m2_number' => $link?->note?->sap_number,
                ];
            })->all();
            $present = collect($rows)->filter(fn (array $row): bool => $row['defect_count'] > 0);
            $categoryQuantity = $this->quantity($category, $categoryAssessments);

            return [
                'code' => $category->value,
                'name' => $category->label(),
                'unit' => $this->displayUnit($category),
                'rows' => $rows,
                'most_critical' => $present->sortBy('priority')->first()['classification_code'] ?? null,
                'total' => $categoryQuantity,
            ];
        })->all();
        $equipment = data_get($inspection->context_snapshot, 'equipment', []);

        return [
            'equipment' => [
                'area' => $equipment['area_name'] ?? '—',
                'subarea' => $equipment['subarea_name'] ?? '—',
                'installation_location' => $equipment['installation_location'] ?? '—',
                'abc_code' => $equipment['abc_code'] ?? '—',
                'inspected_on' => $inspection->inspected_on?->format('d/m/Y') ?? '—',
                'name' => $equipment['name'] ?? '—',
                'tag' => $equipment['tag'] ?? '—',
                'work_order' => $inspection->service_order ?? '—',
                'inspection_procedure' => $inspection->procedure_number ?? '—',
            ],
            'categories' => $categories,
        ];
    }

    /** @return Collection<int, array{code:string,name:string,severity_rank:int}> */
    private static function classifications(DefectCategory $category): Collection
    {
        return \App\Services\Classification\NativeDefectCatalog::classifications($category)
            ->map(fn ($definition): array => [
                'code' => $definition->code,
                'name' => $definition->name,
                'severity_rank' => $definition->severity_rank,
            ]);
    }

    /** @param Collection<int, DefectAssessment> $assessments @return array{value:?float,label:string} */
    private function quantity(DefectCategory $category, Collection $assessments): array
    {
        if ($category === DefectCategory::RoofCladding) return ['value' => null, 'label' => '—'];

        $sum = $assessments->reduce(function (BigDecimal $total, DefectAssessment $assessment): BigDecimal {
            $value = data_get($assessment->quantity_snapshot, 'total');

            return is_numeric($value) ? $total->plus(BigDecimal::of((string) $value)) : $total;
        }, BigDecimal::zero());
        $hasQuantity = $assessments->contains(fn (DefectAssessment $assessment): bool => is_numeric(data_get($assessment->quantity_snapshot, 'total')));
        if (! $hasQuantity) return ['value' => null, 'label' => '—'];

        $value = (float) (string) $sum;
        $unit = $this->displayUnit($category);

        return ['value' => $value, 'label' => number_format($value, 2, ',', '.').($unit === null ? '' : ' '.$unit)];
    }

    private function displayUnit(DefectCategory $category): ?string
    {
        return match ($category) {
            DefectCategory::AnticorrosiveTreatment => 'm²',
            DefectCategory::StructuralRecovery => 'kg',
            DefectCategory::Civil, DefectCategory::RoofCladding => null,
        };
    }
}
