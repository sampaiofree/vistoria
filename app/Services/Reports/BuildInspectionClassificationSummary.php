<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\DefectAssessmentStatus;
use App\Enums\DefectAssessmentClassificationMethod;
use App\Enums\DefectCategory;
use App\Models\DefectAssessment;
use App\Models\Inspection;
use App\Services\InspectionLocations\InspectionLocationPhotoNumbering;
use App\Services\Classification\NativeDefectCatalog;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class BuildInspectionClassificationSummary
{
    public function __construct(private readonly InspectionLocationPhotoNumbering $photoNumbering) {}

    /** @return array<string, mixed> */
    public function build(Inspection $inspection): array
    {
        $inspection->load(['classificationM2Links.note', 'specialAssessmentNotes']);
        $assessments = app(\App\Services\Defects\InspectionAssessmentResolver::class)->query($inspection)
            ->with('defect:id,category')
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
        ])->map(function (DefectCategory $category) use ($assessments, $inspection, $links): array {
            $categoryAssessments = $assessments->filter(
                fn (DefectAssessment $assessment): bool => $assessment->defect->category === $category,
            );
            $rows = self::classifications($category)->map(function (array $definition) use ($categoryAssessments, $inspection, $links, $category): array {
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
                    'color' => $definition['color'],
                    'priority' => $priority,
                    'defect_count' => $group->count(),
                    'm2_due_date' => $group->isEmpty()
                        ? null
                        : ($inspection->reinspection_scope_version === null
                            ? $this->m2DueDate($inspection->inspected_on, $category, $definition['code'])
                            : $group->map(fn (DefectAssessment $assessment): ?CarbonInterface => app(\App\Services\Defects\InspectionAssessmentResolver::class)
                                ->dueDate($inspection, $assessment))->filter()->sortBy(fn (CarbonInterface $date): int => $date->getTimestamp())->first()?->format('d/m/Y')),
                    'quantity' => $quantity,
                    'sap_m2_number' => $group->isEmpty() ? null : $link?->note?->sap_number,
                ];
            })->all();
            $present = collect($rows)->filter(fn (array $row): bool => $row['defect_count'] > 0);
            $categoryQuantity = $this->quantity($category, $categoryAssessments);

            return [
                'code' => $category->value,
                'name' => $category->label(),
                'report_name' => $category === DefectCategory::RoofCladding
                    ? 'TELHADOS E TAPAMENTOS LATERAIS'
                    : $category->label(),
                'unit' => $this->displayUnit($category),
                'rows' => $rows,
                'report_rows' => $this->reportRows($category, $rows),
                'most_critical' => $present->sortBy('priority')->first()['classification_code'] ?? null,
                'most_critical_color' => $present->sortBy('priority')->first()['color'] ?? null,
                'total' => $categoryQuantity,
                'report_total' => $category === DefectCategory::RoofCladding
                    ? $this->emptyQuantity()
                    : $categoryQuantity,
            ];
        })->all();
        $equipment = data_get($inspection->context_snapshot, 'equipment', []);
        $criticality = $this->criticality($assessments);
        $reportAnnexPlan = $this->reportAnnexPlan($inspection);

        return [
            'header' => [
                'area' => $this->displayValue($equipment['area_name'] ?? null),
                'subarea' => $this->displayValue($equipment['subarea_name'] ?? null),
                'installation_location' => $this->displayValue($equipment['installation_location'] ?? null),
                'abc_code' => $this->displayValue($equipment['abc_code'] ?? null),
                'inspection_date' => $inspection->inspected_on?->format('d/m/Y') ?? '—',
                'inspection_date_input' => $inspection->inspected_on?->toDateString(),
                'equipment' => null,
                'tag' => $this->displayValue($equipment['tag'] ?? null),
                'work_order' => $this->displayValue($inspection->service_order),
                'general_drawing' => $inspection->general_drawing,
                'procedure_number' => $inspection->procedure_number,
                'criticality' => $criticality['label'] ?? null,
                'criticality_color' => $criticality['color'] ?? null,
            ],
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
            'special_assessment_rows' => $this->specialAssessmentRows($inspection, $reportAnnexPlan),
            'report_annex_plan' => $reportAnnexPlan,
        ];
    }

    /**
     * @param array{overview:string,category_letters:array<string,string>,quantity_letters:array<string,string>,solidary_structures:?string} $reportAnnexPlan
     * @return list<array{assessment_id:int,assessment_public_id:string,code:string,photos:string,service:?string,priority:?string,note:?string}>
     */
    private function specialAssessmentRows(Inspection $inspection, array $reportAnnexPlan): array
    {
        $notes = $inspection->specialAssessmentNotes->keyBy('defect_assessment_id');
        $assessments = app(\App\Services\Defects\InspectionAssessmentResolver::class)->query($inspection)
            ->where('status', DefectAssessmentStatus::Complete->value)
            ->with(['defect:id,category,code,sequence_number', 'photos'])
            ->get()
            ->filter(fn (DefectAssessment $assessment): bool => ! $assessment->condition->isCanceled())
            ->filter(fn (DefectAssessment $assessment): bool => $assessment->is_unsafe_condition
                || $assessment->classification_method === DefectAssessmentClassificationMethod::EngineeringNote
                || $assessment->defect->category === DefectCategory::SolidaryStructures)
            ->sortBy(fn (DefectAssessment $assessment): array => [
                (int) $assessment->defect->sequence_number,
                (int) $assessment->id,
            ])
            ->values();
        $numbering = $this->photoNumbering->buildForReport($inspection);

        return $assessments->map(function (DefectAssessment $assessment) use ($notes, $numbering, $reportAnnexPlan): array {
            $category = $assessment->defect->category;
            $letter = $category === DefectCategory::SolidaryStructures
                ? $reportAnnexPlan['solidary_structures']
                : ($reportAnnexPlan['category_letters'][$category->value] ?? null);
            $numbers = $this->photoNumbering->numbersForAssessment($assessment, $numbering);
            $interval = $this->photoNumbering->format($numbers);
            $manual = $notes->get($assessment->id);

            return [
                'assessment_id' => $assessment->id,
                'assessment_public_id' => $assessment->public_id,
                'code' => $assessment->defect->code,
                'photos' => $letter === null || $interval === '—' ? '—' : "ANEXO {$letter} - {$interval}",
                'service' => $manual?->service,
                'priority' => $manual?->priority,
                'note' => $manual?->note,
            ];
        })->all();
    }

    /**
     * Mirrors the report-preview annex order, so workspace references never use a separate convention.
     *
     * @return array{overview:string,category_letters:array<string,string>,quantity_letters:array<string,string>,solidary_structures:?string}
     */
    private function reportAnnexPlan(Inspection $inspection): array
    {
        $completed = app(\App\Services\Defects\InspectionAssessmentResolver::class)->query($inspection)
            ->where('status', DefectAssessmentStatus::Complete->value)
            ->with(['defect:id,category', 'quantities', 'location', 'locationMapVersion'])
            ->get();
        $reportable = $completed->filter(fn (DefectAssessment $assessment): bool => ! $assessment->condition->isCanceled()
            && ($assessment->defect->category === DefectCategory::SolidaryStructures
                || ($assessment->locationMapVersion?->isReady() && $assessment->location?->isConfirmed())));
        $categoryLetters = [];
        $quantityLetters = [];
        $nextLetter = 2;

        if ($this->hasReportCategory($reportable, DefectCategory::AnticorrosiveTreatment)) {
            $categoryLetters[DefectCategory::AnticorrosiveTreatment->value] = 'A';
        }

        foreach ([DefectCategory::StructuralRecovery, DefectCategory::Civil, DefectCategory::RoofCladding] as $category) {
            if (! $this->hasReportCategory($reportable, $category)) {
                continue;
            }

            $categoryLetters[$category->value] = $this->annexLetter($nextLetter++);

            if (in_array($category, [DefectCategory::StructuralRecovery, DefectCategory::Civil], true)
                && $completed->contains(fn (DefectAssessment $assessment): bool => $assessment->defect->category === $category
                    && $assessment->quantities->isNotEmpty())) {
                $quantityLetters[$category->value] = $this->annexLetter($nextLetter++);
            }
        }

        return [
            'overview' => 'A',
            'category_letters' => $categoryLetters,
            'quantity_letters' => $quantityLetters,
            'solidary_structures' => $this->hasReportCategory($reportable, DefectCategory::SolidaryStructures)
                ? $this->annexLetter($nextLetter)
                : null,
        ];
    }

    /** @param Collection<int, DefectAssessment> $assessments */
    private function hasReportCategory(Collection $assessments, DefectCategory $category): bool
    {
        return $assessments->contains(
            fn (DefectAssessment $assessment): bool => $assessment->defect->category === $category,
        );
    }

    private function annexLetter(int $number): string
    {
        $letter = '';

        while ($number > 0) {
            $number--;
            $letter = chr(65 + ($number % 26)).$letter;
            $number = intdiv($number, 26);
        }

        return $letter;
    }

    /**
     * TAC is considered only when the inspection has no classified CV, REC, or TEL assessment.
     *
     * @param Collection<int, DefectAssessment> $assessments
     * @return array{label:string,color:string}|null
     */
    private function criticality(Collection $assessments): ?array
    {
        $candidates = $assessments
            ->map(function (DefectAssessment $assessment): ?array {
                $priority = $assessment->classification_priority
                    ?? data_get($assessment->classification_snapshot, 'severity_rank');

                if (! in_array($priority, [1, 2, 3, 4, 5], true)) {
                    return null;
                }

                return [
                    'category' => $assessment->defect->category,
                    'priority' => $priority,
                ];
            })
            ->filter();

        $nonTac = $candidates->reject(
            fn (array $candidate): bool => $candidate['category'] === DefectCategory::AnticorrosiveTreatment,
        );
        $priority = ($nonTac->isNotEmpty() ? $nonTac : $candidates)->min('priority');

        $label = match ($priority) {
            1 => 'Grave',
            2 => 'Alta',
            3 => 'Média',
            4 => 'Baixa',
            5 => 'Muito Baixa',
            default => null,
        };
        $classification = NativeDefectCatalog::classifications(DefectCategory::Civil)
            ->first(fn ($classification): bool => $classification->severity_rank === $priority);

        return $label === null || $classification === null
            ? null
            : ['label' => $label, 'color' => $classification->color];
    }

    private function m2DueDate(?CarbonInterface $inspectionDate, DefectCategory $category, string $classificationCode): ?string
    {
        return AssessmentTreatmentDueDate::forClassification($inspectionDate, $category, $classificationCode)?->format('d/m/Y');
    }

    /** @return Collection<int, array{code:string,name:string,color:string,severity_rank:int}> */
    private static function classifications(DefectCategory $category): Collection
    {
        return \App\Services\Classification\NativeDefectCatalog::classifications($category)
            ->map(fn ($definition): array => [
                'code' => $definition->code,
                'name' => $definition->name,
                'color' => $definition->color,
                'severity_rank' => $definition->severity_rank,
            ]);
    }

    /** @param Collection<int, DefectAssessment> $assessments @return array{value:?float,display:string,label:string,unit:?string} */
    private function quantity(DefectCategory $category, Collection $assessments): array
    {
        if ($category === DefectCategory::RoofCladding) return $this->emptyQuantity();

        $sum = $assessments->reduce(function (BigDecimal $total, DefectAssessment $assessment): BigDecimal {
            $value = data_get($assessment->quantity_snapshot, 'total');

            return is_numeric($value) ? $total->plus(BigDecimal::of((string) $value)) : $total;
        }, BigDecimal::zero());
        $hasQuantity = $assessments->contains(fn (DefectAssessment $assessment): bool => is_numeric(data_get($assessment->quantity_snapshot, 'total')));
        if (! $hasQuantity) return $this->emptyQuantity();

        $value = (float) (string) $sum;
        $unit = $this->displayUnit($category);
        $display = number_format($value, 2, ',', '.');

        return [
            'value' => $value,
            'display' => $display,
            'label' => $display.($unit === null ? '' : ' '.$unit),
            'unit' => $unit,
        ];
    }

    /** @param list<array<string, mixed>> $rows @return list<array<string, mixed>> */
    private function reportRows(DefectCategory $category, array $rows): array
    {
        $zeroCode = match ($category) {
            DefectCategory::StructuralRecovery => 'IE-0',
            DefectCategory::Civil => 'CV-0',
            DefectCategory::RoofCladding => 'TE-0',
            default => null,
        };

        if ($zeroCode === null) return $rows;

        return [[
            'category' => $category->value,
            'classification_code' => $zeroCode,
            'classification_label' => null,
            'color' => '#000000',
            'priority' => null,
            'defect_count' => 0,
            'm2_due_date' => null,
            'quantity' => $this->emptyQuantity(),
            'sap_m2_number' => null,
            'is_placeholder' => true,
        ], ...$rows];
    }

    /** @return array{value:null,display:string,label:string,unit:null} */
    private function emptyQuantity(): array
    {
        return ['value' => null, 'display' => '—', 'label' => '—', 'unit' => null];
    }

    private function displayUnit(DefectCategory $category): ?string
    {
        return match ($category) {
            DefectCategory::AnticorrosiveTreatment => 'm²',
            DefectCategory::StructuralRecovery => 'kg',
            DefectCategory::Civil => 'm³',
            DefectCategory::RoofCladding => null,
        };
    }

    private function displayValue(mixed $value): string
    {
        return is_string($value) && trim($value) !== '' ? $value : '—';
    }
}
