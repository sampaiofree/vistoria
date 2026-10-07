<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectAssessmentStatus;
use App\Enums\DefectCategory;
use App\Models\DefectAssessment;
use App\Models\Inspection;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;

final class BuildInspectionQuantitativeWorksheet
{
    /** @return array<string, mixed> */
    public function build(Inspection $inspection): array
    {
        $rows = app(\App\Services\Defects\InspectionAssessmentResolver::class)->query($inspection)
            ->where('status', DefectAssessmentStatus::Complete->value)
            ->whereIn('condition', [
                DefectAssessmentCondition::New->value,
                DefectAssessmentCondition::Reinspected->value,
                DefectAssessmentCondition::Reclassified->value,
            ])
            ->with(['defect', 'quantities'])
            ->get()
            ->filter(fn (DefectAssessment $assessment): bool => in_array($this->category($assessment), [
                DefectCategory::Civil,
                DefectCategory::StructuralRecovery,
                DefectCategory::RoofCladding,
            ], true))
            ->sortBy(fn (DefectAssessment $assessment): array => [
                (int) (data_get($assessment->defect_snapshot, 'defect.sequence_number') ?? $assessment->defect->sequence_number),
                $assessment->id,
            ])
            ->values()
            ->map(fn (DefectAssessment $assessment, int $index): array => $this->row($inspection, $assessment, $index + 1))
            ->all();

        return [
            'title' => 'QUANTITATIVO PADRÃO DE AVARIAS - INTEGRIDADE ESTRUTURAL',
            'header' => [
                'tag' => $this->cell(data_get($inspection->context_snapshot, 'equipment.tag')),
                'report' => $this->cell(null),
                'installation_location' => $this->cell(data_get($inspection->context_snapshot, 'equipment.installation_location')),
                'inspection_date' => $this->dateCell($inspection->inspected_on),
                'environment' => $this->cell(null),
                'mandator' => $this->cell(null),
                'row_count' => $this->cell(count($rows)),
            ],
            'header_rows' => [
                ['label' => 'TAG:', 'key' => 'tag', 'secondary_label' => 'Ambiente', 'secondary_key' => 'environment'],
                ['label' => 'RELATÓRIO:', 'key' => 'report', 'secondary_label' => 'Mandante', 'secondary_key' => 'mandator'],
                ['label' => 'LOCAL INSTALAÇÃO:', 'key' => 'installation_location', 'secondary_label' => 'Linhas', 'secondary_key' => 'row_count'],
                ['label' => 'DATA DA INSPEÇÃO:', 'key' => 'inspection_date', 'secondary_label' => '', 'secondary_key' => null],
            ],
            'columns' => $this->columns(),
            'theme' => [
                'header_background' => '#0E2841',
                'header_color' => '#FFFFFF',
                'border' => '#0E2841',
                'text' => '#111827',
            ],
            'rows' => $rows,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function columns(): array
    {
        return array_map(fn (array $column): array => array_combine(
            ['key', 'label', 'width', 'type', 'background'], $column,
        ), [
            ['line', 'LINHA', 13.140625, 'number', '#FFFFFF'],
            ['discipline', 'DISCIPLINA', 14, 'text', '#FFFFFF'],
            ['note', 'NOTA', 12.28515625, 'text', '#FFFFCC'],
            ['code', 'CÓD. AVARIA', 16.5703125, 'text', '#FFFFCC'],
            ['quantity', 'PESO/ÁREA', 28, 'quantity', '#D9D9D9'],
            ['impact', 'IMPACTO', 48.42578125, 'text', '#FFFFCC'],
            ['gravity', 'G', 8, 'number', '#FFFFCC'],
            ['urgency', 'U', 8, 'number', '#FFFFCC'],
            ['damage', 'DANO', 30.5703125, 'text', '#FFFFCC'],
            ['trend', 'T', 7.7109375, 'number', '#FFFFCC'],
            ['gut_score', 'PONT. G.U.T', 19.5703125, 'number', '#FFFFFF'],
            ['classification', 'CLASS. DO DANO', 19.5703125, 'text', '#FFFFFF'],
            ['due_date', 'DATA ESTIMADA EXECUÇÃO', 24.5703125, 'date', '#FFFFFF'],
            ['condition', 'STATUS DA AVARIA', 21.7109375, 'text', '#FFFFCC'],
        ]);
    }

    /** @return array<string, mixed> */
    private function row(Inspection $inspection, DefectAssessment $assessment, int $line): array
    {
        $category = $this->category($assessment);
        $engineeringNoteWithoutQuantity = $assessment->isEngineeringNoteWithoutQuantity();
        $usesGut = $category->requiresGut() && ! $engineeringNoteWithoutQuantity;
        $criteria = $usesGut ? (array) data_get($assessment->gut_snapshot, 'criteria', []) : [];
        $gravity = $usesGut ? (data_get($criteria, 'gravity.score') ?? $assessment->gravity) : null;
        $classification = $engineeringNoteWithoutQuantity || $category === DefectCategory::SolidaryStructures
            ? null
            : (data_get($assessment->classification_snapshot, 'code') ?? $assessment->classification_code);
        $score = $usesGut ? (data_get($assessment->gut_snapshot, 'score') ?? $assessment->gut_score) : null;
        $color = data_get($assessment->classification_snapshot, 'color');
        $color = $score !== null && is_string($color) && preg_match('/^#[0-9a-f]{6}$/i', $color) ? $color : null;
        $impact = $gravity !== null && in_array($category, [DefectCategory::StructuralRecovery, DefectCategory::Civil], true)
            ? GutGravityLegend::fromSnapshot((array) ($criteria['gravity'] ?? []))
            : null;

        return [
            'assessment_public_id' => $assessment->public_id,
            'category' => $category->value,
            'cells' => [
                'line' => $this->cell($line),
                'discipline' => $this->cell($category === DefectCategory::Civil ? 'CIVIL' : $category->value),
                'note' => $this->cell(null),
                'code' => $this->cell(data_get($assessment->defect_snapshot, 'defect.code') ?? $assessment->defect->code),
                'quantity' => $engineeringNoteWithoutQuantity
                    ? [...$this->cell(null), 'unit' => null]
                    : $this->quantityCell($assessment, $category),
                'impact' => $this->cell($impact),
                'gravity' => $this->cell($gravity),
                'urgency' => $this->cell($usesGut ? (data_get($criteria, 'urgency.score') ?? $assessment->urgency) : null),
                'damage' => $this->cell(data_get($criteria, 'trend.group.label')),
                'trend' => $this->cell($usesGut ? (data_get($criteria, 'trend.score') ?? $assessment->trend) : null),
                'gut_score' => [...$this->cell($score), 'background' => $color, 'color' => $this->contrast($color)],
                'classification' => [...$this->cell($classification), 'background' => $color, 'color' => $this->contrast($color)],
                'due_date' => $this->dateCell(app(\App\Services\Defects\InspectionAssessmentResolver::class)->dueDate($inspection, $assessment)),
                'condition' => $this->cell(implode("\n", array_filter([
                    $assessment->condition->label(),
                    app(\App\Services\Defects\InspectionAssessmentResolver::class)->historicalLabel($inspection, $assessment),
                ]))),
            ],
        ];
    }

    private function category(DefectAssessment $assessment): DefectCategory
    {
        $code = (string) data_get($assessment->defect_snapshot, 'defect.category');

        return $code === 'CIVIL' ? DefectCategory::Civil : (DefectCategory::tryFrom($code) ?? $assessment->defect->category);
    }

    /** @return array{value: mixed, display: string} */
    private function cell(mixed $value): array
    {
        return ['value' => $value, 'display' => $value === null ? '' : (string) $value];
    }

    /** @return array{value: ?string, display: string} */
    private function dateCell(?CarbonInterface $date): array
    {
        return ['value' => $date?->toDateString(), 'display' => $date?->format('d/m/Y') ?? ''];
    }

    /** @return array<string, mixed> */
    private function quantityCell(DefectAssessment $assessment, DefectCategory $category): array
    {
        $value = data_get($assessment->quantity_snapshot, 'total');
        $unit = match ($category) {
            DefectCategory::StructuralRecovery => 'kg',
            DefectCategory::AnticorrosiveTreatment => 'm²',
            DefectCategory::Civil => 'm³',
            default => null,
        };

        if (! is_numeric($value) || $unit === null) {
            return [...$this->cell(null), 'unit' => null];
        }

        $decimal = (string) BigDecimal::of((string) $value)->toScale(2, RoundingMode::HalfUp);
        [$whole, $fraction] = explode('.', $decimal);
        $display = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole).','.$fraction.' '.$unit;

        return ['value' => (string) $value, 'display' => $display, 'unit' => $unit];
    }

    private function contrast(?string $background): ?string
    {
        if ($background === null) {
            return null;
        }

        $brightness = hexdec(substr($background, 1, 2)) * 0.299
            + hexdec(substr($background, 3, 2)) * 0.587
            + hexdec(substr($background, 5, 2)) * 0.114;

        return $brightness < 150 ? '#FFFFFF' : '#111827';
    }
}
