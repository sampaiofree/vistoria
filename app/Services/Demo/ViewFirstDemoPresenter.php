<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Enums\DefectAssessmentCondition;
use App\Enums\DefectAssessmentStatus;
use App\Enums\InspectionResponsibility;
use App\Enums\InspectionStatus;
use App\Enums\MeasurementUnit;
use App\Models\AssessmentPhoto;
use App\Models\Defect;
use App\Models\DefectAssessment;
use App\Models\Equipment;
use App\Models\Inspection;
use App\Models\User;
use App\Services\Classification\NativeDefectCatalog;
use App\Services\Defects\DefectAssessmentQuantitySnapshot;
use App\Services\Defects\InspectionDefectScope;
use App\Services\Defects\NativeQuantityCatalog;
use App\Services\Defects\ResolvePreviousDefectAssessment;
use App\Services\InspectionLocations\DefectLocationColor;
use App\Services\InspectionLocations\InspectionLocationPhotoNumbering;
use App\Services\Reports\EquipmentRevisionChronology;
use App\Services\Reports\GeneralAspectsDocument;
use App\Services\Reports\InspectionOverviewPresenter;
use App\Services\Reports\InspectionPhotographicDocumentationComposer;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Operational read model for inspections, assessments and reports.
 * All values exposed here come from persisted domain data.
 */
class ViewFirstDemoPresenter
{
    public const REPORT_REVISION = 'R-04';

    public function __construct(
        private readonly InspectionLocationPhotoNumbering $photoNumbering,
        private readonly InspectionPhotographicDocumentationComposer $photographicDocumentation,
        private readonly EquipmentRevisionChronology $revisionChronology,
        private readonly GeneralAspectsDocument $generalAspectsDocuments,
        private readonly InspectionOverviewPresenter $inspectionOverview,
        private readonly InspectionDefectScope $inspectionDefectScope,
        private readonly ResolvePreviousDefectAssessment $previousAssessmentResolver,
        private readonly DefectLocationColor $defectLocationColor,
        private readonly DefectAssessmentQuantitySnapshot $quantitySnapshots,
    ) {}

    /**
     * @return array{criticality: null|array{value:string, label:string, is_provisional:bool}}
     */
    public function equipment(Equipment $equipment): array
    {
        $equipment->loadMissing(['organization', 'defects.assessments.quantities']);

        if ($equipment->defects->isEmpty()) {
            return ['criticality' => null];
        }

        $classification = $equipment->defects
            ->map(function (Defect $defect): array {
                $assessment = $defect->assessments
                    ->filter(fn (DefectAssessment $assessment): bool => $assessment->isComplete())
                    ->sortByDesc('created_at')
                    ->first();

                return $this->technicalData($defect, $assessment)['classification'];
            })
            ->sortBy(fn (array $item): int => $this->criticalityRank($item))
            ->first() ?? $this->classification(null);

        return [
            'criticality' => [
                'value' => $classification['code'],
                'label' => $classification['label'],
                'is_critical' => $classification['is_critical'] ?? false,
                'is_provisional' => false,
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function assessment(DefectAssessment $assessment, User $user): array
    {
        $assessment->loadMissing([
            'defect.equipment.client',
            'inspection.equipment.defects.assessments.inspection',
            'inspection.equipment.defects.assessments.creator',
            'inspection.equipment.defects.assessments.photos',
            'inspection.equipment.defects.assessments.quantities',
            'inspection.equipment',
            'inspection.equipment.defects.firstInspection',
            'photos',
            'quantities',
            'locationMapVersion.map',
            'location',
            'previousAssessment.inspection',
            'previousAssessment.creator',
            'creator',
        ]);

        $technical = $this->technicalData($assessment->defect, $assessment);
        $items = $this->defectsForInspection($assessment->inspection);
        $position = $items->search(
            fn (Defect $defect): bool => $defect->getKey() === $assessment->defect_id,
        );

        $previousUrl = $this->adjacentAssessmentUrl($items, $position, -1, $assessment->inspection);
        $nextUrl = $this->adjacentAssessmentUrl($items, $position, 1, $assessment->inspection);
        $canUpdate = $user->can('update', $assessment);
        $keepPublished = $assessment->isComplete();
        $canEdit = $canUpdate;
        $canChangeStatus = $canUpdate;
        $category = $assessment->defect->category;
        $classification = $technical['classification'];
        $quantities = collect($technical['quantities'])
            ->map(function (array $quantity) use ($assessment, $canEdit): array {
                $live = $assessment->quantities->firstWhere('position', $quantity['position']);

                return [
                    ...$quantity,
                    'public_id' => $live?->public_id,
                    'update_url' => $canEdit && $live !== null
                        ? route('defect-assessment-quantities.update', $live)
                        : null,
                    'delete_url' => $canEdit && $live !== null
                        ? route('defect-assessment-quantities.destroy', $live)
                        : null,
                ];
            })
            ->values()
            ->all();
        $gutDefinition = NativeDefectCatalog::technicalDefinition(
            $category,
            $assessment->inspection->equipment->abc_code,
            $assessment->inspection->atmospheric_classification,
        );
        $reportNumbering = $this->photoNumbering->buildForReport($assessment->inspection);
        $reportCategory = $assessment->defect->categoryCode();
        $assessmentHistory = $this->assessmentHistory($assessment);

        return [
            'assessment' => $this->assessmentPayload($assessment, true),
            'origin_type' => $assessment->inspection_id === $assessment->defect->first_inspection_id
                ? 'new'
                : 'inherited',
            'previous_assessment' => $assessment->previousAssessment === null
                ? null
                : $this->assessmentPayload($assessment->previousAssessment),
            'previous_assessment_summary' => $assessmentHistory[0] ?? null,
            'assessment_history' => $assessmentHistory,
            'reinspection_action' => $this->reinspectionAction($assessment, $user),
            'classification' => $classification,
            'gut' => $technical['gut'],
            'gut_options' => NativeDefectCatalog::gutOptions(),
            'gut_definition' => $gutDefinition,
            'gut_snapshot' => $assessment->gut_snapshot,
            'quantity_snapshot' => $assessment->quantity_snapshot,
            'gut_classification_ranges' => NativeDefectCatalog::classifications($category)
                ->map(fn ($classification): array => $classification->toArray())->all(),
            'characterization' => $technical['characterization'],
            'quantities' => $quantities,
            'quantity_summary' => $technical['quantity_summary'],
            'quantity_definition' => NativeQuantityCatalog::definition($category),
            'discipline' => $technical['discipline'] ?? Str::lower($assessment->defect->categoryCode()),
            'discipline_label' => $technical['discipline_label'] ?? $assessment->defect->categoryLabel(),
            'classification_family' => $category->value,
            'unit' => $technical['unit'] ?? null,
            'project' => $technical['project'] ?? null,
            'drawing' => $technical['drawing'] ?? null,
            'item' => $technical['item'] ?? null,
            'element' => $technical['element'] ?? null,
            'manifestation' => $technical['manifestation'] ?? null,
            'impact' => $technical['impact'] ?? null,
            'photo_interval' => $technical['photo_interval'] ?? null,
            'occurrence' => $technical['occurrence'] ?? null,
            'evidence' => $this->evidenceForDefect($assessment->defect, $technical, $assessment, $canEdit, $reportNumbering),
            'location_map' => $this->assessmentLocationMapPayload($assessment, $canEdit),
            'photos' => $assessment->photos->map(fn ($photo): array => [
                'id' => $photo->public_id,
                'title' => $photo->original_name,
                'caption' => $photo->caption,
                'photo_type' => $photo->photo_type->value,
                'photo_type_label' => $photo->photo_type->label(),
                'processing_status' => $photo->processing_status->value,
                'url' => $photo->isReady() ? route('assessment-photos.show', [$photo, 'optimized']) : null,
                'thumbnail_url' => $photo->isReady() ? route('assessment-photos.show', [$photo, 'thumbnail']) : null,
                'position' => $photo->position,
                'report_number' => $reportNumbering[$photo->public_id] ?? null,
                'report_category' => $reportCategory,
                'reorder_url' => $canEdit ? route('defect-assessments.photos.reorder', $assessment) : null,
                'delete_url' => $canEdit ? route('assessment-photos.destroy', $photo) : null,
            ])->values()->all(),
            'assessment_navigation' => [
                'previous_url' => $previousUrl,
                'next_url' => $nextUrl,
                'inspection_url' => route('inspections.show', $assessment->inspection),
                'defects_url' => route('inspections.defects', $assessment->inspection),
                'position' => is_int($position) ? $position + 1 : 1,
                'total' => $items->count(),
            ],
            'condition_options' => collect(DefectAssessmentCondition::options())
                ->filter(fn (array $option): bool => $assessment->inspection_id === $assessment->defect->first_inspection_id
                    || $option['value'] !== DefectAssessmentCondition::New->value)
                ->values()
                ->all(),
            'measurement_units' => MeasurementUnit::options(),
            'capabilities' => [
                'update' => $canEdit,
                'complete' => $canChangeStatus,
                'keep_published' => $keepPublished,
                'can_move_to_draft' => true,
                'location_marker_count' => $assessment->location === null ? 0 : 1,
                'status_url' => $canChangeStatus
                    ? route('defect-assessments.status.update', $assessment)
                    : null,
                'update_url' => $canEdit
                    ? route('defect-assessments.update', $assessment)
                    : null,
                'complete_url' => $canChangeStatus
                    ? route('defect-assessments.complete', $assessment)
                    : null,
                'photo_upload_url' => $canEdit
                    ? route('defect-assessments.photos.store', $assessment)
                    : null,
                'gut_url' => $canEdit
                    ? route('defect-assessments.gut.update', $assessment)
                    : null,
                'quantity_store_url' => $canEdit
                    ? route('defect-assessments.quantities.store', $assessment)
                    : null,
                'location_map_upload_url' => $canEdit
                    ? route('defect-assessments.location-map.store', $assessment)
                    : null,
                'location_map_delete_url' => $canEdit && $assessment->locationMapVersion !== null
                    ? route('defect-assessments.location-map.destroy', $assessment)
                    : null,
            ],
        ];
    }

    /** @return array<string,mixed>|null */
    private function assessmentLocationMapPayload(DefectAssessment $assessment, bool $canEdit): ?array
    {
        $version = $assessment->locationMapVersion;
        if ($version === null) {
            return null;
        }

        return [
            'map_public_id' => $version->map->public_id,
            'version_public_id' => $version->public_id,
            'version' => $version->version,
            'processing_status' => $version->processing_status->value,
            'processing_error' => $version->processing_error,
            'background_url' => $version->isReady()
                ? route('defect-location-map-versions.background', [
                    'mapVersion' => $version,
                    'variant' => 'thumbnail',
                    'v' => $version->background_checksum,
                ])
                : null,
            'color' => $this->defectLocationColor->forAssessment($assessment),
            'location' => $assessment->location === null ? null : [
                'public_id' => $assessment->location->public_id,
                'geometry' => $assessment->location->geometry,
                'label' => $assessment->location->label,
                'confirmed' => $assessment->location->isConfirmed(),
                'confirmed_at' => $assessment->location->confirmed_at?->format('d/m/Y H:i'),
                'lock_version' => $assessment->location->lock_version,
            ],
            'editor_url' => $canEdit && $version->isReady()
                ? route('defect-assessments.location.editor', $assessment)
                : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function inspectionLinks(Inspection $inspection): array
    {
        return [
            'overview_url' => route('inspections.show', $inspection),
            'report_overview_url' => route('inspections.report-overview', $inspection),
            'defects_url' => route('inspections.defects', $inspection),
            'photos_url' => route('inspections.photos', $inspection),
            'documents_url' => route('inspections.documents', $inspection),
            'history_url' => route('inspections.history', $inspection),
            'report_url' => route('inspections.report-preview', $inspection),
            'reinspection_checklist_url' => route('inspections.reinspection-checklist', $inspection),
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
            ['key' => 'report_overview', 'label' => 'Vista geral', 'url' => route('inspections.report-overview', $inspection)],
            ['key' => 'defects', 'label' => 'Avarias', 'url' => route('inspections.defects', $inspection), 'count' => $summary['total']],
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
                    ['key' => 'active', 'label' => 'Ativas', 'count' => collect($items)->where('is_repaired', false)->count()],
                    ['key' => 'all', 'label' => 'Todas', 'count' => $summary['total']],
                    ['key' => 'pending', 'label' => 'Pendentes', 'count' => $summary['pending']],
                    ['key' => 'treated', 'label' => 'Tratadas', 'count' => $summary['treated']],
                    ['key' => 'canceled', 'label' => 'Canceladas', 'count' => $summary['canceled']],
                    ['key' => 'canceled_sr', 'label' => 'Canceladas S/R', 'count' => $summary['canceled_sr']],
                    ['key' => 'critical', 'label' => 'Críticas', 'count' => $summary['critical']],
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
                    ->sortBy(fn (array $classification): int => $this->criticalityRank($classification))
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
                    ['key' => 'progress', 'label' => 'Avaliações publicadas', 'value' => sprintf('%d/%d', $summary['completed'], $summary['total']), 'detail' => $summary['progress_percent'].'%'],
                    ['key' => 'criticality', 'label' => 'Criticidade atual', 'value' => $summary['criticality']['code'], 'detail' => $summary['criticality']['label']],
                    ['key' => 'critical', 'label' => 'Avarias críticas', 'value' => (string) $summary['critical'], 'detail' => 'prioridade técnica'],
                    ['key' => 'pending', 'label' => 'Pendências', 'value' => (string) $summary['pending'], 'detail' => 'avaliação em aberto'],
                ],
                'highlights' => collect($items)
                    ->filter(fn (array $item): bool => ($item['classification']['is_critical'] ?? false) || $item['is_pending'])
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
            ->sortBy(fn (array $classification): int => $this->criticalityRank($classification))
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
            ->sortBy(fn (array $item): int => $this->criticalityRank($item))
            ->values()
            ->all();

        $exportableCollection = $collection
            ->filter(fn (array $item): bool => ($item['assessment']['status'] ?? null) === DefectAssessmentStatus::Complete->value)
            ->values();
        $quantityTotals = $this->quantityTotals($collection);
        $exportableQuantityTotals = $this->quantityTotals($exportableCollection);
        $singleQuantityTotal = $quantityTotals->count() === 1 ? $quantityTotals->first() : null;
        $singleExportableQuantityTotal = $exportableQuantityTotals->count() === 1
            ? $exportableQuantityTotals->first()
            : null;
        $photoTotal = $collection->sum(fn (array $item): int => count($item['photos'] ?? []));
        $exportablePhotoTotal = $exportableCollection->sum(fn (array $item): int => count($item['photos'] ?? []));
        $quantityByClass = $collection
            ->groupBy('classification.code')
            ->flatMap(function (Collection $group): array {
                $first = $group->first();
                $code = $first['classification']['code'] ?? '—';

                return $this->quantityTotals($group)
                    ->map(fn (array $quantity): array => [
                        'key' => $code.'-'.$quantity['unit_value'],
                        'code' => $code,
                        'label' => $first['classification']['label'] ?? 'Não classificada',
                        'unit' => $quantity['unit'],
                        'total' => $quantity['total'],
                        'total_label' => $quantity['total_label'],
                        'count' => $group->count(),
                    ])
                    ->all();
            })
            ->sortByDesc('total')
            ->values()
            ->all();

        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => $total - $completed,
            'progress_percent' => $total === 0 ? 0 : (int) round(($completed / $total) * 100),
            'critical' => $collection->where('classification.is_critical', true)->count(),
            'treated' => $collection->where('assessment.condition', DefectAssessmentCondition::Treated->value)->count(),
            'canceled' => $collection->where('assessment.condition', DefectAssessmentCondition::Canceled->value)->count(),
            'canceled_sr' => $collection->where('assessment.condition', DefectAssessmentCondition::CanceledWithoutRepair->value)->count(),
            'criticality' => $criticality,
            'condition_breakdown' => $conditionBreakdown,
            'classification_breakdown' => $classificationBreakdown,
            'draft_count' => $collection->where('assessment.status', DefectAssessmentStatus::Draft->value)->count(),
            'photo_total' => $photoTotal,
            'exportable_photo_total' => $exportablePhotoTotal,
            'quantity_total' => $singleQuantityTotal['total'] ?? null,
            'quantity_total_label' => $quantityTotals->isEmpty()
                ? '—'
                : $quantityTotals->pluck('total_label')->implode(' · '),
            'quantity_total_unit' => $singleQuantityTotal['unit'] ?? ($quantityTotals->isEmpty() ? null : 'Múltiplas'),
            'quantity_totals_by_unit' => $quantityTotals->all(),
            'exportable_quantity_total' => $singleExportableQuantityTotal['total'] ?? null,
            'exportable_quantity_total_label' => $exportableQuantityTotals->isEmpty()
                ? '—'
                : $exportableQuantityTotals->pluck('total_label')->implode(' · '),
            'exportable_quantity_totals_by_unit' => $exportableQuantityTotals->all(),
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
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array{unit_value:string,unit:string,total:float,total_label:string}>
     */
    private function quantityTotals(Collection $items): Collection
    {
        return $items
            ->map(function (array $item): ?array {
                $summary = $item['quantity_summary'] ?? null;
                if (! is_array($summary) || ($summary['total'] ?? null) === null) {
                    return null;
                }

                $unitValue = (string) ($summary['unit_value'] ?? MeasurementUnit::Other->value);

                return [
                    'unit_value' => $unitValue,
                    'unit' => $summary['unit'] ?? MeasurementUnit::tryFrom($unitValue)?->symbol() ?? $unitValue,
                    'total_raw' => (string) ($summary['total_raw'] ?? $summary['total']),
                ];
            })
            ->filter()
            ->groupBy('unit_value')
            ->map(function (Collection $rows): array {
                $first = $rows->first();
                $totalRaw = (string) $rows->reduce(
                    fn (BigDecimal $sum, array $row): BigDecimal => $sum->plus(BigDecimal::of($row['total_raw'])),
                    BigDecimal::zero(),
                );
                $total = (float) $totalRaw;

                return [
                    'unit_value' => $first['unit_value'],
                    'unit' => $first['unit'],
                    'total' => $total,
                    'total_raw' => $totalRaw,
                    'total_label' => $this->formatQuantity($total).' '.$first['unit'],
                ];
            })
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function defectCard(Inspection $inspection, Defect $defect, User $user): array
    {
        $assessment = $defect->assessments
            ->firstWhere('inspection_id', $inspection->getKey());
        $previousAssessment = $this->previousAssessmentResolver->handle($defect, $inspection);

        $previousAssessment?->loadMissing(['inspection', 'quantities', 'photos']);
        $technical = $this->technicalData($defect, $assessment);
        $classification = $technical['classification'];
        $evidence = $this->evidenceForDefect($defect, $technical, $assessment);
        $isPending = $assessment === null || $assessment->status === DefectAssessmentStatus::Draft;
        $canCreate = $assessment === null
            && $user->can('create', [DefectAssessment::class, $inspection, $defect]);

        return [
            'id' => $defect->id,
            'public_id' => $defect->public_id,
            'code' => $defect->code,
            'title' => $defect->title,
            'origin_description' => $defect->origin_description,
            'category' => $defect->categoryCode(),
            'category_label' => $defect->categoryLabel(),
            'status' => $defect->status->value,
            'status_label' => $defect->status->label(),
            'sequence_number' => (int) $defect->sequence_number,
            'origin_type' => $defect->first_inspection_id === $inspection->getKey() ? 'new' : 'inherited',
            'previous_assessment_summary' => $previousAssessment === null
                ? null
                : $this->historicalAssessmentPayload($previousAssessment),
            'assessment' => $assessment === null ? null : $this->assessmentPayload($assessment),
            'condition' => $assessment?->condition->value,
            'condition_label' => $assessment?->condition->label() ?? 'Pendente',
            'assessment_status' => $assessment?->status->value ?? 'not_assessed',
            'is_pending' => $isPending,
            'is_repaired' => $defect->isRepaired(),
            'is_canceled' => $assessment?->condition->isCanceled() ?? false,
            'classification' => $classification,
            'gut' => $technical['gut'],
            'characterization' => $technical['characterization'],
            'quantities' => $technical['quantities'],
            'quantity_summary' => $technical['quantity_summary'] ?? null,
            'discipline' => $technical['discipline'] ?? Str::lower($defect->categoryCode()),
            'discipline_label' => $technical['discipline_label'] ?? $defect->categoryLabel(),
            'classification_family' => $technical['classification_family'] ?? $defect->categoryCode(),
            'unit' => $technical['unit'] ?? null,
            'project' => $technical['project'] ?? null,
            'drawing' => $technical['drawing'] ?? null,
            'item' => $technical['item'] ?? null,
            'element' => $technical['element'] ?? null,
            'manifestation' => $technical['manifestation'] ?? null,
            'impact' => $technical['impact'] ?? null,
            'photo_interval' => $technical['photo_interval'] ?? null,
            'occurrence' => $technical['occurrence'] ?? null,
            'evidence' => $evidence,
            'photos' => $evidence,
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
            'item_description' => $assessment->item_description,
            'project_reference' => $assessment->project_reference,
            'impacts_activity' => $assessment->impacts_activity,
            'gravity' => $assessment->gravity,
            'urgency' => $assessment->urgency,
            'trend' => $assessment->trend,
            'gut_score' => $assessment->gut_score,
            'gut_snapshot' => $assessment->gut_snapshot,
            'gut_classified_at' => $assessment->gut_classified_at?->format('d/m/Y H:i'),
            'classification_code' => $assessment->classification_code,
            'classification_snapshot' => $assessment->classification_snapshot,
            'classification_priority' => $assessment->classification_priority,
            'deadline_months' => $assessment->deadline_months,
            'recommended_due_date' => $assessment->recommended_due_date?->format('d/m/Y'),
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
                'category' => $assessment->defect->categoryCode(),
                'category_label' => $assessment->defect->categoryLabel(),
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
    public function defectTechnicalData(Defect $defect, ?DefectAssessment $assessment = null): array
    {
        return $this->technicalData($defect, $assessment);
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
        $classification['profile_version'] = null;
        $classification['historical'] = ($finding['current_condition'] ?? null) === DefectAssessmentCondition::Treated->value;

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
                'profile_version' => null,
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
    private function technicalData(Defect $defect, ?DefectAssessment $assessment = null): array
    {
        $defect->loadMissing('organization');
        $assessment?->loadMissing(['quantities', 'photos']);

        return $this->persistedTechnicalData($defect, $assessment);

        $finding = null;

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
                'characterization' => ['Categoria' => $defect->categoryLabel(), 'Elemento' => 'Equipamento inspecionado'],
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

    private function hasPersistedTechnicalData(DefectAssessment $assessment): bool
    {
        return $assessment->classification_code !== null
            || $assessment->item_description !== null
            || $assessment->project_reference !== null
            || $assessment->impacts_activity !== null
            || $assessment->gravity !== null
            || $assessment->quantities->isNotEmpty()
            || $assessment->photos->isNotEmpty();
    }

    /** @return array<string, mixed> */
    private function persistedTechnicalData(Defect $defect, ?DefectAssessment $assessment): array
    {
        $classification = $this->persistedClassification($assessment);
        $snapshot = $this->quantitySnapshot($assessment);
        $quantities = collect($snapshot['items'] ?? [])
            ->map(fn (array $item): array => $this->quantityPayloadFromSnapshot($item));
        $totalsByUnit = $quantities
            ->groupBy('measurement_unit')
            ->map(function (Collection $rows): array {
                $first = $rows->first();
                $total = (float) $rows->sum('total');

                return [
                    'unit' => $first['unit'],
                    'unit_value' => $first['measurement_unit'],
                    'total' => $total,
                    'total_label' => $this->formatQuantity($total).' '.$first['unit'],
                ];
            })
            ->values();
        $singleTotal = $totalsByUnit->count() === 1 ? $totalsByUnit->first() : null;
        if ($snapshot !== null && $singleTotal !== null) {
            $singleTotal['total'] = (float) $snapshot['total'];
            $singleTotal['total_raw'] = (string) $snapshot['total'];
            $singleTotal['total_label'] = $this->formatQuantity((float) $snapshot['total']).' '.$singleTotal['unit'];
            $totalsByUnit = collect([$singleTotal]);
        }
        $gut = $assessment !== null
            && $assessment->gravity !== null
            && $assessment->urgency !== null
            && $assessment->trend !== null
            ? [
                'severity' => $assessment->gravity,
                'urgency' => $assessment->urgency,
                'tendency' => $assessment->trend,
                'score' => null,
                'formula' => null,
                'provisional' => false,
                'profile_version' => null,
                'snapshot' => $assessment->gut_snapshot,
            ]
            : null;
        $impact = ($assessment?->impacts_activity ?? false)
            ? ['code' => 'IMP. ATIV.', 'label' => 'Impacto na atividade', 'description' => 'A avaliação registra impacto na atividade.']
            : ['code' => '-', 'label' => 'Sem impacto informado', 'description' => 'Nenhum impacto na atividade foi registrado.'];
        $categoryCode = $defect->categoryCode();
        $categoryLabel = $defect->categoryLabel();
        $characterization = collect([
            'Categoria' => $categoryLabel,
            'Item / subitem' => $assessment?->item_description,
            'Projeto / referência' => $assessment?->project_reference,
            'Manifestação' => $defect->title,
            'Impacto' => $impact['label'],
            'Localização' => $assessment?->location_description,
        ])->filter(fn ($value): bool => $value !== null && $value !== '')
            ->map(fn (string $value, string $label): array => compact('label', 'value'))
            ->values()
            ->all();
        $photoCount = $assessment?->photos->count() ?? 0;
        $photoInterval = $photoCount === 0
            ? null
            : ($photoCount === 1 ? 'Foto 01' : sprintf('Fotos 01 a %02d', $photoCount));

        return [
            'discipline' => strtolower($categoryCode),
            'discipline_label' => $categoryLabel,
            'classification_family' => $categoryCode,
            'unit' => $singleTotal['unit'] ?? ($totalsByUnit->isEmpty() ? null : 'Múltiplas'),
            'project' => $assessment?->project_reference,
            'drawing' => null,
            'item' => $assessment?->item_description,
            'element' => $assessment?->item_description,
            'manifestation' => $defect->title,
            'impact' => $impact,
            'classification' => $classification,
            'gut' => $gut,
            'characterization' => $characterization,
            'quantities' => $quantities->values()->all(),
            'quantity_summary' => [
                'total' => $singleTotal['total'] ?? null,
                'total_raw' => $singleTotal['total_raw'] ?? null,
                'total_label' => $totalsByUnit->pluck('total_label')->implode(' · '),
                'unit' => $singleTotal['unit'] ?? ($totalsByUnit->isEmpty() ? null : 'Múltiplas'),
                'unit_value' => $singleTotal['unit_value'] ?? null,
                'line_count' => $quantities->count(),
                'totals_by_unit' => $totalsByUnit->all(),
            ],
            'photo_interval' => $photoInterval,
            'photo_status' => $assessment?->photos->isEmpty() === false ? 'ready' : null,
            'occurrence' => [
                'sequence' => (int) $defect->sequence_number,
                'code' => $defect->code,
                'title' => $defect->title,
                'project' => $assessment?->project_reference,
                'drawing' => null,
                'item' => $assessment?->item_description,
                'element' => $assessment?->item_description,
                'manifestation' => $defect->title,
                'impact' => $impact,
                'location' => $assessment?->location_description,
                'photo_interval' => $photoInterval,
                'photo_count' => $photoCount,
                'quantities' => $quantities->values()->all(),
                'quantity_summary' => [
                    'total' => $singleTotal['total'] ?? null,
                    'total_label' => $totalsByUnit->pluck('total_label')->implode(' · '),
                    'unit' => $singleTotal['unit'] ?? ($totalsByUnit->isEmpty() ? null : 'Múltiplas'),
                    'totals_by_unit' => $totalsByUnit->all(),
                ],
                'classification' => $classification,
                'gut' => $gut,
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    private function quantitySnapshot(?DefectAssessment $assessment): ?array
    {
        if ($assessment === null) {
            return null;
        }

        if ($assessment->isComplete() && is_array($assessment->quantity_snapshot)) {
            return $this->quantitySnapshots->normalize($assessment->quantity_snapshot);
        }

        $assessment->loadMissing(['defect', 'quantities']);

        return $this->quantitySnapshots->build($assessment->defect->category, $assessment->quantities);
    }

    /** @param array<string, mixed> $snapshot @return array<string, mixed> */
    private function quantityPayloadFromSnapshot(array $snapshot, ?string $publicId = null): array
    {
        $unitValue = (string) ($snapshot['measurement_unit'] ?? MeasurementUnit::Other->value);
        $unit = MeasurementUnit::tryFrom($unitValue);
        $inputs = is_array($snapshot['inputs'] ?? null) ? $snapshot['inputs'] : [];
        $totalRaw = (string) ($snapshot['total'] ?? '0');
        $unitRaw = isset($snapshot['unit_value']) ? (string) $snapshot['unit_value'] : null;
        $elementCode = data_get($snapshot, 'element.code');

        return [
            'public_id' => $publicId,
            'position' => (int) ($snapshot['position'] ?? 1),
            'description' => $snapshot['description'] ?? null,
            'category' => $snapshot['category'] ?? null,
            'calculation_type' => $snapshot['calculation_type'] ?? null,
            'rec_element' => $elementCode,
            'element_code' => $elementCode,
            'inputs' => $inputs,
            'quantity' => (string) ($snapshot['quantity'] ?? $inputs['quantity'] ?? '1'),
            'unit_value' => $unitRaw,
            'unit_measurement_value' => $unitRaw,
            'measurement_value' => $totalRaw,
            'measurement_unit' => $unitValue,
            'mode' => $snapshot['mode'] ?? null,
            'formula_version' => $snapshot['formula_version'] ?? null,
            'formula_snapshot' => $snapshot,
            'length' => $inputs['length'] ?? null,
            'height' => $inputs['height'] ?? null,
            'width' => $inputs['width'] ?? null,
            'unit_volume' => $unitRaw,
            'unit' => $unit?->symbol() ?? $unitValue,
            'total' => (float) $totalRaw,
            'total_label' => $this->formatQuantity((float) $totalRaw).' '.($unit?->symbol() ?? $unitValue),
        ];
    }

    /** @return array<string, mixed> */
    private function persistedClassification(?DefectAssessment $assessment): array
    {
        if ($assessment?->classification_code === null) {
            return [
                'code' => '—',
                'label' => 'Não classificada',
                'tone' => 'neutral',
                'score_band' => null,
                'profile_version' => null,
                'severity_rank' => null,
                'is_critical' => false,
                'provisional' => false,
                'historical' => false,
            ];
        }

        $snapshot = $assessment->classification_snapshot ?? [];
        $priority = $snapshot['severity_rank'] ?? $assessment->classification_priority;

        return [
            'code' => $assessment->classification_code,
            'label' => $snapshot['name'] ?? $snapshot['label'] ?? $assessment->classification_code,
            'color' => $snapshot['color'] ?? null,
            'tone' => 'neutral',
            'score_band' => isset($snapshot['lower_limit'], $snapshot['upper_limit'])
                ? $snapshot['lower_limit'].'-'.$snapshot['upper_limit']
                : null,
            'profile_version' => null,
            'catalog_version' => $snapshot['catalog_version'] ?? null,
            'severity_rank' => $priority,
            'is_critical' => $priority !== null && $priority <= 2,
            'provisional' => false,
            'historical' => $assessment->condition === DefectAssessmentCondition::Treated,
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
                'profile_version' => null,
                'severity_rank' => null,
                'is_critical' => false,
                'provisional' => true,
                'historical' => $historical,
            ];
        }

        return [
            'code' => $code,
            'label' => $labels[$code][0] ?? 'Não classificada',
            'tone' => $labels[$code][1] ?? 'neutral',
            'score_band' => $labels[$code][2] ?? null,
            'profile_version' => null,
            'severity_rank' => isset($labels[$code]) ? array_search($code, array_keys($labels), true) + 1 : null,
            'is_critical' => in_array($code, ['CV-1', 'CV-2'], true),
            'provisional' => false,
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
        bool $canEdit = false,
        array $reportNumbering = [],
    ): array {
        if ($assessment?->relationLoaded('photos') && $assessment->photos->isNotEmpty()) {
            return $assessment->photos
                ->map(fn (AssessmentPhoto $photo): array => [
                    'id' => $photo->public_id,
                    'defect_id' => $defect->id,
                    'status' => $photo->processing_status->value,
                    'processing_status' => $photo->processing_status->value,
                    'status_label' => $this->photoStatusLabel($photo->processing_status->value),
                    'title' => $photo->original_name ?? 'Fotografia — '.$defect->code,
                    'caption' => $photo->caption ?? $defect->title,
                    'location' => $assessment->location_description ?? 'Localização registrada na inspeção',
                    'position' => (int) $photo->position,
                    'role' => $photo->photo_type->value,
                    'role_label' => $photo->photo_type->label(),
                    'illustrative' => false,
                    'url' => $photo->isReady() ? route('assessment-photos.show', [$photo, 'optimized']) : null,
                    'thumbnail_url' => $photo->isReady() ? route('assessment-photos.show', [$photo, 'thumbnail']) : null,
                    'report_number' => $reportNumbering[$photo->public_id] ?? null,
                    'report_category' => $defect->categoryCode(),
                    'reorder_url' => $canEdit ? route('defect-assessments.photos.reorder', $assessment) : null,
                    'delete_url' => $canEdit ? route('assessment-photos.destroy', $photo) : null,
                    'captured_at' => $photo->captured_at?->format('d/m/Y H:i'),
                    'finding_code' => $defect->code,
                    'group_label' => $defect->code.' · '.$defect->title,
                ])
                ->values()
                ->all();
        }

        if (isset($technical['photos']) && is_array($technical['photos']) && $technical['photos'] !== []) {
            return collect($technical['photos'])
                ->values()
                ->map(function (array $photo, int $index) use ($defect, $reportNumbering): array {
                    return array_merge($photo, [
                        'position' => (int) ($photo['position'] ?? $index + 1),
                        'report_number' => isset($photo['id']) ? ($reportNumbering[(string) $photo['id']] ?? null) : null,
                        'report_category' => $defect->categoryCode(),
                    ]);
                })
                ->all();
        }

        return [];
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
                    'drawing' => $occurrence['drawing'] ?? $item['drawing'] ?? null,
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

    private function formatQuantity(float $value, bool $precise = false): string
    {
        return number_format($value, 2, ',', '.');
    }

    private function withoutNameSuffix(?string $name): ?string
    {
        return $name === null ? null : trim((string) preg_replace('/\s*—.*$/u', '', $name));
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
        $inspection->loadMissing(['organization', 'equipment.client']);
        $overview = $this->inspectionOverview->present($inspection);
        $mappedAssessmentIds = DefectAssessment::query()
            ->forOrganization($inspection->organization_id)
            ->where('inspection_id', $inspection->id)
            ->whereNotNull('defect_location_map_version_id')
            ->whereHas('locationMapVersion', fn ($query) => $query
                ->where('processing_status', 'ready')
                ->whereNotNull('background_path'))
            ->whereHas('location', fn ($query) => $query->whereNotNull('confirmed_at'))
            ->pluck('id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->all();
        $exportableItems = collect($items)
            ->filter(fn (array $item): bool => ($item['assessment']['status'] ?? null) === DefectAssessmentStatus::Complete->value)
            ->values();
        $mappedItems = $exportableItems
            ->filter(fn (array $item): bool => in_array(
                (int) data_get($item, 'assessment.id'),
                $mappedAssessmentIds,
                true,
            ))
            ->values();
        $photoNumbering = $this->photoNumbering->buildForReport($inspection);
        $photographicDocumentation = $this->photographicDocumentation->compose(
            $mappedItems,
            $photoNumbering,
        );
        $equipmentLabel = 'FOTO '.mb_strtoupper((string) $inspection->equipment->name).' '.mb_strtoupper((string) $inspection->equipment->tag);
        $photographicDocumentation['equipment_label'] = trim($equipmentLabel);
        $photographicDocumentation['blocks'] = collect($photographicDocumentation['blocks'])
            ->map(fn (array $block): array => array_merge($block, ['equipment_label' => $photographicDocumentation['equipment_label']]))
            ->values()
            ->all();
        $exportablePhotos = collect($photographicDocumentation['blocks'])
            ->flatMap(fn (array $block): array => $block['photos'])
            ->values()
            ->all();
        $referenceDocument = (array) data_get($inspectionPayload, 'reference_documents.0.document', []);
        $externalReportNumber = filled($inspection->external_report_number)
            ? (string) $inspection->external_report_number
            : null;
        $designerIReportNumber = filled($inspection->designer_i_report_number)
            ? (string) $inspection->designer_i_report_number
            : null;
        $reportNumber = $inspection->external_report_number ?: $inspection->number;
        $revisionHistory = $this->revisionChronology->forInspectionReport($inspection);
        $currentRevision = $revisionHistory['current']['revision_number'] ?? null;
        $coverRevision = $currentRevision === null ? 'Prévia' : (string) $currentRevision;
        // Keep the top-level compatibility field stable; all visible report
        // headers and footers consume the calculated cover revision below.
        $revision = $referenceDocument['revision'] ?? 'Prévia';
        $procedure = $inspection->procedure_number ?: '—';
        $drawing = collect($items)
            ->pluck('drawing')
            ->filter()
            ->first()
            ?? collect($items)->pluck('project')->filter()->first()
            ?? '—';
        $issuedAt = $inspection->report_date?->format('d/m/Y')
            ?? $inspection->released_at?->format('d/m/Y')
            ?? 'Não emitido';
        $quantityUnit = $summary['quantity_total_unit'] ?? '—';
        $responsibles = collect($inspectionPayload['responsibles'] ?? []);
        $approvalFlow = collect([
            ['key' => 'prepared', 'label' => 'Preparado', 'responsibility' => InspectionResponsibility::Preparer],
            ['key' => 'verified', 'label' => 'Verificado', 'responsibility' => InspectionResponsibility::Reviewer],
            ['key' => 'approved', 'label' => 'Aprovado', 'responsibility' => InspectionResponsibility::Approver],
            ['key' => 'released', 'label' => 'Liberado', 'responsibility' => InspectionResponsibility::Releaser],
        ])->map(function (array $definition) use ($responsibles): array {
            $responsible = $responsibles->first(
                fn (array $item): bool => ($item['responsibility'] ?? null) === $definition['responsibility']->value
                    && (bool) ($item['is_primary'] ?? false),
            );

            return [
                'key' => $definition['key'],
                'label' => $definition['label'],
                'name' => $responsible === null
                    ? null
                    : $this->withoutNameSuffix(data_get($responsible, 'user.name') ?? $responsible['name'] ?? null),
            ];
        })->values();
        $titleTemplate = trim((string) ($inspection->first_page_text_template ?? ''));
        $titleLines = $titleTemplate === ''
            ? []
            : (preg_split(
                '/\r\n|\r|\n/',
                str_replace('[nome do equipamento]', (string) $inspection->equipment->name, $titleTemplate),
            ) ?: []);
        $currentApprovalDate = $inspection->report_date?->format('d/m/Y') ?? '—';
        $blockedIssues = [];
        $exportBlockingIssues = [];
        $externalReportNumberIssue = 'Informe o Número do relatório externo para exportar o relatório.';
        $designerIReportNumberIssue = 'Informe o Nº Projetista I para exportar o relatório.';
        $overviewPhotoIssue = 'Adicione as quatro fotografias da Vista geral para exportar o relatório.';
        $overviewProcessingIssue = 'Aguarde o processamento das quatro fotografias da Vista geral antes de exportar o relatório.';
        $overviewTextIssue = 'Preencha os comentários e recomendações da Vista geral para exportar o relatório.';
        $unindexedPhotoIssue = 'Existem fotografias publicadas sem numeração na categoria; revise os mapas antes de exportar o relatório.';

        if ($externalReportNumber === null) {
            $blockedIssues[] = $externalReportNumberIssue;
            $exportBlockingIssues[] = $externalReportNumberIssue;
        }

        if ($designerIReportNumber === null) {
            $blockedIssues[] = $designerIReportNumberIssue;
            $exportBlockingIssues[] = $designerIReportNumberIssue;
        }

        $overviewSlots = collect($overview['blocks'])
            ->flatMap(fn (array $block): array => $block['photos'])
            ->values();

        if ($overviewSlots->contains(fn (array $slot): bool => $slot['photo'] === null)) {
            $blockedIssues[] = $overviewPhotoIssue;
            $exportBlockingIssues[] = $overviewPhotoIssue;
        }

        if ($overviewSlots->contains(fn (array $slot): bool => $slot['photo'] !== null
            && ($slot['photo']['status'] ?? null) !== 'ready')) {
            $blockedIssues[] = $overviewProcessingIssue;
            $exportBlockingIssues[] = $overviewProcessingIssue;
        }

        if (collect($overview['blocks'])->contains(fn (array $block): bool => blank($block['comment']) || blank($block['recommendation']))) {
            $blockedIssues[] = $overviewTextIssue;
            $exportBlockingIssues[] = $overviewTextIssue;
        }

        if (($photographicDocumentation['unindexed_photo_ids'] ?? []) !== []) {
            $blockedIssues[] = $unindexedPhotoIssue;
            $exportBlockingIssues[] = $unindexedPhotoIssue;
        }

        $missingApprovalRoles = $approvalFlow
            ->filter(fn (array $item): bool => blank($item['name']))
            ->pluck('label')
            ->values();

        if ($missingApprovalRoles->isNotEmpty()) {
            $blockedIssues[] = 'Defina os responsáveis principais para: '.$missingApprovalRoles->implode(', ').'.';
        }

        if ($summary['pending'] > 0) {
            $pendingIssue = sprintf('%d registro(s) ainda não foram consolidados.', $summary['pending']);
            $blockedIssues[] = $pendingIssue;
            $exportBlockingIssues[] = $pendingIssue;
        }

        if (($summary['quantity_totals_by_unit'] ?? []) !== ($summary['exportable_quantity_totals_by_unit'] ?? [])) {
            $blockedIssues[] = 'A consolidação completa ainda inclui registros não exportáveis.';
        }

        $unreadyPhotos = collect($exportablePhotos)
            ->filter(fn (array $photo): bool => ($photo['status'] ?? $photo['processing_status'] ?? 'ready') !== 'ready');
        if ($unreadyPhotos->isNotEmpty()) {
            $blockedIssues[] = sprintf(
                '%d fotografia(s) publicada(s) ainda não estão prontas para o documento.',
                $unreadyPhotos->count(),
            );
        }

        $blocked = $blockedIssues !== [];

        return [
            'number' => $reportNumber,
            'external_report_number' => $externalReportNumber,
            'report_designer' => $inspection->report_designer ?: 'PROJETISTA II',
            'designer_i_report_number' => $designerIReportNumber,
            'revision' => $revision,
            'current_revision' => $coverRevision,
            'title_lines' => $titleLines,
            'revision_history' => $revisionHistory['rows'],
            'revision_density' => $revisionHistory['density'],
            'emission_types' => $this->revisionChronology->emissionLegend(),
            'generated_label' => 'Prévia técnica da inspeção',
            'cover' => [
                'eyebrow' => 'Relatório técnico de inspeção',
                'title' => $reportNumber,
                'client' => $inspection->equipment->client?->name,
                'client_logo_url' => $inspection->equipment->client?->logo_path !== null
                    ? Storage::disk('public')->url($inspection->equipment->client->logo_path)
                    : null,
                'provider' => $inspection->organization?->name,
                'provider_logo_url' => $inspection->organization?->logo_path !== null
                    ? Storage::disk('public')->url($inspection->organization->logo_path)
                    : null,
                'equipment_tag' => $inspection->equipment->tag,
                'equipment_name' => $inspection->equipment->name,
                'inspection_type' => $inspection->inspection_type->label(),
                'inspection_number' => $inspection->number,
                'external_report_number' => $externalReportNumber,
                'report_designer' => $inspection->report_designer ?: 'PROJETISTA II',
                'designer_i_report_number' => $designerIReportNumber,
                'service_order' => $inspection->service_order,
                'procedure' => $procedure,
                'drawing' => $drawing,
                'inspected_on' => $inspection->inspected_on?->format('d/m/Y') ?? $inspection->planned_start_on?->format('d/m/Y'),
                'revision' => $coverRevision,
                'current_revision' => $coverRevision,
                'issued_at' => $issuedAt,
                'approval_date' => $currentApprovalDate,
                'approval_flow' => $approvalFlow->all(),
                'title_lines' => $titleLines,
                'revision_history' => $revisionHistory['rows'],
                'revision_density' => $revisionHistory['density'],
                'emission_types' => $this->revisionChronology->emissionLegend(),
            ],
            'general_aspects' => $this->generalAspectsDocuments->fromStored($inspection->general_notes),
            'overview' => array_merge($overview, [
                'equipment_label' => trim($equipmentLabel),
                'title' => 'ANEXO A – LOCALIZAÇÃO E DOCUMENTAÇÃO FOTOGRÁFICA - TAC',
                'section_title' => 'DOCUMENTAÇÃO FOTOGRÁFICA - TAC',
            ]),
            'executive_summary' => [
                'criticality' => $summary['criticality'],
                'headline' => $summary['critical'] > 0
                    ? 'O equipamento requer tratamento prioritário das manifestações de maior criticidade.'
                    : 'A condição observada permite acompanhamento no ciclo programado.',
                'description' => sprintf(
                    '%d ocorrências civis foram consolidadas; %d avaliações estão publicadas e %d permanecem em aberto. %d registro(s) não serão exportados.',
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
                    'quantity_total_label' => $summary['quantity_total_label'] ?? '—',
                    'exportable_quantity_total' => $summary['exportable_quantity_total'] ?? 0.0,
                    'exportable_quantity_total_label' => $summary['exportable_quantity_total_label'] ?? '—',
                    'draft_count' => $summary['draft_count'] ?? 0,
                ],
            ],
            // A localização operacional é injetada pelo compositor persistido de mapas.
            'locations' => [],
            'findings' => $exportableItems
                ->map(function (array $item): array {
                    $assessment = $item['assessment'] ?? [];
                    $occurrence = $item['occurrence'] ?? [];
                    $previousClassification = data_get($item, 'previous_assessment_summary.classification');

                    return [
                        'id' => $item['id'],
                        'code' => $item['code'],
                        'title' => $item['title'],
                        'assessment' => $assessment,
                        'origin_type' => $item['origin_type'] ?? 'new',
                        'condition' => $assessment['condition'] ?? null,
                        'condition_label' => $assessment['condition_label'] ?? '—',
                        'previous_classification' => $previousClassification,
                        'current_classification' => $item['classification'],
                        'classification' => $item['classification'],
                        'gut' => $item['gut'],
                        'location' => $assessment['location_description'] ?? $occurrence['location'] ?? '—',
                        'project' => $occurrence['project'] ?? $item['project'] ?? '—',
                        'drawing' => $occurrence['drawing'] ?? $item['drawing'] ?? null,
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
                        'reason' => $assessment['reason'] ?? null,
                    ];
                })
                ->values()
                ->all(),
            'evolution_rows' => $exportableItems
                ->map(function (array $item): array {
                    $assessment = $item['assessment'] ?? [];

                    return [
                        'id' => $item['id'],
                        'code' => $item['code'],
                        'title' => $item['title'],
                        'origin_type' => $item['origin_type'] ?? 'new',
                        'condition' => $assessment['condition'] ?? null,
                        'condition_label' => $assessment['condition_label'] ?? '—',
                        'previous_classification' => data_get($item, 'previous_assessment_summary.classification'),
                        'current_classification' => $item['classification'],
                        'quantity' => $item['quantity_summary']['total_label'] ?? '—',
                    ];
                })
                ->values()
                ->all(),
            'photographic_documentation' => $photographicDocumentation,
            'quantities' => [
                'total' => $summary['quantity_total'] ?? 0.0,
                'total_label' => $summary['quantity_total_label'] ?? '—',
                'exportable_total' => $summary['exportable_quantity_total'] ?? 0.0,
                'exportable_total_label' => $summary['exportable_quantity_total_label'] ?? '—',
                'unit' => $quantityUnit,
                'totals_by_unit' => $summary['quantity_totals_by_unit'] ?? [],
                'exportable_totals_by_unit' => $summary['exportable_quantity_totals_by_unit'] ?? [],
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
            'print_enabled' => $exportBlockingIssues === [],
            'export_disabled_reason' => $exportBlockingIssues === []
                ? null
                : implode(' ', $exportBlockingIssues),
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
            'equipment.defects.assessments.photos',
            'equipment.defects.assessments.quantities',
        ]);

        return $this->inspectionDefectScope->handle($inspection);
    }

    /** @return array<int, array<string, mixed>> */
    private function assessmentHistory(DefectAssessment $assessment): array
    {
        $history = [];
        $seen = [];
        $previousId = $assessment->previous_assessment_id;
        $validInspectionIds = array_flip(
            $this->inspectionDefectScope->ancestorInspectionIds($assessment->inspection),
        );

        while ($previousId !== null && ! isset($seen[$previousId])) {
            $seen[$previousId] = true;
            $previous = DefectAssessment::query()
                ->forOrganization($assessment->organization_id)
                ->with(['inspection', 'quantities', 'photos'])
                ->where('defect_id', $assessment->defect_id)
                ->whereKey($previousId)
                ->first();

            if ($previous === null) {
                break;
            }

            if ($previous->status === DefectAssessmentStatus::Complete
                && $previous->inspection?->status !== InspectionStatus::Canceled
                && isset($validInspectionIds[$previous->inspection_id])) {
                $history[] = $this->historicalAssessmentPayload($previous);
            }

            $previousId = $previous->previous_assessment_id;
        }

        return $history;
    }

    /** @return array<string, mixed>|null */
    private function reinspectionAction(DefectAssessment $assessment, User $user): ?array
    {
        $inspection = Inspection::query()
            ->forOrganization($assessment->organization_id)
            ->where('equipment_id', $assessment->equipment_id)
            ->whereIn('status', [
                InspectionStatus::InProgress->value,
                InspectionStatus::InCorrection->value,
            ])
            ->whereKeyNot($assessment->inspection_id)
            ->with(['previousInspection', 'responsibles'])
            ->orderByDesc('id')
            ->get()
            ->first(fn (Inspection $candidate): bool => in_array(
                $assessment->inspection_id,
                $this->inspectionDefectScope->ancestorInspectionIds($candidate),
                true,
            ));

        if ($inspection === null) {
            return null;
        }

        $currentAssessment = DefectAssessment::query()
            ->forOrganization($assessment->organization_id)
            ->where('defect_id', $assessment->defect_id)
            ->where('inspection_id', $inspection->getKey())
            ->first();
        $canCreate = $currentAssessment === null
            && $user->can('create', [DefectAssessment::class, $inspection, $assessment->defect]);
        $canViewCurrent = $currentAssessment !== null && $user->can('view', $currentAssessment);

        return [
            'inspection' => [
                'id' => $inspection->id,
                'public_id' => $inspection->public_id,
                'number' => $inspection->number,
                'status' => $inspection->status->value,
                'status_label' => $inspection->status->label(),
            ],
            'defects_url' => route('inspections.defects', $inspection),
            'assessment_url' => $canViewCurrent
                ? route('defect-assessments.show', $currentAssessment)
                : null,
            'assessment_store_url' => $canCreate
                ? route('inspections.defects.assessments.store', [$inspection, $assessment->defect])
                : null,
        ];
    }

    /** @return array<string, mixed> */
    private function historicalAssessmentPayload(DefectAssessment $assessment): array
    {
        $classification = $this->snapshotClassification($assessment);
        $quantity = $this->quantitySnapshot($assessment);

        return [
            'id' => $assessment->id,
            'public_id' => $assessment->public_id,
            'inspection' => [
                'id' => $assessment->inspection->id,
                'public_id' => $assessment->inspection->public_id,
                'number' => $assessment->inspection->number,
                'show_url' => route('inspections.show', $assessment->inspection),
            ],
            'condition' => $assessment->condition->value,
            'condition_label' => $assessment->condition->label(),
            'assessed_at' => $assessment->assessed_at?->format('d/m/Y H:i'),
            'classification' => $classification,
            'gut' => [
                'gravity' => $assessment->gravity,
                'urgency' => $assessment->urgency,
                'trend' => $assessment->trend,
                'score' => $assessment->gut_score,
            ],
            'quantity' => $quantity === null ? null : (function () use ($quantity): array {
                $unit = MeasurementUnit::tryFrom((string) ($quantity['measurement_unit'] ?? ''));

                return [
                    'value' => (string) ($quantity['total'] ?? '0'),
                    'unit' => $unit?->value ?? ($quantity['measurement_unit'] ?? null),
                    'unit_label' => $unit?->label() ?? ($quantity['measurement_unit'] ?? null),
                    'unit_symbol' => $unit?->symbol() ?? ($quantity['measurement_unit'] ?? null),
                    'snapshot' => $quantity,
                ];
            })(),
            'location_description' => $assessment->location_description,
            'comment' => $assessment->comment,
            'recommendation' => $assessment->recommendation,
            'reason' => $assessment->reason,
            'photos' => $assessment->photos
                ->map(fn (AssessmentPhoto $photo): array => [
                    'id' => $photo->public_id,
                    'title' => $photo->original_name,
                    'caption' => $photo->caption,
                    'status' => $photo->processing_status->value,
                    'url' => $photo->isReady() ? route('assessment-photos.show', [$photo, 'optimized']) : null,
                    'thumbnail_url' => $photo->isReady() ? route('assessment-photos.show', [$photo, 'thumbnail']) : null,
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return array{code:?string,label:string,color:?string} */
    private function snapshotClassification(?DefectAssessment $assessment): array
    {
        if ($assessment === null) {
            return ['code' => null, 'label' => 'Não classificada', 'color' => null];
        }

        $snapshot = $assessment->classification_snapshot ?? [];
        $code = $snapshot['code'] ?? $assessment->classification_code;

        return [
            'code' => $code,
            'label' => $snapshot['name'] ?? $snapshot['label'] ?? $code ?? 'Não classificada',
            'color' => $snapshot['color'] ?? null,
        ];
    }

    private function criticalityRank(array $classification): int
    {
        if (is_numeric($classification['severity_rank'] ?? null)) {
            return (int) $classification['severity_rank'];
        }

        return match ($classification['code'] ?? null) {
            'CV-1' => 1,
            'CV-2' => 2,
            'CV-3' => 3,
            'CV-4' => 4,
            'CV-5' => 5,
            default => 99,
        };
    }
}
