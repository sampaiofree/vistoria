<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectAssessmentStatus;
use App\Enums\DefectStatus;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use App\Services\Demo\ViewFirstCivilScenario;

/**
 * Read model for the View First CIVIL demonstration.
 *
 * GUT/CV, characterization, quantities and photographic evidence are fed from
 * a single structured civil scenario while the product keeps one read model.
 */
final class ViewFirstDemoPresenter
{
    public const REPORT_REVISION = ViewFirstCivilScenario::REPORT_REVISION;

    /**
     * @return array{criticality: null|array{value:string, label:string, is_provisional:bool}}
     */
    public function equipment(Equipment $equipment): array
    {
        $equipment->loadMissing('defects');

        if ($equipment->defects->isEmpty()) {
            return ['criticality' => null];
        }

        $classification = $equipment->defects
            ->map(fn (Defect $defect): array => $this->technicalData($defect)['classification'])
            ->sortBy(fn (array $item): int => $this->criticalityRank($item['code']))
            ->first() ?? $this->classification(null);

        return [
            'criticality' => [
                'value' => $classification['code'],
                'label' => $classification['label'],
                'is_provisional' => true,
            ],
        ];
    }

    /**
     * @return array{completed:int, total:int, percentage:int}
     */
    public function progress(Inspection $inspection): array
    {
        $defects = $this->defectsForInspection($inspection);
        $total = $defects->count();
        $completed = $defects
            ->filter(function (Defect $defect) use ($inspection): bool {
                $assessment = $defect->assessments
                    ->firstWhere('inspection_id', $inspection->getKey());

                return $assessment?->status === DefectAssessmentStatus::Complete;
            })
            ->count();

        return [
            'completed' => $completed,
            'total' => $total,
            'percentage' => $total === 0 ? 0 : (int) round(($completed / $total) * 100),
        ];
    }

    /**
     * @param  array<string, mixed>  $inspectionPayload
     * @return array<string, mixed>
     */
    public function inspection(
        Inspection $inspection,
        User $user,
        array $inspectionPayload,
        string $activeTab,
    ): array {
        $inspection->loadMissing('organization');

        $defects = $this->defectsForInspection($inspection);

        $items = $defects
            ->map(fn (Defect $defect): array => $this->defectCard($inspection, $defect, $user))
            ->all();

        $summary = $this->summary($items);
        $photos = $this->photos($items);

        return [
            'inspection' => array_merge(
                $inspectionPayload,
                ['defects' => $items],
                $this->inspectionLinks($inspection),
            ),
            'summary' => $summary,
            'tabs' => $this->tabs($inspection, $summary, count($photos)),
            'active_tab' => $activeTab,
            'content' => $this->content(
                $activeTab,
                $inspection,
                $items,
                $photos,
                $summary,
                $inspectionPayload,
            ),
            'demo' => $this->demoMetadata(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function assessment(DefectAssessment $assessment, User $user): array
    {
        $assessment->loadMissing([
            'defect.equipment.client',
            'defect.equipment.unit',
            'inspection.equipment.defects.assessments.inspection',
            'inspection.equipment.defects.assessments.creator',
            'inspection.equipment.defects.firstInspection',
            'previousAssessment.inspection',
            'previousAssessment.creator',
            'creator',
        ]);

        $technical = $this->technicalData($assessment->defect);
        $items = $this->defectsForInspection($assessment->inspection);
        $position = $items->search(
            fn (Defect $defect): bool => $defect->getKey() === $assessment->defect_id,
        );

        $previousUrl = $this->adjacentAssessmentUrl($items, $position, -1, $assessment->inspection);
        $nextUrl = $this->adjacentAssessmentUrl($items, $position, 1, $assessment->inspection);
        $canUpdate = $user->can('update', $assessment);
        $canComplete = $user->can('complete', $assessment);

        return [
            'assessment' => $this->assessmentPayload($assessment, true),
            'previous_assessment' => $assessment->previousAssessment === null
                ? null
                : $this->assessmentPayload($assessment->previousAssessment),
            'classification' => $technical['classification'],
            'gut' => $technical['gut'],
            'characterization' => $technical['characterization'],
            'quantities' => $technical['quantities'],
            'quantity_summary' => $technical['quantity_summary'] ?? null,
            'discipline' => $technical['discipline'] ?? ViewFirstCivilScenario::DISCIPLINE,
            'discipline_label' => $technical['discipline_label'] ?? ViewFirstCivilScenario::DISCIPLINE_LABEL,
            'classification_family' => $technical['classification_family'] ?? ViewFirstCivilScenario::CLASSIFICATION_FAMILY,
            'unit' => $technical['unit'] ?? ViewFirstCivilScenario::UNIT,
            'project' => $technical['project'] ?? null,
            'drawing' => $technical['drawing'] ?? ViewFirstCivilScenario::DRAWING,
            'item' => $technical['item'] ?? null,
            'element' => $technical['element'] ?? null,
            'manifestation' => $technical['manifestation'] ?? null,
            'impact' => $technical['impact'] ?? null,
            'photo_interval' => $technical['photo_interval'] ?? null,
            'occurrence' => $technical['occurrence'] ?? null,
            'evidence' => $this->evidenceForDefect($assessment->defect, $technical, $assessment),
            'assessment_navigation' => [
                'previous_url' => $previousUrl,
                'next_url' => $nextUrl,
                'inspection_url' => route('inspections.show', $assessment->inspection),
                'defects_url' => route('inspections.defects', $assessment->inspection),
                'position' => is_int($position) ? $position + 1 : 1,
                'total' => $items->count(),
            ],
            'condition_options' => collect(DefectAssessmentCondition::options())
                ->when(
                    $assessment->inspection_id !== $assessment->defect->first_inspection_id,
                    fn (Collection $options): Collection => $options
                        ->reject(fn (array $option): bool => $option['value'] === DefectAssessmentCondition::New->value),
                )
                ->values()
                ->all(),
            'capabilities' => [
                'update' => $canUpdate,
                'complete' => $canComplete,
                'update_url' => $canUpdate
                    ? route('defect-assessments.update', $assessment)
                    : null,
                'complete_url' => $canComplete
                    ? route('defect-assessments.complete', $assessment)
                    : null,
            ],
            'demo' => $this->demoMetadata(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function inspectionLinks(Inspection $inspection): array
    {
        return [
            'overview_url' => route('inspections.show', $inspection),
            'defects_url' => route('inspections.defects', $inspection),
            'locations_url' => route('inspections.locations', $inspection),
            'photos_url' => route('inspections.photos', $inspection),
            'documents_url' => route('inspections.documents', $inspection),
            'history_url' => route('inspections.history', $inspection),
            'report_url' => route('inspections.report-preview', $inspection),
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<int, array<string, mixed>>
     */
    private function tabs(Inspection $inspection, array $summary, int $photoCount): array
    {
        return [
            ['key' => 'overview', 'label' => 'Visão geral', 'url' => route('inspections.show', $inspection)],
            ['key' => 'defects', 'label' => 'Avarias', 'url' => route('inspections.defects', $inspection), 'count' => $summary['total']],
            ['key' => 'locations', 'label' => 'Localização', 'url' => route('inspections.locations', $inspection), 'count' => $summary['total']],
            ['key' => 'photos', 'label' => 'Fotografias', 'url' => route('inspections.photos', $inspection), 'count' => $photoCount],
            ['key' => 'documents', 'label' => 'Documentos', 'url' => route('inspections.documents', $inspection), 'count' => $inspection->referenceDocuments->count()],
            ['key' => 'history', 'label' => 'Histórico', 'url' => route('inspections.history', $inspection), 'count' => $inspection->statusHistories->count()],
            ['key' => 'report', 'label' => 'Relatório', 'url' => route('inspections.report-preview', $inspection)],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, array<string, mixed>>  $photos
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $inspectionPayload
     * @return array<string, mixed>
     */
    private function content(
        string $activeTab,
        Inspection $inspection,
        array $items,
        array $photos,
        array $summary,
        array $inspectionPayload,
    ): array {
        return match ($activeTab) {
            'defects' => [
                'items' => $items,
                'filters' => [
                    ['key' => 'all', 'label' => 'Todas', 'count' => $summary['total']],
                    ['key' => 'critical', 'label' => 'Críticas', 'count' => $summary['critical']],
                    ['key' => 'pending', 'label' => 'Pendentes', 'count' => $summary['pending']],
                    ['key' => 'repaired', 'label' => 'Reparadas', 'count' => $summary['repaired']],
                    ['key' => 'not_inspected', 'label' => 'Não inspecionadas', 'count' => $summary['not_inspected']],
                ],
            ],
            'photos' => [
                'items' => $photos,
                'counts' => collect($photos)
                    ->countBy('status')
                    ->all(),
            ],
            'locations' => [
                'items' => $this->locations($items),
                'legend' => collect($items)
                    ->pluck('classification')
                    ->unique('code')
                    ->sortBy(fn (array $classification): int => $this->criticalityRank($classification['code']))
                    ->map(fn (array $classification): array => [
                        'code' => $classification['code'],
                        'label' => $classification['label'],
                    ])
                    ->values()
                    ->all(),
            ],
            'documents' => [
                'items' => $inspectionPayload['reference_documents'] ?? [],
                'reference_document_ids' => $inspectionPayload['reference_document_ids'] ?? [],
                'empty_message' => 'Nenhum documento técnico foi vinculado a esta inspeção.',
            ],
            'history' => [
                'items' => $inspectionPayload['history'] ?? [],
                'previous_inspection' => $inspectionPayload['previous_inspection'] ?? null,
                'next_inspections' => $inspectionPayload['next_inspections'] ?? [],
            ],
            'report' => $this->report($inspection, $items, $photos, $summary, $inspectionPayload),
            default => [
                'metrics' => [
                    ['key' => 'progress', 'label' => 'Avaliações concluídas', 'value' => sprintf('%d/%d', $summary['completed'], $summary['total']), 'detail' => $summary['progress_percent'].'%'],
                    ['key' => 'criticality', 'label' => 'Criticidade atual', 'value' => $summary['criticality']['code'], 'detail' => $summary['criticality']['label']],
                    ['key' => 'critical', 'label' => 'Avarias críticas', 'value' => (string) $summary['critical'], 'detail' => 'prioridade técnica'],
                    ['key' => 'pending', 'label' => 'Pendências', 'value' => (string) $summary['pending'], 'detail' => 'avaliação em aberto'],
                ],
                'highlights' => collect($items)
                    ->filter(fn (array $item): bool => in_array($item['classification']['code'], ['CV-1', 'CV-2'], true) || $item['is_pending'])
                    ->take(3)
                    ->values()
                    ->all(),
                'primary_action' => [
                    'label' => $summary['pending'] > 0 ? 'Continuar avaliações' : 'Revisar avarias',
                    'url' => $this->firstPendingUrl($items) ?? route('inspections.defects', $inspection),
                ],
            ],
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function summary(array $items): array
    {
        $collection = collect($items);
        $total = count($items);
        $completed = $collection->where('assessment.status', DefectAssessmentStatus::Complete->value)->count();
        $criticality = $collection
            ->pluck('classification')
            ->sortBy(fn (array $classification): int => $this->criticalityRank($classification['code']))
            ->first() ?? $this->classification(null);

        $conditionBreakdown = $collection
            ->groupBy(fn (array $item): string => $item['assessment']['condition'] ?? 'pending')
            ->map(function (Collection $group, string $condition): array {
                $first = $group->first();

                return [
                    'key' => $condition,
                    'label' => $first['condition_label'] ?? 'Pendente',
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('count')
            ->values()
            ->all();

        $classificationBreakdown = $collection
            ->groupBy('classification.code')
            ->map(function (Collection $group, string $code): array {
                $first = $group->first();

                return [
                    'code' => $code,
                    'label' => $first['classification']['label'] ?? 'Não classificada',
                    'count' => $group->count(),
                    'historical_count' => $group
                        ->where('classification.historical', true)
                        ->count(),
                ];
            })
            ->sortBy(fn (array $item): int => $this->criticalityRank($item['code']))
            ->values()
            ->all();

        $quantityTotal = round(
            $collection->sum(fn (array $item): float => (float) ($item['quantity_summary']['total'] ?? 0)),
            2,
        );
        $exportableCollection = $collection
            ->reject(fn (array $item): bool => ($item['assessment']['status'] ?? null) === DefectAssessmentStatus::Draft->value)
            ->values();
        $exportableQuantityTotal = round(
            $exportableCollection->sum(fn (array $item): float => (float) ($item['quantity_summary']['total'] ?? 0)),
            2,
        );
        $photoTotal = $collection->sum(fn (array $item): int => count($item['photos'] ?? []));
        $exportablePhotoTotal = $exportableCollection->sum(fn (array $item): int => count($item['photos'] ?? []));
        $quantityByClass = $collection
            ->groupBy('classification.code')
            ->map(function (Collection $group): array {
                $first = $group->first();
                $unit = $first['quantity_summary']['unit'] ?? ViewFirstCivilScenario::UNIT;
                $total = round($group->sum(fn (array $item): float => (float) ($item['quantity_summary']['total'] ?? 0)), 2);

                return [
                    'code' => $first['classification']['code'] ?? '—',
                    'label' => $first['classification']['label'] ?? 'Não classificada',
                    'unit' => $unit,
                    'total' => $total,
                    'total_label' => $this->formatQuantity($total).' '.$unit,
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();

        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => $total - $completed,
            'progress_percent' => $total === 0 ? 0 : (int) round(($completed / $total) * 100),
            'critical' => $collection->whereIn('classification.code', ['CV-1', 'CV-2'])->count(),
            'repaired' => $collection->where('assessment.condition', DefectAssessmentCondition::Repaired->value)->count(),
            'not_inspected' => $collection->where('assessment.condition', DefectAssessmentCondition::NotInspected->value)->count(),
            'criticality' => $criticality,
            'condition_breakdown' => $conditionBreakdown,
            'classification_breakdown' => $classificationBreakdown,
            'draft_count' => $collection->where('assessment.status', DefectAssessmentStatus::Draft->value)->count(),
            'photo_total' => $photoTotal,
            'exportable_photo_total' => $exportablePhotoTotal,
            'quantity_total' => $quantityTotal,
            'quantity_total_label' => $this->formatQuantity($quantityTotal).' '.ViewFirstCivilScenario::UNIT,
            'quantity_total_unit' => ViewFirstCivilScenario::UNIT,
            'exportable_quantity_total' => $exportableQuantityTotal,
            'exportable_quantity_total_label' => $this->formatQuantity($exportableQuantityTotal).' '.ViewFirstCivilScenario::UNIT,
            'exportable_total' => $exportableCollection->count(),
            'quantity_by_class' => $quantityByClass,
            'by_condition' => $collection
                ->groupBy('assessment.condition')
                ->map->count()
                ->all(),
            'by_classification' => $collection
                ->groupBy('classification.code')
                ->map->count()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defectCard(Inspection $inspection, Defect $defect, User $user): array
    {
        $assessment = $defect->assessments
            ->firstWhere('inspection_id', $inspection->getKey());
        $technical = $this->technicalData($defect);
        $isPending = $assessment === null || $assessment->status === DefectAssessmentStatus::Draft;
        $canCreate = $assessment === null
            && $user->can('create', [DefectAssessment::class, $inspection, $defect]);

        return [
            'id' => $defect->id,
            'public_id' => $defect->public_id,
            'code' => $defect->code,
            'title' => $defect->title,
            'origin_description' => $defect->origin_description,
            'category' => $defect->category->value,
            'category_label' => $defect->category->label(),
            'status' => $defect->status->value,
            'status_label' => $defect->status->label(),
            'sequence_number' => (int) $defect->sequence_number,
            'assessment' => $assessment === null ? null : $this->assessmentPayload($assessment),
            'condition' => $assessment?->condition->value,
            'condition_label' => $assessment?->condition->label() ?? 'Pendente',
            'assessment_status' => $assessment?->status->value ?? 'not_assessed',
            'is_pending' => $isPending,
            'is_repaired' => $assessment?->condition === DefectAssessmentCondition::Repaired,
            'is_not_inspected' => $assessment?->condition === DefectAssessmentCondition::NotInspected,
            'classification' => $technical['classification'],
            'gut' => $technical['gut'],
            'characterization' => $technical['characterization'],
            'quantities' => $technical['quantities'],
            'quantity_summary' => $technical['quantity_summary'] ?? null,
            'discipline' => $technical['discipline'] ?? ViewFirstCivilScenario::DISCIPLINE,
            'discipline_label' => $technical['discipline_label'] ?? ViewFirstCivilScenario::DISCIPLINE_LABEL,
            'classification_family' => $technical['classification_family'] ?? ViewFirstCivilScenario::CLASSIFICATION_FAMILY,
            'unit' => $technical['unit'] ?? ViewFirstCivilScenario::UNIT,
            'project' => $technical['project'] ?? null,
            'drawing' => $technical['drawing'] ?? ViewFirstCivilScenario::DRAWING,
            'item' => $technical['item'] ?? null,
            'element' => $technical['element'] ?? null,
            'manifestation' => $technical['manifestation'] ?? null,
            'impact' => $technical['impact'] ?? null,
            'photo_interval' => $technical['photo_interval'] ?? null,
            'occurrence' => $technical['occurrence'] ?? null,
            'evidence' => $this->evidenceForDefect($defect, $technical, $assessment),
            'assessment_url' => $assessment === null
                ? null
                : route('defect-assessments.show', $assessment),
            'assessment_store_url' => $canCreate
                ? route('inspections.defects.assessments.store', [$inspection, $defect])
                : null,
            'show_url' => route('defects.show', $defect),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function assessmentPayload(DefectAssessment $assessment, bool $withDefect = false): array
    {
        $payload = [
            'id' => $assessment->id,
            'public_id' => $assessment->public_id,
            'condition' => $assessment->condition->value,
            'condition_label' => $assessment->condition->label(),
            'condition_requires_reason' => $assessment->condition->requiresReason(),
            'status' => $assessment->status->value,
            'status_label' => $assessment->status->label(),
            'location_description' => $assessment->location_description,
            'comment' => $assessment->comment,
            'recommendation' => $assessment->recommendation,
            'reason' => $assessment->reason,
            'internal_notes' => $assessment->internal_notes,
            'assessed_at' => $assessment->assessed_at?->format('d/m/Y H:i'),
            'assessed_at_iso' => $assessment->assessed_at?->toISOString(),
            'snapshot_version' => (int) $assessment->snapshot_version,
            'defect_snapshot' => $assessment->defect_snapshot ?? [],
            'show_url' => route('defect-assessments.show', $assessment),
            'inspection' => [
                'id' => $assessment->inspection->id,
                'public_id' => $assessment->inspection->public_id,
                'number' => $assessment->inspection->number,
                'status' => $assessment->inspection->status->value,
                'status_label' => $assessment->inspection->status->label(),
                'show_url' => route('inspections.show', $assessment->inspection),
            ],
            'creator' => $assessment->creator === null
                ? null
                : [
                    'id' => $assessment->creator->id,
                    'public_id' => $assessment->creator->public_id,
                    'name' => $assessment->creator->name,
                ],
        ];

        if ($withDefect) {
            $payload['defect'] = [
                'id' => $assessment->defect->id,
                'public_id' => $assessment->defect->public_id,
                'code' => $assessment->defect->code,
                'title' => $assessment->defect->title,
                'origin_description' => $assessment->defect->origin_description,
                'category' => $assessment->defect->category->value,
                'category_label' => $assessment->defect->category->label(),
                'status' => $assessment->defect->status->value,
                'status_label' => $assessment->defect->status->label(),
                'equipment' => [
                    'id' => $assessment->defect->equipment->id,
                    'public_id' => $assessment->defect->equipment->public_id,
                    'tag' => $assessment->defect->equipment->tag,
                    'name' => $assessment->defect->equipment->name,
                    'show_url' => route('equipments.show', $assessment->defect->equipment),
                ],
            ];
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function defectTechnicalData(Defect $defect): array
    {
        return $this->technicalData($defect);
    }

    /**
     * @return array<string, mixed>
     */
    private function structuredTechnicalData(Defect $defect, array $finding): array
    {
        $classification = $this->classification(
            $finding['classification_code'],
            $finding['classification_historical'] ?? false,
        );
        $classification['score_band'] = $finding['classification_score_band'];
        $classification['profile_version'] = ViewFirstCivilScenario::PROFILE_VERSION;
        $classification['historical'] = ($finding['current_condition'] ?? null) === DefectAssessmentCondition::Repaired->value;

        $gut = $finding['gut'];
        $score = (int) $finding['gut_score'];
        $quantityRows = $this->quantityPayloads($finding['quantities'], $finding['unit']);
        $quantityTotal = $finding['quantity_total'];
        $photos = $this->evidenceForFinding($defect, $finding);
        $photoInterval = sprintf('Fotos %02d a %02d', 1, count($photos));

        return [
            'discipline' => $finding['discipline'],
            'discipline_label' => $finding['discipline_label'],
            'classification_family' => $finding['classification_family'],
            'unit' => $finding['unit'],
            'project' => $finding['project'],
            'drawing' => $finding['drawing'],
            'item' => $finding['item'],
            'element' => $finding['element'],
            'manifestation' => $finding['manifestation'],
            'impact' => $finding['impact'],
            'classification' => $classification,
            'gut' => [
                'severity' => $gut[0],
                'urgency' => $gut[1],
                'tendency' => $gut[2],
                'score' => $score,
                'formula' => sprintf('%d×%d×%d = %d', $gut[0], $gut[1], $gut[2], $score),
                'provisional' => true,
                'profile_version' => ViewFirstCivilScenario::PROFILE_VERSION,
                'score_band' => $finding['classification_score_band'],
            ],
            'characterization' => collect([
                'Disciplina' => $finding['discipline_label'],
                'Projeto' => $finding['project'],
                'Desenho' => $finding['drawing'],
                'Item' => $finding['item'],
                'Elemento' => $finding['element'],
                'ManifestaÃ§Ã£o' => $finding['manifestation'],
                'Impacto' => $finding['impact']['label'] ?? '-',
                'LocalizaÃ§Ã£o' => $finding['current_location'],
            ])
                ->map(fn (string $value, string $label): array => compact('label', 'value'))
                ->values()
                ->all(),
            'quantities' => $quantityRows,
            'quantity_summary' => [
                'total' => $quantityTotal,
                'total_label' => $this->formatQuantity($quantityTotal).' '.$finding['unit'],
                'unit' => $finding['unit'],
                'line_count' => count($quantityRows),
            ],
            'photo_interval' => $photoInterval,
            'photo_status' => 'ready',
            'photos' => $photos,
            'occurrence' => [
                'sequence' => $finding['sequence'],
                'code' => $finding['code'],
                'title' => $finding['title'],
                'project' => $finding['project'],
                'drawing' => $finding['drawing'],
                'item' => $finding['item'],
                'element' => $finding['element'],
                'manifestation' => $finding['manifestation'],
                'impact' => $finding['impact'],
                'location' => $finding['current_location'],
                'photo_interval' => $photoInterval,
                'photo_count' => count($photos),
                'quantities' => $quantityRows,
                'quantity_summary' => [
                    'total' => $quantityTotal,
                    'total_label' => $this->formatQuantity($quantityTotal).' '.$finding['unit'],
                    'unit' => $finding['unit'],
                ],
                'classification' => $classification,
                'gut' => $gut,
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function quantityPayloads(array $rows, string $unit): array
    {
        return collect($rows)
            ->map(function (array $row) use ($unit): array {
                $unitVolume = round(($row['length'] ?? 0) * ($row['height'] ?? 0) * ($row['width'] ?? 0), 2);
                $totalVolume = round($unitVolume * ($row['quantity'] ?? 1), 2);

                return [
                    'label' => $row['label'] ?? 'Volume',
                    'length' => $row['length'] ?? 0,
                    'height' => $row['height'] ?? 0,
                    'width' => $row['width'] ?? 0,
                    'quantity' => $row['quantity'] ?? 1,
                    'unit_volume' => $unitVolume,
                    'total_volume' => $totalVolume,
                    'unit' => $unit,
                    'length_label' => $this->formatQuantity((float) ($row['length'] ?? 0)),
                    'height_label' => $this->formatQuantity((float) ($row['height'] ?? 0)),
                    'width_label' => $this->formatQuantity((float) ($row['width'] ?? 0)),
                    'unit_volume_label' => $this->formatQuantity($unitVolume).' '.$unit,
                    'total_volume_label' => $this->formatQuantity($totalVolume).' '.$unit,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function technicalData(Defect $defect): array
    {
        $finding = ViewFirstCivilScenario::findingBySequence((int) $defect->sequence_number);

        if ($finding !== null) {
            return $this->structuredTechnicalData($defect, $finding);
        }

        $title = Str::lower(Str::ascii($defect->title));

        $data = match (true) {
            Str::contains($title, 'fissura longitudinal') => [
                'cv' => 'CV-2', 'gut' => [3, 4, 3],
                'characterization' => ['Manifestação' => 'Fissura longitudinal', 'Elemento' => 'Pedestal de concreto', 'Abertura estimada' => '0,8 mm'],
                'quantities' => [['label' => 'Extensão', 'value' => '1,85', 'unit' => 'm'], ['label' => 'Abertura máxima', 'value' => '0,8', 'unit' => 'mm']],
                'photo_status' => 'ready',
            ],
            Str::contains($title, 'desplacamento') => [
                'cv' => 'CV-3', 'gut' => [3, 5, 2],
                'characterization' => ['Manifestação' => 'Desplacamento', 'Elemento' => 'Base do motor', 'Exposição' => 'Cobrimento comprometido'],
                'quantities' => [['label' => 'Área estimada', 'value' => '0,32', 'unit' => 'm²'], ['label' => 'Profundidade média', 'value' => '18', 'unit' => 'mm']],
                'photo_status' => 'processing',
            ],
            Str::contains($title, 'corrosao') => [
                'cv' => 'CV-3', 'gut' => [3, 4, 2],
                'characterization' => ['Manifestação' => 'Corrosão aparente', 'Elemento' => 'Chumbadores', 'Intensidade' => 'Moderada'],
                'quantities' => [['label' => 'Chumbadores afetados', 'value' => '4', 'unit' => 'un.']],
                'photo_status' => 'ready',
            ],
            Str::contains($title, 'selagem') => [
                'cv' => 'CV-4', 'gut' => [3, 4, 1],
                'characterization' => ['Manifestação' => 'Falha de selagem', 'Elemento' => 'Interface base/piso', 'Continuidade' => 'Descontínua'],
                'quantities' => [['label' => 'Extensão afetada', 'value' => '2,40', 'unit' => 'm']],
                'photo_status' => 'pending',
            ],
            Str::contains($title, 'umidade') => [
                'cv' => 'CV-5', 'gut' => [3, 1, 2],
                'characterization' => ['Manifestação' => 'Umidade superficial', 'Elemento' => 'Canaleta adjacente', 'Aspecto' => 'Sem percolação ativa'],
                'quantities' => [['label' => 'Área observada', 'value' => '0,75', 'unit' => 'm²']],
                'photo_status' => 'failed',
            ],
            Str::contains($title, 'fissura capilar') => [
                'cv' => 'CV-3', 'gut' => null,
                'characterization' => ['Manifestação' => 'Fissura capilar reparada', 'Elemento' => 'Bloco de fundação', 'Tratamento' => 'Selagem confirmada'],
                'quantities' => [['label' => 'Trecho reparado', 'value' => '0,95', 'unit' => 'm']],
                'photo_status' => 'ready',
                'historical' => true,
            ],
            Str::contains($title, 'sem acesso'), Str::contains($title, 'regiao posterior') => [
                'cv' => null, 'gut' => null,
                'characterization' => ['Situação' => 'Não inspecionada', 'Elemento' => 'Região posterior do pedestal', 'Restrição' => 'Acesso físico impedido'],
                'quantities' => [],
                'photo_status' => 'ready',
            ],
            default => [
                'cv' => 'CV-4', 'gut' => null,
                'characterization' => ['Categoria' => $defect->category->label(), 'Elemento' => 'Equipamento inspecionado'],
                'quantities' => [],
                'photo_status' => 'ready',
            ],
        };

        $gut = $data['gut'];

        return [
            'classification' => $this->classification($data['cv'], (bool) ($data['historical'] ?? false)),
            'gut' => $gut === null
                ? null
                : [
                    'severity' => $gut[0],
                    'urgency' => $gut[1],
                    'tendency' => $gut[2],
                    'score' => $gut[0] * $gut[1] * $gut[2],
                    'formula' => sprintf('%d×%d×%d = %d', $gut[0], $gut[1], $gut[2], $gut[0] * $gut[1] * $gut[2]),
                    'provisional' => true,
                ],
            'characterization' => collect($data['characterization'])
                ->map(fn (string $value, string $label): array => compact('label', 'value'))
                ->values()
                ->all(),
            'quantities' => $data['quantities'],
            'photo_status' => $data['photo_status'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function classification(?string $code, bool $historical = false): array
    {
        $labels = [
            'CV-1' => ['Crítica', 'critical', '75-125'],
            'CV-2' => ['Alta', 'danger', '36-73'],
            'CV-3' => ['Moderada', 'warning', '16-35'],
            'CV-4' => ['Baixa', 'info', '8-15'],
            'CV-5' => ['Mínima', 'success', '1-7'],
        ];

        if ($code === null) {
            return [
                'code' => '—',
                'label' => 'Não classificada',
                'tone' => 'neutral',
                'score_band' => null,
                'profile_version' => ViewFirstCivilScenario::PROFILE_VERSION,
                'provisional' => true,
                'historical' => $historical,
            ];
        }

        return [
            'code' => $code,
            'label' => $labels[$code][0] ?? 'Não classificada',
            'tone' => $labels[$code][1] ?? 'neutral',
            'score_band' => $labels[$code][2] ?? null,
            'profile_version' => ViewFirstCivilScenario::PROFILE_VERSION,
            'provisional' => true,
            'historical' => $historical,
        ];
    }

    /**
     * @param  array<string, mixed>  $finding
     * @return array<int, array<string, mixed>>
     */
    private function evidenceForFinding(Defect $defect, array $finding): array
    {
        return collect($finding['photos'] ?? [])
            ->values()
            ->map(function (array $photo, int $index) use ($defect, $finding): array {
                return [
                    'id' => sprintf('evidence-%s-%02d', $defect->public_id, $index + 1),
                    'defect_id' => $defect->id,
                    'status' => $photo['status'] ?? 'ready',
                    'status_label' => $this->photoStatusLabel($photo['status'] ?? 'ready'),
                    'title' => sprintf('%s — %s', $finding['code'], $photo['role']),
                    'caption' => $photo['caption'] ?? $finding['title'],
                    'location' => $finding['current_location'],
                    'role' => $photo['role'] ?? 'Detalhe',
                    'role_label' => $photo['role'] ?? 'Detalhe',
                    'illustrative' => true,
                    'url' => null,
                    'placeholder_variant' => match ($photo['visual_variant'] ?? 'concrete') {
                        'structure' => 'structure',
                        'surface' => 'surface',
                        'repair' => 'repair',
                        default => 'concrete',
                    },
                    'finding_sequence' => $finding['sequence'],
                    'finding_code' => $finding['code'],
                    'group_label' => sprintf('%s · %s', $finding['code'], $finding['title']),
                    'discipline' => $finding['discipline'],
                    'discipline_label' => $finding['discipline_label'],
                    'classification_family' => $finding['classification_family'],
                    'unit' => $finding['unit'],
                    'photo_interval' => sprintf('Fotos %02d a %02d', 1, count($finding['photos'] ?? [])),
                    'is_primary' => $index === 0,
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $technical
     * @return array<int, array<string, mixed>>
     */
    private function evidenceForDefect(
        Defect $defect,
        array $technical,
        ?DefectAssessment $assessment = null,
    ): array {
        if (isset($technical['photos']) && is_array($technical['photos']) && $technical['photos'] !== []) {
            return $technical['photos'];
        }

        $status = $technical['photo_status'] ?? 'ready';

        return [[
            'id' => 'evidence-'.$defect->public_id.'-1',
            'defect_id' => $defect->id,
            'status' => $status,
            'status_label' => $this->photoStatusLabel($status),
            'title' => 'Vista técnica — '.$defect->code,
            'caption' => $defect->title,
            'location' => $assessment?->location_description ?? 'Localização registrada na inspeção',
            'illustrative' => true,
            'url' => null,
            'placeholder_variant' => (($defect->sequence_number - 1) % 4) + 1,
        ]];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function photos(array $items): array
    {
        return collect($items)
            ->flatMap(fn (array $item): array => $item['evidence'])
            ->values()
            ->all();
    }

    private function photoStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendente',
            'processing' => 'Processando',
            'failed' => 'Falha no processamento',
            default => 'Disponível',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function locations(array $items): array
    {
        return collect($items)
            ->map(function (array $item): array {
                $occurrence = $item['occurrence'] ?? [];
                $assessment = $item['assessment'] ?? [];

                return [
                    'id' => $item['id'],
                    'code' => $item['code'],
                    'title' => $item['title'],
                    'marker' => str_pad((string) ($occurrence['sequence'] ?? $item['sequence_number'] ?? 0), 2, '0', STR_PAD_LEFT),
                    'location' => $assessment['location_description'] ?? $occurrence['location'] ?? '—',
                    'project' => $occurrence['project'] ?? $item['project'] ?? '—',
                    'drawing' => $occurrence['drawing'] ?? $item['drawing'] ?? ViewFirstCivilScenario::DRAWING,
                    'item' => $occurrence['item'] ?? $item['item'] ?? '—',
                    'element' => $occurrence['element'] ?? $item['element'] ?? '—',
                    'manifestation' => $occurrence['manifestation'] ?? $item['manifestation'] ?? '—',
                    'impact' => $occurrence['impact'] ?? $item['impact'] ?? ['code' => '-', 'label' => 'Sem impacto direto'],
                    'classification' => $item['classification'] ?? [],
                    'gut' => $item['gut'] ?? [],
                    'photo_interval' => $occurrence['photo_interval'] ?? $item['photo_interval'] ?? '—',
                    'photo_count' => count($item['photos'] ?? []),
                    'quantity_summary' => $item['quantity_summary'] ?? [],
                    'quantities' => $item['quantities'] ?? [],
                    'assessment_status' => $assessment['status'] ?? null,
                    'assessment_label' => $assessment['status_label'] ?? null,
                    'is_draft' => ($assessment['status'] ?? null) === DefectAssessmentStatus::Draft->value,
                ];
            })
            ->values()
            ->all();
    }

    private function formatQuantity(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, array<string, mixed>>  $photos
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $inspectionPayload
     * @return array<string, mixed>
     */
    private function report(
        Inspection $inspection,
        array $items,
        array $photos,
        array $summary,
        array $inspectionPayload,
    ): array {
        $exportableItems = collect($items)
            ->reject(fn (array $item): bool => ($item['assessment']['status'] ?? null) === DefectAssessmentStatus::Draft->value)
            ->values();
        $exportablePhotos = $exportableItems
            ->flatMap(fn (array $item): array => $item['photos'] ?? [])
            ->values()
            ->all();
        $blockedIssues = [];

        if ($summary['pending'] > 0) {
            $blockedIssues[] = sprintf('%d registro(s) ainda não foram consolidados.', $summary['pending']);
        }

        if (($summary['quantity_total'] ?? 0.0) !== ($summary['exportable_quantity_total'] ?? 0.0)) {
            $blockedIssues[] = 'A consolidação completa ainda inclui registros não exportáveis.';
        }

        if (($summary['quantity_total_unit'] ?? ViewFirstCivilScenario::UNIT) !== 'm²') {
            $blockedIssues[] = sprintf(
                'A unidade consolidada do CIVIL está em %s, enquanto o resumo oficial do PDF usa m².',
                $summary['quantity_total_unit'] ?? ViewFirstCivilScenario::UNIT,
            );
        }

        $blocked = $blockedIssues !== [];

        return [
            'number' => ViewFirstCivilScenario::REPORT_NUMBER,
            'revision' => self::REPORT_REVISION,
            'generated_label' => 'Prévia preparada para apresentação',
            'cover' => [
                'eyebrow' => 'Relatório técnico de inspeção CIVIL',
                'title' => ViewFirstCivilScenario::REPORT_NUMBER,
                'client' => $inspection->equipment->client?->name,
                'provider' => $inspection->organization?->name,
                'equipment_tag' => $inspection->equipment->tag,
                'equipment_name' => $inspection->equipment->name,
                'inspection_type' => $inspection->inspection_type->label(),
                'inspection_number' => $inspection->number,
                'service_order' => $inspection->service_order,
                'procedure' => ViewFirstCivilScenario::PROCEDURE_NUMBER,
                'drawing' => ViewFirstCivilScenario::DRAWING,
                'inspected_on' => $inspection->inspected_on?->format('d/m/Y') ?? $inspection->scheduled_for?->format('d/m/Y'),
                'revision' => self::REPORT_REVISION,
                'issued_at' => ViewFirstCivilScenario::REPORT_DATE,
            ],
            'general_aspects' => [
                ['label' => 'Disciplina', 'value' => ViewFirstCivilScenario::DISCIPLINE_LABEL],
                ['label' => 'Família', 'value' => ViewFirstCivilScenario::CLASSIFICATION_FAMILY],
                ['label' => 'Unidade', 'value' => ViewFirstCivilScenario::UNIT],
                ['label' => 'Emissão', 'value' => ViewFirstCivilScenario::REPORT_DATE],
                ['label' => 'O.S.', 'value' => $inspection->service_order ?? '—'],
                ['label' => 'Procedimento', 'value' => ViewFirstCivilScenario::PROCEDURE_NUMBER],
                ['label' => 'Desenho', 'value' => ViewFirstCivilScenario::DRAWING],
                ['label' => 'Revisão', 'value' => self::REPORT_REVISION],
            ],
            'executive_summary' => [
                'criticality' => $summary['criticality'],
                'headline' => $summary['critical'] > 0
                    ? 'O equipamento requer tratamento prioritário das manifestações de maior criticidade.'
                    : 'A condição observada permite acompanhamento no ciclo programado.',
                'description' => sprintf(
                    '%d ocorrências civis foram consolidadas; %d avaliações estão concluídas e %d permanecem em aberto. %d registro(s) não serão exportados.',
                    $summary['total'],
                    $summary['completed'],
                    $summary['pending'],
                    $summary['draft_count'] ?? 0,
                ),
                'metrics' => [
                    'total' => $summary['total'],
                    'completed' => $summary['completed'],
                    'pending' => $summary['pending'],
                    'photo_total' => $summary['photo_total'] ?? 0,
                    'quantity_total' => $summary['quantity_total'] ?? 0.0,
                    'quantity_total_label' => $summary['quantity_total_label'] ?? '0,00 '.ViewFirstCivilScenario::UNIT,
                    'exportable_quantity_total' => $summary['exportable_quantity_total'] ?? 0.0,
                    'exportable_quantity_total_label' => $summary['exportable_quantity_total_label'] ?? '0,00 '.ViewFirstCivilScenario::UNIT,
                    'draft_count' => $summary['draft_count'] ?? 0,
                ],
            ],
            'locations' => $this->locations($exportableItems->all()),
            'findings' => $exportableItems
                ->map(function (array $item) use ($inspection): array {
                    $assessment = $item['assessment'] ?? [];
                    $occurrence = $item['occurrence'] ?? [];

                    return [
                        'id' => $item['id'],
                        'code' => $item['code'],
                        'title' => $item['title'],
                        'assessment' => $assessment,
                        'classification' => $item['classification'],
                        'gut' => $item['gut'],
                        'location' => $assessment['location_description'] ?? $occurrence['location'] ?? '—',
                        'project' => $occurrence['project'] ?? $item['project'] ?? '—',
                        'drawing' => $occurrence['drawing'] ?? $item['drawing'] ?? ViewFirstCivilScenario::DRAWING,
                        'item' => $occurrence['item'] ?? $item['item'] ?? '—',
                        'element' => $occurrence['element'] ?? $item['element'] ?? '—',
                        'manifestation' => $occurrence['manifestation'] ?? $item['manifestation'] ?? '—',
                        'impact' => $occurrence['impact'] ?? $item['impact'] ?? ['code' => '-', 'label' => 'Sem impacto direto'],
                        'photo_interval' => $occurrence['photo_interval'] ?? $item['photo_interval'] ?? '—',
                        'photos' => $item['photos'] ?? [],
                        'quantity_summary' => $item['quantity_summary'] ?? [],
                        'quantities' => $item['quantities'] ?? [],
                        'comment' => $assessment['comment'] ?? null,
                        'recommendation' => $assessment['recommendation'] ?? null,
                    ];
                })
                ->values()
                ->all(),
            'quantities' => [
                'total' => $summary['quantity_total'] ?? 0.0,
                'total_label' => $summary['quantity_total_label'] ?? '0,00 '.ViewFirstCivilScenario::UNIT,
                'exportable_total' => $summary['exportable_quantity_total'] ?? 0.0,
                'exportable_total_label' => $summary['exportable_quantity_total_label'] ?? '0,00 '.ViewFirstCivilScenario::UNIT,
                'unit' => ViewFirstCivilScenario::UNIT,
                'by_class' => $summary['quantity_by_class'] ?? [],
            ],
            'sections' => [
                [
                    'key' => 'defects',
                    'title' => 'Avarias e avaliações CIVIL',
                    'items' => $exportableItems->all(),
                ],
                [
                    'key' => 'evidence',
                    'title' => 'Registro fotográfico',
                    'items' => $exportablePhotos,
                ],
                [
                    'key' => 'responsibles',
                    'title' => 'Responsabilidade técnica',
                    'items' => $inspectionPayload['responsibles'] ?? [],
                ],
                [
                    'key' => 'documents',
                    'title' => 'Documentos de referência',
                    'items' => $inspectionPayload['reference_documents'] ?? [],
                ],
            ],
            'validation' => [
                'blocked' => $blocked,
                'issues' => $blockedIssues,
                'draft_count' => $summary['draft_count'] ?? 0,
            ],
            'print_enabled' => true,
            'pdf_enabled' => ! $blocked,
            'pdf_disabled_reason' => $blocked
                ? implode(' ', $blockedIssues)
                : 'A geração do PDF oficial será habilitada no módulo de relatórios.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function demoMetadata(): array
    {
        return [
            'enabled' => true,
            'provisional_notice' => 'A leitura CIVIL já está estruturada; GUT, classificação, quantitativos e evidências permanecem em modo somente leitura.',
            'photo_notice' => 'As evidências desta demonstração usam placeholders controlados e não representam os arquivos privados do cliente.',
            'report_revision' => self::REPORT_REVISION,
        ];
    }

    /**
     * @param  Collection<int, Defect>  $items
     */
    private function adjacentAssessmentUrl(
        Collection $items,
        int|false $position,
        int $offset,
        Inspection $inspection,
    ): ?string {
        if (! is_int($position)) {
            return null;
        }

        $defect = $items->get($position + $offset);

        if (! $defect instanceof Defect) {
            return null;
        }

        $assessment = $defect->assessments
            ->firstWhere('inspection_id', $inspection->getKey());

        return $assessment instanceof DefectAssessment
            ? route('defect-assessments.show', $assessment)
            : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function firstPendingUrl(array $items): ?string
    {
        $item = collect($items)
            ->first(fn (array $item): bool => $item['is_pending'] && $item['assessment_url'] !== null);

        return $item['assessment_url'] ?? null;
    }

    /**
     * Excludes defects introduced only in a later inspection while retaining
     * existing defects that still need an assessment in an open inspection.
     *
     * @return Collection<int, Defect>
     */
    private function defectsForInspection(Inspection $inspection): Collection
    {
        $inspection->loadMissing([
            'equipment.defects.firstInspection',
            'equipment.defects.assessments.inspection',
            'equipment.defects.assessments.creator',
        ]);

        $inspectionKey = $this->inspectionOrderKey($inspection);

        return $inspection->equipment->defects
            ->filter(function (Defect $defect) use ($inspection, $inspectionKey): bool {
                if ($defect->assessments->contains('inspection_id', $inspection->getKey())) {
                    return true;
                }

                if ($defect->firstInspection === null) {
                    return false;
                }

                if ($defect->status !== DefectStatus::Active) {
                    return false;
                }

                return $this->inspectionOrderKey($defect->firstInspection) <= $inspectionKey;
            })
            ->sortBy('sequence_number')
            ->values();
    }

    /**
     * @return array{int, int}
     */
    private function inspectionOrderKey(Inspection $inspection): array
    {
        return [
            $inspection->inspected_on?->getTimestamp()
                ?? $inspection->scheduled_for?->getTimestamp()
                ?? $inspection->created_at?->getTimestamp()
                ?? 0,
            (int) $inspection->getKey(),
        ];
    }

    private function criticalityRank(string $code): int
    {
        return match ($code) {
            'CV-1' => 1,
            'CV-2' => 2,
            'CV-3' => 3,
            'CV-4' => 4,
            'CV-5' => 5,
            default => 99,
        };
    }
}
